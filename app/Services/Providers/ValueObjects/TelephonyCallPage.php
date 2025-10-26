<?php

namespace App\Services\Providers\ValueObjects;

use Illuminate\Support\Collection;

class TelephonyCallPage
{
    /**
     * @param Collection<int, array<string, mixed>> $calls
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        private readonly Collection $calls,
        private readonly ?string $nextCursor,
        private readonly ?array $raw = null,
    ) {
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function calls(): Collection
    {
        return $this->calls;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function raw(): ?array
    {
        return $this->raw;
    }
}
