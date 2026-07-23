<?php

namespace App\Livewire;

use App\Exports\ManagerReportExport;
use App\Services\ManagerReportService;
use Carbon\Carbon;
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

    #[Url]
    public $weekStart = null;

    #[Url]
    public $range = 'fortnight';

    public function render()
    {
        $payload = $this->buildReportPayload();
        $payload['resolvedWeekStart'] = $this->resolvedWeekStart();
        $payload['isCurrentWeek'] = $this->resolvedWeekStart()->equalTo(now()->startOfWeek());

        return view('livewire.manager-report', $payload);
    }

    public function updatedWeekStart(): void
    {
        $this->weekStart = $this->resolvedWeekStart()->toDateString();
    }

    public function goToToday(): void
    {
        $this->weekStart = null;
    }

    public function resolvedWeekStart(): Carbon
    {
        if (! $this->weekStart) {
            return now()->startOfWeek();
        }

        return Carbon::parse($this->weekStart)->startOfWeek();
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
        $start = $this->resolvedWeekStart();
        $daysToShow = $this->range === 'month' ? 28 : 14;

        return app(ManagerReportService::class)
            ->configure(
                showLocation: $this->showLocation,
                selectedTeams: $this->selectedTeams,
                from: $start->toDateString(),
                to: $start->copy()->addDays($daysToShow - 1)->toDateString(),
            )
            ->buildReportPayload();
    }
}
