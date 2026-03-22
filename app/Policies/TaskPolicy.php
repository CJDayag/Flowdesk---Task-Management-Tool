<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

class TaskPolicy
{
    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return $this->hasTaskAccess($user, $task);
    }

    /**
     * Determine whether the user can create tasks in a workspace.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can update a task.
     */
    public function update(User $user, Task $task): bool
    {
        return $this->hasTaskAccess($user, $task);
    }

    /**
     * Determine whether the user can delete a task.
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->hasTaskAccess($user, $task);
    }

    /**
     * Determine whether the user can assign tasks.
     */
    public function assign(User $user, Task $task): bool
    {
        if (! $this->hasTaskAccess($user, $task)) {
            return false;
        }

        $role = $user->roleInWorkspace($task->workspace);

        return $role?->canAssignTasks() ?? false;
    }

    /**
     * Determine whether a user can access the task's project context.
     */
    private function hasTaskAccess(User $user, Task $task): bool
    {
        if (! $task->workspace->members()->where('user_id', $user->id)->exists()) {
            return false;
        }

        if (! $task->project) {
            return true;
        }

        return $task->project->userCanAccess($user);
    }
}
