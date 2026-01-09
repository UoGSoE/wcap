<?php

namespace App\Services;

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OccupancyReportService
{
    private ?Collection $onsiteEntries = null;

    private ?Collection $entriesByDateAndLocation = null;

    private ?Collection $baseCapacities = null;

    private ?Collection $userDefaultLocations = null;

    private const WEEKLY_THRESHOLD = 25;

    public function buildReportPayload(?Carbon $snapshotDate = null, ?Carbon $rangeStart = null, ?Carbon $rangeEnd = null, bool $skipAggregation = false): array
    {
        $days = $this->buildDays($rangeStart, $rangeEnd);
        $physicalLocations = Location::physical()->orderBy('name')->get();

        $this->loadData($days);

        $snapshotDate = $snapshotDate ?? $this->getSnapshotDate();
        $periodMatrix = $this->buildPeriodMatrix($days, $physicalLocations);

        // Auto-aggregate to weekly view if range is large (unless skipped for detailed export)
        $aggregation = 'daily';
        if (! $skipAggregation && count($days) >= self::WEEKLY_THRESHOLD) {
            $condensed = $this->condenseToWeeks($days, $periodMatrix);
            $days = $condensed['days'];
            $periodMatrix = $condensed['periodMatrix'];
            $aggregation = 'weekly';
        }

        return [
            'days' => $days,
            'snapshotDate' => $snapshotDate,
            'daySnapshot' => $this->buildDaySnapshot($snapshotDate),
            'periodMatrix' => $periodMatrix,
            'summaryStats' => $this->buildSummaryStats($this->buildDays($rangeStart, $rangeEnd), $physicalLocations),
            'physicalLocations' => $physicalLocations,
            'aggregation' => $aggregation,
        ];
    }

    public function buildDays(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start = $start?->copy()->startOfDay() ?? now()->startOfWeek();
        $end = $end?->copy()->startOfDay() ?? $start->copy()->addDays(13);

        $days = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $days[] = [
                    'date' => $current->copy(),
                    'key' => $current->toDateString(),
                ];
            }
            $current->addDay();
        }

        return $days;
    }

    public function buildDaySnapshot(?Carbon $date = null): array
    {
        $date = $date ?? $this->getSnapshotDate();
        $dateKey = $date->toDateString();
        $physicalLocations = Location::physical()->orderBy('name')->get();

        $snapshot = [];

        foreach ($physicalLocations as $location) {
            $baseCapacity = $this->getBaseCapacity($location->id);
            $entriesAtLocation = $this->entriesByDateAndLocation->get($dateKey)?->get($location->id) ?? collect();

            $homeUsers = collect();
            $visitorUsers = collect();

            foreach ($entriesAtLocation as $entry) {
                $userDefaultLocationId = $this->userDefaultLocations->get($entry->user_id);

                if ($userDefaultLocationId === $location->id) {
                    $homeUsers->push($entry->user);
                } else {
                    $visitorUsers->push($entry->user);
                }
            }

            $homeCount = $homeUsers->count();
            $visitorCount = $visitorUsers->count();
            $totalPresent = $homeCount + $visitorCount;
            $utilizationPct = $baseCapacity > 0 ? round(($totalPresent / $baseCapacity) * 100, 1) : 0;

            $snapshot[] = [
                'location_id' => $location->id,
                'location_name' => $location->name,
                'short_label' => $location->shortLabel(),
                'base_capacity' => $baseCapacity,
                'home_count' => $homeCount,
                'visitor_count' => $visitorCount,
                'total_present' => $totalPresent,
                'utilization_pct' => $utilizationPct,
                'home_users' => $homeUsers,
                'visitor_users' => $visitorUsers,
            ];
        }

        return $snapshot;
    }

    public function buildPeriodMatrix(array $days, Collection $physicalLocations): array
    {
        $matrix = [];

        foreach ($physicalLocations as $location) {
            $baseCapacity = $this->getBaseCapacity($location->id);

            $row = [
                'location_id' => $location->id,
                'location_name' => $location->name,
                'short_label' => $location->shortLabel(),
                'base_capacity' => $baseCapacity,
                'days' => [],
            ];

            foreach ($days as $day) {
                $dateKey = $day['key'];
                $entriesAtLocation = $this->entriesByDateAndLocation->get($dateKey)?->get($location->id) ?? collect();

                $homeCount = 0;
                $visitorCount = 0;

                foreach ($entriesAtLocation as $entry) {
                    $userDefaultLocationId = $this->userDefaultLocations->get($entry->user_id);

                    if ($userDefaultLocationId === $location->id) {
                        $homeCount++;
                    } else {
                        $visitorCount++;
                    }
                }

                $totalPresent = $homeCount + $visitorCount;
                $utilizationPct = $baseCapacity > 0 ? round(($totalPresent / $baseCapacity) * 100, 1) : 0;

                $row['days'][] = [
                    'date' => $day['date'],
                    'home_count' => $homeCount,
                    'visitor_count' => $visitorCount,
                    'total_present' => $totalPresent,
                    'utilization_pct' => $utilizationPct,
                    'cell_class' => $this->getUtilizationCellClass($utilizationPct),
                ];
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    public function buildSummaryStats(array $days, Collection $physicalLocations): array
    {
        $stats = [];

        foreach ($physicalLocations as $location) {
            $baseCapacity = $this->getBaseCapacity($location->id);
            $dailyTotals = [];

            foreach ($days as $day) {
                $dateKey = $day['key'];
                $entriesAtLocation = $this->entriesByDateAndLocation->get($dateKey)?->get($location->id) ?? collect();
                $dailyTotals[$dateKey] = $entriesAtLocation->count();
            }

            $totalsCollection = collect($dailyTotals);
            $meanOccupancy = $totalsCollection->avg() ?? 0;
            $medianOccupancy = $this->calculateMedian($totalsCollection->values()->toArray());
            $peakOccupancy = $totalsCollection->max() ?? 0;
            $peakDateKey = $totalsCollection->search($peakOccupancy);
            $peakDate = $peakDateKey ? Carbon::parse($peakDateKey) : null;

            $stats[] = [
                'location_id' => $location->id,
                'location_name' => $location->name,
                'short_label' => $location->shortLabel(),
                'base_capacity' => $baseCapacity,
                'mean_occupancy' => (int) ceil($meanOccupancy),
                'median_occupancy' => (int) ceil($medianOccupancy),
                'peak_occupancy' => $peakOccupancy,
                'peak_date' => $peakDate,
            ];
        }

        return $stats;
    }

    private function loadData(array $days): void
    {
        $start = $days[0]['key'];
        $end = end($days)['key'];

        $this->onsiteEntries = PlanEntry::query()
            ->with(['user', 'location'])
            ->where('availability_status', AvailabilityStatus::ONSITE)
            ->whereNotNull('location_id')
            ->whereHas('location', fn ($q) => $q->where('is_physical', true))
            ->whereBetween('entry_date', [$start, $end])
            ->get();

        $this->entriesByDateAndLocation = $this->onsiteEntries->groupBy([
            fn ($e) => $e->entry_date->toDateString(),
            fn ($e) => $e->location_id,
        ]);

        $this->baseCapacities = User::query()
            ->whereNotNull('default_location_id')
            ->whereHas('defaultLocation', fn ($q) => $q->where('is_physical', true))
            ->selectRaw('default_location_id, COUNT(*) as count')
            ->groupBy('default_location_id')
            ->pluck('count', 'default_location_id');

        $this->userDefaultLocations = User::query()
            ->whereNotNull('default_location_id')
            ->pluck('default_location_id', 'id');
    }

    private function getBaseCapacity(int $locationId): int
    {
        return $this->baseCapacities->get($locationId, 0);
    }

    private function getSnapshotDate(): Carbon
    {
        $today = now()->startOfDay();

        if ($today->isWeekend()) {
            return $today->next(Carbon::MONDAY);
        }

        return $today;
    }

    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    private function getUtilizationCellClass(float $utilizationPct): string
    {
        return match (true) {
            $utilizationPct >= 80 => 'bg-emerald-200 dark:bg-emerald-800',
            $utilizationPct >= 50 => 'bg-emerald-100 dark:bg-emerald-900',
            $utilizationPct > 0 => 'bg-amber-100 dark:bg-amber-900/50',
            default => 'bg-zinc-100 dark:bg-zinc-800',
        };
    }

    private function condenseToWeeks(array $days, array $periodMatrix): array
    {
        // Group days by week (using Monday's date as key)
        $weekGroups = [];
        foreach ($days as $index => $day) {
            $weekKey = $day['date']->copy()->startOfWeek()->toDateString();
            if (! isset($weekGroups[$weekKey])) {
                $weekGroups[$weekKey] = [
                    'date' => $day['date']->copy()->startOfWeek(),
                    'key' => $weekKey,
                    'dayIndices' => [],
                ];
            }
            $weekGroups[$weekKey]['dayIndices'][] = $index;
        }

        // Build condensed days array (one per week)
        $condensedDays = array_values($weekGroups);

        // Condense period matrix
        $condensedMatrix = [];
        foreach ($periodMatrix as $locationRow) {
            $condensedRow = [
                'location_id' => $locationRow['location_id'],
                'location_name' => $locationRow['location_name'],
                'short_label' => $locationRow['short_label'],
                'base_capacity' => $locationRow['base_capacity'],
                'days' => [],
            ];

            foreach ($weekGroups as $weekData) {
                $weekDays = array_map(fn ($i) => $locationRow['days'][$i], $weekData['dayIndices']);
                $dayCount = count($weekDays);

                $avgHomeCount = (int) ceil(array_sum(array_column($weekDays, 'home_count')) / $dayCount);
                $avgVisitorCount = (int) ceil(array_sum(array_column($weekDays, 'visitor_count')) / $dayCount);
                $avgTotalPresent = (int) ceil(array_sum(array_column($weekDays, 'total_present')) / $dayCount);
                $avgUtilizationPct = $condensedRow['base_capacity'] > 0
                    ? round(($avgTotalPresent / $condensedRow['base_capacity']) * 100, 1)
                    : 0;

                $condensedRow['days'][] = [
                    'date' => $weekData['date'],
                    'home_count' => $avgHomeCount,
                    'visitor_count' => $avgVisitorCount,
                    'total_present' => $avgTotalPresent,
                    'utilization_pct' => $avgUtilizationPct,
                    'cell_class' => $this->getUtilizationCellClass($avgUtilizationPct),
                ];
            }

            $condensedMatrix[] = $condensedRow;
        }

        return [
            'days' => $condensedDays,
            'periodMatrix' => $condensedMatrix,
        ];
    }
}
