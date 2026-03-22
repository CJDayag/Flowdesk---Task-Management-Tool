<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

function workspaceWithSearchContext(): array
{
    $owner = User::factory()->create(['name' => 'Owner User']);

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Search Workspace',
        'slug' => 'search-workspace',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$workspace, $owner];
}

test('global search finds matching tasks users and projects', function () {
    [$workspace, $owner] = workspaceWithSearchContext();

    $alphaUser = User::factory()->create([
        'name' => 'Alice Alpha',
        'email' => 'alice.alpha@example.test',
    ]);

    $workspace->members()->attach($alphaUser->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $project = Project::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'name' => 'Alpha Project',
        'slug' => 'alpha-project',
        'visibility' => 'public',
    ]);

    Task::create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'title' => 'Alpha Task',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $response = $this->actingAs($owner)->get(route('projects.index', [
        'q' => 'alpha',
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('projects/index')
        ->where('filters.q', 'alpha')
        ->has('searchResults.tasks', 1)
        ->has('searchResults.users', 1)
        ->has('searchResults.projects', 1)
        ->where('searchResults.tasks.0.title', 'Alpha Task')
        ->where('searchResults.users.0.name', 'Alice Alpha')
        ->where('searchResults.projects.0.name', 'Alpha Project')
    );
});

test('task filters support status assignee priority and due date', function () {
    [$workspace, $owner] = workspaceWithSearchContext();

    $assignee = User::factory()->create(['name' => 'Filter Member']);

    $workspace->members()->attach($assignee->id, [
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $project = Project::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'name' => 'Filter Project',
        'slug' => 'filter-project',
        'visibility' => 'public',
    ]);

    $matchingTask = Task::create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'assigned_to' => $assignee->id,
        'title' => 'Filtered Task',
        'status' => 'in_progress',
        'priority' => 'high',
        'due_at' => '2026-03-25 09:00:00',
    ]);

    Task::create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'title' => 'Other Task',
        'status' => 'todo',
        'priority' => 'low',
        'due_at' => '2026-03-28 09:00:00',
    ]);

    $response = $this->actingAs($owner)->get(route('projects.index', [
        'project_id' => $project->id,
        'status' => 'in_progress',
        'assignee_id' => $assignee->id,
        'priority' => 'high',
        'due_date' => '2026-03-25',
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('projects/index')
        ->where('filters.status', 'in_progress')
        ->where('filters.assignee_id', $assignee->id)
        ->where('filters.priority', 'high')
        ->where('filters.due_date', '2026-03-25')
        ->has('tasks', 1)
        ->where('tasks.0.id', $matchingTask->id)
        ->where('tasks.0.title', 'Filtered Task')
    );
});
