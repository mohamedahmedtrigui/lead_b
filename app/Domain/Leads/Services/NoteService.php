<?php

namespace App\Domain\Leads\Services;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Models\CallAttempt;
use App\Models\DispatcherNote;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Str;

class NoteService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function add(
        Lead $lead,
        User $author,
        string $body,
        string $type = DispatcherNote::TYPE_NOTE,
        ?CallAttempt $call = null,
    ): DispatcherNote {
        $note = DispatcherNote::create([
            'lead_id' => $lead->id,
            'user_id' => $author->id,
            'call_attempt_id' => $call?->id,
            'type' => $type,
            'body' => trim($body),
        ]);

        $this->audit->log(AuditEvent::NOTE_ADDED, $lead, $note, [
            'type' => $type,
            'excerpt' => Str::limit($note->body, 80),
        ], $author);

        return $note;
    }
}
