@php
    $fmt = fn ($date, $pattern = 'd/m/Y H:i') => $date ? $date->copy()->timezone($tz)->format($pattern) : '—';
    $levelColors = ['HOT' => '#dc2626', 'WARM' => '#ea580c', 'INTERESTED' => '#2563eb', 'LOW' => '#64748b'];
@endphp
<html>
<head>
<style>
    body { font-family: dejavusans; color: #1e293b; font-size: 9pt; }
    .band { background: #1d4ed8; color: #ffffff; padding: 12px 14px; }
    .band .brand { font-size: 15pt; font-weight: bold; }
    .band .sub { font-size: 8.5pt; color: #bfdbfe; }
    h2 { font-size: 11pt; color: #1d4ed8; border-bottom: 1px solid #bfdbfe; padding-bottom: 3px; margin: 16px 0 8px 0; }
    h3 { font-size: 9.5pt; color: #0f172a; margin: 0 0 4px 0; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid td, table.grid th { padding: 4px 6px; border-bottom: 0.5px solid #e2e8f0; vertical-align: top; text-align: left; }
    table.grid th { background: #eff6ff; color: #1e3a8a; font-size: 8pt; text-transform: uppercase; }
    td.k { color: #64748b; width: 34%; }
    td.v { font-weight: bold; }
    .badge { padding: 1px 6px; border-radius: 8px; font-size: 8pt; font-weight: bold; color: #ffffff; }
    .muted { color: #64748b; }
    .step { border: 0.5px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; page-break-inside: avoid; }
    .step-title { font-size: 8pt; font-weight: bold; color: #1d4ed8; text-transform: uppercase; margin-bottom: 4px; }
    .said { background: #eff6ff; border-left: 3px solid #2563eb; padding: 5px 8px; margin: 3px 0; }
    .client { background: #f0fdf4; border-left: 3px solid #16a34a; padding: 5px 8px; margin: 3px 0; }
    .who { font-size: 7pt; font-weight: bold; text-transform: uppercase; color: #64748b; }
    .note { background: #f8fafc; border: 0.5px solid #e2e8f0; padding: 6px 8px; margin-bottom: 6px; }
    .summary { background: #fffbeb; border: 0.5px solid #fde68a; padding: 8px; }
</style>
</head>
<body>

<div class="band">
    <table width="100%"><tr>
        <td>
            <div class="brand">MiralDrive</div>
            <div class="sub">Fiche lead · Qualification de la demande de transport</div>
        </td>
        <td align="right" style="color:#ffffff">
            <div style="font-size:13pt;font-weight:bold">{{ $lead->name }}</div>
            <div class="sub">Lead #{{ $lead->id }} · {{ $lead->status->label() }}</div>
        </td>
    </tr></table>
</div>
<p class="muted" style="font-size:7.5pt">Généré le {{ $generatedAt->format('d/m/Y à H:i') }} par {{ $generatedBy->full_name }}</p>

{{-- 1. Client --}}
<h2>1. Informations client</h2>
<table width="100%"><tr>
    <td width="50%" style="vertical-align:top;padding-right:8px">
        <table class="grid">
            <tr><td class="k">Nom</td><td class="v">{{ $lead->name }}</td></tr>
            <tr><td class="k">Téléphone</td><td class="v">{{ $lead->phone ?? '—' }}</td></tr>
            <tr><td class="k">WhatsApp</td><td>{{ $lead->whatsapp_number ?? '—' }}</td></tr>
            <tr><td class="k">Autre numéro</td><td>{{ $lead->secondary_phone ?? '—' }}</td></tr>
            <tr><td class="k">E-mail</td><td>{{ $lead->email ?? '—' }}</td></tr>
        </table>
    </td>
    <td width="50%" style="vertical-align:top;padding-left:8px">
        <table class="grid">
            <tr><td class="k">Source / canal</td><td>{{ collect([$lead->source, $lead->channel])->filter()->join(' · ') ?: '—' }}</td></tr>
            <tr><td class="k">Formulaire</td><td>{{ $lead->form ?? '—' }}</td></tr>
            <tr><td class="k">Reçu le</td><td>{{ $fmt($lead->source_created_at) }}</td></tr>
            <tr><td class="k">Dispatcher</td><td>{{ $lead->assignee?->full_name ?? 'Non assigné' }}</td></tr>
            <tr><td class="k">Tentatives NRP</td><td>{{ $lead->nrp_attempts }} / {{ config('leads.nrp.max_attempts') }}</td></tr>
        </table>
    </td>
</tr></table>

{{-- 2. Qualification --}}
<h2>2. Synthèse de la qualification</h2>
@if ($q)
    <table width="100%"><tr>
        <td width="50%" style="vertical-align:top;padding-right:8px">
            <table class="grid">
                <tr><td class="k">Statut</td><td class="v">{{ $q->isCompleted() ? 'Complétée le '.$fmt($q->completed_at) : 'Brouillon (non complétée)' }}</td></tr>
                <tr><td class="k">Score d’intérêt</td><td class="v">{{ $q->interest_score }} / 100
                    @if ($q->interest_level)
                        <span class="badge" style="background:{{ $levelColors[$q->interest_level->value] }}">{{ $q->interest_level->label() }}</span>
                    @endif
                </td></tr>
                <tr><td class="k">Priorité (agent)</td><td class="v">{{ $q->priority_stars ? str_repeat('★', $q->priority_stars).str_repeat('☆', 5 - $q->priority_stars) : '—' }}</td></tr>
                <tr><td class="k">Prochaine action</td><td class="v">{{ $q->next_action ? ($nextActionLabels[$q->next_action->value] ?? $q->next_action->value) : '—' }}</td></tr>
                @if ($q->callback_at)
                    <tr><td class="k">Rappel</td><td class="v">{{ $fmt($q->callback_at) }}</td></tr>
                @endif
                <tr><td class="k">Besoin B2B</td><td>{{ $q->is_b2b ? 'Oui' : 'Non' }}</td></tr>
                <tr><td class="k">Qualifié par</td><td>{{ $q->dispatcher?->full_name ?? '—' }}</td></tr>
            </table>
        </td>
        <td width="50%" style="vertical-align:top;padding-left:8px">
            <table class="grid">
                <tr><th colspan="2">Détail du score</th></tr>
                @forelse ($q->score_breakdown ?? [] as $item)
                    <tr><td>{{ $ruleLabels[$item['rule']] ?? $item['rule'] }}</td><td align="right" style="color:#16a34a;font-weight:bold">+{{ $item['points'] }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">Aucun critère.</td></tr>
                @endforelse
            </table>
        </td>
    </tr></table>
    @if ($q->summary_note)
        <div class="summary" style="margin-top:8px">
            <div class="who">Résumé interne du dispatcher</div>
            {!! nl2br(e($q->summary_note)) !!}
        </div>
    @endif
@else
    <p class="muted">Ce lead n’a pas encore été qualifié.</p>
@endif

{{-- 3. Conversation --}}
<h2>3. Discours échangé avec le client</h2>
@if ($transcript)
    <p class="muted" style="font-size:7.5pt;margin-top:0">
        {{ $snapshot ? 'Conversation enregistrée à la clôture de la qualification.' : 'Reconstitution à partir du script actuel (qualification non clôturée).' }}
    </p>
    @foreach ($transcript as $index => $entry)
        <div class="step">
            <div class="step-title">{{ $index + 1 }}. {{ $entry['title'] }}</div>
            @foreach ($entry['said'] as $paragraph)
                <div class="said"><span class="who">Dispatcher</span><br>« {{ $paragraph }} »</div>
            @endforeach
            @if ($entry['question'])
                <div class="said"><span class="who">Dispatcher</span><br><b>« {{ $entry['question'] }} »</b></div>
            @endif
            @if ($entry['answer'])
                <div class="client"><span class="who">Client</span><br>{{ $entry['answer'] }}</div>
            @endif
            @if ($entry['reply'])
                <div class="said"><span class="who">Dispatcher</span><br>« {{ $entry['reply'] }} »</div>
            @endif
            @if (count($entry['details']))
                <table class="grid" style="margin-top:4px">
                    @foreach ($entry['details'] as [$label, $value])
                        <tr><td class="k">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endforeach
@else
    <p class="muted">Aucune conversation enregistrée.</p>
@endif

{{-- 4. Calls --}}
<h2>4. Historique des appels</h2>
@if ($calls->isEmpty())
    <p class="muted">Aucun appel.</p>
@else
    <table class="grid">
        <tr><th>#</th><th>Date</th><th>Dispatcher</th><th>Issue</th><th>Durée</th><th>Note</th></tr>
        @foreach ($calls as $call)
            <tr>
                <td>{{ $call->attempt_number }}</td>
                <td>{{ $fmt($call->started_at) }}</td>
                <td>{{ $call->dispatcher?->full_name }}</td>
                <td>{{ $call->outcome?->label() ?? 'En cours' }}</td>
                <td>{{ $call->duration_seconds !== null ? gmdate('i:s', $call->duration_seconds) : '—' }}</td>
                <td>{{ $call->note }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- 5. Notes --}}
<h2>5. Notes internes</h2>
@forelse ($notes as $note)
    <div class="note">
        <div class="who">{{ $note->type === 'SUMMARY' ? 'Résumé d’appel' : 'Note' }} · {{ $note->author?->full_name }} · {{ $fmt($note->created_at) }}</div>
        {!! nl2br(e($note->body)) !!}
    </div>
@empty
    <p class="muted">Aucune note.</p>
@endforelse

{{-- 6. Audit --}}
<h2>6. Journal d’activité</h2>
<table class="grid">
    <tr><th>Date</th><th>Utilisateur</th><th>Événement</th><th>Détails</th></tr>
    @foreach ($events as $event)
        @php
            $p = $event->properties ?? [];
            $details = match ($event->event->value) {
                'STATUS_CHANGED' => collect([
                    isset($p['from']) ? \App\Domain\Leads\Enums\LeadStatus::tryFrom($p['from'])?->label() : null,
                    \App\Domain\Leads\Enums\LeadStatus::tryFrom($p['to'] ?? '')?->label(),
                ])->filter()->join(' → '),
                'SCORE_CHANGED' => ($p['from'] ?? '').' → '.($p['to'] ?? ''),
                'LEAD_ASSIGNED', 'LEAD_REASSIGNED' => $p['to_name'] ?? '',
                'NRP_REGISTERED' => 'Tentative '.($p['attempt'] ?? '?').' / '.($p['max'] ?? '?'),
                'CALL_ENDED' => \App\Domain\Calls\Enums\CallOutcome::tryFrom($p['outcome'] ?? '')?->label() ?? '',
                'QUALIFICATION_COMPLETED' => 'Score '.($p['score'] ?? '').' · '.(\App\Domain\Qualification\Enums\InterestLevel::tryFrom($p['level'] ?? '')?->label() ?? ''),
                default => '',
            };
        @endphp
        <tr>
            <td>{{ $fmt($event->created_at) }}</td>
            <td>{{ $event->user?->full_name ?? 'Système' }}</td>
            <td>{{ $event->event->label() }}</td>
            <td>{{ $details }}</td>
        </tr>
    @endforeach
</table>

</body>
</html>
