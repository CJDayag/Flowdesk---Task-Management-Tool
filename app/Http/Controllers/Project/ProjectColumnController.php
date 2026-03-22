<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ReorderProjectColumnsRequest;
use App\Http\Requests\Project\StoreProjectColumnRequest;
use App\Models\Project;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;

class ProjectColumnController extends Controller
{
    /**
     * Create a custom Kanban column for the project.
     */
    public function store(StoreProjectColumnRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $sortOrder = $project->columns()->max('sort_order');

        $column = $project->columns()->create([
            'name' => $request->string('name')->toString(),
            'color' => $request->string('color')->toString() ?: '#94a3b8',
            'sort_order' => is_int($sortOrder) ? $sortOrder + 1 : 0,
        ]);

        ActivityLogger::log(
            $request->user(),
            $project->workspace,
            'project.column_created',
            'Created column '.$column->name.' in '.$project->name,
            $column,
            ['project_id' => $project->id, 'column_id' => $column->id],
        );

        return back()->with('status', 'Column created.');
    }

    /**
     * Reorder Kanban columns for a project.
     */
    public function reorder(ReorderProjectColumnsRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $orderedIds = collect($request->validated('ordered_column_ids'));

        $validIds = $project->columns()
            ->whereIn('id', $orderedIds->all())
            ->pluck('id');

        foreach ($orderedIds->values() as $index => $columnId) {
            if (! $validIds->contains($columnId)) {
                continue;
            }

            $project->columns()->where('id', $columnId)->update(['sort_order' => $index]);
        }

        ActivityLogger::log(
            $request->user(),
            $project->workspace,
            'project.columns_reordered',
            'Reordered columns in '.$project->name,
            $project,
            ['project_id' => $project->id],
        );

        return back()->with('status', 'Columns reordered.');
    }
}
