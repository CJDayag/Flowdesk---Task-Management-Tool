<?php

use App\Models\User;

test('user can update compact view preference', function () {
    $user = User::factory()->create([
        'compact_view' => false,
    ]);

    $enableResponse = $this->actingAs($user)->patch(route('preferences.update'), [
        'compact_view' => true,
    ]);

    $enableResponse->assertRedirect();

    expect($user->fresh()->compact_view)->toBeTrue();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $user->id,
        'action' => 'preferences.updated',
    ]);

    $disableResponse = $this->actingAs($user)->patch(route('preferences.update'), [
        'compact_view' => false,
    ]);

    $disableResponse->assertRedirect();

    expect($user->fresh()->compact_view)->toBeFalse();
});
