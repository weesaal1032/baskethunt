<?php

use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Storage::fake('local');
});

test('authorized administrators can stream local recordings', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $recording = Recording::factory()->create([
        'storage_backend' => 'local',
        'local_path' => 'recordings/test.mp3',
        'status' => 'ready',
    ]);

    Storage::disk('local')->put($recording->local_path, 'audio');

    $signedUrl = URL::temporarySignedRoute('admin.recordings.audio', now()->addMinute(), ['recording' => $recording->id]);

    $response = $this->actingAs($user)->get($signedUrl);

    $response->assertOk();
});

test('inactive users are forbidden from streaming recordings even with signed url', function () {
    $user = User::factory()->create(['role' => 'qa', 'status' => 'disabled']);
    $recording = Recording::factory()->create([
        'storage_backend' => 'local',
        'local_path' => 'recordings/test.mp3',
        'status' => 'ready',
    ]);

    Storage::disk('local')->put($recording->local_path, 'audio');

    $signedUrl = URL::temporarySignedRoute('admin.recordings.audio', now()->addMinute(), ['recording' => $recording->id]);

    $response = $this->actingAs($user)->get($signedUrl);

    $response->assertForbidden();
});
