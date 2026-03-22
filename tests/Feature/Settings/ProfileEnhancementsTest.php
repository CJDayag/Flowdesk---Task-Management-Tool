<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('user can update profile with avatar bio and role', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'bio' => 'I lead delivery for platform initiatives.',
        'profile_role' => 'Engineering Manager',
        'avatar' => UploadedFile::fake()->image('avatar.png'),
    ]);

    $response->assertRedirect(route('profile.edit', absolute: false));

    $updated = $user->fresh();

    expect($updated->bio)->toBe('I lead delivery for platform initiatives.')
        ->and($updated->profile_role)->toBe('Engineering Manager')
        ->and($updated->avatar_path)->not->toBeNull();

    Storage::disk('public')->assertExists($updated->avatar_path);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $updated->id,
        'action' => 'profile.updated',
    ]);
});
