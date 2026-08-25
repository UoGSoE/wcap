<?php

namespace App\Http\Controllers\Api;

use App\Enums\AvailabilityStatus;
use App\Http\Controllers\Api\Concerns\ParsesDateWindowFilter;
use App\Http\Requests\UpsertPlanEntriesRequest;
use App\Models\Location;
use App\Models\PlanEntry;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PlanController
{
    use ParsesDateWindowFilter;

    private const PLAN_ENTRY_FIELDS = [
        'id', 'entry_date', 'location', 'location_label', 'note',
        'availability_status', 'availability_status_label',
        'is_holiday', 'category', 'category_label',
    ];

    #[QueryParameter('filter[location_slug]', description: 'Only return entries at this location (e.g. "rankine").', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[is_holiday]', description: 'Only return entries flagged as holiday when true.', type: 'boolean', example: true)]
    #[QueryParameter('filter[availability_status]', description: 'Filter by availability: "onsite", "remote", or "not_available".', type: 'string', example: 'onsite')]
    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to].', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from].', type: 'string', example: '2026-04-24')]
    #[QueryParameter('fields[entries]', description: 'Comma-separated list of entry fields to return (e.g. "entry_date,location,note"). Unknown fields 4xx.', type: 'string', example: 'entry_date,location,note')]
    public function myPlan(Request $request): JsonResponse
    {
        $user = $request->user();

        [$from, $to] = $this->parseDateWindow($request);

        if ($from && $to) {
            $windowStart = $from;
            $windowEnd = $to;
        } else {
            // Default: the next 10 weekdays starting Monday of this week.
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

            $windowStart = $weekdayDates[0];
            $windowEnd = end($weekdayDates);
        }

        // Load user's plan entries.
        // Using whereDate bounds rather than whereIn so this behaves the same on
        // SQLite (tests) and MySQL (prod) — SQLite stores date-cast columns with
        // a "00:00:00" time suffix that breaks a straight string whereIn.
        $entries = QueryBuilder::for(
            $user->planEntries()
                ->with('location')
                ->whereDate('entry_date', '>=', $windowStart)
                ->whereDate('entry_date', '<=', $windowEnd)
        )
            ->allowedFilters(
                AllowedFilter::callback('location_slug', function ($query, $value) {
                    $query->whereHas('location', function ($q) use ($value) {
                        $q->where('slug', $value);
                    });
                }),
                AllowedFilter::exact('is_holiday'),
                AllowedFilter::exact('availability_status'),
                AllowedFilter::callback('from', fn () => null),
                AllowedFilter::callback('to', fn () => null),
            )
            ->orderBy('entry_date')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'entry_date' => $entry->entry_date->toDateString(),
                    'location' => $entry->location?->slug,
                    'location_label' => $entry->location?->label(),
                    'note' => $entry->note,
                    'availability_status' => $entry->availability_status->value,
                    'availability_status_label' => $entry->availability_status->label(),
                    'is_holiday' => $entry->is_holiday,
                    'category' => $entry->category?->value,
                    'category_label' => $entry->category?->label(),
                ];
            });

        $requestedFields = $this->requestedEntryFields($request);
        if ($requestedFields !== null) {
            $entries = $entries->map(fn ($entry) => array_intersect_key($entry, array_flip($requestedFields)));
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
            ],
            'date_range' => [
                'start' => $windowStart,
                'end' => $windowEnd,
            ],
            'entries' => $entries,
        ]);
    }

    public function upsert(UpsertPlanEntriesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        foreach ($validated['entries'] as $entry) {
            // Look up location by slug if provided
            $locationId = null;
            if (! empty($entry['location'])) {
                $location = Location::where('slug', $entry['location'])->first();
                $locationId = $location?->id;
            }

            $attributes = [
                'user_id' => $user->id,
                'entry_date' => $entry['entry_date'],
                'location_id' => $locationId,
                'note' => $entry['note'] ?? null,
                'availability_status' => $entry['availability_status'] ?? AvailabilityStatus::ONSITE->value,
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

    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = $request->user()->planEntries()->findOrFail($id);
        $entry->delete();

        return response()->json([
            'message' => 'Plan entry deleted successfully',
        ]);
    }

    /** @return array<int, string>|null */
    private function requestedEntryFields(Request $request): ?array
    {
        $raw = $request->input('fields.entries');

        if ($raw === null || $raw === '') {
            return null;
        }

        $requested = array_filter(array_map('trim', explode(',', $raw)));
        $unknown = array_diff($requested, self::PLAN_ENTRY_FIELDS);

        abort_if(
            ! empty($unknown),
            400,
            'Requested field(s) not allowed: '.implode(', ', $unknown),
        );

        return $requested;
    }

    public function locations(Request $request): JsonResponse
    {
        $locations = Location::orderBy('name')->get()->map(fn ($location) => [
            'value' => $location->slug,
            'label' => $location->label(),
            'short_label' => $location->shortLabel(),
        ]);

        return response()->json([
            'locations' => $locations,
        ]);
    }
}
