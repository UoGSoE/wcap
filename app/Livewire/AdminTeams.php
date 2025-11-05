<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Livewire\Component;

class AdminTeams extends Component
{
    public ?int $editingTeamId = null;

    public string $teamName = '';

    public ?int $managerId = null;

    public array $selectedUserIds = [];

    public bool $showDeleteModal = false;

    public ?int $deletingTeamId = null;

    public ?int $transferTeamId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->isAdmin()) {
            abort(403, 'You must be an admin to access this page.');
        }
    }

    public function render()
    {
        $teams = Team::with(['manager', 'users'])->orderBy('name')->get();
        $users = User::orderBy('surname')->orderBy('forenames')->get();

        return view('livewire.admin-teams', [
            'teams' => $teams,
            'users' => $users,
        ]);
    }

    public function createTeam(): void
    {
        $this->editingTeamId = -1;
        $this->teamName = '';
        $this->managerId = null;
        $this->selectedUserIds = [];
    }

    public function editTeam(int $teamId): void
    {
        $team = Team::with('users')->findOrFail($teamId);

        $this->editingTeamId = $teamId;
        $this->teamName = $team->name;
        $this->managerId = $team->manager_id;
        $this->selectedUserIds = $team->users->pluck('id')->toArray();
    }

    public function save(): void
    {
        $uniqueRule = $this->editingTeamId === -1
            ? 'unique:teams,name'
            : 'unique:teams,name,'.$this->editingTeamId;

        $validated = $this->validate([
            'teamName' => 'required|string|max:255|'.$uniqueRule,
            'managerId' => 'required|integer|exists:users,id',
            'selectedUserIds' => 'array',
            'selectedUserIds.*' => 'integer|exists:users,id',
        ], [
            'teamName.required' => 'Team name is required.',
            'teamName.unique' => 'A team with this name already exists.',
            'managerId.required' => 'Manager is required.',
        ]);

        if ($this->editingTeamId === -1) {
            $team = Team::create([
                'name' => $validated['teamName'],
                'manager_id' => $validated['managerId'],
            ]);

            Flux::toast(
                heading: 'Team created!',
                text: 'The team has been created successfully.',
                variant: 'success'
            );
        } else {
            $team = Team::findOrFail($this->editingTeamId);
            $team->update([
                'name' => $validated['teamName'],
                'manager_id' => $validated['managerId'],
            ]);

            Flux::toast(
                heading: 'Team updated!',
                text: 'The team has been updated successfully.',
                variant: 'success'
            );
        }

        $team->users()->sync($validated['selectedUserIds']);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->editingTeamId = null;
        $this->teamName = '';
        $this->managerId = null;
        $this->selectedUserIds = [];
    }

    public function confirmDelete(int $teamId): void
    {
        $this->deletingTeamId = $teamId;
        $this->transferTeamId = null;
        $this->showDeleteModal = true;
    }

    public function deleteTeam(): void
    {
        $validated = $this->validate([
            'transferTeamId' => 'nullable|integer|exists:teams,id',
        ]);

        $team = Team::with('users')->findOrFail($this->deletingTeamId);

        if ($validated['transferTeamId']) {
            $transferTeam = Team::findOrFail($validated['transferTeamId']);
            $memberIds = $team->users->pluck('id')->toArray();
            $transferTeam->users()->syncWithoutDetaching($memberIds);
        }

        $team->users()->detach();
        $team->delete();

        Flux::toast(
            heading: 'Team deleted!',
            text: 'The team has been deleted successfully.',
            variant: 'success'
        );

        $this->showDeleteModal = false;
        $this->deletingTeamId = null;
        $this->transferTeamId = null;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingTeamId = null;
        $this->transferTeamId = null;
    }
}
