<?php

namespace App\Http\Controllers\Api;

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
        $entries = PlanEntry::where('user_id', $user->id)
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
}
