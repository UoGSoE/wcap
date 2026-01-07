<?php

namespace App\Livewire;

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PlanEntryEditor extends Component
{
    #[Locked]
    public int $userId;

    public bool $readOnly = false;

    public bool $createdByManager = false;

    public array $entries = [];

    public function mount(User $user, bool $readOnly = false, bool $createdByManager = false): void
    {
        $this->userId = $user->id;
        $this->readOnly = $readOnly;
        $this->createdByManager = $createdByManager;

        $this->loadEntries($user);
    }

    public function save(): void
    {
        if ($this->readOnly) {
            return;
        }

        $validator = Validator::make(
            ['entries' => $this->entries],
            [
                'entries.*.id' => [
                    'nullable',
                    'integer',
                    Rule::exists('plan_entries', 'id')->where('user_id', $this->userId),
                ],
                'entries.*.note' => 'nullable|string',
                'entries.*.location_id' => 'nullable|integer|exists:locations,id',
                'entries.*.entry_date' => 'required|date',
                'entries.*.availability_status' => ['required', 'integer', Rule::enum(AvailabilityStatus::class)],
            ],
            [
                'entries.*.location_id.required' => 'Location is required when available.',
            ]
        );

        $validator->sometimes('entries.*.location_id', 'required', function (Fluent $input, Fluent $item) {
            return (int) $item->availability_status > 0;
        });

        $validated = $validator->validate();

        $user = User::find($this->userId);

        foreach ($validated['entries'] as $index => $entry) {
            $savedEntry = PlanEntry::updateOrCreate(
                ['id' => $entry['id']],
                [
                    'user_id' => $user->id,
                    'entry_date' => $entry['entry_date'],
                    'note' => $entry['note'],
                    'location_id' => $entry['location_id'] ?: null,
                    'availability_status' => $entry['availability_status'],
                    'created_by_manager' => $this->createdByManager,
                ]
            );

            // Update the entry id in case it was newly created
            $this->entries[$index]['id'] = $savedEntry->id;
        }

        Flux::toast(
            heading: 'Success!',
            text: "Plan saved for {$user->full_name}.",
            variant: 'success'
        );
    }

    public function copyNext(int $dayIndex): void
    {
        if ($this->readOnly) {
            return;
        }

        if ($dayIndex < 13) {
            $this->entries[$dayIndex + 1]['note'] = $this->entries[$dayIndex]['note'];
            $this->entries[$dayIndex + 1]['location_id'] = $this->entries[$dayIndex]['location_id'];
            $this->entries[$dayIndex + 1]['availability_status'] = $this->entries[$dayIndex]['availability_status'];
        }
    }

    public function copyRest(int $dayIndex): void
    {
        if ($this->readOnly) {
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

        $defaultNote = $user->default_category;
        $defaultLocationId = $user->default_location_id;
        $defaultAvailabilityStatus = $user->default_availability_status ?? AvailabilityStatus::ONSITE;

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

            $this->entries[$index] = [
                'id' => $existing?->id,
                'entry_date' => $dateKey,
                'note' => $existing?->note ?? $defaultNote,
                'location_id' => $existing?->location_id ?? $defaultLocationId,
                'availability_status' => $existing?->availability_status->value ?? $defaultAvailabilityStatus->value,
            ];
        }
    }

    private function getDays(): array
    {
        $start = now()->startOfWeek();

        return collect(range(0, 13))->map(fn ($offset) => $start->copy()->addDays($offset))->toArray();
    }
}
