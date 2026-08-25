<?php

namespace App\Http\Controllers\Api;

use App\Enums\AvailabilityStatus;
use App\Http\Controllers\Api\Concerns\ParsesDateWindowFilter;
use App\Http\Requests\ManagerUpsertPlanEntriesRequest;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\ManagerReportService;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerPlanController
{
    use ParsesDateWindowFilter;

    public function teamMembers(Request $request): JsonResponse
    {
        $user = $request->user();

        $teamMembers = $this->getManageableUsers($user)
            ->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->full_name,
                'email' => $member->email,
            ]);

        return response()->json([
            'team_members' => $teamMembers,
        ]);
    }

    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to].', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from].', type: 'string', example: '2026-04-24')]
    public function show(Request $request, int $userId, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($userId);

        if (! $user->canManagePlanFor($targetUser)) {
            abort(403, 'You cannot manage this user\'s plan.');
        }

        [$from, $to] = $this->parseDateWindow($request);
        $days = $service->buildDays($from, $to);
        $weekdayDates = array_map(fn ($d) => $d['key'], $days);

        $windowStart = $from ?? $weekdayDates[0];
        $windowEnd = $to ?? end($weekdayDates);

        $entries = $targetUser->planEntries()
            ->whereDate('entry_date', '>=', $windowStart)
            ->whereDate('entry_date', '<=', $windowEnd)
            ->orderBy('entry_date')
            ->get()
            ->map(fn ($entry) => $this->transformEntry($entry));

        return response()->json([
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->full_name,
            ],
            'date_range' => [
                'start' => $windowStart,
                'end' => $windowEnd,
            ],
            'entries' => $entries,
        ]);
    }

    public function upsert(ManagerUpsertPlanEntriesRequest $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($userId);

        if (! $user->canManagePlanFor($targetUser)) {
            abort(403, 'You cannot manage this user\'s plan.');
        }

        $validated = $request->validated();

        foreach ($validated['entries'] as $entry) {
            $locationId = null;
            if (! empty($entry['location'])) {
                $locationId = Location::where('slug', $entry['location'])->first()?->id;
            }

            $attributes = [
                'user_id' => $targetUser->id,
                'entry_date' => $entry['entry_date'],
                'location_id' => $locationId,
                'note' => $entry['note'] ?? null,
                'availability_status' => $entry['availability_status'] ?? AvailabilityStatus::ONSITE->value,
                'is_holiday' => $entry['is_holiday'] ?? false,
                'category' => $entry['category'] ?? null,
                'created_by_manager' => true,
            ];

            if (! empty($entry['id'])) {
                PlanEntry::updateOrCreate(['id' => $entry['id']], $attributes);

                continue;
            }

            unset($attributes['user_id']);

            // Match by date (same pattern as PlanController for SQLite compatibility)
            $existing = $targetUser->planEntries()
                ->whereDate('entry_date', $entry['entry_date'])
                ->first();

            if ($existing) {
                $existing->update($attributes);
            } else {
                $targetUser->planEntries()->create($attributes);
            }
        }

        return response()->json([
            'message' => 'Plan entries saved successfully',
        ]);
    }

    public function fillDefaults(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($userId);

        if (! $user->canManagePlanFor($targetUser)) {
            abort(403, 'You cannot manage this user\'s plan.');
        }

        $validated = $request->validate([
            'week_start' => 'required|date',
            'only_date' => 'nullable|date',
        ]);

        $createdByManager = $targetUser->isNot($user);

        if (isset($validated['only_date'])) {
            $onlyDate = Carbon::parse($validated['only_date']);
            $filledDays = $targetUser->fillPlanDayFromDefaults($onlyDate, $createdByManager) ? 1 : 0;
            $windowFrom = $windowTo = $onlyDate->toDateString();
        } else {
            $weekStart = Carbon::parse($validated['week_start'])->startOfWeek();
            $filledDays = $targetUser->fillPlanFromDefaults($weekStart, createdByManager: $createdByManager);
            $windowFrom = $weekStart->toDateString();
            $windowTo = $weekStart->copy()->addDays(11)->toDateString();
        }

        $response = [
            'filled_days' => $filledDays,
            'window' => [
                'from' => $windowFrom,
                'to' => $windowTo,
            ],
            'user' => ['email' => $targetUser->email],
        ];

        if (! $targetUser->hasUsableDefaults()) {
            $response['skipped_reason'] = 'no_defaults';
        }

        return response()->json($response);
    }

    public function destroy(Request $request, int $userId, int $entryId): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($userId);

        if (! $user->canManagePlanFor($targetUser)) {
            abort(403, 'You cannot manage this user\'s plan.');
        }

        $entry = $targetUser->planEntries()->findOrFail($entryId);
        $entry->delete();

        return response()->json([
            'message' => 'Plan entry deleted successfully',
        ]);
    }

    private function getManageableUsers(User $user)
    {
        if ($user->isAdmin()) {
            return User::orderBy('surname')->get();
        }

        return Team::whereIn('id', $user->allManagedTeamIds())
            ->with('users')
            ->get()
            ->flatMap(fn ($team) => $team->users)
            ->unique('id')
            ->sortBy('surname')
            ->values();
    }

    private function transformEntry(PlanEntry $entry): array
    {
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
            'created_by_manager' => $entry->created_by_manager,
        ];
    }
}
