<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDueSoonInAppNotification;
use Illuminate\Console\Command;

class SendTaskDueSoonNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:notify-due-soon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create in-app notifications for tasks with upcoming due dates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tasks = Task::query()
            ->with(['assignees:id,name,email', 'assignee:id,name,email'])
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->where('due_at', '<=', now()->addDay())
            ->whereNot('status', 'done')
            ->get();

        $sentCount = 0;

        foreach ($tasks as $task) {
            $targets = $task->assignees;

            if ($targets->isEmpty() && $task->assignee) {
                $targets = collect([$task->assignee]);
            }

            foreach ($targets as $user) {
                $user->notify(new TaskDueSoonInAppNotification($task));
                $sentCount++;
            }
        }

        $this->info('Created '.$sentCount.' due-soon notifications.');

        return self::SUCCESS;
    }
}
