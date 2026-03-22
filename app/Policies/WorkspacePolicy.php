<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view the workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can update workspace settings.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageWorkspaceSettings() ?? false;
    }

    /**
     * Determine whether the user can manage workspace members.
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageMembers() ?? false;
    }
}
