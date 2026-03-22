<?php

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

function createWorkspaceAndOwner(): array
{
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Delivery Team',
        'slug' => 'delivery-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    return [$workspace, $owner];
}

test('member can create update and delete tasks', function () {
    [$workspace, $owner] = createWorkspaceAndOwner();
    $member = User::factory()->create();

    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $createResponse = $this->actingAs($member)->post(route('workspaces.tasks.store', $workspace), [
        'title' => 'Draft release notes',
        'description' => 'Prepare notes for Friday release',
    ]);

    $createResponse->assertRedirect();

    $task = Task::query()->firstOrFail();

    $updateResponse = $this->actingAs($member)->patch(route('tasks.update', $task), [
        'status' => 'in_progress',
    ]);

    $updateResponse->assertRedirect();

    $deleteResponse = $this->actingAs($member)->delete(route('tasks.destroy', $task));

    $deleteResponse->assertRedirect();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

test('member cannot assign tasks while admin can', function () {
    [$workspace, $owner] = createWorkspaceAndOwner();

    $member = User::factory()->create();
    $admin = User::factory()->create();
    $assignee = User::factory()->create();

    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $workspace->members()->attach($admin->id, [
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    $workspace->members()->attach($assignee->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $task = $workspace->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Create sprint board',
        'status' => 'todo',
    ]);

    $memberResponse = $this->actingAs($member)->post(route('tasks.assign', $task), [
        'assignee_ids' => [$assignee->id],
    ]);

    $memberResponse->assertForbidden();

    $adminResponse = $this->actingAs($admin)->post(route('tasks.assign', $task), [
        'assignee_ids' => [$assignee->id],
    ]);

    $adminResponse->assertRedirect();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'assigned_to' => $assignee->id,
    ]);
});
