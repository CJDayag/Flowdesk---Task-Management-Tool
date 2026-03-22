<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskCommentRequest;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskCommentAddedInAppNotification;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    /**
     * Store a task comment or threaded reply.
     */
    public function store(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $parentId = $request->integer('parent_id') ?: null;

        if ($parentId !== null) {
            $parent = TaskComment::query()->findOrFail($parentId);

            if ($parent->task_id !== $task->id) {
                abort(422, 'Parent comment must belong to the same task.');
            }
        }

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'body' => $request->string('body')->toString(),
        ]);

        ActivityLogger::log(
            $request->user(),
            $task->workspace,
            'task.comment_added',
            'Added a comment on '.$task->title,
            $comment,
            ['task_id' => $task->id, 'comment_id' => $comment->id],
        );

        $this->notifyCommentAdded($task, $request->user());

        return back()->with('status', 'Comment added.');
    }

    /**
     * Delete a task comment.
     */
    public function destroy(TaskComment $comment): RedirectResponse
    {
        $task = $comment->task;

        $this->authorize('update', $task);

        $commentId = $comment->id;

        $comment->delete();

        ActivityLogger::log(
            request()->user(),
            $task->workspace,
            'task.comment_deleted',
            'Deleted a comment on '.$task->title,
            null,
            ['task_id' => $task->id, 'comment_id' => $commentId],
        );

        return back()->with('status', 'Comment deleted.');
    }

    /**
     * Send in-app comment notifications to task participants.
     */
    private function notifyCommentAdded(Task $task, User $actor): void
    {
        $recipientIds = collect();

        if ($task->created_by !== $actor->id) {
            $recipientIds->push($task->created_by);
        }

        $assigneeIds = $task->assignees()->pluck('users.id');

        if ($assigneeIds->isEmpty() && $task->assigned_to !== null) {
            $assigneeIds = collect([$task->assigned_to]);
        }

        $recipientIds = $recipientIds
            ->merge($assigneeIds)
            ->unique()
            ->filter(fn ($id): bool => is_int($id) && $id !== $actor->id);

        $recipients = User::query()->whereIn('id', $recipientIds->all())->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new TaskCommentAddedInAppNotification($task, $actor->name));
        }
    }
}
