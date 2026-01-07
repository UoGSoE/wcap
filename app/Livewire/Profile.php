<?php

namespace App\Livewire;

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use Flux\Flux;
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
            return \Laravel\Sanctum\PersonalAccessToken::query()
                ->with('tokenable')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $user->tokens()
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

        // Managers get team viewing and management abilities
        if ($user->isManager()) {
            $abilities[] = 'view:team-plans';
            $abilities[] = 'manage:team-plans';
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
        $user = auth()->user();

        // Admins with toggle on can revoke any token
        if ($user->isAdmin() && $this->showAllTokens) {
            $token = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);
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
     * Get available endpoints based on token abilities.
     */
    public function getAvailableEndpoints(array $abilities): array
    {
        $endpoints = [];

        // Everyone with a token can access their own plan
        if (in_array('view:own-plan', $abilities)) {
            $endpoints[] = [
                'name' => 'Personal Plan',
                'method' => 'GET',
                'path' => '/api/v1/plan',
                'ability' => 'view:own-plan',
                'description' => 'Get your own plan entries for the next 10 weekdays',
            ];

            $endpoints[] = [
                'name' => 'Create/Update Plan Entries',
                'method' => 'POST',
                'path' => '/api/v1/plan',
                'ability' => 'view:own-plan',
                'description' => 'Create or update plan entries. Always send an entries array. Match by id or entry_date.',
            ];

            $endpoints[] = [
                'name' => 'Delete Plan Entry',
                'method' => 'DELETE',
                'path' => '/api/v1/plan/{id}',
                'ability' => 'view:own-plan',
                'description' => 'Delete a specific plan entry by id',
            ];

            $endpoints[] = [
                'name' => 'List Locations',
                'method' => 'GET',
                'path' => '/api/v1/locations',
                'ability' => 'view:own-plan',
                'description' => 'Get all available locations with their values, labels, and short labels',
            ];
        }

        // Managers and admins can access reporting endpoints
        if (in_array('view:team-plans', $abilities) || in_array('view:all-plans', $abilities)) {
            $endpoints[] = [
                'name' => 'Team Report',
                'method' => 'GET',
                'path' => '/api/v1/reports/team',
                'ability' => 'view:team-plans or view:all-plans',
                'description' => 'Get person × day grid showing team member locations and work',
            ];

            $endpoints[] = [
                'name' => 'Location Report',
                'method' => 'GET',
                'path' => '/api/v1/reports/location',
                'ability' => 'view:team-plans or view:all-plans',
                'description' => 'Get day × location grouping showing who is at each location',
            ];

            $endpoints[] = [
                'name' => 'Coverage Report',
                'method' => 'GET',
                'path' => '/api/v1/reports/coverage',
                'ability' => 'view:team-plans or view:all-plans',
                'description' => 'Get location coverage matrix with people counts per day',
            ];

            if (config('wcap.services_enabled')) {
                $endpoints[] = [
                    'name' => 'Service Availability',
                    'method' => 'GET',
                    'path' => '/api/v1/reports/service-availability',
                    'ability' => 'view:team-plans or view:all-plans',
                    'description' => 'Get service availability with manager-only indicators',
                ];
            }
        }

        // Managers and admins can manage team members' plans
        if (in_array('manage:team-plans', $abilities)) {
            $endpoints[] = [
                'name' => 'List Team Members',
                'method' => 'GET',
                'path' => '/api/v1/manager/team-members',
                'ability' => 'manage:team-plans',
                'description' => 'List users you can manage',
            ];

            $endpoints[] = [
                'name' => 'Get Team Member Plan',
                'method' => 'GET',
                'path' => '/api/v1/manager/team-members/{userId}/plan',
                'ability' => 'manage:team-plans',
                'description' => 'Get a team member\'s plan entries for the next 10 weekdays',
            ];

            $endpoints[] = [
                'name' => 'Update Team Member Plan',
                'method' => 'POST',
                'path' => '/api/v1/manager/team-members/{userId}/plan',
                'ability' => 'manage:team-plans',
                'description' => 'Create or update plan entries for a team member',
            ];

            $endpoints[] = [
                'name' => 'Delete Team Member Entry',
                'method' => 'DELETE',
                'path' => '/api/v1/manager/team-members/{userId}/plan/{entryId}',
                'ability' => 'manage:team-plans',
                'description' => 'Delete a specific plan entry for a team member',
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
