<?php

namespace App\Services\Providers;

use App\Services\Providers\ValueObjects\TelephonyCallPage;
use Carbon\CarbonInterface;

interface TelephonyClientInterface
{
    public function listCalls(CarbonInterface $from, CarbonInterface $to, ?string $cursor = null): TelephonyCallPage;

    public function getRecordingUrl(string $providerCallId): string;

    /**
     * @return array<string, mixed>
     */
    public function testConnectivity(): array;
}
