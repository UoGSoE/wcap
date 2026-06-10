<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable('username', 'surname', 'forenames', 'email', 'password', 'default_location_id', 'default_category', 'default_availability_status', 'is_admin', 'is_staff')]
#[Hidden('password', 'remember_token')]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'is_admin' => 'boolean',
            'is_staff' => 'boolean',
            'default_availability_status' => AvailabilityStatus::class,
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function planEntries(): HasMany
    {
        return $this->hasMany(PlanEntry::class);
    }

    public function managedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function managedServices(): HasMany
    {
        return $this->hasMany(Service::class, 'manager_id');
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    public function isManager(): bool
    {
        return $this->managedTeams->count() > 0;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /** @return array<int> */
    public function allManagedTeamIds(): array
    {
        $directIds = $this->managedTeams()->pluck('id')->toArray();
        $allIds = $directIds;

        foreach (Team::whereIn('id', $directIds)->with('childTeams')->get() as $team) {
            $allIds = array_merge($allIds, $team->descendantIds());
        }

        return array_values(array_unique($allIds));
    }

    public function canManagePlanFor(User $targetUser): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($targetUser->id === $this->id) {
            return true;
        }

        return Team::whereIn('id', $this->allManagedTeamIds())
            ->whereHas('users', fn ($q) => $q->where('users.id', $targetUser->id))
            ->exists();
    }

    public function getFullNameAttribute(): string
    {
        return $this->surname.', '.$this->forenames;
    }
}
