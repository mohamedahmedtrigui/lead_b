<?php

namespace App\Domain\Calls\Services;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Domain\Calls\Enums\CallOutcome;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadStatusService;
use App\Domain\Leads\Services\NoteService;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Call lifecycle: start, end, and the quick outcomes a dispatcher can register
 * without going through the whole qualification (NRP, callback, invalid...).
 */
class CallService
{
    public function __construct(
        private readonly LeadStatusService $statuses,
        private readonly NoteService $notes,
        private readonly AuditLogger $audit,
    ) {}

    public function openCall(Lead $lead, User $dispatcher): ?CallAttempt
    {
        return CallAttempt::query()
            ->where('lead_id', $lead->id)
            ->where('dispatcher_id', $dispatcher->id)
            ->open()
            ->latest('started_at')
            ->first();
    }

    public function ensureCallable(Lead $lead): void
    {
        if ($lead->status->isClosedForDispatcher()) {
            throw new BusinessRuleException('Ce lead est clôturé ('.$lead->status->value.'). Contactez un administrateur pour le rouvrir.', 'lead_closed');
        }

        if ($lead->status === LeadStatus::NRP && $lead->isNrpFinal()) {
            throw new BusinessRuleException('NRP final atteint : ce lead ne peut plus être appelé.', 'nrp_final');
        }
    }

    public function start(Lead $lead, User $dispatcher): CallAttempt
    {
        $this->ensureCallable($lead);

        if ($open = $this->openCall($lead, $dispatcher)) {
            return $open;
        }

        return DB::transaction(function () use ($lead, $dispatcher) {
            $call = CallAttempt::create([
                'lead_id' => $lead->id,
                'dispatcher_id' => $dispatcher->id,
                'attempt_number' => CallAttempt::where('lead_id', $lead->id)->count() + 1,
                'started_at' => now(),
            ]);

            $lead->last_contacted_at = now();
            $this->statuses->change($lead, LeadStatus::IN_PROGRESS, $dispatcher, 'Appel démarré');

            $this->audit->log(AuditEvent::CALL_STARTED, $lead, $call, ['attempt' => $call->attempt_number], $dispatcher);

            return $call;
        });
    }

    /**
     * Ends the open call with the given outcome. When no call is open (e.g.
     * the dispatcher registers an NRP straight from the lead list), an
     * instantaneous attempt is recorded so statistics stay exact.
     */
    public function end(Lead $lead, User $dispatcher, CallOutcome $outcome, ?string $note = null): CallAttempt
    {
        $call = $this->openCall($lead, $dispatcher) ?? CallAttempt::create([
            'lead_id' => $lead->id,
            'dispatcher_id' => $dispatcher->id,
            'attempt_number' => CallAttempt::where('lead_id', $lead->id)->count() + 1,
            'started_at' => now(),
        ]);

        $call->fill([
            'outcome' => $outcome,
            'ended_at' => now(),
            'duration_seconds' => (int) max(0, $call->started_at->diffInSeconds(now())),
            'note' => $note,
        ])->save();

        $lead->last_contacted_at = now();
        $lead->save();

        $this->audit->log(AuditEvent::CALL_ENDED, $lead, $call, [
            'attempt' => $call->attempt_number,
            'outcome' => $outcome->value,
            'duration' => $call->duration_seconds,
        ], $dispatcher);

        return $call;
    }

    /**
     * @param  array{note?: ?string, callback_at?: ?CarbonInterface}  $data
     */
    public function registerOutcome(Lead $lead, User $dispatcher, CallOutcome $outcome, array $data = []): Lead
    {
        $note = $data['note'] ?? null;

        return DB::transaction(fn () => match ($outcome) {
            CallOutcome::NO_ANSWER => $this->registerNoAnswer($lead, $dispatcher, $note),
            CallOutcome::CALLBACK_REQUESTED => $this->scheduleCallback($lead, $dispatcher, $data['callback_at'], $note),
            CallOutcome::INVALID_NUMBER => $this->close($lead, $dispatcher, $outcome, LeadStatus::INVALID, $note),
            CallOutcome::NOT_INTERESTED => $this->close($lead, $dispatcher, $outcome, LeadStatus::NOT_INTERESTED, $note),
            CallOutcome::CONNECTED => $this->endConnected($lead, $dispatcher, $note),
        });
    }

    /**
     * NRP workflow: attempt 1 -> NRP, attempt 2 -> NRP, attempt N (max) -> NRP final.
     * Attempts must be spaced out, so the final NRP cannot be reached instantly.
     */
    public function registerNoAnswer(Lead $lead, User $dispatcher, ?string $note = null): Lead
    {
        $max = (int) config('leads.nrp.max_attempts');
        $interval = (int) config('leads.nrp.min_interval_minutes');

        if ($lead->isNrpFinal()) {
            throw new BusinessRuleException('NRP final déjà atteint pour ce lead.', 'nrp_final');
        }

        if ($lead->last_nrp_at && $interval > 0 && $lead->last_nrp_at->copy()->addMinutes($interval)->isFuture()) {
            $next = $lead->last_nrp_at->copy()->addMinutes($interval);
            throw new BusinessRuleException(
                "Tentative NRP trop rapprochée : la prochaine tentative est possible après {$next->timezone(config('leads.import.timezone'))->format('H:i')} ({$interval} min minimum entre deux tentatives).",
                'nrp_too_soon',
            );
        }

        $call = $this->end($lead, $dispatcher, CallOutcome::NO_ANSWER, $note);

        $lead->nrp_attempts++;
        $lead->last_nrp_at = now();
        $this->statuses->change($lead, LeadStatus::NRP, $dispatcher, "NRP tentative {$lead->nrp_attempts}/{$max}");

        $this->audit->log(AuditEvent::NRP_REGISTERED, $lead, $call, [
            'attempt' => $lead->nrp_attempts,
            'max' => $max,
            'final' => $lead->isNrpFinal(),
        ], $dispatcher);

        $this->addNote($lead, $dispatcher, $note, $call);

        return $lead;
    }

    public function scheduleCallback(Lead $lead, User $dispatcher, CarbonInterface $at, ?string $note = null, bool $endCall = true): Lead
    {
        $call = $endCall ? $this->end($lead, $dispatcher, CallOutcome::CALLBACK_REQUESTED, $note) : null;

        $lead->callback_at = $at;
        $this->statuses->change($lead, LeadStatus::CALLBACK, $dispatcher, 'Rappel programmé');

        $this->audit->log(AuditEvent::CALLBACK_SCHEDULED, $lead, $lead, ['callback_at' => $at->toIso8601String()], $dispatcher);

        $this->addNote($lead, $dispatcher, $note, $call);

        return $lead;
    }

    private function close(Lead $lead, User $dispatcher, CallOutcome $outcome, LeadStatus $status, ?string $note): Lead
    {
        $call = $this->end($lead, $dispatcher, $outcome, $note);
        $lead->callback_at = null;
        $this->statuses->change($lead, $status, $dispatcher);
        $this->addNote($lead, $dispatcher, $note, $call);

        return $lead;
    }

    private function endConnected(Lead $lead, User $dispatcher, ?string $note): Lead
    {
        $call = $this->end($lead, $dispatcher, CallOutcome::CONNECTED, $note);
        $this->addNote($lead, $dispatcher, $note, $call);

        return $lead;
    }

    private function addNote(Lead $lead, User $dispatcher, ?string $note, ?CallAttempt $call): void
    {
        if (filled($note)) {
            $this->notes->add($lead, $dispatcher, $note, call: $call);
        }
    }
}
