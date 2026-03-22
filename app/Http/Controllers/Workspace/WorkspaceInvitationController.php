<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceInvitationRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationInAppNotification;
use App\Notifications\WorkspaceInvitationNotification;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceInvitationController extends Controller
{
    /**
     * Create a new invitation and send it via email.
     */
    public function store(StoreWorkspaceInvitationRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $token = Str::random(64);
        $email = Str::lower($request->string('email')->toString());
        $expiresAt = now()->addHours($request->integer('expires_in_hours', 48));

        $workspace->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->delete();

        $invitation = $workspace->invitations()->create([
            'invited_by' => $request->user()->id,
            'email' => $email,
            'role' => $request->string('role')->toString(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        $this->sendInvitationEmail($invitation, $token);
        $this->sendInvitationInAppNotifications($request->user(), $invitation, $token, false);

        ActivityLogger::log(
            $request->user(),
            $workspace,
            'workspace.invitation_sent',
            'Sent invitation to '.$email,
            $invitation,
            ['email' => $email, 'role' => $invitation->role],
        );

        return back()->with('status', 'Invitation sent.');
    }

    /**
     * Resend an existing pending invitation with a refreshed token.
     */
    public function resend(Request $request, Workspace $workspace, WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        abort_unless($invitation->workspace_id === $workspace->id, 404);
        abort_unless($invitation->accepted_at === null, 422, 'Cannot resend an accepted invitation.');

        $token = Str::random(64);

        $invitation->forceFill([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours($request->integer('expires_in_hours', 48)),
            'invited_by' => $request->user()->id,
        ])->save();

        $this->sendInvitationEmail($invitation, $token);
        $this->sendInvitationInAppNotifications($request->user(), $invitation, $token, true);

        ActivityLogger::log(
            $request->user(),
            $workspace,
            'workspace.invitation_resent',
            'Resent invitation to '.$invitation->email,
            $invitation,
            ['email' => $invitation->email, 'role' => $invitation->role],
        );

        return back()->with('status', 'Invitation resent.');
    }

    /**
     * Revoke a pending invitation.
     */
    public function destroy(Request $request, Workspace $workspace, WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        abort_unless($invitation->workspace_id === $workspace->id, 404);
        abort_unless($invitation->accepted_at === null, 422, 'Cannot revoke an accepted invitation.');

        $email = $invitation->email;
        $invitation->delete();

        ActivityLogger::log(
            $request->user(),
            $workspace,
            'workspace.invitation_revoked',
            'Revoked invitation for '.$email,
            null,
            ['email' => $email],
        );

        return back()->with('status', 'Invitation revoked.');
    }

    /**
     * Show invitation details from a signed invitation link.
     */
    public function show(Request $request, WorkspaceInvitation $invitation): Response|RedirectResponse
    {
        $hasValidSignature = $request->hasValidSignature() || $request->hasValidSignature(absolute: false);

        if (! $hasValidSignature || ! $this->isValidInvitationToken($request, $invitation)) {
            return Inertia::render('workspace-invitations/error', [
                'title' => 'Invitation unavailable',
                'message' => 'This invitation link is invalid or has expired. Ask your workspace admin to send a new invite.',
            ]);
        }

        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        return Inertia::render('workspace-invitations/show', [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
            ],
            'workspace' => [
                'id' => $invitation->workspace_id,
                'name' => $invitation->workspace->name,
                'slug' => $invitation->workspace->slug,
            ],
            'token' => $request->query('token'),
        ]);
    }

    /**
     * Accept an invitation and join the workspace.
     */
    public function accept(Request $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        if (! $this->isValidInvitationToken($request, $invitation)) {
            return back()->withErrors([
                'invitation' => 'This invitation is invalid or has expired. Please request a new invite.',
            ]);
        }

        $user = $request->user();

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            return back()->withErrors([
                'invitation' => 'This invitation was sent to '.$invitation->email.'. Sign in with that email to accept.',
            ]);
        }

        $invitation->workspace->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $invitation->role,
                'joined_at' => now(),
            ],
        ]);

        $invitation->forceFill(['accepted_at' => now()])->save();

        $user->forceFill(['current_workspace_id' => $invitation->workspace_id])->save();

        ActivityLogger::log(
            $user,
            $invitation->workspace,
            'workspace.invitation_accepted',
            'Accepted workspace invitation',
            $invitation,
            ['workspace_id' => $invitation->workspace_id],
        );

        return to_route('dashboard')->with('status', 'You joined the workspace successfully.');
    }

    private function isValidInvitationToken(Request $request, WorkspaceInvitation $invitation): bool
    {
        $token = $request->query('token');

        if (! is_string($token)) {
            return false;
        }

        return $invitation->isPending() && $invitation->matchesToken($token);
    }

    /**
     * Send invitation notification to invite email.
     */
    private function sendInvitationEmail(WorkspaceInvitation $invitation, string $token): void
    {
        $invitation->load('workspace');

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation, $token));
    }

    /**
     * Send invite-related in-app notifications.
     */
    private function sendInvitationInAppNotifications(
        User $actor,
        WorkspaceInvitation $invitation,
        string $token,
        bool $resent,
    ): void {
        $invitation->loadMissing('workspace');

        $settingsUrl = '/settings/workspace';

        $actor->notify(new WorkspaceInvitationInAppNotification([
            'kind' => $resent ? 'workspace_invitation_resent' : 'workspace_invitation_sent',
            'title' => $resent ? 'Invitation resent' : 'Invitation sent',
            'message' => ($resent ? 'Invitation resent to ' : 'Invitation sent to ').$invitation->email.'.',
            'workspace_id' => $invitation->workspace_id,
            'workspace_name' => $invitation->workspace->name,
            'url' => $settingsUrl,
        ]));

        $invitee = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])
            ->first();

        if (! $invitee || $invitee->id === $actor->id) {
            return;
        }

        $acceptUrl = URL::temporarySignedRoute(
            'workspace-invitations.show',
            $invitation->expires_at,
            ['invitation' => $invitation->id, 'token' => $token],
            false,
        );

        $invitee->notify(new WorkspaceInvitationInAppNotification([
            'kind' => 'workspace_invited',
            'title' => 'Workspace invitation',
            'message' => $actor->name.' invited you to join '.$invitation->workspace->name.'.',
            'workspace_id' => $invitation->workspace_id,
            'workspace_name' => $invitation->workspace->name,
            'url' => $acceptUrl,
        ]));
    }
}
