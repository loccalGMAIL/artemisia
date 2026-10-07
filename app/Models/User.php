<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccessPortal;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Each panel is the portal of the same id: the role must correspond to it (RF-24).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        $portal = AccessPortal::tryFrom($panel->getId());

        return $portal !== null && $portal->allows($this);
    }

    /**
     * Whether this account is an active admin and no other active admin exists (RF-10).
     */
    public function isLastActiveAdmin(): bool
    {
        if (! $this->is_active || ! $this->roles()->where('name', 'admin')->exists()) {
            return false;
        }

        return ! static::query()
            ->role('admin')
            ->where('is_active', true)
            ->whereKeyNot($this->getKey())
            ->lockForUpdate()
            ->exists();
    }
}
