<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Qualification\Enums\InterestLevel;
use App\Domain\Qualification\Enums\QualificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\ScriptStep;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of qualified leads (semicolon + UTF-8 BOM so Excel FR opens it).
 */
class LeadExportController extends Controller
{
    private const COLUMNS = [
        'ID' => 'lead.id',
        'Nom' => 'lead.name',
        'Téléphone' => 'lead.phone',
        'WhatsApp' => 'lead.whatsapp_number',
        'Email' => 'lead.email',
        'Source' => 'lead.source',
        'Canal' => 'lead.channel',
        'Statut' => 'lead.status',
        'Dispatcher' => 'lead.assignee',
        'Bénéficiaire' => 'q.beneficiary',
        'Type de transport' => 'q.transport_need',
        'Départ' => 'q.departure',
        'Destination' => 'q.destination',
        'Type de trajet' => 'q.trip_type',
        'Heure départ' => 'q.departure_time',
        'Heure retour' => 'q.return_time',
        'Fréquence' => 'q.frequency',
        'Jours' => 'q.days_of_week',
        'Trajets / semaine' => 'q.trips_per_week',
        'Passagers' => 'q.passengers_count',
        'Transport partagé' => 'q.shared_transport',
        'Sens du partage' => 'q.shared_direction',
        'Déjà client MiralDrive' => 'q.used_miraldrive',
        'Note expérience' => 'q.experience_rating',
        'Solution actuelle' => 'q.current_provider',
        'Point de douleur' => 'q.pain_point',
        'Ce qui plaît' => 'q.customer_preference',
        'B2B' => 'q.is_b2b',
        'Entreprise' => 'q.company_name',
        'Taille entreprise' => 'q.company_size',
        'Employés concernés' => 'q.employees_concerned',
        'Passagers / trajet' => 'q.estimated_passengers_per_trip',
        'Trajets / jour' => 'q.trips_per_day',
        'Horaires identiques (B2B)' => 'q.b2b_same_schedule',
        'Rôle décisionnel' => 'q.decision_role',
        'Priorité client' => 'q.main_priority',
        'Récap validé' => 'q.recap_confirmed',
        'Demande devis' => 'q.wants_quotation',
        'Score' => 'q.interest_score',
        'Niveau' => 'q.interest_level',
        'Étoiles' => 'q.priority_stars',
        'Prochaine action' => 'q.next_action',
        'Rappel' => 'lead.callback_at',
        'Résumé' => 'q.summary_note',
        'Qualifié le' => 'q.completed_at',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(LeadStatus::class)],
            'interest_level' => ['nullable', Rule::enum(InterestLevel::class)],
            'assigned_to' => ['nullable', 'integer'],
        ]);

        $query = Lead::query()
            ->with(['assignee', 'qualification'])
            ->whereHas('qualification', fn ($q) => $q
                ->where('status', QualificationStatus::COMPLETED)
                ->when($request->input('interest_level'), fn ($q, $level) => $q->where('interest_level', $level)))
            ->when($request->input('status'), fn ($q, $statuses) => $q->whereIn('status', $statuses))
            ->when($request->input('assigned_to'), fn ($q, $id) => $q->where('assigned_to', $id))
            ->orderBy('id');

        $filename = 'leads-qualifies-'.now()->format('Y-m-d-His').'.csv';
        $this->labels = $this->optionLabels();

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys(self::COLUMNS), ';');

            $query->chunk(500, function ($leads) use ($out) {
                foreach ($leads as $lead) {
                    fputcsv($out, array_map(fn ($path) => $this->value($lead, $path), self::COLUMNS), ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @var array<string, array<string, string>> field => value => label */
    private array $labels = [];

    /**
     * Option labels as configured by the admin in the call script, so the
     * export reads like the wizard ("Aller-retour" rather than ROUND_TRIP).
     *
     * @return array<string, array<string, string>>
     */
    private function optionLabels(): array
    {
        $labels = [];
        foreach (ScriptStep::query()->pluck('options') as $options) {
            foreach ($options ?? [] as $field => $choices) {
                $labels[$field] = array_column($choices, 'label', 'value');
            }
        }

        return $labels;
    }

    private function value(Lead $lead, string $path): string
    {
        [$scope, $field] = explode('.', $path, 2);

        $value = match (true) {
            $path === 'lead.assignee' => $lead->assignee?->full_name,
            $scope === 'lead' => $lead->{$field},
            default => $lead->qualification?->{$field},
        };

        $label = fn (string $raw) => $this->labels[$field][$raw] ?? $raw;

        return match (true) {
            $value === null => '',
            $value instanceof LeadStatus => $value->label(),
            $value instanceof \BackedEnum => $label($value->value),
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            is_bool($value) => $value ? 'Oui' : 'Non',
            is_array($value) => implode(', ', array_map($label, $value)),
            in_array($field, ['departure_time', 'return_time'], true) => substr((string) $value, 0, 5),
            default => (string) $value,
        };
    }
}
