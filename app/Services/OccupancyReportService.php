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

    public function buildReportPayload(): array
    {
        $days = $this->buildDays();
        $physicalLocations = Location::physical()->orderBy('name')->get();

        $this->loadData($days);

        return [
            'days' => $days,
            'snapshotDate' => $this->getSnapshotDate(),
            'daySnapshot' => $this->buildDaySnapshot(),
            'periodMatrix' => $this->buildPeriodMatrix($days, $physicalLocations),
            'summaryStats' => $this->buildSummaryStats($days, $physicalLocations),
            'physicalLocations' => $physicalLocations,
        ];
    }

    public function buildDays(): array
    {
        $start = now()->startOfWeek();
        $days = [];

        for ($offset = 0; $offset < 14; $offset++) {
            $day = $start->copy()->addDays($offset);

            if ($day->isWeekday()) {
                $days[] = [
                    'date' => $day,
                    'key' => $day->toDateString(),
                ];
            }
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
            $utilizationPct = $baseCapacity > 0 ? round(($homeCount / $baseCapacity) * 100, 1) : 0;

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
                $utilizationPct = $baseCapacity > 0 ? round(($homeCount / $baseCapacity) * 100, 1) : 0;

                $row['days'][] = [
                    'date' => $day['date'],
                    'home_count' => $homeCount,
                    'visitor_count' => $visitorCount,
                    'total_present' => $totalPresent,
                    'utilization_pct' => $utilizationPct,
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
                'mean_occupancy' => round($meanOccupancy, 1),
                'median_occupancy' => $medianOccupancy,
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
}
