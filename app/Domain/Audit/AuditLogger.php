<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Single entry point to record business events (who did what, on which lead).
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        AuditEvent $event,
        ?Lead $lead = null,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= auth()->user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'lead_id' => $lead?->id,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
