<?php

namespace Tests\Unit\Services;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_config_fallback_when_not_persisted(): void
    {
        config(['app.name' => 'CallHub']);

        $service = app(SettingsService::class);

        $this->assertSame('CallHub', $service->get('app.name'));
    }
}
