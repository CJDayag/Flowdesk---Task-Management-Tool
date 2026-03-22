<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectMembersRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Render project views (board/list/calendar) for the current workspace.
     */
    public function index(Request $request): Response
    {
        $workspace = $request->user()?->currentWorkspace;

        abort_unless($workspace instanceof Workspace, 403, 'No active workspace selected.');

        $this->authorize('viewAny', [Project::class, $workspace]);

        $projects = $workspace->projects()
            ->with('members:id,name,email')
            ->orderBy('name')
            ->get();

        $visibleProjects = $projects->filter(fn (Project $project) => $project->userCanAccess($request->user()));
        $visibleProjectIds = $visibleProjects->pluck('id');

        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $priority = $request->string('priority')->toString();
        $dueDate = $request->string('due_date')->toString();
        $assigneeId = $request->integer('assignee_id');

        $selectedProjectId = $request->integer('project_id');
        $selectedProject = $visibleProjects->firstWhere('id', $selectedProjectId) ?? $visibleProjects->first();

        $taskQuery = Task::query()
            ->where('workspace_id', $workspace->id)
            ->with(['assignee:id,name', 'creator:id,name', 'column:id,project_id,name,color,sort_order'])
            ->orderBy('sort_order');

        if ($selectedProject instanceof Project) {
            $taskQuery->where('project_id', $selectedProject->id);
        }

        if ($search !== '') {
            $taskQuery->where(function (Builder $query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $taskQuery->where('status', $status);
        }

        if ($priority !== '') {
            $taskQuery->where('priority', $priority);
        }

        if ($assigneeId > 0) {
            $taskQuery->where(function (Builder $query) use ($assigneeId): void {
                $query
                    ->where('assigned_to', $assigneeId)
                    ->orWhereHas('assignees', function (Builder $assigneeQuery) use ($assigneeId): void {
                        $assigneeQuery->where('users.id', $assigneeId);
                    });
            });
        }

        if ($dueDate !== '') {
            $taskQuery->whereDate('due_at', $dueDate);
        }

        $tasks = $taskQuery->get();

        $columns = $selectedProject
            ? $selectedProject->columns()->orderBy('sort_order')->get()
            : collect();

        $searchResults = [
            'tasks' => [],
            'projects' => [],
            'users' => [],
        ];

        if ($search !== '') {
            $searchResults['projects'] = $visibleProjects
                ->filter(fn (Project $project): bool => str_contains(strtolower($project->name.' '.$project->description), strtolower($search)))
                ->take(8)
                ->values()
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'visibility' => $project->visibility->value,
                ])
                ->all();

            $searchResults['users'] = $workspace->members()
                ->select('users.id', 'users.name', 'users.email')
                ->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                })
                ->orderBy('users.name')
                ->limit(8)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all();

            $searchTaskQuery = Task::query()
                ->where('workspace_id', $workspace->id)
                ->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
                ->with(['assignee:id,name', 'project:id,name'])
                ->orderByDesc('updated_at')
                ->limit(12);

            if ($visibleProjectIds->isNotEmpty()) {
                $searchTaskQuery->where(function (Builder $query) use ($visibleProjectIds): void {
                    $query
                        ->whereNull('project_id')
                        ->orWhereIn('project_id', $visibleProjectIds->all());
                });
            } else {
                $searchTaskQuery->whereNull('project_id');
            }

            $searchResults['tasks'] = $searchTaskQuery
                ->get()
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority?->value,
                    'due_at' => $task->due_at?->toDateTimeString(),
                    'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
                    'project' => $task->project ? ['id' => $task->project->id, 'name' => $task->project->name] : null,
                ])
                ->all();
        }

        return Inertia::render('projects/index', [
            'projects' => $visibleProjects->values(),
            'selectedProjectId' => $selectedProject?->id,
            'view' => $request->string('view')->toString() ?: 'board',
            'tasks' => $tasks,
            'columns' => $columns,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
                'priority' => $priority,
                'due_date' => $dueDate,
            ],
            'searchResults' => $searchResults,
            'workspaceMembers' => $workspace->members()->select('users.id', 'users.name', 'users.email')->orderBy('users.name')->get(),
        ]);
    }

    /**
     * Create a project within the current workspace.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $workspace = $request->user()?->currentWorkspace;

        abort_unless($workspace instanceof Workspace, 403, 'No active workspace selected.');

        $this->authorize('create', [Project::class, $workspace]);

        $validated = $request->validated();

        $project = $workspace->projects()->create([
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'slug' => Project::uniqueSlugForWorkspace($workspace, $validated['name']),
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'],
        ]);

        $project->columns()->createMany([
            ['name' => 'To Do', 'color' => '#64748b', 'sort_order' => 0],
            ['name' => 'In Progress', 'color' => '#3b82f6', 'sort_order' => 1],
            ['name' => 'Done', 'color' => '#22c55e', 'sort_order' => 2],
        ]);

        $memberIds = $this->workspaceMemberIds($workspace, collect($validated['member_ids'] ?? []));
        $memberIds->push($request->user()->id);

        $project->members()->sync($memberIds->unique()->mapWithKeys(
            fn (int $id): array => [$id => ['joined_at' => now()]]
        )->all());

        ActivityLogger::log(
            $request->user(),
            $workspace,
            'project.created',
            'Created project '.$project->name,
            $project,
            ['project_id' => $project->id],
        );

        return back()->with('status', 'Project created.');
    }

    /**
     * Update project details.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        if (($validated['name'] ?? null) && $validated['name'] !== $project->name) {
            $validated['slug'] = Project::uniqueSlugForWorkspace($project->workspace, $validated['name']);
        }

        $project->update($validated);

        ActivityLogger::log(
            $request->user(),
            $project->workspace,
            'project.updated',
            'Updated project '.$project->name,
            $project,
            ['project_id' => $project->id],
        );

        return back()->with('status', 'Project updated.');
    }

    /**
     * Delete a project.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $workspace = $project->workspace;
        $projectId = $project->id;
        $name = $project->name;

        Task::query()->where('project_id', $project->id)->update(['project_id' => null]);
        $project->delete();

        ActivityLogger::log(
            request()->user(),
            $workspace,
            'project.deleted',
            'Deleted project '.$name,
            null,
            ['project_id' => $projectId],
        );

        return back()->with('status', 'Project deleted.');
    }

    /**
     * Sync project members.
     */
    public function updateMembers(UpdateProjectMembersRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $memberIds = $this->workspaceMemberIds($project->workspace, collect($request->validated('member_ids')));

        if (! $memberIds->contains($project->created_by)) {
            $memberIds->push($project->created_by);
        }

        $project->members()->sync($memberIds->unique()->mapWithKeys(
            fn (int $id): array => [$id => ['joined_at' => now()]]
        )->all());

        ActivityLogger::log(
            $request->user(),
            $project->workspace,
            'project.members_updated',
            'Updated members for project '.$project->name,
            $project,
            ['project_id' => $project->id, 'member_count' => $memberIds->count()],
        );

        return back()->with('status', 'Project members updated.');
    }

    /**
     * Keep only IDs that belong to this workspace.
     *
     * @param  Collection<int, int>  $memberIds
     * @return Collection<int, int>
     */
    private function workspaceMemberIds(Workspace $workspace, Collection $memberIds): Collection
    {
        if ($memberIds->isEmpty()) {
            return collect();
        }

        return $workspace->members()
            ->whereIn('users.id', $memberIds->unique()->all())
            ->pluck('users.id');
    }
}
