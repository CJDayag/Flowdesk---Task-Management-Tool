<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\UpdateWorkspaceMemberRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;

class WorkspaceMemberController extends Controller
{
    /**
     * Update the role for a member in the workspace.
     */
    public function update(UpdateWorkspaceMemberRequest $request, Workspace $workspace, User $member): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        if (! $member->belongsToWorkspace($workspace)) {
            abort(404);
        }

        if ($workspace->owner_id === $member->id) {
            abort(422, 'Owner role cannot be changed.');
        }

        $role = $request->string('role')->toString();

        $workspace->members()->updateExistingPivot($member->id, ['role' => $role]);

        ActivityLogger::log(
            $request->user(),
            $workspace,
            'member.role_updated',
            $request->user()->name.' changed '.$member->name.' role to '.$role,
            $member,
            ['member_id' => $member->id, 'role' => $role],
        );

        return back()->with('status', 'Member role updated.');
    }

    /**
     * Remove a member from the workspace.
     */
    public function destroy(Workspace $workspace, User $member): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $actor = request()->user();

        if (! $member->belongsToWorkspace($workspace)) {
            abort(404);
        }

        if ($workspace->owner_id === $member->id) {
            abort(422, 'Owner cannot be removed from the workspace.');
        }

        $workspace->members()->detach($member->id);

        if ($member->current_workspace_id === $workspace->id) {
            $nextWorkspaceId = $member->workspaces()->value('workspaces.id');
            $member->forceFill(['current_workspace_id' => $nextWorkspaceId])->save();
        }

        ActivityLogger::log(
            $actor,
            $workspace,
            'member.removed',
            $actor->name.' removed member '.$member->name,
            $member,
            ['member_id' => $member->id],
        );

        return back()->with('status', 'Member removed.');
    }
}
