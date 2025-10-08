<?php

use App\Models\Call;
use App\Models\Recording;
use App\Models\User;
use App\Repositories\Eloquent\RecordingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recordings repository filters by agent', function () {
    $repository = app(RecordingsRepository::class);

    $agent = User::factory()->create(['role' => 'qa']);
    $otherAgent = User::factory()->create(['role' => 'qa']);

    $targetCall = Call::factory()->create(['agent_id' => $agent->id]);
    $matchingRecording = Recording::factory()->create([
        'call_id' => $targetCall->id,
        'status' => 'ready',
    ]);

    Recording::factory()->create([
        'call_id' => Call::factory()->create(['agent_id' => $otherAgent->id])->id,
        'status' => 'ready',
    ]);

    $paginated = $repository->paginateLibrary(['agent' => $agent->id], 10);

    expect($paginated->total())->toBe(1)
        ->and($paginated->items()[0]->id)->toBe($matchingRecording->id);
});

test('recordings repository loads detail with transcript and QA context', function () {
    $repository = app(RecordingsRepository::class);

    $call = Call::factory()->create();
    $recording = Recording::factory()->create([
        'call_id' => $call->id,
        'status' => 'ready',
    ]);

    $detail = $repository->findForDetail($recording->id);

    expect($detail)
        ->not->toBeNull()
        ->and($detail->call)->not->toBeNull()
        ->and($detail->call->agent)->not->toBeNull();
});
