<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any project in the workspace.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $project->userCanAccess($user);
    }

    /**
     * Determine whether the user can create projects in the workspace.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        $role = $user->roleInWorkspace($project->workspace);

        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true)
            || $project->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /**
     * Determine whether the user can manage project members.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        $role = $user->roleInWorkspace($project->workspace);

        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true)
            || $project->created_by === $user->id;
    }
}
