<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'short_label',
        'slug',
        'is_physical',
    ];

    protected function casts(): array
    {
        return [
            'is_physical' => 'boolean',
        ];
    }

    public function planEntries(): HasMany
    {
        return $this->hasMany(PlanEntry::class);
    }

    public function usersWithDefault(): HasMany
    {
        return $this->hasMany(User::class, 'default_location_id');
    }

    public function scopePhysical(Builder $query): Builder
    {
        return $query->where('is_physical', true);
    }

    public function label(): string
    {
        return $this->name;
    }

    public function shortLabel(): string
    {
        return $this->short_label;
    }

    public function isPhysical(): bool
    {
        return $this->is_physical;
    }
}
