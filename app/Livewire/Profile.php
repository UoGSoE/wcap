<?php

namespace App\Livewire;

use App\Enums\Location;
use Flux\Flux;
use Livewire\Component;

class Profile extends Component
{
    public string $default_location = '';

    public string $default_category = '';

    public function mount(): void
    {
        $this->default_location = auth()->user()->default_location;
        $this->default_category = auth()->user()->default_category;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'default_location' => 'nullable|string',
            'default_category' => 'nullable|string',
        ]);

        auth()->user()->update($validated);

        Flux::toast(
            heading: 'Success!',
            text: 'Your defaults have been saved.',
            variant: 'success'
        );
    }

    public function render()
    {
        return view('livewire.profile', [
            'locations' => Location::cases(),
        ]);
    }
}
