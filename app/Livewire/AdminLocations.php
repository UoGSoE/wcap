<?php

namespace App\Livewire;

use App\Models\Location;
use Flux\Flux;
use Livewire\Component;

class AdminLocations extends Component
{
    public ?int $editingLocationId = null;

    public string $locationName = '';

    public string $shortLabel = '';

    public bool $isPhysical = true;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public ?int $deletingLocationId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->isAdmin()) {
            abort(403, 'You must be an admin to access this page.');
        }
    }

    public function render()
    {
        $locations = Location::orderBy('name')->get();

        return view('livewire.admin-locations', [
            'locations' => $locations,
        ]);
    }

    public function createLocation(): void
    {
        $this->editingLocationId = -1;
        $this->locationName = '';
        $this->shortLabel = '';
        $this->isPhysical = true;
        $this->showEditModal = true;
    }

    public function editLocation(int $locationId): void
    {
        $location = Location::findOrFail($locationId);

        $this->editingLocationId = $locationId;
        $this->locationName = $location->name;
        $this->shortLabel = $location->short_label;
        $this->isPhysical = $location->is_physical;
        $this->showEditModal = true;
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

        if ($this->editingLocationId === -1) {
            // Generate slug from name
            $slug = \Illuminate\Support\Str::slug($validated['locationName']);

            // Ensure slug is unique
            $originalSlug = $slug;
            $counter = 1;
            while (Location::where('slug', $slug)->exists()) {
                $slug = $originalSlug.'-'.$counter;
                $counter++;
            }

            Location::create([
                'name' => $validated['locationName'],
                'short_label' => $validated['shortLabel'],
                'slug' => $slug,
                'is_physical' => $validated['isPhysical'],
            ]);

            Flux::toast(
                heading: 'Location created!',
                text: 'The location has been created successfully.',
                variant: 'success'
            );
        } else {
            $location = Location::findOrFail($this->editingLocationId);
            $location->update([
                'name' => $validated['locationName'],
                'short_label' => $validated['shortLabel'],
                'is_physical' => $validated['isPhysical'],
            ]);

            Flux::toast(
                heading: 'Location updated!',
                text: 'The location has been updated successfully.',
                variant: 'success'
            );
        }

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->showEditModal = false;
        $this->editingLocationId = null;
        $this->locationName = '';
        $this->shortLabel = '';
        $this->isPhysical = true;
    }

    public function confirmDelete(int $locationId): void
    {
        $this->deletingLocationId = $locationId;
        $this->showDeleteModal = true;
    }

    public function deleteLocation(): void
    {
        $location = Location::findOrFail($this->deletingLocationId);

        // Check if location is in use
        $usageCount = $location->planEntries()->count() + $location->usersWithDefault()->count();

        if ($usageCount > 0) {
            Flux::toast(
                heading: 'Cannot delete location',
                text: 'This location is in use by plan entries or as a user default.',
                variant: 'danger'
            );
            $this->closeDeleteModal();

            return;
        }

        $location->delete();

        Flux::toast(
            heading: 'Location deleted!',
            text: 'The location has been deleted successfully.',
            variant: 'success'
        );

        $this->closeDeleteModal();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingLocationId = null;
    }
}
