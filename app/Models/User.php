<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }

        if ($this->role === $role) {
            return true;
        }

        return $this->roles()->where('slug', $role)->exists();
    }

    public function canAccess(string $permission): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function isAdmin(): bool
    {
        if (in_array($this->role, ['super_admin', 'admin', 'reception', 'accountant', 'librarian'], true)) {
            return true;
        }

        return $this->roles()->whereIn('slug', [
            'super-admin',
            'branch-admin',
            'reception',
            'accountant',
            'librarian',
            'counselor',
            'staff',
        ])->exists();
    }
}
