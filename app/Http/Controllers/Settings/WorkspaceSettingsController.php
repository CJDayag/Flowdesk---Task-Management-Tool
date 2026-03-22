<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceSettingsController extends Controller
{
    /**
     * Display workspace settings and member management.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        if (! $workspace instanceof Workspace) {
            $workspace = $user?->workspaces()->orderBy('workspaces.created_at')->first();
        }

        if (! $workspace instanceof Workspace) {
            return Inertia::render('settings/workspace', [
                'workspace' => null,
                'members' => [],
                'pendingInvitations' => [],
                'canUpdateWorkspace' => false,
                'canManageMembers' => false,
            ]);
        }

        $members = $workspace->members()
            ->select('users.id', 'users.name', 'users.email', 'workspace_user.role', 'workspace_user.joined_at')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => (string) $member->pivot->role,
                'joined_at' => $member->pivot->joined_at,
                'is_owner' => $member->id === $workspace->owner_id,
            ]);

        $pendingInvitations = $workspace->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get(['id', 'email', 'role', 'expires_at', 'created_at'])
            ->map(fn ($invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
                'created_at' => $invitation->created_at,
            ]);

        return Inertia::render('settings/workspace', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'theme' => $workspace->theme,
            ],
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'canUpdateWorkspace' => $request->user()->can('update', $workspace),
            'canManageMembers' => $request->user()->can('manageMembers', $workspace),
        ]);
    }
}
