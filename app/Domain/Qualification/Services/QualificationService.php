<?php

namespace App\Domain\Qualification\Services;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Domain\Calls\Enums\CallOutcome;
use App\Domain\Calls\Services\CallService;
use App\Domain\Leads\Services\LeadStatusService;
use App\Domain\Leads\Services\NoteService;
use App\Domain\Qualification\Enums\NextAction;
use App\Domain\Qualification\Enums\PreviousExperience;
use App\Domain\Qualification\Enums\QualificationStatus;
use App\Domain\Qualification\Enums\SharedTransport;
use App\Models\DispatcherNote;
use App\Models\Lead;
use App\Models\LeadQualification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QualificationService
{
    private const B2B_FIELDS = ['company_name', 'company_size', 'employees_concerned', 'b2b_same_schedule',
        'decision_maker_name', 'decision_role', 'total_employees', 'estimated_passengers_per_trip'];

    private const EXPERIENCE_FIELDS = ['experience_rating', 'experience_feedback', 'improvement_request'];

    public function __construct(
        private readonly ScoringService $scoring,
        private readonly CallService $calls,
        private readonly LeadStatusService $statuses,
        private readonly NoteService $notes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Saves wizard progress. Called on every step change so nothing is lost.
     *
     * @param  array<string, mixed>  $answers
     */
    public function saveDraft(Lead $lead, User $dispatcher, array $answers): LeadQualification
    {
        $this->calls->ensureCallable($lead);

        return DB::transaction(function () use ($lead, $dispatcher, $answers) {
            $qualification = $this->findOrStart($lead, $dispatcher);
            $qualification->fill($answers);
            $qualification->dispatcher_id = $dispatcher->id;
            $this->rescore($qualification, $lead, $dispatcher);
            $qualification->save();

            return $qualification;
        });
    }

    /**
     * Completes the qualification: closes the call, applies the next action
     * to the lead status and stores the summary note.
     *
     * @param  array<string, mixed>  $answers
     */
    public function complete(Lead $lead, User $dispatcher, array $answers): LeadQualification
    {
        $this->calls->ensureCallable($lead);

        return DB::transaction(function () use ($lead, $dispatcher, $answers) {
            $qualification = $this->findOrStart($lead, $dispatcher);
            $qualification->fill($answers);
            $this->clearIrrelevantAnswers($qualification);

            $nextAction = $qualification->next_action;
            $qualification->dispatcher_id = $dispatcher->id;
            $qualification->status = QualificationStatus::COMPLETED;
            $qualification->completed_at = now();
            if ($nextAction !== NextAction::CALLBACK) {
                $qualification->callback_at = null;
            }
            $this->rescore($qualification, $lead, $dispatcher);
            $qualification->save();

            $this->notes->add($lead, $dispatcher, $qualification->summary_note, DispatcherNote::TYPE_SUMMARY);

            match ($nextAction) {
                NextAction::NRP => $this->calls->registerNoAnswer($lead, $dispatcher),
                NextAction::CALLBACK => $this->calls->scheduleCallback($lead, $dispatcher, Carbon::parse($qualification->callback_at)),
                default => $this->closeConnected($lead, $dispatcher, $nextAction),
            };

            $this->audit->log(AuditEvent::QUALIFICATION_COMPLETED, $lead, $qualification, [
                'score' => $qualification->interest_score,
                'level' => $qualification->interest_level?->value,
                'next_action' => $nextAction->value,
            ], $dispatcher);

            return $qualification->fresh();
        });
    }

    private function closeConnected(Lead $lead, User $dispatcher, NextAction $nextAction): void
    {
        $outcome = $nextAction === NextAction::NOT_INTERESTED ? CallOutcome::NOT_INTERESTED : CallOutcome::CONNECTED;
        $this->calls->end($lead, $dispatcher, $outcome);

        $lead->callback_at = null;
        $this->statuses->change($lead, $nextAction->leadStatus(), $dispatcher, 'Qualification terminée : '.$nextAction->value);
    }

    private function findOrStart(Lead $lead, User $dispatcher): LeadQualification
    {
        $qualification = $lead->qualification()->first();
        if ($qualification) {
            return $qualification;
        }

        $qualification = new LeadQualification;
        $qualification->lead_id = $lead->id;
        $qualification->dispatcher_id = $dispatcher->id;
        $qualification->started_at = now();
        $qualification->save();

        $this->audit->log(AuditEvent::QUALIFICATION_STARTED, $lead, $qualification, [], $dispatcher);

        return $qualification;
    }

    private function rescore(LeadQualification $qualification, Lead $lead, User $dispatcher): void
    {
        $previous = (int) $qualification->getOriginal('interest_score');
        $result = $this->scoring->evaluate($qualification);

        $qualification->is_b2b = $qualification->detectB2b();
        $qualification->interest_score = $result['score'];
        $qualification->interest_level = $result['level'];
        $qualification->score_breakdown = $result['breakdown'];

        if ($qualification->exists && $previous !== $result['score']) {
            $this->audit->log(AuditEvent::SCORE_CHANGED, $lead, $qualification, [
                'from' => $previous,
                'to' => $result['score'],
                'level' => $result['level']->value,
            ], $dispatcher);
        }
    }

    /**
     * Drops answers of branches that no longer apply (e.g. B2B details when
     * the transport is finally for the customer himself).
     */
    private function clearIrrelevantAnswers(LeadQualification $q): void
    {
        if (! $q->sharedApplicable()) {
            $q->shared_transport = null;
        }

        if ($q->shared_transport !== SharedTransport::YES) {
            $q->shared_direction = null;
        }

        if ($q->used_miraldrive !== PreviousExperience::YES) {
            foreach (self::EXPERIENCE_FIELDS as $field) {
                $q->{$field} = null;
            }
        }

        if (! $q->detectB2b()) {
            foreach (self::B2B_FIELDS as $field) {
                $q->{$field} = null;
            }
        }
    }
}
