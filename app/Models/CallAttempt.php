<?php

namespace App\Models;

use App\Domain\Calls\Enums\CallOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallAttempt extends Model
{
    protected $fillable = [
        'lead_id',
        'dispatcher_id',
        'attempt_number',
        'outcome',
        'started_at',
        'ended_at',
        'duration_seconds',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => CallOutcome::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'attempt_number' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatcher_id')->withTrashed();
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
