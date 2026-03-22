<?php

use App\Models\ProjectColumn;
use App\Models\User;
use App\Models\Workspace;

function kanbanWorkspaceWithOwner(): array
{
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Kanban Workspace',
        'slug' => 'kanban-workspace',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$workspace, $owner];
}

test('project supports custom columns and column reorder', function () {
    [$workspace, $owner] = kanbanWorkspaceWithOwner();

    $project = $workspace->projects()->create([
        'created_by' => $owner->id,
        'name' => 'Board Project',
        'slug' => 'board-project',
        'visibility' => 'public',
    ]);

    $todo = $project->columns()->create(['name' => 'To Do', 'color' => '#64748b', 'sort_order' => 0]);
    $doing = $project->columns()->create(['name' => 'Doing', 'color' => '#3b82f6', 'sort_order' => 1]);

    $createResponse = $this->actingAs($owner)->post(route('projects.columns.store', $project), [
        'name' => 'Blocked',
        'color' => '#ef4444',
    ]);

    $createResponse->assertRedirect();

    $blocked = ProjectColumn::query()->where('project_id', $project->id)->where('name', 'Blocked')->firstOrFail();

    $reorderResponse = $this->actingAs($owner)->patch(route('projects.columns.reorder', $project), [
        'ordered_column_ids' => [$blocked->id, $todo->id, $doing->id],
    ]);

    $reorderResponse->assertRedirect();

    expect($blocked->fresh()->sort_order)->toBe(0)
        ->and($todo->fresh()->sort_order)->toBe(1)
        ->and($doing->fresh()->sort_order)->toBe(2);
});

test('tasks can move between columns and reorder inside target column', function () {
    [$workspace, $owner] = kanbanWorkspaceWithOwner();

    $project = $workspace->projects()->create([
        'created_by' => $owner->id,
        'name' => 'Delivery Board',
        'slug' => 'delivery-board',
        'visibility' => 'public',
    ]);

    $todo = $project->columns()->create(['name' => 'To Do', 'color' => '#64748b', 'sort_order' => 0]);
    $done = $project->columns()->create(['name' => 'Done', 'color' => '#22c55e', 'sort_order' => 1]);

    $taskA = $workspace->tasks()->create([
        'project_id' => $project->id,
        'project_column_id' => $todo->id,
        'created_by' => $owner->id,
        'title' => 'Task A',
        'status' => 'todo',
        'priority' => 'medium',
        'sort_order' => 0,
    ]);

    $taskB = $workspace->tasks()->create([
        'project_id' => $project->id,
        'project_column_id' => $done->id,
        'created_by' => $owner->id,
        'title' => 'Task B',
        'status' => 'done',
        'priority' => 'low',
        'sort_order' => 0,
    ]);

    $moveResponse = $this->actingAs($owner)->patch(route('tasks.move', $taskA), [
        'project_column_id' => $done->id,
        'ordered_task_ids' => [$taskB->id, $taskA->id],
    ]);

    $moveResponse->assertRedirect();

    expect($taskA->fresh()->project_column_id)->toBe($done->id)
        ->and($taskA->fresh()->status)->toBe('done')
        ->and($taskA->fresh()->sort_order)->toBe(1)
        ->and($taskB->fresh()->sort_order)->toBe(0);
});
