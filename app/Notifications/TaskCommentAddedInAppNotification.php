<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCommentAddedInAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly string $commentAuthor,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'comment_added',
            'title' => 'New comment',
            'message' => $this->commentAuthor.' commented on "'.$this->task->title.'".',
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'workspace_id' => $this->task->workspace_id,
            'url' => '/projects?project_id='.$this->task->project_id.'&view=board',
        ];
    }
}
