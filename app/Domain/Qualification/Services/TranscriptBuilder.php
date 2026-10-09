<?php

namespace App\Domain\Qualification\Services;

use App\Domain\Qualification\Enums\Beneficiary;
use App\Domain\Qualification\Enums\SharedTransport;
use App\Domain\Qualification\Enums\TripType;
use App\Models\Lead;
use App\Models\LeadQualification;
use App\Models\ScriptStep;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Rebuilds the conversation between the dispatcher and the client, step by
 * step: what the dispatcher said (script with placeholders filled), the
 * question, the client's answer and the dispatcher's reply.
 *
 * Mirrors lead_f/src/features/qualification/utils/placeholders.jsx.
 */
class TranscriptBuilder
{
    /**
     * Conversation steps: main answer + secondary questions. Agent-only steps
     * (evaluation, internal summary) are not part of the conversation.
     */
    private const STEPS = [
        'introduction' => ['main' => 'call_availability', 'details' => []],
        'beneficiary' => ['main' => 'beneficiary', 'details' => ['beneficiary_details']],
        'need' => ['main' => 'transport_need', 'details' => ['transport_need_details'], 'when' => 'other_person'],
        'route' => ['main' => 'departure', 'details' => ['destination', 'trip_type', 'departure_time', 'arrival_time', 'return_time', 'extra_routes',
            'frequency', 'days_of_week', 'trips_per_day', 'trips_per_week', 'is_recurring']],
        'passengers' => ['main' => 'passengers_count', 'details' => ['total_employees', 'estimated_passengers_per_trip']],
        'shared' => ['main' => 'shared_transport', 'details' => ['shared_direction'], 'when' => 'shared'],
        // Experience + current solution (merged step).
        'experience' => ['main' => 'used_miraldrive', 'details' => ['experience_rating', 'experience_feedback', 'improvement_request',
            'current_provider', 'current_provider_details', 'other_apps', 'other_apps_issues', 'other_apps_feedback',
            'pain_point', 'customer_preference']],
        'b2b' => ['main' => 'decision_role', 'details' => ['company_name', 'company_size', 'employees_concerned',
            'trips_per_day', 'b2b_same_schedule', 'decision_maker_name'], 'when' => 'b2b'],
        'recap' => ['main' => 'recap_confirmed', 'details' => []],
        'closing' => ['main' => 'next_action', 'details' => ['callback_at']],
    ];

    /** Labels used when the script has no prompt for a field. */
    private const FIELD_LABELS = [
        'beneficiary_details' => 'Précisions',
        'transport_need_details' => 'Précisions',
        'departure' => 'Départ',
        'destination' => 'Destination',
        'trip_type' => 'Type de trajet',
        'departure_time' => 'Heure de départ',
        'arrival_time' => 'Heure d’arrivée exacte',
        'extra_routes' => 'Autres trajets / horaires',
        'other_apps_used' => 'Autres applications utilisées',
        'other_apps' => 'Applications',
        'other_apps_issues' => 'Problèmes rencontrés',
        'other_apps_feedback' => 'Avis du client',
        'return_time' => 'Heure de retour',
        'frequency' => 'Fréquence',
        'days_of_week' => 'Jours',
        'trips_per_day' => 'Trajets par jour',
        'trips_per_week' => 'Trajets par semaine',
        'is_recurring' => 'Besoin récurrent',
        'passengers_count' => 'Nombre de passagers',
        'total_employees' => 'Employés concernés',
        'estimated_passengers_per_trip' => 'Passagers par trajet',
        'shared_direction' => 'Sens du partage',
        'experience_rating' => 'Note de l’expérience',
        'experience_feedback' => 'Détails',
        'improvement_request' => 'Améliorations souhaitées',
        'current_provider' => 'Service utilisé actuellement',
        'current_provider_details' => 'Précisions',
        'customer_preference' => 'Ce que le client apprécie',
        'company_name' => 'Entreprise',
        'company_size' => 'Taille de l’entreprise',
        'employees_concerned' => 'Employés concernés',
        'b2b_same_schedule' => 'Mêmes horaires',
        'decision_maker_name' => 'Personne à contacter',
        'callback_at' => 'Rappel prévu',
    ];

    /**
     * @return array<int, array{key: string, title: string, said: array<int, string>, question: ?string, answer: ?string, reply: ?string, details: array<int, array{0: string, 1: string}>}>
     */
    public function build(LeadQualification $q, Lead $lead, ?User $dispatcher): array
    {
        $steps = ScriptStep::query()->orderBy('position')->get()->keyBy('key');
        $labels = $this->optionLabels($steps);
        $context = $this->context($q, $lead, $dispatcher, $labels);

        $entries = [];
        foreach (self::STEPS as $key => $def) {
            $step = $steps->get($key);
            if (! $step || ! $this->visible($def['when'] ?? null, $q)) {
                continue;
            }

            $main = $def['main'];
            $answer = $this->format($main, $q->{$main}, $labels);
            $details = [];
            foreach ($def['details'] as $field) {
                $value = $this->format($field, $q->{$field}, $labels);
                if ($value !== null) {
                    $label = $this->fill($step->prompts[$field] ?? null, $context) ?: self::FIELD_LABELS[$field] ?? $field;
                    $details[] = [$label, $value];
                }
            }

            // Steps the conversation never reached are left out.
            if ($answer === null && $details === [] && $key !== 'introduction') {
                continue;
            }

            $entries[] = [
                'key' => $key,
                'title' => $step->title,
                'said' => array_values(array_filter(array_map(
                    fn ($p) => $this->fill(trim($p), $context),
                    preg_split('/\n{2,}/', (string) $step->script)
                ))),
                'question' => $this->fill($step->question, $context),
                'answer' => $answer,
                'reply' => $this->replyApplies($key, $q)
                    ? $this->fill($step->responses[$main][$this->replyKey($q->{$main})] ?? null, $context)
                    : null,
                'details' => $details,
            ];
        }

        return $entries;
    }

    /**
     * The "Oui" reply of the shared step asks aller / retour / les deux:
     * not said for a one-way trip.
     */
    private function replyApplies(string $key, LeadQualification $q): bool
    {
        return ! ($key === 'shared'
            && $q->shared_transport === SharedTransport::YES
            && $q->trip_type !== TripType::ROUND_TRIP);
    }

    private function visible(?string $condition, LeadQualification $q): bool
    {
        return match ($condition) {
            'shared' => $q->sharedApplicable(),
            'b2b' => $q->detectB2b(),
            'other_person' => $q->beneficiary !== Beneficiary::SELF,
            default => true,
        };
    }

    /**
     * @param  Collection<string, ScriptStep>  $steps
     * @return array<string, array<string, string>> field => value => label
     */
    private function optionLabels(Collection $steps): array
    {
        $labels = [];
        foreach ($steps as $step) {
            foreach ($step->options ?? [] as $field => $choices) {
                $labels[$field] = array_column($choices, 'label', 'value');
            }
        }

        return $labels;
    }

    /**
     * @param  array<string, array<string, string>>  $labels
     */
    private function format(string $field, mixed $value, array $labels): ?string
    {
        $label = fn (string $raw) => $labels[$field][$raw] ?? $raw;

        if ($field === 'extra_routes') {
            return $this->formatRoutes($value ?? [], $labels);
        }

        return match (true) {
            $value === null, $value === '', $value === [] => null,
            $value instanceof \BackedEnum => $label($value->value),
            $value instanceof CarbonInterface => $value->copy()->timezone(config('leads.import.timezone'))->format('d/m/Y à H:i'),
            is_bool($value) => isset($labels[$field]) ? $label($value ? 'YES' : 'NO') : ($value ? 'Oui' : 'Non'),
            is_array($value) => implode(', ', array_map($label, $value)),
            in_array($field, ['departure_time', 'arrival_time', 'return_time'], true) => $this->hour((string) $value),
            $field === 'experience_rating' => $value.' / 5',
            default => (string) $value,
        };
    }

    /**
     * "Ali : Sfax → Centre-ville · Lun, Mar · arrivée 08h00 · retour 17h00 (note)"
     *
     * @param  array<int, array<string, mixed>>  $routes
     * @param  array<string, array<string, string>>  $labels
     */
    private function formatRoutes(array $routes, array $labels): ?string
    {
        $lines = [];
        foreach ($routes as $route) {
            $days = implode(', ', array_map(fn ($d) => $labels['days_of_week'][$d] ?? $d, $route['days'] ?? []));
            $path = trim(($route['departure'] ?? '').' → '.($route['destination'] ?? ''), ' →');
            $parts = array_filter([
                $path ?: null,
                $days ?: null,
                ! empty($route['arrival_time']) ? 'arrivée '.$this->hour($route['arrival_time']) : null,
                ! empty($route['return_time']) ? 'retour '.$this->hour($route['return_time']) : null,
            ]);
            $line = (filled($route['label'] ?? null) ? $route['label'].' : ' : '').implode(' · ', $parts);
            if (filled($route['note'] ?? null)) {
                $line .= ' ('.$route['note'].')';
            }
            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }

        return $lines ? implode("\n", $lines) : null;
    }

    private function replyKey(mixed $value): ?string
    {
        return match (true) {
            $value === true => 'YES',
            $value === false => 'NO',
            $value instanceof \BackedEnum => (string) $value->value,
            $value === null => null,
            default => (string) $value,
        };
    }

    private function hour(?string $time): ?string
    {
        return $time ? str_replace(':', 'h', substr($time, 0, 5)) : null;
    }

    /**
     * Values of the [PLACEHOLDERS] of the script, keyed by normalized name.
     *
     * @param  array<string, array<string, string>>  $labels
     * @return array<string, ?string>
     */
    private function context(LeadQualification $q, Lead $lead, ?User $dispatcher, array $labels): array
    {
        $label = fn (string $field, $value) => $value instanceof \BackedEnum ? ($labels[$field][$value->value] ?? $value->value) : null;
        $b2b = $q->detectB2b();
        $days = implode(', ', array_map(fn ($d) => $labels['days_of_week'][$d] ?? $d, $q->days_of_week ?? []));
        $frequency = trim(Str::lower((string) $label('frequency', $q->frequency)).($days ? " ({$days})" : ''));
        $schedule = implode(', ', array_filter([
            $q->arrival_time ? 'arrivée '.$this->hour($q->arrival_time) : $this->hour($q->departure_time),
            $q->trip_type === TripType::ROUND_TRIP && $q->return_time ? 'retour '.$this->hour($q->return_time) : null,
        ]));
        $passengers = $b2b ? $q->estimated_passengers_per_trip : $q->passengers_count;

        $trip = match (true) {
            ! $q->sharedApplicable() => $passengers ? 'trajet individuel' : null,
            $q->shared_transport === SharedTransport::YES => 'trajet partagé',
            $q->shared_transport === SharedTransport::MAYBE => 'trajet partagé possible',
            $q->shared_transport === SharedTransport::NO => 'trajet individuel',
            default => null,
        };

        $callback = $q->callback_at?->copy()->timezone(config('leads.import.timezone'))->locale('fr');

        return [
            'PRENOM' => $dispatcher?->first_name,
            'CLIENT' => $lead->name,
            'DEPART' => $q->departure,
            'DESTINATION' => $q->destination,
            'FREQUENCE' => $frequency ?: null,
            'HORAIRE' => $schedule ?: null,
            'NOMBRE' => $passengers ? (string) $passengers : null,
            'TRAJET PARTAGE / INDIVIDUEL' => $trip,
            'JOUR' => $callback?->isoFormat('dddd D MMMM'),
            'HEURE' => $callback?->format('H\hi'),
        ];
    }

    /**
     * @param  array<string, ?string>  $context
     */
    private function fill(?string $text, array $context): ?string
    {
        if (blank($text)) {
            return null;
        }

        $filled = preg_replace_callback('/\[([^\]]+)\]/u', function ($match) use ($context) {
            $key = Str::upper(Str::squish(Str::ascii($match[1])));

            return $context[$key] ?? $match[0];
        }, $text);

        return self::isolateArabic($filled);
    }

    /**
     * Wraps Arabic words with left-to-right marks (U+200E) so that, in the
     * mostly Latin dialect sentences, neighbouring digits and words keep
     * their order ("elli تستحق 3lih" instead of "elli 3 تستحقlih").
     */
    public static function isolateArabic(string $text): string
    {
        $lrm = "\u{200E}";
        $text = str_replace($lrm, '', $text);

        return preg_replace('/[\x{0600}-\x{06FF}]+(?:\s+[\x{0600}-\x{06FF}]+)*/u', $lrm.'$0'.$lrm, $text);
    }
}
