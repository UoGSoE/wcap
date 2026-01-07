<?php

namespace App\Livewire;

use App\Services\OccupancyReportService;
use Livewire\Attributes\Url;
use Livewire\Component;

class OccupancyReport extends Component
{
    #[Url]
    public $tab = 'today';

    public function render()
    {
        $payload = app(OccupancyReportService::class)->buildReportPayload();

        return view('livewire.occupancy-report', $payload);
    }
}
