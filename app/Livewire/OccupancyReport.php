<?php

namespace App\Livewire;

use App\Exports\OccupancyReportExport;
use App\Services\OccupancyReportService;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class OccupancyReport extends Component
{
    #[Url]
    public $tab = 'today';

    #[Url]
    public $date;

    #[Url]
    public array $range = [];

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
    }

    public function render()
    {
        $snapshotDate = Carbon::parse($this->date);
        $rangeStart = Carbon::parse($this->range['start']);
        $rangeEnd = Carbon::parse($this->range['end']);

        $payload = app(OccupancyReportService::class)->buildReportPayload($snapshotDate, $rangeStart, $rangeEnd);

        return view('livewire.occupancy-report', $payload);
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
