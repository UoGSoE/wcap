<?php

namespace App\Livewire;

use App\Enums\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
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
                'entries.*.id' => 'nullable|integer|exists:plan_entries,id',
                'entries.*.note' => 'nullable|string',
                'entries.*.location' => 'string',
                'entries.*.entry_date' => 'required|date',
                'entries.*.is_available' => 'required|boolean',
            ],
            [
                'entries.*.location.required' => 'Location is required when available.',
            ]
        );

        $validator->sometimes('entries.*.location', 'required', function (Fluent $input, Fluent $item) {
            return $item->is_available === true;
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
                    'location' => $entry['location'] ?: null,
                    'is_available' => $entry['is_available'],
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
            $this->entries[$dayIndex + 1]['location'] = $this->entries[$dayIndex]['location'];
            $this->entries[$dayIndex + 1]['is_available'] = $this->entries[$dayIndex]['is_available'];
        }
    }

    public function copyRest(int $dayIndex): void
    {
        if ($this->readOnly) {
            return;
        }

        $sourceNote = $this->entries[$dayIndex]['note'];
        $sourceLocation = $this->entries[$dayIndex]['location'];
        $sourceIsAvailable = $this->entries[$dayIndex]['is_available'];

        for ($i = $dayIndex + 1; $i < 14; $i++) {
            $this->entries[$i]['note'] = $sourceNote;
            $this->entries[$i]['location'] = $sourceLocation;
            $this->entries[$i]['is_available'] = $sourceIsAvailable;
        }
    }

    public function render()
    {
        return view('livewire.plan-entry-editor', [
            'days' => $this->getDays(),
            'locations' => Location::cases(),
        ]);
    }

    private function loadEntries(User $user): void
    {
        $days = $this->getDays();

        $defaultNote = $user->default_category;
        $defaultLocation = $user->default_location;

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
                'location' => $existing?->location?->value ?? $defaultLocation,
                'is_available' => $existing?->is_available ?? true,
            ];
        }
    }

    private function getDays(): array
    {
        $start = now()->startOfWeek();

        return collect(range(0, 13))->map(fn ($offset) => $start->copy()->addDays($offset))->toArray();
    }
}
