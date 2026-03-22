<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskDueSoonInAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
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
            'kind' => 'due_approaching',
            'title' => 'Due date approaching',
            'message' => '"'.$this->task->title.'" is due soon.',
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'workspace_id' => $this->task->workspace_id,
            'due_at' => $this->task->due_at?->toIso8601String(),
            'url' => '/projects?project_id='.$this->task->project_id.'&view=calendar',
        ];
    }
}
