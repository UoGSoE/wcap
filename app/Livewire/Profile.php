<?php

namespace App\Livewire;

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use Flux\Flux;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Profile extends Component
{
    public $default_location_id = null;

    public string $default_category = '';

    public $default_availability_status = null;

    public string $newTokenName = '';

    public ?string $generatedToken = null;

    public bool $showAllTokens = false;

    public ?int $selectedTokenId = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->default_location_id = $user->default_location_id;
        $this->default_category = $user->default_category;
        $this->default_availability_status = $user->default_availability_status?->value ?? AvailabilityStatus::ONSITE->value;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'default_location_id' => 'nullable|integer|exists:locations,id',
            'default_category' => 'nullable|string',
            'default_availability_status' => 'nullable|integer',
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
        $user = auth()->user();

        if ($user->isAdmin() && $this->showAllTokens) {
            return PersonalAccessToken::query()
                ->with('tokenable')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $user->tokens()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createToken(): void
    {
        $this->validate([
            'newTokenName' => 'required|string|max:255',
        ]);

        // Tokens grant whatever access the user's role allows — the API gates
        // on role now, not on token abilities. Sanctum's default ['*'] is fine.
        $token = auth()->user()->createToken($this->newTokenName);

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
        $user = auth()->user();

        // Admins with toggle on can revoke any token
        if ($user->isAdmin() && $this->showAllTokens) {
            $token = PersonalAccessToken::find($tokenId);
        } else {
            $token = $user->tokens()->find($tokenId);
        }

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
        Flux::modal('create-token')->close();
        $this->generatedToken = null;
        $this->newTokenName = '';
    }

    public function selectToken(int $tokenId): void
    {
        // Toggle: if clicking the same token, deselect it
        if ($this->selectedTokenId === $tokenId) {
            $this->selectedTokenId = null;
        } else {
            $this->selectedTokenId = $tokenId;
        }
    }

    #[Computed]
    public function selectedToken()
    {
        if (! $this->selectedTokenId) {
            return null;
        }

        return $this->tokens->firstWhere('id', $this->selectedTokenId);
    }

    #[Computed]
    public function baseUrl(): string
    {
        return url('/');
    }

    /**
     * Endpoints the current user can hit with their API token.
     *
     * Access is role-based now (see `accessManagerApi` gate), so this UI only
     * runs inside the `@adminOrManager` Blade guard — every viewer gets every
     * endpoint.
     *
     * @return array<int, array{name: string, method: string, path: string, description: string}>
     */
    public function getAvailableEndpoints(): array
    {
        $endpoints = [
            [
                'name' => 'Personal Plan',
                'method' => 'GET',
                'path' => '/api/v1/plan',
                'description' => 'Get your own plan entries for the next 10 weekdays',
            ],
            [
                'name' => 'Create/Update Plan Entries',
                'method' => 'POST',
                'path' => '/api/v1/plan',
                'description' => 'Create or update plan entries. Always send an entries array. Match by id or entry_date.',
            ],
            [
                'name' => 'Delete Plan Entry',
                'method' => 'DELETE',
                'path' => '/api/v1/plan/{id}',
                'description' => 'Delete a specific plan entry by id',
            ],
            [
                'name' => 'List Locations',
                'method' => 'GET',
                'path' => '/api/v1/locations',
                'description' => 'Get all available locations with their values, labels, and short labels',
            ],
            [
                'name' => 'Team Report',
                'method' => 'GET',
                'path' => '/api/v1/reports/team',
                'description' => 'Get person × day grid showing team member locations and work',
            ],
            [
                'name' => 'Location Report',
                'method' => 'GET',
                'path' => '/api/v1/reports/location',
                'description' => 'Get day × location grouping showing who is at each location',
            ],
            [
                'name' => 'Coverage Report',
                'method' => 'GET',
                'path' => '/api/v1/reports/coverage',
                'description' => 'Get location coverage matrix with people counts per day',
            ],
            [
                'name' => 'List Team Members',
                'method' => 'GET',
                'path' => '/api/v1/manager/team-members',
                'description' => 'List users you can manage',
            ],
            [
                'name' => 'Get Team Member Plan',
                'method' => 'GET',
                'path' => '/api/v1/manager/team-members/{userId}/plan',
                'description' => 'Get a team member\'s plan entries for the next 10 weekdays',
            ],
            [
                'name' => 'Update Team Member Plan',
                'method' => 'POST',
                'path' => '/api/v1/manager/team-members/{userId}/plan',
                'description' => 'Create or update plan entries for a team member',
            ],
            [
                'name' => 'Delete Team Member Entry',
                'method' => 'DELETE',
                'path' => '/api/v1/manager/team-members/{userId}/plan/{entryId}',
                'description' => 'Delete a specific plan entry for a team member',
            ],
        ];

        if (config('wcap.services_enabled')) {
            $endpoints[] = [
                'name' => 'Service Availability',
                'method' => 'GET',
                'path' => '/api/v1/reports/service-availability',
                'description' => 'Get service availability with manager-only indicators',
            ];
        }

        return $endpoints;
    }

    public function render()
    {
        return view('livewire.profile', [
            'locations' => Location::orderBy('name')->get(),
            'availabilityStatuses' => AvailabilityStatus::cases(),
        ]);
    }
}
