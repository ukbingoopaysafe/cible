<?php

namespace App\Models;

use App\Enums\Department;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar_url',
        'password',
        'is_company_manager',
        'can_add_rental_areas',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_company_manager' => 'boolean',
            'can_add_rental_areas' => 'boolean',
        ];
    }

    public function departmentMemberships(): HasMany
    {
        return $this->hasMany(UserDepartment::class);
    }

    public function canWorkInDepartment(Department $department): bool
    {
        if ($this->is_company_manager) {
            return true;
        }

        return $this->departmentMemberships()
            ->where('department', $department->value)
            ->exists();
    }

    public function canManageDepartment(Department $department): bool
    {
        return $this->is_company_manager;
    }

    public function canManageAreas(): bool
    {
        return $this->is_company_manager || $this->can_add_rental_areas;
    }

    public function canAddRentalAreas(): bool
    {
        return $this->canManageAreas();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_company_manager || $this->departmentMemberships()->exists();
    }

    public function hasMultiFactorAuthentication(): bool
    {
        return filled($this->app_authentication_secret);
    }

    public function disableMultiFactorAuthentication(): void
    {
        $this->app_authentication_secret = null;
        $this->app_authentication_recovery_codes = null;
        $this->save();
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::disk('public')->url($this->avatar_url) : asset('images/brand/default-avatar.svg');
    }
}
