<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'manager_id',
        'parent_team_id',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function parentTeam(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_team_id');
    }

    public function childTeams(): HasMany
    {
        return $this->hasMany(self::class, 'parent_team_id');
    }

    /** @return array<int> */
    public function descendantIds(): array
    {
        $ids = [];
        foreach ($this->childTeams as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /** @return array<int> */
    public function ancestorIds(): array
    {
        $ids = [];
        $current = $this->parentTeam;
        while ($current) {
            $ids[] = $current->id;
            $current = $current->parentTeam;
        }

        return $ids;
    }
}
