<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('workspace', null)
        ->has('analytics.tasksCompletedByDay', 0)
        ->has('analytics.tasksCompletedByWeek', 0)
        ->has('analytics.tasksByStatus', 0)
        ->has('analytics.userProductivity', 0)
        ->has('analytics.activityTimeline', 0)
    );
});

test('dashboard recent activity supports pagination', function () {
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Dashboard Team',
        'slug' => 'dashboard-team',
        'theme' => 'system',
    ]);

    $workspace->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

    for ($i = 1; $i <= 13; $i++) {
        ActivityLog::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'action' => 'task.updated',
            'description' => 'Activity '.$i,
            'properties' => ['index' => $i],
        ]);
    }

    $response = $this->actingAs($owner)->get(route('dashboard', ['activity_page' => 2]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('recentActivity.current_page', 2)
        ->where('recentActivity.last_page', 2)
        ->has('recentActivity.data', 5)
        ->has('analytics.tasksCompletedByDay', 14)
        ->has('analytics.tasksCompletedByWeek', 8)
        ->has('analytics.tasksByStatus', 3)
        ->has('analytics.activityTimeline', 14)
    );
});