<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParsesDateWindowFilter;
use App\Models\Location;
use App\Models\User;
use App\Services\ManagerReportService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReportController
{
    use ParsesDateWindowFilter;

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
    #[QueryParameter('filter[location_slug]', description: 'Only return team rows for users with at least one entry at this location (e.g. "rankine").', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[state]', description: 'Only return rows for users with at least one matching entry. "planned" = has a location, "away" = no location.', type: 'string', example: 'planned')]
    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to]. Default: Monday of the current week.', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from]. Default: two weeks from filter[from].', type: 'string', example: '2026-04-24')]
    public function team(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $ability = $this->getTokenAbility($request);

        $userIds = $service->getScopedUserIds($user, $ability);
        [$from, $to] = $this->parseDateWindow($request);
        $days = $service->buildDays($from, $to);

        $teamMembers = QueryBuilder::for(User::query()->whereIn('id', $userIds))
            ->allowedFilters(
                AllowedFilter::callback('location_slug', function ($query, $value) {
                    $query->whereHas('planEntries.location', function ($q) use ($value) {
                        $q->where('slug', $value);
                    });
                }),
                AllowedFilter::callback('state', function ($query, $value) {
                    $query->whereHas('planEntries', function ($q) use ($value) {
                        $value === 'planned'
                            ? $q->whereNotNull('location_id')
                            : $q->whereNull('location_id');
                    });
                }),
                AllowedFilter::callback('from', fn () => null),
                AllowedFilter::callback('to', fn () => null),
            )
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
            'team_rows' => array_map(function ($row) {
                $row['days'] = array_map(fn ($day) => [
                    ...$day,
                    'date' => $day['date']->toDateString(),
                ], $row['days']);

                return $row;
            }, $teamRows),
        ]);
    }

    /**
     * Get location report (day × location grouping).
     *
     * Requires: view:team-plans or view:all-plans token ability
     */
    #[QueryParameter('filter[location_slug]', description: 'Narrow each day to a single location (e.g. "rankine").', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[is_physical]', description: 'Exclude non-physical locations like Remote/Other when true.', type: 'boolean', example: true)]
    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to].', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from].', type: 'string', example: '2026-04-24')]
    public function location(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $ability = $this->getTokenAbility($request);

        $userIds = $service->getScopedUserIds($user, $ability);
        [$from, $to] = $this->parseDateWindow($request);
        $days = $service->buildDays($from, $to);

        $teamMembers = User::whereIn('id', $userIds)
            ->orderBy('surname')
            ->get()
            ->all();

        $locations = QueryBuilder::for(Location::class)
            ->allowedFilters(
                AllowedFilter::exact('location_slug', 'slug'),
                AllowedFilter::exact('is_physical'),
                AllowedFilter::callback('from', fn () => null),
                AllowedFilter::callback('to', fn () => null),
            )
            ->orderBy('name')
            ->get();

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
    #[QueryParameter('filter[location_slug]', description: 'Narrow the coverage matrix to a single location.', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[is_physical]', description: 'Exclude non-physical locations like Remote/Other when true.', type: 'boolean', example: true)]
    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to].', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from].', type: 'string', example: '2026-04-24')]
    public function coverage(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $ability = $this->getTokenAbility($request);

        $userIds = $service->getScopedUserIds($user, $ability);
        [$from, $to] = $this->parseDateWindow($request);
        $days = $service->buildDays($from, $to);

        $teamMembers = User::whereIn('id', $userIds)
            ->orderBy('surname')
            ->get()
            ->all();

        $locations = QueryBuilder::for(Location::class)
            ->allowedFilters(
                AllowedFilter::exact('location_slug', 'slug'),
                AllowedFilter::exact('is_physical'),
                AllowedFilter::callback('from', fn () => null),
                AllowedFilter::callback('to', fn () => null),
            )
            ->orderBy('name')
            ->get();

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
