<?php

namespace App\Http\Controllers\Api;

use App\Enums\Location;
use App\Models\PlanEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /**
     * Create or update plan entries for the authenticated user.
     * Supports both single entry and batch operations.
     * Matches by id (if provided) or entry_date.
     */
    public function upsert(Request $request): JsonResponse
    {
        $user = $request->user();

        // Support both single entry and array of entries
        $isBatch = is_array($request->input('entries'));
        $entries = $isBatch ? $request->input('entries') : [$request->all()];

        // Validate entries
        $validated = $request->validate([
            'entries' => 'sometimes|array',
            'entries.*.id' => [
                'nullable',
                'integer',
                Rule::exists('plan_entries', 'id')->where('user_id', $user->id),
            ],
            'entries.*.entry_date' => 'required|date',
            'entries.*.location' => ['required', 'string', Rule::enum(Location::class)],
            'entries.*.note' => 'nullable|string',
            'entries.*.is_available' => 'nullable|boolean',
            'entries.*.is_holiday' => 'nullable|boolean',
            'entries.*.category' => 'nullable|string',

            // Single entry mode validation
            'id' => [
                'nullable',
                'integer',
                Rule::exists('plan_entries', 'id')->where('user_id', $user->id),
            ],
            'entry_date' => 'required_without:entries|date',
            'location' => ['required_without:entries', 'string', Rule::enum(Location::class)],
            'note' => 'nullable|string',
            'is_available' => 'nullable|boolean',
            'is_holiday' => 'nullable|boolean',
            'category' => 'nullable|string',
        ]);

        $processedEntries = collect($entries)->map(function ($entry) use ($user) {
            $attributes = [
                'user_id' => $user->id,
                'entry_date' => $entry['entry_date'],
                'location' => $entry['location'],
                'note' => $entry['note'] ?? null,
                'is_available' => $entry['is_available'] ?? true,
                'is_holiday' => $entry['is_holiday'] ?? false,
                'category' => $entry['category'] ?? null,
            ];

            // Match by id or find by entry_date
            if (! empty($entry['id'])) {
                // Update existing entry by id
                $planEntry = PlanEntry::updateOrCreate(
                    ['id' => $entry['id']],
                    $attributes
                );
            } else {
                // Find by entry_date or create new
                $planEntry = PlanEntry::where('user_id', $user->id)
                    ->whereDate('entry_date', $entry['entry_date'])
                    ->first();

                if ($planEntry) {
                    $planEntry->update($attributes);
                } else {
                    $planEntry = PlanEntry::create($attributes);
                }
            }

            return [
                'id' => $planEntry->id,
                'entry_date' => $planEntry->entry_date->toDateString(),
                'location' => $planEntry->location?->value,
                'location_label' => $planEntry->location?->label(),
                'note' => $planEntry->note,
                'is_available' => $planEntry->is_available,
                'is_holiday' => $planEntry->is_holiday,
                'category' => $planEntry->category?->value,
                'category_label' => $planEntry->category?->label(),
            ];
        });

        if ($isBatch) {
            return response()->json([
                'message' => 'Plan entries saved successfully',
                'entries' => $processedEntries,
            ]);
        }

        return response()->json([
            'message' => 'Plan entry saved successfully',
            'entry' => $processedEntries->first(),
        ]);
    }

    /**
     * Delete a plan entry.
     * Users can only delete their own entries.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $entry = PlanEntry::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $entry) {
            return response()->json([
                'message' => 'Plan entry not found',
            ], 404);
        }

        $entry->delete();

        return response()->json([
            'message' => 'Plan entry deleted successfully',
        ]);
    }
}
