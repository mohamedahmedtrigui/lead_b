<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatcherNote extends Model
{
    public const TYPE_NOTE = 'NOTE';

    public const TYPE_SUMMARY = 'SUMMARY';

    protected $fillable = [
        'lead_id',
        'user_id',
        'call_attempt_id',
        'type',
        'body',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
