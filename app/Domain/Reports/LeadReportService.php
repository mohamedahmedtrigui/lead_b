<?php

namespace App\Domain\Reports;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Domain\Qualification\Services\ScoringService;
use App\Domain\Qualification\Services\TranscriptBuilder;
use App\Models\Lead;
use App\Models\ScriptStep;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

/**
 * Printable lead file (PDF): client details, qualification, the full
 * conversation held with the client, call history, notes and audit trail.
 */
class LeadReportService
{
    public function __construct(
        private readonly TranscriptBuilder $transcripts,
        private readonly AuditLogger $audit,
    ) {}

    public function filename(Lead $lead): string
    {
        return 'fiche-lead-'.$lead->id.'-'.str($lead->name)->slug().'.pdf';
    }

    /**
     * @return string PDF binary
     */
    public function render(Lead $lead, User $by): string
    {
        $lead->load([
            'assignee',
            'qualification.dispatcher',
            'callAttempts' => fn ($q) => $q->with('dispatcher')->reorder()->orderBy('started_at'),
            'notes' => fn ($q) => $q->with('author')->reorder()->orderBy('created_at'),
            'auditLogs' => fn ($q) => $q->with('user')->reorder()->orderBy('created_at')->orderBy('id'),
        ]);

        $q = $lead->qualification;
        $transcript = null;
        $snapshot = false;
        if ($q) {
            $snapshot = ! empty($q->transcript);
            $transcript = $snapshot
                ? $q->transcript
                : $this->transcripts->build($q, $lead, $q->dispatcher ?? $lead->assignee);
        }

        $html = view('reports.lead', [
            'lead' => $lead,
            'q' => $q,
            'transcript' => $transcript,
            'snapshot' => $snapshot,
            'nextActionLabels' => $this->optionLabels('next_action'),
            'ruleLabels' => ScoringService::RULE_LABELS,
            'calls' => $lead->callAttempts,
            'notes' => $lead->notes,
            'events' => $lead->auditLogs,
            'generatedBy' => $by,
            'generatedAt' => now()->timezone(config('leads.import.timezone')),
            'tz' => config('leads.import.timezone'),
        ])->render();

        $pdf = $this->mpdf($lead);
        $pdf->WriteHTML($html);
        $binary = $pdf->Output('', 'S');

        $this->audit->log(AuditEvent::LEAD_REPORT_EXPORTED, $lead, $lead, ['format' => 'pdf'], $by);

        return $binary;
    }

    private function mpdf(Lead $lead): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'tempDir' => $tempDir,
            'format' => 'A4',
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_left' => 14,
            'margin_right' => 14,
            'default_font' => 'dejavusans',
            'default_font_size' => 9,
            // Latin + Arabic (dialect script) in the same paragraph.
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $pdf->SetTitle('Fiche lead #'.$lead->id.' – '.$lead->name);
        $pdf->SetAuthor('MiralDrive');
        $pdf->SetHTMLFooter(
            '<table width="100%" style="font-size:7.5pt;color:#64748b;border-top:0.5px solid #cbd5e1;padding-top:4px"><tr>'
            .'<td>MiralDrive · Document interne – données personnelles confidentielles</td>'
            .'<td align="right">Lead #'.$lead->id.' · page {PAGENO} / {nbpg}</td></tr></table>'
        );

        return $pdf;
    }

    /**
     * @return array<string, string>
     */
    private function optionLabels(string $field): array
    {
        foreach (ScriptStep::query()->pluck('options') as $options) {
            if (isset($options[$field])) {
                return array_column($options[$field], 'label', 'value');
            }
        }

        return [];
    }
}
