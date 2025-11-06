<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\User;
use App\Enums\Location;
use App\Models\Service;
use Livewire\Component;
use App\Exports\ManagerReportExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\ManagerReportService;

class ManagerReport extends Component
{
    public bool $showLocation = true;

    public bool $showAllUsers = false;

    public array $selectedTeams = [];

    public function mount(): void
    {
        $user = auth()->user();

        // Check if user is a manager
        if ($user->managedTeams->isEmpty()) {
            abort(403, 'You do not manage any teams.');
        }

        if ($user->isAdmin()) {
            $this->showAllUsers = true;
        }
    }

    public function render()
    {
        $payload = $this->buildReportPayload();

        return view('livewire.manager-report', $payload);
    }

    public function exportAll()
    {
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
        $managerReportService = new ManagerReportService($this->showLocation, $this->showAllUsers, $this->selectedTeams);
        return $managerReportService->buildReportPayload();
    }
}
