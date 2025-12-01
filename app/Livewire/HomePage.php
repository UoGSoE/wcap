<?php

namespace App\Livewire;

use Livewire\Component;

class HomePage extends Component
{
    public function mount(): void
    {
        $user = auth()->user();

        // Managers are redirected to the manager report
        if ($user->isManager()) {
            $this->redirect(route('manager.report'));
        }
    }

    public function render()
    {
        return view('livewire.home-page', [
            'user' => auth()->user(),
        ]);
    }
}
