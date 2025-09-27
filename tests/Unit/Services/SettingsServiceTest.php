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

    public function test_get_uses_callhub_config_fallbacks(): void
    {
        config([
            'callhub.telephony.base_url' => 'https://telephony.test',
            'callhub.notifications.mail.host' => 'smtp.test',
        ]);

        $service = app(SettingsService::class);

        $this->assertSame('https://telephony.test', $service->get('telephony.provider.base_url'));
        $this->assertSame('smtp.test', $service->get('notifications.mail.host'));
    }
}
