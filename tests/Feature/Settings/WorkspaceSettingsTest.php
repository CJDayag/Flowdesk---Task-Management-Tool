<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Notification;

function workspaceSettingsContext(): array
{
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Operations Team',
        'slug' => 'operations-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$workspace, $owner];
}

test('workspace settings page renders workspace and member data', function () {
    [$workspace, $owner] = workspaceSettingsContext();

    $member = User::factory()->create();

    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('workspace-settings.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/workspace')
        ->where('workspace.id', $workspace->id)
        ->where('workspace.name', 'Operations Team')
        ->where('canUpdateWorkspace', true)
        ->where('canManageMembers', true)
        ->has('members', 2)
        ->has('pendingInvitations')
    );
});

test('workspace settings page shows create-only state when user has no workspaces', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('workspace-settings.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/workspace')
        ->where('workspace', null)
        ->where('canUpdateWorkspace', false)
        ->where('canManageMembers', false)
        ->has('members', 0)
        ->has('pendingInvitations', 0)
    );
});

test('owner can rename workspace and manage members from settings actions', function () {
    [$workspace, $owner] = workspaceSettingsContext();

    $member = User::factory()->create();

    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $renameResponse = $this->actingAs($owner)->patch(route('workspaces.update', $workspace), [
        'name' => 'Operations Command',
        'theme' => 'system',
    ]);

    $renameResponse->assertRedirect();

    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => 'Operations Command',
    ]);

    $roleResponse = $this->actingAs($owner)->patch(route('workspaces.members.update', [
        'workspace' => $workspace,
        'member' => $member,
    ]), [
        'role' => 'admin',
    ]);

    $roleResponse->assertRedirect();

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => 'admin',
    ]);

    $removeResponse = $this->actingAs($owner)->delete(route('workspaces.members.destroy', [
        'workspace' => $workspace,
        'member' => $member,
    ]));

    $removeResponse->assertRedirect();

    $this->assertDatabaseMissing('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
});

test('owner can resend and revoke pending invitations from workspace settings', function () {
    Notification::fake();

    [$workspace, $owner] = workspaceSettingsContext();

    $invitation = WorkspaceInvitation::create([
        'workspace_id' => $workspace->id,
        'invited_by' => $owner->id,
        'email' => 'invitee@example.com',
        'role' => 'member',
        'token_hash' => hash('sha256', 'original-token'),
        'expires_at' => now()->addHours(24),
    ]);

    $oldTokenHash = $invitation->token_hash;

    $resendResponse = $this->actingAs($owner)->patch(route('workspaces.invitations.resend', [
        'workspace' => $workspace,
        'invitation' => $invitation,
    ]));

    $resendResponse->assertRedirect();

    $invitation->refresh();

    expect($invitation->token_hash)->not->toBe($oldTokenHash);

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, function ($notification, array $channels, object $notifiable): bool {
        return in_array('mail', $channels, true)
            && $notifiable->routes['mail'] === 'invitee@example.com';
    });

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'workspace.invitation_resent',
    ]);

    $revokeResponse = $this->actingAs($owner)->delete(route('workspaces.invitations.destroy', [
        'workspace' => $workspace,
        'invitation' => $invitation,
    ]));

    $revokeResponse->assertRedirect();

    $this->assertDatabaseMissing('workspace_invitations', [
        'id' => $invitation->id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'action' => 'workspace.invitation_revoked',
    ]);
});
