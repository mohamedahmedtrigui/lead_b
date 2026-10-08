<?php

namespace App\Models;

use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    protected function fullName(): Attribute
    {
        // Archived accounts stay visible in history, flagged as such.
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}").($this->isArchived() ? ' (supprimé)' : ''));
    }

    /**
     * Soft-deleted account. Reads the raw attribute so it also works on
     * in-memory models that never loaded the column (strict mode).
     */
    public function isArchived(): bool
    {
        return ($this->attributes['deleted_at'] ?? null) !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isDispatcher(): bool
    {
        return $this->role === UserRole::DISPATCHER;
    }

    public function isApproved(): bool
    {
        return $this->status === UserStatus::APPROVED;
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function callAttempts(): HasMany
    {
        return $this->hasMany(CallAttempt::class, 'dispatcher_id');
    }

    public function scopeDispatchers(Builder $query): Builder
    {
        return $query->where('role', UserRole::DISPATCHER);
    }

    public function scopeActiveDispatchers(Builder $query): Builder
    {
        return $query->dispatchers()->where('status', UserStatus::APPROVED);
    }
}
