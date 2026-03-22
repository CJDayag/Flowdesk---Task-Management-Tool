<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskLabelRequest;
use App\Http\Requests\Task\UpdateTaskLabelRequest;
use App\Models\TaskLabel;
use App\Models\Workspace;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;

class TaskLabelController extends Controller
{
    /**
     * Create a custom workspace label for tasks.
     */
    public function store(StoreTaskLabelRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $label = TaskLabel::create([
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'name' => $request->string('name')->toString(),
            'color' => $request->string('color')->toString() ?: '#94a3b8',
        ]);

        ActivityLogger::log(
            $request->user(),
            $workspace,
            'task.label_created',
            'Created label '.$label->name,
            $label,
            ['label_id' => $label->id],
        );

        return back()->with('status', 'Label created.');
    }

    /**
     * Update a custom label.
     */
    public function update(UpdateTaskLabelRequest $request, TaskLabel $label): RedirectResponse
    {
        $this->authorize('update', $label->workspace);

        $label->update($request->validated());

        ActivityLogger::log(
            $request->user(),
            $label->workspace,
            'task.label_updated',
            'Updated label '.$label->name,
            $label,
            ['label_id' => $label->id],
        );

        return back()->with('status', 'Label updated.');
    }

    /**
     * Delete a custom label.
     */
    public function destroy(TaskLabel $label): RedirectResponse
    {
        $this->authorize('update', $label->workspace);

        $workspace = $label->workspace;
        $name = $label->name;
        $labelId = $label->id;

        $label->delete();

        ActivityLogger::log(
            request()->user(),
            $workspace,
            'task.label_deleted',
            'Deleted label '.$name,
            null,
            ['label_id' => $labelId],
        );

        return back()->with('status', 'Label deleted.');
    }
}
