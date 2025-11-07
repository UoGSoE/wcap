<?php

namespace App\Models;

use App\Enums\Category;
use App\Enums\Location;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEntry extends Model
{
    /** @use HasFactory<\Database\Factories\PlanEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entry_date',
        'note',
        'category',
        'location',
        'is_available',
        'is_holiday',
        'created_by_manager',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'location' => Location::class,
            'category' => Category::class,
            'is_available' => 'boolean',
            'is_holiday' => 'boolean',
            'created_by_manager' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
