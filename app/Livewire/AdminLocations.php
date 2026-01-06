<?php

namespace App\Livewire;

use Flux\Flux;
use Livewire\Component;
use App\Models\Location;
use Illuminate\Support\Str;

class AdminLocations extends Component
{
    public ?int $editingLocationId = null;

    public string $locationName = '';

    public string $shortLabel = '';

    public bool $isPhysical = true;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public ?int $deletingLocationId = null;

    public ?int $replacementLocationId = null;

    public int $planEntryCount = 0;

    public int $userDefaultCount = 0;

    public function render()
    {
        $locations = Location::orderBy('name')->get();

        $replacementLocations = $this->deletingLocationId
            ? $locations->where('id', '!=', $this->deletingLocationId)
            : collect();

        return view('livewire.admin-locations', [
            'locations' => $locations,
            'replacementLocations' => $replacementLocations,
        ]);
    }

    public function createLocation(): void
    {
        $this->editingLocationId = -1;
        $this->locationName = '';
        $this->shortLabel = '';
        $this->isPhysical = true;
        Flux::modal('location-editor')->show();
    }

    public function editLocation(int $locationId): void
    {
        $location = Location::findOrFail($locationId);

        $this->editingLocationId = $locationId;
        $this->locationName = $location->name;
        $this->shortLabel = $location->short_label;
        $this->isPhysical = $location->is_physical;
        Flux::modal('location-editor')->show();
    }

    public function save(): void
    {
        $uniqueNameRule = $this->editingLocationId === -1
            ? 'unique:locations,name'
            : 'unique:locations,name,'.$this->editingLocationId;

        $validated = $this->validate([
            'locationName' => 'required|string|max:255|'.$uniqueNameRule,
            'shortLabel' => 'required|string|max:20',
            'isPhysical' => 'boolean',
        ]);

        $slug = Str::slug($validated['locationName']);
        $originalSlug = $slug;

        $counter = 1;
        while (Location::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        if ($this->editingLocationId === -1) {
            $location = new Location();
        } else {
            $location = Location::findOrFail($this->editingLocationId);
        }
        $location->name = $validated['locationName'];
        $location->short_label = $validated['shortLabel'];
        $location->slug = $slug;
        $location->is_physical = $validated['isPhysical'];
        $location->save();

        Flux::toast(
            heading: 'Location saved!',
            text: 'The location has been saved successfully.',
            variant: 'success'
        );

        Flux::modal('location-editor')->close();
        $this->editingLocationId = -1;
        $this->locationName = '';
        $this->shortLabel = '';
        $this->isPhysical = true;
    }

    public function confirmDelete(int $locationId): void
    {
        $location = Location::findOrFail($locationId);

        $this->deletingLocationId = $locationId;
        $this->planEntryCount = $location->planEntries()->count();
        $this->userDefaultCount = $location->usersWithDefault()->count();

        // Pre-select first available replacement location
        $firstReplacement = Location::where('id', '!=', $locationId)->orderBy('name')->first();
        $this->replacementLocationId = $firstReplacement?->id;

        Flux::modal('location-delete')->show();
    }

    public function deleteLocation(): void
    {
        $location = Location::findOrFail($this->deletingLocationId);

        $usageCount = $location->planEntries()->count() + $location->usersWithDefault()->count();

        // If location is in use, we need a replacement location selected
        if ($usageCount > 0) {
            $this->validate([
                'replacementLocationId' => 'required|exists:locations,id',
            ]);

            $location->planEntries()->update(['location_id' => $this->replacementLocationId]);
            $location->usersWithDefault()->update(['default_location_id' => $this->replacementLocationId]);
        }

        $location->delete();

        Flux::toast(
            text: 'Location deleted!',
            variant: 'success'
        );

        Flux::modal('location-delete')->close();
    }
}
