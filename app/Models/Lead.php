<?php

namespace App\Models;

use App\Domain\Leads\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * Columns coming from the CSV source. The import only ever writes these.
     */
    public const SOURCE_FIELDS = [
        'source_created_at',
        'name',
        'email',
        'source',
        'form',
        'channel',
        'stage',
        'source_owner',
        'labels',
        'phone',
        'secondary_phone',
        'whatsapp_number',
    ];

    protected $fillable = [
        'dedupe_key',
        'lead_import_id',
        ...self::SOURCE_FIELDS,
    ];

    protected $attributes = [
        'status' => 'PENDING',
        'nrp_attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source_created_at' => 'datetime',
            'assigned_at' => 'datetime',
            'last_nrp_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'callback_at' => 'datetime',
            'converted_at' => 'datetime',
            'nrp_attempts' => 'integer',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function qualification(): HasOne
    {
        return $this->hasOne(LeadQualification::class);
    }

    public function callAttempts(): HasMany
    {
        return $this->hasMany(CallAttempt::class)->latest('started_at');
    }

    /**
     * Call currently in progress (constrain on dispatcher_id when eager loading).
     */
    public function openCall(): HasOne
    {
        return $this->hasOne(CallAttempt::class)->whereNull('ended_at')->latest('started_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DispatcherNote::class)->latest();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->latest('created_at')->latest('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest('created_at')->latest('id');
    }

    public function isNrpFinal(): bool
    {
        return $this->nrp_attempts >= (int) config('leads.nrp.max_attempts');
    }

    /**
     * Data isolation: dispatchers only ever see the leads assigned to them.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('assigned_to', $user->id);
    }

    /**
     * Leads nobody has worked on yet: still "PENDING", never called, never
     * qualified. Only these can be (re)allocated automatically.
     */
    public function scopeUntouched(Builder $query): Builder
    {
        return $query
            ->where('status', LeadStatus::PENDING)
            ->where('nrp_attempts', 0)
            ->whereNull('last_contacted_at')
            ->whereDoesntHave('callAttempts')
            ->whereDoesntHave('qualification');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereLike('name', $like)
            ->orWhereLike('phone', $like)
            ->orWhereLike('email', $like)
            ->orWhereLike('whatsapp_number', $like));
    }
}
