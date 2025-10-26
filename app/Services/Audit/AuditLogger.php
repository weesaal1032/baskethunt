<?php

namespace App\Services\Audit;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public function log(User $user, string $action, Model|int|string $subject, array $meta = []): void
    {
        try {
            [$subjectType, $subjectId] = $this->normalizeSubject($subject);

            Audit::query()->create([
                'user_id' => $user->id,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'meta' => $meta === [] ? null : $meta,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to write audit log entry.', [
                'action' => $action,
                'user_id' => $user->id,
                'subject' => $subject instanceof Model ? $subject::class : $subject,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function logSystem(string $action, Model|int|string $subject, array $meta = []): void
    {
        $user = User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if ($user === null) {
            Log::warning('Attempted to write audit entry without available admin context.', [
                'action' => $action,
                'subject' => $subject instanceof Model ? $subject::class : $subject,
            ]);

            return;
        }

        $this->log($user, $action, $subject, $meta);
    }

    private function normalizeSubject(Model|int|string $subject): array
    {
        if ($subject instanceof Model) {
            return [$subject->getMorphClass(), (int) $subject->getKey()];
        }

        if (is_int($subject)) {
            return ['raw', $subject];
        }

        if (is_numeric($subject)) {
            return ['raw', (int) $subject];
        }

        return [$subject, 0];
    }
}
