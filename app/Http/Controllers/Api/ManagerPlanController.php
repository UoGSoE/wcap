<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ManagerUpsertPlanEntriesRequest;
use App\Models\PlanEntry;
use App\Models\User;
use App\Services\ManagerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerPlanController
{
    /**
     * List team members the authenticated user can manage.
     */
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
            'count' => $teamMembers->count(),
        ]);
    }

    /**
     * Get a team member's plan entries.
     */
    public function show(Request $request, int $userId, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($userId);

        if (! $user->canManagePlanFor($targetUser)) {
            abort(403, 'You cannot manage this user\'s plan.');
        }

        $days = $service->buildDays();
        $weekdayDates = array_map(fn ($d) => $d['key'], $days);

        $entries = $targetUser->planEntries()
            ->whereBetween('entry_date', [$weekdayDates[0], end($weekdayDates)])
            ->orderBy('entry_date')
            ->get()
            ->map(fn ($entry) => $this->transformEntry($entry));

        return response()->json([
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->full_name,
            ],
            'date_range' => [
                'start' => $weekdayDates[0],
                'end' => end($weekdayDates),
            ],
            'entries' => $entries,
        ]);
    }

    /**
     * Upsert plan entries for a team member.
     */
    public function upsert(ManagerUpsertPlanEntriesRequest $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($userId);

        if (! $user->canManagePlanFor($targetUser)) {
            abort(403, 'You cannot manage this user\'s plan.');
        }

        $validated = $request->validated();

        foreach ($validated['entries'] as $entry) {
            $attributes = [
                'user_id' => $targetUser->id,
                'entry_date' => $entry['entry_date'],
                'location' => $entry['location'],
                'note' => $entry['note'] ?? null,
                'is_available' => $entry['is_available'] ?? true,
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

    /**
     * Delete a plan entry for a team member.
     */
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

    /**
     * Get users that the authenticated user can manage.
     */
    private function getManageableUsers(User $user)
    {
        if ($user->isAdmin()) {
            return User::orderBy('surname')->get();
        }

        return $user->managedTeams()
            ->with('users')
            ->get()
            ->flatMap(fn ($team) => $team->users)
            ->unique('id')
            ->sortBy('surname')
            ->values();
    }

    /**
     * Transform a plan entry for JSON response.
     */
    private function transformEntry(PlanEntry $entry): array
    {
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
            'created_by_manager' => $entry->created_by_manager,
        ];
    }
}
