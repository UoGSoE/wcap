<?php

namespace App\Livewire;

use App\Exports\ManagerReportExport;
use App\Services\ManagerReportService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class ManagerReport extends Component
{
    #[Url]
    public $tab = 'team';

    public bool $showLocation = true;

    #[Url]
    public array $selectedTeams = [];

    public function render()
    {
        $payload = $this->buildReportPayload();

        return view('livewire.manager-report', $payload);
    }

    public function exportAll()
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isManager(), 403);

        $payload = $this->buildReportPayload();

        $start = $payload['days'][0]['date']->format('Ymd');
        $end = end($payload['days'])['date']->format('Ymd');

        return Excel::download(
            new ManagerReportExport($payload),
            "manager-report-{$start}-{$end}.xlsx",
        );
    }

    private function buildReportPayload(): array
    {
        return app(ManagerReportService::class)
            ->configure(
                showLocation: $this->showLocation,
                selectedTeams: $this->selectedTeams,
            )
            ->buildReportPayload();
    }
}
