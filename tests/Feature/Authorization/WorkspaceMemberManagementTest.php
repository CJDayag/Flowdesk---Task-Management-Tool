<?php

use App\Models\User;
use App\Models\Workspace;

function createWorkspaceWithOwner(User $owner): Workspace
{
    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Alpha Team',
        'slug' => 'alpha-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    return $workspace;
}

test('owner can update member role', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = createWorkspaceWithOwner($owner);
    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner)->patch(route('workspaces.members.update', [
        'workspace' => $workspace,
        'member' => $member,
    ]), [
        'role' => 'admin',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => 'admin',
    ]);
});

test('member cannot manage members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $target = User::factory()->create();

    $workspace = createWorkspaceWithOwner($owner);

    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $workspace->members()->attach($target->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($member)->patch(route('workspaces.members.update', [
        'workspace' => $workspace,
        'member' => $target,
    ]), [
        'role' => 'admin',
    ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $target->id,
        'role' => 'member',
    ]);
});
