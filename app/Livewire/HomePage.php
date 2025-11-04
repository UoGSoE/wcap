<?php

namespace App\Livewire;

use App\Enums\Category;
use App\Enums\Location;
use Livewire\Component;

class HomePage extends Component
{
    public function render()
    {
        return view('livewire.home-page', [
            'days' => $this->getDays(),
            'categories' => Category::cases(),
            'locations' => Location::cases(),
        ]);
    }

    private function getDays(): array
    {
        $start = now()->startOfWeek();
        return collect(range(0, 13))->map(fn ($offset) => $start->copy()->addDays($offset))->toArray();
    }

}
