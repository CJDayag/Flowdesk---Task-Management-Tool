<?php

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskLabel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createWorkspaceWithRole(string $role = 'owner'): array
{
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Core Tasks Workspace',
        'slug' => 'core-tasks-workspace',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => $role,
        'joined_at' => now(),
    ]);

    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$workspace, $owner];
}

test('task crud supports status priority due date and labels', function () {
    [$workspace, $owner] = createWorkspaceWithRole();

    $label = TaskLabel::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'name' => 'Bug',
        'color' => '#ef4444',
    ]);

    $createResponse = $this->actingAs($owner)->post(route('workspaces.tasks.store', $workspace), [
        'title' => 'Fix login redirect',
        'description' => 'Handle redirect after verification',
        'status' => 'in_progress',
        'priority' => 'high',
        'due_at' => now()->addDays(2)->toDateString(),
        'label_ids' => [$label->id],
    ]);

    $createResponse->assertRedirect();

    $task = Task::query()->firstOrFail();

    expect($task->status)->toBe('in_progress')
        ->and($task->priority->value)->toBe('high');

    $this->assertDatabaseHas('label_task', [
        'task_id' => $task->id,
        'task_label_id' => $label->id,
    ]);

    $updateResponse = $this->actingAs($owner)->patch(route('tasks.update', $task), [
        'status' => 'done',
        'priority' => 'low',
    ]);

    $updateResponse->assertRedirect();

    expect($task->fresh()->status)->toBe('done')
        ->and($task->fresh()->priority->value)->toBe('low');

    $deleteResponse = $this->actingAs($owner)->delete(route('tasks.destroy', $task));

    $deleteResponse->assertRedirect();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

test('task can be assigned to multiple users and reassigned', function () {
    [$workspace, $owner] = createWorkspaceWithRole();

    $assigneeA = User::factory()->create();
    $assigneeB = User::factory()->create();
    $assigneeC = User::factory()->create();

    foreach ([$assigneeA, $assigneeB, $assigneeC] as $member) {
        $workspace->members()->attach($member->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }

    $task = $workspace->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Prepare sprint goals',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $assignResponse = $this->actingAs($owner)->post(route('tasks.assign', $task), [
        'assignee_ids' => [$assigneeA->id, $assigneeB->id],
    ]);

    $assignResponse->assertRedirect();

    $this->assertDatabaseHas('task_user', ['task_id' => $task->id, 'user_id' => $assigneeA->id]);
    $this->assertDatabaseHas('task_user', ['task_id' => $task->id, 'user_id' => $assigneeB->id]);

    $reassignResponse = $this->actingAs($owner)->post(route('tasks.assign', $task), [
        'assignee_ids' => [$assigneeC->id],
    ]);

    $reassignResponse->assertRedirect();

    $this->assertDatabaseHas('task_user', ['task_id' => $task->id, 'user_id' => $assigneeC->id]);
    $this->assertDatabaseMissing('task_user', ['task_id' => $task->id, 'user_id' => $assigneeA->id]);
});

test('workspace admin can manage custom labels', function () {
    [$workspace, $owner] = createWorkspaceWithRole();

    $response = $this->actingAs($owner)->post(route('workspaces.task-labels.store', $workspace), [
        'name' => 'Feature',
        'color' => '#22c55e',
    ]);

    $response->assertRedirect();

    $label = TaskLabel::query()->firstOrFail();

    $update = $this->actingAs($owner)->patch(route('task-labels.update', $label), [
        'name' => 'Enhancement',
        'color' => '#16a34a',
    ]);

    $update->assertRedirect();

    expect($label->fresh()->name)->toBe('Enhancement');

    $delete = $this->actingAs($owner)->delete(route('task-labels.destroy', $label));

    $delete->assertRedirect();

    $this->assertDatabaseMissing('task_labels', ['id' => $label->id]);
});

test('task attachments and threaded comments are supported', function () {
    Storage::fake('public');

    [$workspace, $owner] = createWorkspaceWithRole();

    $task = $workspace->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Document release notes',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $attachmentResponse = $this->actingAs($owner)->post(route('tasks.attachments.store', $task), [
        'files' => [
            UploadedFile::fake()->image('proof.png'),
            UploadedFile::fake()->create('spec.docx', 12),
        ],
    ]);

    $attachmentResponse->assertRedirect();

    expect($task->attachments()->count())->toBe(2);

    $commentResponse = $this->actingAs($owner)->post(route('tasks.comments.store', $task), [
        'body' => 'Initial investigation complete.',
    ]);

    $commentResponse->assertRedirect();

    $parentComment = TaskComment::query()->firstOrFail();

    $replyResponse = $this->actingAs($owner)->post(route('tasks.comments.store', $task), [
        'body' => 'Adding implementation details.',
        'parent_id' => $parentComment->id,
    ]);

    $replyResponse->assertRedirect();

    $this->assertDatabaseHas('task_comments', [
        'task_id' => $task->id,
        'parent_id' => $parentComment->id,
    ]);

    $deleteCommentResponse = $this->actingAs($owner)->delete(route('tasks.comments.destroy', $parentComment));

    $deleteCommentResponse->assertRedirect();

    $this->assertDatabaseMissing('task_comments', ['id' => $parentComment->id]);

    $attachment = $task->attachments()->firstOrFail();

    $deleteAttachmentResponse = $this->actingAs($owner)->delete(route('tasks.attachments.destroy', $attachment));

    $deleteAttachmentResponse->assertRedirect();

    $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
});
