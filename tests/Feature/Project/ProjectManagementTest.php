<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

function workspaceWithOwnerAndCurrentContext(): array
{
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Product Workspace',
        'slug' => 'product-workspace',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$workspace, $owner];
}

test('workspace member can create update and delete project', function () {
    [$workspace, $owner] = workspaceWithOwnerAndCurrentContext();

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);
    $member->forceFill(['current_workspace_id' => $workspace->id])->save();

    $createResponse = $this->actingAs($member)->post(route('projects.store'), [
        'name' => 'Mobile App',
        'description' => 'Track all mobile delivery tasks',
        'visibility' => 'public',
    ]);

    $createResponse->assertRedirect();

    $project = Project::query()->firstOrFail();

    expect($project->workspace_id)->toBe($workspace->id)
        ->and($project->created_by)->toBe($member->id)
        ->and($project->visibility->value)->toBe('public');

    $updateResponse = $this->actingAs($member)->patch(route('projects.update', $project), [
        'name' => 'Mobile App Revamp',
        'visibility' => 'private',
    ]);

    $updateResponse->assertRedirect();

    expect($project->fresh()->name)->toBe('Mobile App Revamp')
        ->and($project->fresh()->visibility->value)->toBe('private');

    $deleteResponse = $this->actingAs($member)->delete(route('projects.destroy', $project));

    $deleteResponse->assertRedirect();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

test('private project is hidden from non-member workspace users', function () {
    [$workspace, $owner] = workspaceWithOwnerAndCurrentContext();

    $privateMember = User::factory()->create();
    $outsideMember = User::factory()->create();

    $workspace->members()->attach($privateMember->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);
    $workspace->members()->attach($outsideMember->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $privateMember->forceFill(['current_workspace_id' => $workspace->id])->save();
    $outsideMember->forceFill(['current_workspace_id' => $workspace->id])->save();

    $project = $workspace->projects()->create([
        'created_by' => $owner->id,
        'name' => 'Security Initiative',
        'slug' => 'security-initiative',
        'visibility' => 'private',
    ]);

    $project->members()->attach([$owner->id, $privateMember->id], ['joined_at' => now()]);

    $visibleResponse = $this->actingAs($privateMember)->get(route('projects.index'));
    $visibleResponse->assertOk();
    $visibleResponse->assertSee('Security Initiative');

    $hiddenResponse = $this->actingAs($outsideMember)->get(route('projects.index'));
    $hiddenResponse->assertOk();
    $hiddenResponse->assertDontSee('Security Initiative');
});

test('project manager can assign project members and task links project', function () {
    [$workspace, $owner] = workspaceWithOwnerAndCurrentContext();

    $admin = User::factory()->create();
    $member = User::factory()->create();

    $workspace->members()->attach($admin->id, [
        'role' => 'admin',
        'joined_at' => now(),
    ]);
    $workspace->members()->attach($member->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $admin->forceFill(['current_workspace_id' => $workspace->id])->save();

    $project = $workspace->projects()->create([
        'created_by' => $owner->id,
        'name' => 'API Platform',
        'slug' => 'api-platform',
        'visibility' => 'private',
    ]);

    $memberSyncResponse = $this->actingAs($admin)->put(route('projects.members.update', $project), [
        'member_ids' => [$member->id],
    ]);

    $memberSyncResponse->assertRedirect();

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);

    $taskCreateResponse = $this->actingAs($member)->post(route('workspaces.tasks.store', $workspace), [
        'project_id' => $project->id,
        'title' => 'Implement auth guard',
        'status' => 'todo',
    ]);

    $taskCreateResponse->assertRedirect();

    $task = Task::query()->latest('id')->firstOrFail();

    expect($task->project_id)->toBe($project->id);
});
