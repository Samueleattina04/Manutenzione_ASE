<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManutentore(): bool
    {
        return $this->role === 'manutentore';
    }

    public function isOperatore(): bool
    {
        return $this->role === 'operatore';
    }

    /** Può prendere in carico e aggiornare le richieste. */
    public function canManutentore(): bool
    {
        return in_array($this->role, ['manutentore', 'admin'], true);
    }

    public function createdRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'created_by');
    }

    public function assignedRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'assigned_to');
    }
}
