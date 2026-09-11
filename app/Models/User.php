<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'active', 'is_super_admin'])]
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
            'is_super_admin' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Super-amministratore: admin con accesso alle impostazioni sensibili. */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin' && (bool) $this->is_super_admin;
    }

    public function isManutentore(): bool
    {
        return $this->role === 'manutentore';
    }

    public function isOperatore(): bool
    {
        return $this->role === 'operatore';
    }

    public function isManutentoreEsterno(): bool
    {
        return $this->role === 'manutentore_esterno';
    }

    public function isManutentoreStraordinario(): bool
    {
        return $this->role === 'manutentore_straordinario';
    }

    /** Manutentore "specialista" a cui le richieste vengono assegnate (esterno o straordinario). */
    public function riceveAssegnazioni(): bool
    {
        return in_array($this->role, ['manutentore_esterno', 'manutentore_straordinario'], true);
    }

    /** Può prendere in carico e aggiornare le richieste. */
    public function canManutentore(): bool
    {
        return in_array($this->role, ['manutentore', 'manutentore_esterno', 'manutentore_straordinario', 'admin'], true);
    }

    public function roleLabel(): string
    {
        return config('manutenzione.ruoli.'.$this->role, $this->role);
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
