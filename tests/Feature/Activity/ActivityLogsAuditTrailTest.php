<?php

use App\Models\Project;
use App\Models\ProjectColumn;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

function createAuditWorkspaceWithOwner(): array
{
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Audit Workspace',
        'slug' => 'audit-workspace',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$workspace, $owner];
}

test('audit trail tracks task create update delete and status changes', function () {
    [$workspace, $owner] = createAuditWorkspaceWithOwner();

    $createResponse = $this->actingAs($owner)->post(route('workspaces.tasks.store', $workspace), [
        'title' => 'Task A',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $createResponse->assertRedirect();

    $task = Task::query()->firstOrFail();

    $updateResponse = $this->actingAs($owner)->patch(route('tasks.update', $task), [
        'status' => 'done',
    ]);

    $updateResponse->assertRedirect();

    $deleteResponse = $this->actingAs($owner)->delete(route('tasks.destroy', $task));

    $deleteResponse->assertRedirect();

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'task.created',
        'description' => $owner->name.' created task Task A',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'task.updated',
        'description' => $owner->name.' updated task Task A',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'task.status_changed',
        'description' => $owner->name." changed status of Task A from 'todo' to 'done'",
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'task.deleted',
        'description' => $owner->name.' deleted task Task A',
    ]);
});

test('audit trail stores human readable move message', function () {
    [$workspace, $owner] = createAuditWorkspaceWithOwner();

    $project = Project::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'name' => 'Audit Project',
        'slug' => 'audit-project',
        'visibility' => 'public',
    ]);

    $todo = ProjectColumn::create([
        'project_id' => $project->id,
        'name' => 'To Do',
        'color' => '#64748b',
        'sort_order' => 0,
    ]);

    $inProgress = ProjectColumn::create([
        'project_id' => $project->id,
        'name' => 'In Progress',
        'color' => '#3b82f6',
        'sort_order' => 1,
    ]);

    $taskA = Task::create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'project_column_id' => $todo->id,
        'created_by' => $owner->id,
        'title' => 'Task A',
        'status' => 'todo',
        'priority' => 'medium',
        'sort_order' => 0,
    ]);

    $taskB = Task::create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'project_column_id' => $inProgress->id,
        'created_by' => $owner->id,
        'title' => 'Task B',
        'status' => 'in_progress',
        'priority' => 'medium',
        'sort_order' => 0,
    ]);

    $moveResponse = $this->actingAs($owner)->patch(route('tasks.move', $taskA), [
        'project_column_id' => $inProgress->id,
        'ordered_task_ids' => [$taskB->id, $taskA->id],
    ]);

    $moveResponse->assertRedirect();

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'task.moved',
        'description' => $owner->name." moved Task A to 'In Progress'",
    ]);
});

test('audit trail tracks member actions and activity logs page supports filtering', function () {
    [$workspace, $owner] = createAuditWorkspaceWithOwner();

    $member = User::factory()->create();

    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $roleUpdateResponse = $this->actingAs($owner)->patch(route('workspaces.members.update', [
        'workspace' => $workspace,
        'member' => $member,
    ]), [
        'role' => 'admin',
    ]);

    $roleUpdateResponse->assertRedirect();

    $removeResponse = $this->actingAs($owner)->delete(route('workspaces.members.destroy', [
        'workspace' => $workspace,
        'member' => $member,
    ]));

    $removeResponse->assertRedirect();

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'member.role_updated',
        'description' => $owner->name.' changed '.$member->name.' role to admin',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'member.removed',
        'description' => $owner->name.' removed member '.$member->name,
    ]);

    $pageResponse = $this->actingAs($owner)->get(route('activity-logs.index', [
        'action' => 'member.role_updated',
    ]));

    $pageResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('activity-logs/index')
        ->where('actionFilter', 'member.role_updated')
        ->has('logs.data', 1)
    );
});
