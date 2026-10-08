<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScriptStep extends Model
{
    protected $fillable = [
        'key',
        'position',
        'title',
        'objective',
        'script',
        'question',
        'prompts',
        'options',
        'tips',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'prompts' => 'array',
            'options' => 'array',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
