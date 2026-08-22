<?php

namespace App\Livewire;

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PlanEntryEditor extends Component
{
    #[Locked]
    public int $userId;

    public bool $readOnly = false;

    public bool $createdByManager = false;

    public ?string $startDate = null;

    public array $entries = [];

    public function mount(User $user, bool $readOnly = false, bool $createdByManager = false, ?string $startDate = null): void
    {
        $this->userId = $user->id;
        $this->readOnly = $readOnly;
        $this->createdByManager = $createdByManager;
        $this->startDate = $startDate;

        $this->loadEntries($user);
    }

    public function updated(string $name): void
    {
        if ($this->readOnly) {
            return;
        }

        if (! preg_match('/^entries\.(\d+)\./', $name, $matches)) {
            return;
        }

        $this->saveRow((int) $matches[1]);
    }

    public function copyNext(int $dayIndex): void
    {
        if ($this->readOnly) {
            return;
        }

        if (! $this->isRowSavable($dayIndex)) {
            return;
        }

        if ($dayIndex < 13) {
            $this->entries[$dayIndex + 1]['note'] = $this->entries[$dayIndex]['note'];
            $this->entries[$dayIndex + 1]['location_id'] = $this->entries[$dayIndex]['location_id'];
            $this->entries[$dayIndex + 1]['availability_status'] = $this->entries[$dayIndex]['availability_status'];

            $this->saveRow($dayIndex + 1);
        }
    }

    public function copyRest(int $dayIndex): void
    {
        if ($this->readOnly) {
            return;
        }

        if (! $this->isRowSavable($dayIndex)) {
            return;
        }

        $sourceNote = $this->entries[$dayIndex]['note'];
        $sourceLocationId = $this->entries[$dayIndex]['location_id'];
        $sourceAvailabilityStatus = $this->entries[$dayIndex]['availability_status'];

        for ($i = $dayIndex + 1; $i < 14; $i++) {
            $this->entries[$i]['note'] = $sourceNote;
            $this->entries[$i]['location_id'] = $sourceLocationId;
            $this->entries[$i]['availability_status'] = $sourceAvailabilityStatus;
        }

        for ($i = $dayIndex + 1; $i < 14; $i++) {
            $this->saveRow($i);
        }
    }

    public function fillFromDefaults(): void
    {
        if ($this->readOnly) {
            return;
        }

        $user = User::findOrFail($this->userId);

        $filledCount = $user->fillPlanFromDefaults(
            Carbon::parse($this->entries[0]['entry_date']),
            createdByManager: $this->createdByManager,
        );

        $this->loadEntries($user);

        Flux::toast(
            heading: 'Fill from defaults',
            text: $this->fillFromDefaultsMessage($user, $filledCount),
            variant: $filledCount > 0 ? 'success' : 'warning',
        );
    }

    private function fillFromDefaultsMessage(User $user, int $filledCount): string
    {
        if ($filledCount > 0) {
            return "Filled {$filledCount} days from defaults";
        }

        if ($user->hasUsableDefaults()) {
            return 'Nothing to fill - all days already planned';
        }

        return 'No defaults set - nothing to fill';
    }

    public function isRowSavable(int $index): bool
    {
        $row = $this->entries[$index] ?? null;

        if (! $row || blank($row['availability_status'])) {
            return false;
        }

        $status = AvailabilityStatus::from((int) $row['availability_status']);

        return ! $status->isAvailable() || $row['location_id'];
    }

    private function saveRow(int $index): void
    {
        if (! $this->isRowSavable($index)) {
            return;
        }

        $row = $this->entries[$index];
        $rowKey = "entries.{$index}";

        $rules = [
            "{$rowKey}.id" => [
                'nullable',
                'integer',
                Rule::exists('plan_entries', 'id')->where('user_id', $this->userId),
            ],
            "{$rowKey}.note" => 'nullable|string',
            "{$rowKey}.entry_date" => 'required|date',
            "{$rowKey}.availability_status" => ['required', 'integer', Rule::enum(AvailabilityStatus::class)],
            "{$rowKey}.location_id" => [
                (int) ($row['availability_status'] ?? 0) > 0 ? 'required' : 'nullable',
                'integer',
                'exists:locations,id',
            ],
        ];

        $messages = [
            "{$rowKey}.location_id.required" => 'Location is required when available.',
        ];

        $this->validate($rules, $messages);

        $savedEntry = PlanEntry::updateOrCreate(
            ['id' => $row['id']],
            [
                'user_id' => $this->userId,
                'entry_date' => $row['entry_date'],
                'note' => $row['note'],
                'location_id' => $row['location_id'] ?: null,
                'availability_status' => $row['availability_status'],
                'created_by_manager' => $this->createdByManager,
            ]
        );

        $this->entries[$index]['id'] = $savedEntry->id;

        $this->dispatch('plan-entry-saved');
    }

    public function render()
    {
        return view('livewire.plan-entry-editor', [
            'days' => $this->getDays(),
            'locations' => Location::orderBy('name')->get(),
            'availabilityStatuses' => AvailabilityStatus::cases(),
        ]);
    }

    private function loadEntries(User $user): void
    {
        $days = $this->getDays();

        $existingEntries = $user->planEntries()
            ->whereBetween('entry_date', [
                $days[0]->format('Y-m-d'),
                $days[13]->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(fn ($entry) => $entry->entry_date->format('Y-m-d'));

        foreach ($days as $index => $day) {
            $dateKey = $day->format('Y-m-d');
            $existing = $existingEntries->get($dateKey);

            // Selects bind '' rather than null so the placeholder option
            // survives Livewire's client-side value sync.
            $this->entries[$index] = [
                'id' => $existing?->id,
                'entry_date' => $dateKey,
                'note' => $existing?->note,
                'location_id' => $existing?->location_id ?? '',
                'availability_status' => $existing?->availability_status->value ?? '',
            ];
        }
    }

    private function getDays(): array
    {
        $start = $this->startDate
            ? Carbon::parse($this->startDate)->startOfWeek()
            : now()->startOfWeek();

        return collect(range(0, 13))->map(fn ($offset) => $start->copy()->addDays($offset))->toArray();
    }
}
