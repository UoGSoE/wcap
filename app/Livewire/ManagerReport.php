<?php

namespace App\Livewire;

use App\Exports\ManagerReportExport;
use App\Models\User;
use App\Services\ManagerReportService;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

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
        return app(ManagerReportService::class)
            ->configure(
                showLocation: $this->showLocation,
                showAllUsers: $this->showAllUsers,
                selectedTeams: $this->selectedTeams,
            )
            ->buildReportPayload();
    }
}
