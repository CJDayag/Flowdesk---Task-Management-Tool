<?php

use App\Http\Controllers\Workspace\WorkspaceController;
use App\Http\Controllers\Workspace\WorkspaceInvitationController;
use App\Http\Controllers\Workspace\WorkspaceMemberController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectColumnController;
use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Task\TaskLabelController;
use App\Http\Controllers\Task\TaskAttachmentController;
use App\Http\Controllers\Task\TaskCommentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('workspaces', [WorkspaceController::class, 'store'])
        ->name('workspaces.store');

    Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])
        ->name('workspaces.update');

    Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])
        ->name('workspaces.invitations.store');

    Route::patch('workspaces/{workspace}/invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])
        ->name('workspaces.invitations.resend');

    Route::delete('workspaces/{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])
        ->name('workspaces.invitations.destroy');

    Route::patch('workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'update'])
        ->name('workspaces.members.update');

    Route::delete('workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'destroy'])
        ->name('workspaces.members.destroy');

    Route::post('workspaces/{workspace}/tasks', [TaskController::class, 'store'])
        ->name('workspaces.tasks.store');

    Route::post('workspaces/{workspace}/task-labels', [TaskLabelController::class, 'store'])
        ->name('workspaces.task-labels.store');

    Route::patch('task-labels/{label}', [TaskLabelController::class, 'update'])
        ->name('task-labels.update');

    Route::delete('task-labels/{label}', [TaskLabelController::class, 'destroy'])
        ->name('task-labels.destroy');

    Route::get('projects', [ProjectController::class, 'index'])
        ->name('projects.index');

    Route::post('projects', [ProjectController::class, 'store'])
        ->name('projects.store');

    Route::patch('projects/{project}', [ProjectController::class, 'update'])
        ->name('projects.update');

    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
        ->name('projects.destroy');

    Route::put('projects/{project}/members', [ProjectController::class, 'updateMembers'])
        ->name('projects.members.update');

    Route::post('projects/{project}/columns', [ProjectColumnController::class, 'store'])
        ->name('projects.columns.store');

    Route::patch('projects/{project}/columns/reorder', [ProjectColumnController::class, 'reorder'])
        ->name('projects.columns.reorder');

    Route::patch('tasks/{task}', [TaskController::class, 'update'])
        ->name('tasks.update');

    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])
        ->name('tasks.destroy');

    Route::post('tasks/{task}/assign', [TaskController::class, 'assign'])
        ->name('tasks.assign');

    Route::patch('tasks/{task}/move', [TaskController::class, 'move'])
        ->name('tasks.move');

    Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])
        ->name('tasks.attachments.store');

    Route::delete('tasks/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])
        ->name('tasks.attachments.destroy');

    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])
        ->name('tasks.comments.store');

    Route::delete('tasks/comments/{comment}', [TaskCommentController::class, 'destroy'])
        ->name('tasks.comments.destroy');

    Route::post('workspace-invitations/{invitation}/accept', [WorkspaceInvitationController::class, 'accept'])
        ->name('workspace-invitations.accept');

    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');

    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->name('activity-logs.index');
});

Route::get('workspace-invitations/{invitation}', [WorkspaceInvitationController::class, 'show'])
    ->name('workspace-invitations.show');
