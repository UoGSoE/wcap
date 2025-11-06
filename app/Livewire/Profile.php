<?php

namespace App\Livewire;

use App\Enums\Location;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Profile extends Component
{
    public string $default_location = '';

    public string $default_category = '';

    // Token management
    public bool $showTokenModal = false;

    public string $newTokenName = '';

    public ?string $generatedToken = null;

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

    #[Computed]
    public function tokens()
    {
        return auth()->user()->tokens()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Determine which token abilities to assign based on user role.
     */
    public function determineTokenAbilities(): array
    {
        $user = auth()->user();

        // Start with base ability (everyone gets this)
        $abilities = ['view:own-plan'];

        // Managers get team viewing ability
        if ($user->isManager()) {
            $abilities[] = 'view:team-plans';
        }

        // Admins get all abilities
        if ($user->isAdmin()) {
            $abilities[] = 'view:all-plans';
        }

        return $abilities;
    }

    public function createToken(): void
    {
        $this->validate([
            'newTokenName' => 'required|string|max:255',
        ]);

        $abilities = $this->determineTokenAbilities();

        $token = auth()->user()->createToken($this->newTokenName, $abilities);

        $this->generatedToken = $token->plainTextToken;
        $this->newTokenName = '';

        Flux::toast(
            heading: 'Token Created',
            text: 'Your API token has been generated. Copy it now - you won\'t see it again!',
            variant: 'success'
        );
    }

    public function revokeToken(int $tokenId): void
    {
        $token = auth()->user()->tokens()->find($tokenId);

        if ($token) {
            $token->delete();

            Flux::toast(
                heading: 'Token Revoked',
                text: 'The API token has been deleted.',
                variant: 'success'
            );
        }
    }

    public function closeTokenModal(): void
    {
        $this->showTokenModal = false;
        $this->generatedToken = null;
        $this->newTokenName = '';
    }

    public function render()
    {
        return view('livewire.profile', [
            'locations' => Location::cases(),
        ]);
    }
}
