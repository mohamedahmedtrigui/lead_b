<?php

namespace App\Domain\Leads\Services;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;

/**
 * Every lead status change goes through here so it is always audited.
 */
class LeadStatusService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Persists the lead (including any other pending attribute changes) and
     * records a STATUS_CHANGED event when the status actually changed.
     */
    public function change(Lead $lead, LeadStatus $to, ?User $actor = null, ?string $reason = null): Lead
    {
        $from = $lead->status;
        $lead->status = $to;

        if ($to === LeadStatus::CONVERTED && $lead->converted_at === null) {
            $lead->converted_at = now();
        }

        $lead->save();

        if ($from !== $to) {
            $this->audit->log(AuditEvent::STATUS_CHANGED, $lead, $lead, array_filter([
                'from' => $from?->value,
                'to' => $to->value,
                'reason' => $reason,
            ]), $actor);
        }

        return $lead;
    }
}
