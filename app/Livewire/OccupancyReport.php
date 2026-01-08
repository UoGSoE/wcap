<?php

namespace App\Livewire;

use App\Exports\OccupancyReportExport;
use App\Models\Location;
use App\Services\OccupancyReportService;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class OccupancyReport extends Component
{
    private const CHART_COLORS = [
        '#3b82f6', // blue-500
        '#10b981', // emerald-500
        '#f59e0b', // amber-500
        '#f43f5e', // rose-500
        '#8b5cf6', // violet-500
        '#06b6d4', // cyan-500
        '#f97316', // orange-500
        '#84cc16', // lime-500
        '#ec4899', // pink-500
        '#14b8a6', // teal-500
        '#6366f1', // indigo-500
        '#eab308', // yellow-500
    ];

    #[Url]
    public $tab = 'today';

    #[Url]
    public $date;

    #[Url]
    public array $range = [];

    #[Url]
    public array $selectedLocations = [];

    public array $chartData = [];

    public function mount()
    {
        if (! $this->date) {
            $today = now();
            // Default to Monday if viewing on a weekend
            if ($today->isWeekend()) {
                $today = $today->next(Carbon::MONDAY);
            }
            $this->date = $today->toDateString();
        }

        if (empty($this->range)) {
            $this->range = [
                'start' => now()->startOfWeek()->toDateString(),
                'end' => now()->startOfWeek()->addDays(13)->toDateString(),
            ];
        }

        // Default to all locations selected
        if (empty($this->selectedLocations)) {
            $this->selectedLocations = Location::physical()
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }
    }

    public function render()
    {
        $snapshotDate = Carbon::parse($this->date);
        $rangeStart = Carbon::parse($this->range['start']);
        $rangeEnd = Carbon::parse($this->range['end']);

        $payload = app(OccupancyReportService::class)->buildReportPayload($snapshotDate, $rangeStart, $rangeEnd);

        // Build chart data for trends tab (public property so Livewire tracks changes)
        $this->chartData = $this->buildChartData($payload['days'], $payload['periodMatrix']);
        $payload['chartColors'] = $this->getChartColors($payload['physicalLocations']);

        return view('livewire.occupancy-report', $payload);
    }

    private function buildChartData(array $days, array $periodMatrix): array
    {
        $chartData = [];

        foreach ($days as $index => $day) {
            $row = [
                'date' => $day['date']->format('j M'),
            ];

            foreach ($periodMatrix as $locationRow) {
                // Use utilization percentage as decimal (0.5 = 50%) for chart formatting
                $row[$locationRow['location_name']] = $locationRow['days'][$index]['utilization_pct'] / 100;
            }

            $chartData[] = $row;
        }

        return $chartData;
    }

    private function getChartColors($locations): array
    {
        $colors = [];
        foreach ($locations->values() as $index => $location) {
            $colorIndex = $index % count(self::CHART_COLORS);
            $colors[$location->id] = [
                'name' => $location->name,
                'color' => self::CHART_COLORS[$colorIndex],
            ];
        }

        return $colors;
    }

    public function exportCurrent()
    {
        $payload = $this->buildExportPayload(skipAggregation: false);

        return $this->downloadExport($payload);
    }

    public function exportDetailed()
    {
        $payload = $this->buildExportPayload(skipAggregation: true);

        return $this->downloadExport($payload);
    }

    private function buildExportPayload(bool $skipAggregation): array
    {
        $rangeStart = Carbon::parse($this->range['start']);
        $rangeEnd = Carbon::parse($this->range['end']);

        return app(OccupancyReportService::class)->buildReportPayload(
            snapshotDate: null,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
            skipAggregation: $skipAggregation,
        );
    }

    private function downloadExport(array $payload)
    {
        $start = $payload['days'][0]['date']->format('Ymd');
        $end = end($payload['days'])['date']->format('Ymd');
        $suffix = $payload['aggregation'] === 'weekly' ? '-weekly' : '';

        return Excel::download(
            new OccupancyReportExport($payload),
            "occupancy-report-{$start}-{$end}{$suffix}.xlsx",
        );
    }
}
