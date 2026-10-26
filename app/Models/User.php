<?php

namespace App\Models;

use App\Enums\Department;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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

    public function canAddRentalAreas(): bool
    {
        return $this->is_company_manager || $this->can_add_rental_areas;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_company_manager || $this->departmentMemberships()->exists();
    }
}
