<?php

namespace App\Livewire;

use App\Services\OccupancyReportService;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

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
}
