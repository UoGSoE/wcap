<?php

namespace App\Livewire;

use App\Enums\Location;
use App\Models\PlanEntry;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Livewire\Component;

class HomePage extends Component
{
    public array $entries = [];

    public function mount(): void
    {
        $user = auth()->user();
        $days = $this->getDays();

        $defaultNote = $user->default_category;
        $defaultLocation = $user->default_location;

        // Load existing entries for the 14-day period
        $existingEntries = $user->planEntries()
            ->whereBetween('entry_date', [
                $days[0]->format('Y-m-d'),
                $days[13]->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(fn ($entry) => $entry->entry_date->format('Y-m-d'));

        // Initialize entries array for all 14 days
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

    public function save(): void
    {
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
                'entries.*.location.required' => 'Location is required when you are available.',
            ]
        );

        $validator->sometimes('entries.*.location', 'required', function (Fluent $input, Fluent $item) {
            return $item->is_available === true;
        });

        $validated = $validator->validate();

        $user = auth()->user();

        foreach ($validated['entries'] as $entry) {
            PlanEntry::updateOrCreate(
                ['id' => $entry['id']],
                [
                    'user_id' => $user->id,
                    'entry_date' => $entry['entry_date'],
                    'note' => $entry['note'],
                    'location' => $entry['location'] ?: null,
                    'is_available' => $entry['is_available'],
                ]
            );
        }

        Flux::toast(
            heading: 'Success!',
            text: 'Your plan has been saved successfully.',
            variant: 'success'
        );
    }

    public function copyNext(int $dayIndex): void
    {
        if ($dayIndex < 13) {
            $this->entries[$dayIndex + 1]['note'] = $this->entries[$dayIndex]['note'];
            $this->entries[$dayIndex + 1]['location'] = $this->entries[$dayIndex]['location'];
            $this->entries[$dayIndex + 1]['is_available'] = $this->entries[$dayIndex]['is_available'];
        }
    }

    public function copyRest(int $dayIndex): void
    {
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
        return view('livewire.home-page', [
            'days' => $this->getDays(),
            'locations' => Location::cases(),
        ]);
    }

    private function getDays(): array
    {
        $start = now()->startOfWeek();

        return collect(range(0, 13))->map(fn ($offset) => $start->copy()->addDays($offset))->toArray();
    }
}
