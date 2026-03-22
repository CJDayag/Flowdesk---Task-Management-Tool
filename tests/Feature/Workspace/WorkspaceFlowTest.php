<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationInAppNotification;
use App\Notifications\WorkspaceInvitationNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('authenticated user can create a workspace and become the owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('workspaces.store'), [
        'name' => 'Acme Engineering',
        'theme' => 'dark',
    ]);

    $workspace = Workspace::first();

    expect($workspace)->not->toBeNull()
        ->and($workspace->name)->toBe('Acme Engineering')
        ->and($workspace->theme)->toBe('dark')
        ->and($workspace->owner_id)->toBe($user->id);

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('workspace owner can invite a member via email', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Acme Team',
        'slug' => 'acme-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $existingUser = User::factory()->create([
        'email' => 'new.member@example.com',
    ]);

    $response = $this->actingAs($owner)->from(route('dashboard'))->post(route('workspaces.invitations.store', $workspace), [
        'email' => 'new.member@example.com',
        'role' => 'member',
    ]);

    $this->assertDatabaseHas('workspace_invitations', [
        'workspace_id' => $workspace->id,
        'email' => 'new.member@example.com',
        'role' => 'member',
    ]);

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, function ($notification, array $channels, object $notifiable): bool {
        return in_array('mail', $channels, true)
            && $notifiable->routes['mail'] === 'new.member@example.com';
    });

    Notification::assertSentTo($owner, WorkspaceInvitationInAppNotification::class);
    Notification::assertSentTo($existingUser, WorkspaceInvitationInAppNotification::class);

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('invited user can accept a pending invitation with a valid token', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'member@example.com']);

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Roadmap Team',
        'slug' => 'roadmap-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $token = 'invite-token-123';

    $invitation = WorkspaceInvitation::create([
        'workspace_id' => $workspace->id,
        'invited_by' => $owner->id,
        'email' => 'member@example.com',
        'role' => 'member',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->actingAs($invitedUser)->post(route('workspace-invitations.accept', $invitation).'?token='.$token);

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $invitedUser->id,
        'role' => 'member',
    ]);

    expect($invitedUser->fresh()->current_workspace_id)->toBe($workspace->id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('signed invitation link renders acceptance page for authenticated invitee', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'member2@example.com']);

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Growth Team',
        'slug' => 'growth-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $token = 'invite-token-456';

    $invitation = WorkspaceInvitation::create([
        'workspace_id' => $workspace->id,
        'invited_by' => $owner->id,
        'email' => 'member2@example.com',
        'role' => 'admin',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addHours(24),
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'workspace-invitations.show',
        $invitation->expires_at,
        ['invitation' => $invitation->id, 'token' => $token],
    );

    $response = $this->actingAs($invitedUser)->get($signedUrl);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('workspace-invitations/show')
        ->where('workspace.id', $workspace->id)
        ->where('workspace.name', 'Growth Team')
        ->where('invitation.id', $invitation->id)
        ->where('invitation.role', 'admin')
    );
});

test('invalid invitation link renders user-friendly error page', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'member3@example.com']);

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Platform Team',
        'slug' => 'platform-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $invitation = WorkspaceInvitation::create([
        'workspace_id' => $workspace->id,
        'invited_by' => $owner->id,
        'email' => 'member3@example.com',
        'role' => 'member',
        'token_hash' => hash('sha256', 'token-789'),
        'expires_at' => now()->addHours(24),
    ]);

    $invalidSignedUrl = URL::temporarySignedRoute(
        'workspace-invitations.show',
        $invitation->expires_at,
        ['invitation' => $invitation->id, 'token' => 'wrong-token'],
    );

    $response = $this->actingAs($invitedUser)->get($invalidSignedUrl);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('workspace-invitations/error')
        ->where('title', 'Invitation unavailable')
    );
});

test('relative signed invitation url from in-app notification is accepted', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'member4@example.com']);

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'QA Team',
        'slug' => 'qa-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $token = 'invite-token-relative';

    $invitation = WorkspaceInvitation::create([
        'workspace_id' => $workspace->id,
        'invited_by' => $owner->id,
        'email' => 'member4@example.com',
        'role' => 'member',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addHours(24),
    ]);

    $relativeSignedUrl = URL::temporarySignedRoute(
        'workspace-invitations.show',
        $invitation->expires_at,
        ['invitation' => $invitation->id, 'token' => $token],
        false,
    );

    $response = $this->actingAs($invitedUser)->get($relativeSignedUrl);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('workspace-invitations/show')
        ->where('workspace.id', $workspace->id)
        ->where('invitation.id', $invitation->id)
    );
});
