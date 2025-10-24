<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Providers\TelephonyClientInterface;
use App\Services\Providers\ValueObjects\TelephonyCallPage;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class E2eHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('s3');

        $this->app->bind(TelephonyClientInterface::class, fn () => new class() implements TelephonyClientInterface {
            public function listCalls(CarbonInterface $from, CarbonInterface $to, ?string $cursor = null): TelephonyCallPage
            {
                return new TelephonyCallPage(Collection::make(), null);
            }

            public function getRecordingUrl(string $providerCallId): string
            {
                return '';
            }

            public function testConnectivity(): array
            {
                return ['status' => 'skipped'];
            }
        });
    }

    public function test_admin_can_run_end_to_end_diagnostics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.health.e2e'));

        $response->assertOk();
        $response->assertSeeText('End-to-End Validation');
        $response->assertSeeText('All checks completed successfully');
        $response->assertSeeText('Dashboard Snapshot');
    }
}
