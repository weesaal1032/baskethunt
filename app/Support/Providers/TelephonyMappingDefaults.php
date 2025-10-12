<?php

namespace App\Support\Providers;

class TelephonyMappingDefaults
{
    /**
     * @return array<string, string>
     */
    public static function values(): array
    {
        return [
            'provider_call_id' => 'id',
            'from_number' => 'from',
            'to_number' => 'to',
            'started_at' => 'started_at',
            'ended_at' => 'ended_at',
            'duration' => 'duration',
            'direction' => 'direction',
            'status' => 'status',
            'disposition' => 'status',
            'queue' => 'queue',
            'agent_id' => 'agent.id',
            'agent_email' => 'agent.email',
            'agent_name' => 'agent.name',
            'recording_url' => 'recording_url',
        ];
    }
}
