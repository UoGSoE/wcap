<?php

namespace App\Http\Controllers\Api;

use App\Enums\Location;
use App\Http\Requests\UpsertPlanEntriesRequest;
use App\Models\PlanEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController
{
    /**
     * Get the authenticated user's plan entries for the next 10 weekdays.
     */
    public function myPlan(Request $request): JsonResponse
    {
        $user = $request->user();

        // Calculate date range (next 10 weekdays)
        $start = now()->startOfWeek();
        $weekdayDates = [];

        for ($offset = 0; $offset < 14; $offset++) {
            $day = $start->copy()->addDays($offset);
            if ($day->isWeekday()) {
                $weekdayDates[] = $day->toDateString();
            }
            if (count($weekdayDates) === 10) {
                break;
            }
        }

        // Load user's plan entries
        $entries = $user->planEntries()
            ->whereIn('entry_date', $weekdayDates)
            ->orderBy('entry_date')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'entry_date' => $entry->entry_date->toDateString(),
                    'location' => $entry->location?->value,
                    'location_label' => $entry->location?->label(),
                    'note' => $entry->note,
                    'is_available' => $entry->is_available,
                    'is_holiday' => $entry->is_holiday,
                    'category' => $entry->category?->value,
                    'category_label' => $entry->category?->label(),
                ];
            });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
            ],
            'date_range' => [
                'start' => $weekdayDates[0],
                'end' => end($weekdayDates),
            ],
            'entries' => $entries,
        ]);
    }

    /**
     * Create or update plan entries for the authenticated user.
     * Always expects an entries array. Matches by id (if provided) or entry_date.
     */
    public function upsert(UpsertPlanEntriesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        foreach ($validated['entries'] as $entry) {
            $attributes = [
                'user_id' => $user->id,
                'entry_date' => $entry['entry_date'],
                'location' => $entry['location'],
                'note' => $entry['note'] ?? null,
                'is_available' => $entry['is_available'] ?? true,
                'is_holiday' => $entry['is_holiday'] ?? false,
                'category' => $entry['category'] ?? null,
            ];

            if (! empty($entry['id'])) {
                PlanEntry::updateOrCreate(['id' => $entry['id']], $attributes);

                continue;
            }

            unset($attributes['user_id']);

            // Can't use updateOrCreate due to SQLite vs MySQL date handling
            $existing = $user->planEntries()
                ->whereDate('entry_date', $entry['entry_date'])
                ->first();

            if ($existing) {
                $existing->update($attributes);
            } else {
                $user->planEntries()->create($attributes);
            }
        }

        return response()->json([
            'message' => 'Plan entries saved successfully',
        ]);
    }

    /**
     * Delete a plan entry.
     * Users can only delete their own entries.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = $request->user()->planEntries()->findOrFail($id);
        $entry->delete();

        return response()->json([
            'message' => 'Plan entry deleted successfully',
        ]);
    }

    /**
     * Get all available locations.
     * Useful for building forms or validation in external applications.
     */
    public function locations(Request $request): JsonResponse
    {
        $locations = collect(Location::cases())->map(fn ($location) => $location->toArray());

        return response()->json([
            'locations' => $locations,
        ]);
    }
}
