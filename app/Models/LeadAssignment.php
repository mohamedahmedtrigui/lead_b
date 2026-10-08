<?php

namespace App\Models;

use App\Domain\Leads\Enums\AssignmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'from_user_id',
        'to_user_id',
        'assigned_by',
        'type',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssignmentType::class,
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id')->withTrashed();
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id')->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }
}
