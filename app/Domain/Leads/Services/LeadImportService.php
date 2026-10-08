<?php

namespace App\Domain\Leads\Services;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Domain\Leads\Support\PhoneNormalizer;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;
use Throwable;

/**
 * Imports the lead source CSV.
 *
 * Only source columns are written: pipeline state (status, assignment, NRP)
 * and qualification data are never overwritten by a re-import.
 */
class LeadImportService
{
    /** CSV header (lower-cased) => leads column. */
    private const COLUMN_MAP = [
        'created' => 'source_created_at',
        'name' => 'name',
        'email' => 'email',
        'source' => 'source',
        'form' => 'form',
        'channel' => 'channel',
        'stage' => 'stage',
        'owner' => 'source_owner',
        'labels' => 'labels',
        'phone' => 'phone',
        'secondary phone number' => 'secondary_phone',
        'whatsapp number' => 'whatsapp_number',
    ];

    private const REQUIRED_HEADERS = ['name'];

    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly AuditLogger $audit,
    ) {}

    public function import(string $path, string $originalName, ?User $by = null): LeadImport
    {
        $rows = $this->readRows($path);

        $import = LeadImport::create([
            'user_id' => $by?->id,
            'file_name' => $originalName,
            'total_rows' => count($rows),
        ]);

        // Rows with a phone number first: phone-less duplicates (e.g. the same
        // person reaching out on Messenger) are then merged into them.
        usort($rows, fn (array $a, array $b) => (int) blank($a['data']['phone']) <=> (int) blank($b['data']['phone']));

        $created = $updated = $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $import, &$created, &$updated, &$skipped, &$errors) {
            foreach ($rows as ['line' => $line, 'data' => $data]) {
                if (blank($data['name'])) {
                    $skipped++;
                    $errors[] = ['line' => $line, 'message' => 'Nom manquant'];

                    continue;
                }

                $key = $this->dedupeKey($data);
                $lead = $this->findExisting($data, $key);

                if ($lead === null) {
                    Lead::create([...$data, 'dedupe_key' => $key, 'lead_import_id' => $import->id]);
                    $created++;

                    continue;
                }

                // Same contact: refresh source columns (never blank them).
                // Merged contact (matched by email/name): only fill the gaps.
                $sameKey = $lead->dedupe_key === $key;
                foreach (Lead::SOURCE_FIELDS as $field) {
                    $value = $data[$field] ?? null;
                    if (blank($value) || ($field === 'source_created_at' && $lead->source_created_at !== null)) {
                        continue;
                    }
                    if ($sameKey || blank($lead->{$field})) {
                        $lead->{$field} = $value;
                    }
                }

                if ($lead->isDirty()) {
                    $lead->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        });

        $import->update([
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'errors' => $errors ?: null,
        ]);

        $this->audit->log(AuditEvent::LEADS_IMPORTED, null, $import, [
            'file' => $originalName,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ], $by);

        return $import;
    }

    /**
     * @return array<int, array{line: int, data: array<string, mixed>}>
     */
    private function readRows(string $path): array
    {
        $file = new SplFileObject($path, 'r');
        $firstLine = (string) $file->fgets();
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $file->rewind();
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->setCsvControl($delimiter, '"', '\\');

        $header = null;
        $rows = [];
        $line = 0;

        foreach ($file as $raw) {
            $line++;
            if (! is_array($raw) || $raw === [null]) {
                continue;
            }

            $raw = array_map(fn ($v) => $this->toUtf8((string) $v), $raw);

            if ($header === null) {
                $raw[0] = preg_replace('/^\xEF\xBB\xBF/', '', $raw[0]);
                $header = array_map(fn ($h) => Str::lower(trim($h)), $raw);
                $missing = array_diff(self::REQUIRED_HEADERS, $header);
                if ($missing) {
                    throw new BusinessRuleException('Colonnes manquantes dans le fichier CSV : '.implode(', ', $missing));
                }

                continue;
            }

            $values = [];
            foreach ($header as $i => $column) {
                if (isset(self::COLUMN_MAP[$column])) {
                    $values[self::COLUMN_MAP[$column]] = trim($raw[$i] ?? '');
                }
            }

            $rows[] = ['line' => $line, 'data' => $this->normalize($values)];
        }

        if ($header === null) {
            throw new BusinessRuleException('Le fichier CSV est vide.');
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        $data = [];
        foreach (Lead::SOURCE_FIELDS as $field) {
            $value = $values[$field] ?? '';
            $data[$field] = $value === '' ? null : $value;
        }

        $data['name'] = $data['name'] ? Str::squish($data['name']) : null;
        $data['email'] = $data['email'] ? Str::lower($data['email']) : null;
        if ($data['email'] && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $data['email'] = null;
        }

        foreach (['phone', 'secondary_phone', 'whatsapp_number'] as $field) {
            $data[$field] = $this->phones->normalize($data[$field]);
        }

        $data['source_created_at'] = $this->parseDate($data['source_created_at']);

        return $data;
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        $tz = config('leads.import.timezone');

        try {
            return CarbonImmutable::createFromFormat(config('leads.import.created_format'), Str::lower($value), $tz)->utc();
        } catch (Throwable) {
            try {
                return CarbonImmutable::parse($value, $tz)->utc();
            } catch (Throwable) {
                return null;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findExisting(array $data, string $key): ?Lead
    {
        $lead = Lead::where('dedupe_key', $key)->first();
        if ($lead || filled($data['phone'])) {
            return $lead;
        }

        // Phone-less row: try to merge with a known contact.
        if (filled($data['email'])) {
            $lead = Lead::where('email', $data['email'])->first();
        }
        if (! $lead && filled($data['whatsapp_number'])) {
            $lead = Lead::where('phone', $data['whatsapp_number'])->orWhere('whatsapp_number', $data['whatsapp_number'])->first();
        }

        return $lead ?? Lead::where('name', $data['name'])->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dedupeKey(array $data): string
    {
        return match (true) {
            filled($data['phone']) => 'phone:'.$data['phone'],
            filled($data['email']) => 'email:'.$data['email'],
            filled($data['whatsapp_number']) => 'whatsapp:'.$data['whatsapp_number'],
            default => 'name:'.Str::slug((string) $data['name']).'|'.Str::lower((string) $data['channel']),
        };
    }

    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
