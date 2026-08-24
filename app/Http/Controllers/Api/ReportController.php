<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParsesDateWindowFilter;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\ManagerReportService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReportController
{
    use ParsesDateWindowFilter;

    private function getScope(Request $request): string
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return 'all';
        }

        if ($user->isManager()) {
            return 'team';
        }

        return 'own';
    }

    #[QueryParameter('filter[location_slug]', description: 'Only return team rows for users with at least one entry at this location (e.g. "rankine").', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[state]', description: 'Only return rows for users with at least one matching entry. "planned" = has a location, "away" = no location.', type: 'string', example: 'planned')]
    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to]. Default: Monday of the current week.', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from]. Default: two weeks from filter[from].', type: 'string', example: '2026-04-24')]
    public function team(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $scope = $this->getScope($request);

        $userIds = $service->getScopedUserIds($user, $scope);
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
            'scope' => $scope,
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

    #[QueryParameter('filter[location_slug]', description: 'Narrow each day to a single location (e.g. "rankine").', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[is_physical]', description: 'Exclude non-physical locations like Remote/Other when true.', type: 'boolean', example: true)]
    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to].', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from].', type: 'string', example: '2026-04-24')]
    public function location(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $scope = $this->getScope($request);

        $userIds = $service->getScopedUserIds($user, $scope);
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
            'scope' => $scope,
            'location_days' => array_map(function ($day) {
                return [
                    'date' => $day['date']->toDateString(),
                    'day_name' => $day['date']->format('l'),
                    'locations' => array_values(array_map(fn ($loc) => [
                        'location_slug' => $loc['slug'],
                        'location' => $loc['label'],
                        'is_physical' => $loc['is_physical'],
                        'members' => $loc['members'],
                    ], $day['locations'])),
                ];
            }, $locationDays),
        ]);
    }

    #[QueryParameter('filter[location_slug]', description: 'Narrow the coverage matrix to a single location.', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[is_physical]', description: 'Exclude non-physical locations like Remote/Other when true.', type: 'boolean', example: true)]
    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to].', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from].', type: 'string', example: '2026-04-24')]
    public function coverage(Request $request, ManagerReportService $service): JsonResponse
    {
        $user = $request->user();
        $scope = $this->getScope($request);

        $userIds = $service->getScopedUserIds($user, $scope);
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
            'scope' => $scope,
            'days' => array_map(fn ($d) => [
                'date' => $d['date']->toDateString(),
                'day_name' => $d['date']->format('l'),
            ], $days),
            'coverage_matrix' => array_map(function ($row) {
                return [
                    'location' => $row['label'],
                    'location_slug' => $row['slug'],
                    'entries' => array_map(fn ($e) => [
                        'date' => $e['date']->toDateString(),
                        'count' => $e['count'],
                    ], $row['entries']),
                ];
            }, $coverageMatrix),
        ]);
    }

    #[QueryParameter('filter[service_slug]', description: 'Narrow to a single service. Slug is a kebab-case derivation of the service name (e.g. "VPN Service" → "vpn-service").', type: 'string', example: 'vpn-service')]
    #[QueryParameter('filter[manager_only]', description: 'When true, return only services whose coverage relies on the manager on at least one day in the window — the "at risk" view.', type: 'boolean', example: true)]
    #[QueryParameter('filter[from]', description: 'Start of a custom date window (YYYY-MM-DD). Must be paired with filter[to].', type: 'string', example: '2026-04-20')]
    #[QueryParameter('filter[to]', description: 'End of a custom date window (YYYY-MM-DD). Must be paired with filter[from].', type: 'string', example: '2026-04-24')]
    public function serviceAvailability(Request $request, ManagerReportService $service): JsonResponse
    {
        [$from, $to] = $this->parseDateWindow($request);
        $days = $service->buildDays($from, $to);

        // QueryBuilder is used here purely for its allowlist — unknown filters
        // raise Spatie's native InvalidFilterQuery (with helpful "allowed
        // filter(s) are ..." messaging). The non-query filters (service_slug,
        // manager_only, from/to) are callbacks that no-op at the DB level;
        // the actual logic lives below in PHP so the matrix shape is preserved.
        $services = QueryBuilder::for(Service::class)
            ->allowedFilters(
                AllowedFilter::callback('service_slug', fn () => null),
                AllowedFilter::callback('manager_only', fn () => null),
                AllowedFilter::callback('from', fn () => null),
                AllowedFilter::callback('to', fn () => null),
            )
            ->with(['users', 'manager'])
            ->orderBy('name')
            ->get();

        if ($serviceSlug = $request->input('filter.service_slug')) {
            $services = $services->filter(fn ($s) => Str::slug($s->name) === $serviceSlug)->values();
        }

        $matrix = $service->buildServiceAvailabilityMatrix($days, $services);

        if ($request->boolean('filter.manager_only')) {
            $matrix = array_values(array_filter(
                $matrix,
                fn ($row) => collect($row['entries'])->contains(fn ($e) => $e['manager_only']),
            ));
        }

        // The service matrix is not role-scoped — every caller sees the same
        // services and counts — but we still surface scope=global so consumers
        // can write generic "always read response.scope" code across reports.
        return response()->json([
            'scope' => 'global',
            'days' => array_map(fn ($d) => [
                'date' => $d['date']->toDateString(),
                'day_name' => $d['date']->format('l'),
            ], $days),
            'service_availability_matrix' => array_map(function ($row) {
                return [
                    'service' => $row['label'],
                    'service_slug' => Str::slug($row['label']),
                    'entries' => array_map(fn ($e) => [
                        'date' => $e['date']->toDateString(),
                        'count' => $e['count'],
                        'manager_only' => $e['manager_only'],
                    ], $row['entries']),
                ];
            }, $matrix),
        ]);
    }
}
