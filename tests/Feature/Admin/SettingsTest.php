<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_general_settings_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.settings.general'));

        $response->assertOk();
        $response->assertSeeText('General Settings');
        $response->assertSee('Site Name');
    }

    public function test_admin_can_update_general_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'site_name' => 'CallHub QA Ops',
            'timezone' => 'UTC',
            'app_url' => 'https://callhub.test',
        ];

        $response = $this->actingAs($admin)->post(route('admin.settings.general.update'), $payload);

        $response->assertRedirect(route('admin.settings.general'));
        $response->assertSessionHas('status', 'General settings updated successfully.');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.name',
            'value' => 'CallHub QA Ops',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'app.timezone',
            'value' => 'UTC',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'app.url',
            'value' => 'https://callhub.test',
        ]);

        $this->assertSame('CallHub QA Ops', setting('app.name'));
    }
}
