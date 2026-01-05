<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use App\Models\User;
use App\Services\ManagerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController
{
    /**
     * Determine which token ability the user has for scoping.
     */
    private function getTokenAbility(Request $request): string
    {
        $user = $request->user();

        if ($user->tokenCan('view:all-plans')) {
            return 'view:all-plans';
        }

        if ($user->tokenCan('view:team-plans')) {
            return 'view:team-plans';
        }

        // Shouldn't reach here due to middleware, but safe default
        return 'view:own-plan';
    }

    /**
     * Get team report (person × day grid).
     *
     * Requires: view:team-plans or view:all-plans token ability
     */
    public function team(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $ability = $this->getTokenAbility($request);

        $userIds = $service->getScopedUserIds($user, $ability);
        $days = $service->buildDays();

        // Get team members (filtered by scoped user IDs)
        $teamMembers = User::whereIn('id', $userIds)
            ->orderBy('surname')
            ->get()
            ->all();

        $entriesByUser = $service->buildEntriesByUser($teamMembers, $days);
        $teamRows = $service->buildTeamRows($teamMembers, $days, $entriesByUser);

        return response()->json([
            'scope' => $ability,
            'days' => array_map(fn ($d) => [
                'date' => $d['date']->toDateString(),
                'day_name' => $d['date']->format('l'),
            ], $days),
            'team_rows' => $teamRows,
        ]);
    }

    /**
     * Get location report (day × location grouping).
     *
     * Requires: view:team-plans or view:all-plans token ability
     */
    public function location(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $ability = $this->getTokenAbility($request);

        $userIds = $service->getScopedUserIds($user, $ability);
        $days = $service->buildDays();

        $teamMembers = User::whereIn('id', $userIds)
            ->orderBy('surname')
            ->get()
            ->all();

        $locations = Location::orderBy('name')->get();
        $entriesByUser = $service->buildEntriesByUser($teamMembers, $days);
        $locationDays = $service->buildLocationDays($days, $teamMembers, $entriesByUser, $locations);

        return response()->json([
            'scope' => $ability,
            'location_days' => array_map(function ($day) {
                return [
                    'date' => $day['date']->toDateString(),
                    'day_name' => $day['date']->format('l'),
                    'locations' => $day['locations'],
                ];
            }, $locationDays),
        ]);
    }

    /**
     * Get coverage matrix (location × day with counts).
     *
     * Requires: view:team-plans or view:all-plans token ability
     */
    public function coverage(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $ability = $this->getTokenAbility($request);

        $userIds = $service->getScopedUserIds($user, $ability);
        $days = $service->buildDays();

        $teamMembers = User::whereIn('id', $userIds)
            ->orderBy('surname')
            ->get()
            ->all();

        $locations = Location::orderBy('name')->get();
        $entriesByUser = $service->buildEntriesByUser($teamMembers, $days);
        $locationDays = $service->buildLocationDays($days, $teamMembers, $entriesByUser, $locations);
        $coverageMatrix = $service->buildCoverageMatrix($days, $locationDays, $locations);

        return response()->json([
            'scope' => $ability,
            'days' => array_map(fn ($d) => [
                'date' => $d['date']->toDateString(),
                'day_name' => $d['date']->format('l'),
            ], $days),
            'coverage_matrix' => array_map(function ($row) {
                return [
                    'location' => $row['label'],
                    'entries' => array_map(fn ($e) => [
                        'date' => $e['date']->toDateString(),
                        'count' => $e['count'],
                    ], $row['entries']),
                ];
            }, $coverageMatrix),
        ]);
    }

    /**
     * Get service availability matrix (service × day with availability counts).
     *
     * Requires: view:team-plans or view:all-plans token ability
     *
     * Note: Shows ALL services (not scoped by user's teams), but counts
     * may differ based on token ability (team vs all users).
     */
    public function serviceAvailability(Request $request, ManagerReportService $service): JsonResponse
    {
        $days = $service->buildDays();
        $serviceAvailabilityMatrix = $service->buildServiceAvailabilityMatrix($days);

        return response()->json([
            'scope' => $this->getTokenAbility($request),
            'days' => array_map(fn ($d) => [
                'date' => $d['date']->toDateString(),
                'day_name' => $d['date']->format('l'),
            ], $days),
            'service_availability_matrix' => array_map(function ($row) {
                return [
                    'service' => $row['label'],
                    'entries' => array_map(fn ($e) => [
                        'date' => $e['date']->toDateString(),
                        'count' => $e['count'],
                        'manager_only' => $e['manager_only'],
                    ], $row['entries']),
                ];
            }, $serviceAvailabilityMatrix),
        ]);
    }
}
