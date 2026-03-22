<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\AssignTaskRequest;
use App\Http\Requests\Task\MoveTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\ProjectColumn;
use App\Models\Task;
use App\Models\TaskLabel;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TaskAssignedInAppNotification;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

class TaskController extends Controller
{
    /**
     * Create a task in the provided workspace.
     */
    public function store(StoreTaskRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('create', [Task::class, $workspace]);

        $validated = $request->validated();

        if (isset($validated['project_id'])) {
            $project = Project::query()->findOrFail($validated['project_id']);

            if ($project->workspace_id !== $workspace->id || ! $project->userCanAccess($request->user())) {
                abort(422, 'Invalid project selection for this task.');
            }
        }

        if (isset($validated['project_column_id'])) {
            $column = ProjectColumn::query()->findOrFail($validated['project_column_id']);

            if (! isset($project) || $column->project_id !== $project->id) {
                abort(422, 'Column must belong to the selected project.');
            }
        }

        $assigneeIds = $this->workspaceMemberIds($workspace, collect($validated['assignee_ids'] ?? []));
        $labelIds = $this->workspaceLabelIds($workspace, collect($validated['label_ids'] ?? []));

        if (isset($project) && ! isset($validated['project_column_id'])) {
            $validated['project_column_id'] = $project->columns()->value('id');
        }

        $sortOrder = 0;
        if (isset($validated['project_column_id'])) {
            $maxSort = Task::query()
                ->where('project_column_id', $validated['project_column_id'])
                ->max('sort_order');
            $sortOrder = is_int($maxSort) ? $maxSort + 1 : 0;
        }

        unset($validated['assignee_ids'], $validated['label_ids']);

        $task = $workspace->tasks()->create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => $request->input('status', 'todo'),
            'priority' => $request->input('priority', 'medium'),
            'assigned_to' => $assigneeIds->first(),
            'sort_order' => $sortOrder,
        ]);

        if ($assigneeIds->isNotEmpty()) {
            $task->assignees()->sync($assigneeIds->mapWithKeys(
                fn (int $id): array => [$id => ['assigned_at' => now()]]
            )->all());

            $this->notifyTaskAssigned($task, $request->user(), $assigneeIds);
        }

        if ($labelIds->isNotEmpty()) {
            $task->labels()->sync($labelIds->all());
        }

        $actorName = $request->user()->name;

        ActivityLogger::log(
            $request->user(),
            $workspace,
            'task.created',
            $actorName.' created task '.$task->title,
            $task,
            ['task_id' => $task->id],
        );

        return back()->with('status', 'Task created.');
    }

    /**
     * Update task content.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $previousStatus = $task->status;

        $validated = $request->validated();

        if (array_key_exists('project_id', $validated) && $validated['project_id'] !== null) {
            $project = Project::query()->findOrFail($validated['project_id']);

            if ($project->workspace_id !== $task->workspace_id || ! $project->userCanAccess($request->user())) {
                abort(422, 'Invalid project selection for this task.');
            }
        }

        if (array_key_exists('project_column_id', $validated) && $validated['project_column_id'] !== null) {
            $column = ProjectColumn::query()->findOrFail($validated['project_column_id']);
            $projectId = $validated['project_id'] ?? $task->project_id;

            if ($projectId === null || $column->project_id !== (int) $projectId) {
                abort(422, 'Column must belong to the selected project.');
            }
        }

        $assigneeIds = null;
        if (array_key_exists('assignee_ids', $validated)) {
            $assigneeIds = $this->workspaceMemberIds($task->workspace, collect($validated['assignee_ids']));
        }

        $labelIds = null;
        if (array_key_exists('label_ids', $validated)) {
            $labelIds = $this->workspaceLabelIds($task->workspace, collect($validated['label_ids']));
        }

        unset($validated['assignee_ids'], $validated['label_ids']);

        if ($assigneeIds instanceof Collection) {
            $validated['assigned_to'] = $assigneeIds->first();
        }

        $task->update($validated);

        if ($assigneeIds instanceof Collection) {
            $task->assignees()->sync($assigneeIds->mapWithKeys(
                fn (int $id): array => [$id => ['assigned_at' => now()]]
            )->all());

            $this->notifyTaskAssigned($task, $request->user(), $assigneeIds);
        }

        if ($labelIds instanceof Collection) {
            $task->labels()->sync($labelIds->all());
        }

        $actorName = $request->user()->name;

        if (array_key_exists('status', $validated) && $validated['status'] !== $previousStatus) {
            ActivityLogger::log(
                $request->user(),
                $task->workspace,
                'task.status_changed',
                $actorName." changed status of {$task->title} from '{$previousStatus}' to '{$task->status}'",
                $task,
                [
                    'task_id' => $task->id,
                    'from_status' => $previousStatus,
                    'to_status' => $task->status,
                ],
            );
        }

        ActivityLogger::log(
            $request->user(),
            $task->workspace,
            'task.updated',
            $actorName.' updated task '.$task->title,
            $task,
            ['task_id' => $task->id],
        );

        return back()->with('status', 'Task updated.');
    }

    /**
     * Delete a task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $actor = request()->user();
        $workspace = $task->workspace;
        $title = $task->title;
        $taskId = $task->id;

        $task->delete();

        ActivityLogger::log(
            $actor,
            $workspace,
            'task.deleted',
            $actor->name.' deleted task '.$title,
            null,
            ['task_id' => $taskId],
        );

        return back()->with('status', 'Task deleted.');
    }

    /**
     * Assign a task to a member of the same workspace.
     */
    public function assign(AssignTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('assign', $task);

        $assigneeIds = $this->workspaceMemberIds($task->workspace, collect($request->validated('assignee_ids')));

        if ($assigneeIds->isEmpty()) {
            abort(422, 'Assignees must be workspace members.');
        }

        $task->assignees()->sync($assigneeIds->mapWithKeys(
            fn (int $id): array => [$id => ['assigned_at' => now()]]
        )->all());

        $this->notifyTaskAssigned($task, $request->user(), $assigneeIds);

        $task->forceFill(['assigned_to' => $assigneeIds->first()])->save();

        $assigneeNames = User::query()->whereIn('id', $assigneeIds)->pluck('name')->implode(', ');

        ActivityLogger::log(
            $request->user(),
            $task->workspace,
            'task.assigned',
            $request->user()->name.' assigned task '.$task->title.' to '.$assigneeNames,
            $task,
            ['task_id' => $task->id, 'assignee_ids' => $assigneeIds->all()],
        );

        return back()->with('status', 'Task assignees updated.');
    }

    /**
     * Move or reorder a task on the Kanban board.
     */
    public function move(MoveTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $column = ProjectColumn::query()->findOrFail($request->integer('project_column_id'));

        if ($task->project_id === null || $column->project_id !== $task->project_id) {
            abort(422, 'Target column does not belong to this task project.');
        }

        $orderedTaskIds = collect($request->validated('ordered_task_ids'));

        $status = $this->statusForColumnName($column->name);

        $updates = ['project_column_id' => $column->id];

        if ($status !== null) {
            $updates['status'] = $status;
        }

        $task->forceFill($updates)->save();

        $validTaskIds = Task::query()
            ->where('project_id', $task->project_id)
            ->where('project_column_id', $column->id)
            ->whereIn('id', $orderedTaskIds->all())
            ->pluck('id');

        foreach ($orderedTaskIds->values() as $index => $taskId) {
            if (! $validTaskIds->contains($taskId)) {
                continue;
            }

            Task::query()->where('id', $taskId)->update(['sort_order' => $index]);
        }

        ActivityLogger::log(
            $request->user(),
            $task->workspace,
            'task.moved',
            $request->user()->name." moved {$task->title} to '{$column->name}'",
            $task,
            ['task_id' => $task->id, 'column_id' => $column->id],
        );

        return back()->with('status', 'Task moved.');
    }

    /**
     * Infer task status from canonical Kanban column names.
     */
    private function statusForColumnName(string $columnName): ?string
    {
        $normalized = strtolower(trim($columnName));
        $compact = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';

        return match ($compact) {
            'todo', 'backlog', 'open' => 'todo',
            'inprogress', 'doing', 'wip' => 'in_progress',
            'done', 'complete', 'completed', 'closed' => 'done',
            default => null,
        };
    }

    /**
     * Keep only users that belong to the given workspace.
     *
     * @param  Collection<int, int>  $assigneeIds
     * @return Collection<int, int>
     */
    private function workspaceMemberIds(Workspace $workspace, Collection $assigneeIds): Collection
    {
        if ($assigneeIds->isEmpty()) {
            return collect();
        }

        return $workspace->members()
            ->whereIn('users.id', $assigneeIds->unique()->all())
            ->pluck('users.id');
    }

    /**
     * Keep only labels that belong to this workspace.
     *
     * @param  Collection<int, int>  $labelIds
     * @return Collection<int, int>
     */
    private function workspaceLabelIds(Workspace $workspace, Collection $labelIds): Collection
    {
        if ($labelIds->isEmpty()) {
            return collect();
        }

        return TaskLabel::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $labelIds->unique()->all())
            ->pluck('id');
    }

    /**
     * Send in-app assignment notifications to assignees.
     *
     * @param  Collection<int, int>  $assigneeIds
     */
    private function notifyTaskAssigned(Task $task, User $actor, Collection $assigneeIds): void
    {
        $targets = User::query()
            ->whereIn('id', $assigneeIds->all())
            ->where('id', '!=', $actor->id)
            ->get();

        foreach ($targets as $target) {
            $target->notify(new TaskAssignedInAppNotification($task, $actor->name));
        }
    }
}
