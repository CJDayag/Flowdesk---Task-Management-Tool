<?php

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;

function notificationWorkspace(): array
{
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Notifications Workspace',
        'slug' => 'notifications-workspace',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$workspace, $owner];
}

test('task assignment creates in-app notification', function () {
    [$workspace, $owner] = notificationWorkspace();

    $assignee = User::factory()->create();

    $workspace->members()->attach($assignee->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $task = $workspace->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Notify assignment',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $response = $this->actingAs($owner)->post(route('tasks.assign', $task), [
        'assignee_ids' => [$assignee->id],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $assignee->id,
        'notifiable_type' => User::class,
        'type' => App\Notifications\TaskAssignedInAppNotification::class,
    ]);
});

test('task comment creates in-app notification for other participants', function () {
    [$workspace, $owner] = notificationWorkspace();

    $assignee = User::factory()->create();

    $workspace->members()->attach($assignee->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $task = $workspace->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Notify comment',
        'status' => 'todo',
        'priority' => 'medium',
        'assigned_to' => $assignee->id,
    ]);

    $task->assignees()->sync([$assignee->id => ['assigned_at' => now()]]);

    $response = $this->actingAs($owner)->post(route('tasks.comments.store', $task), [
        'body' => 'Please review this update.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $assignee->id,
        'notifiable_type' => User::class,
        'type' => App\Notifications\TaskCommentAddedInAppNotification::class,
    ]);
});

test('due soon command creates in-app notifications', function () {
    [$workspace, $owner] = notificationWorkspace();

    $assignee = User::factory()->create();

    $workspace->members()->attach($assignee->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $task = $workspace->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Due soon task',
        'status' => 'in_progress',
        'priority' => 'high',
        'due_at' => now()->addHours(6),
        'assigned_to' => $assignee->id,
    ]);

    $task->assignees()->sync([$assignee->id => ['assigned_at' => now()]]);

    Artisan::call('tasks:notify-due-soon');

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $assignee->id,
        'notifiable_type' => User::class,
        'type' => App\Notifications\TaskDueSoonInAppNotification::class,
    ]);
});

test('user can mark notification as read and mark all read', function () {
    [$workspace, $owner] = notificationWorkspace();

    $task = $workspace->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Read notification task',
        'status' => 'todo',
        'priority' => 'medium',
        'due_at' => now()->addHours(8),
    ]);

    $owner->notify(new App\Notifications\TaskDueSoonInAppNotification($task));
    $owner->notify(new App\Notifications\TaskAssignedInAppNotification($task, 'System'));

    $first = $owner->notifications()->firstOrFail();

    $singleReadResponse = $this->actingAs($owner)->post(route('notifications.read', $first));
    $singleReadResponse->assertRedirect();

    $this->assertDatabaseMissing('notifications', [
        'id' => $first->id,
        'read_at' => null,
    ]);

    $markAllResponse = $this->actingAs($owner)->post(route('notifications.read-all'));
    $markAllResponse->assertRedirect();

    expect($owner->fresh()->unreadNotifications()->count())->toBe(0);
});
