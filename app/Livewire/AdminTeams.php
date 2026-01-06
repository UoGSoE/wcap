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

    public ?int $deletingTeamId = null;

    public ?int $transferTeamId = null;

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
        Flux::modal('team-editor')->show();
    }

    public function editTeam(int $teamId): void
    {
        $team = Team::with('users')->findOrFail($teamId);

        $this->editingTeamId = $teamId;
        $this->teamName = $team->name;
        $this->managerId = $team->manager_id;
        $this->selectedUserIds = $team->users->pluck('id')->toArray();
        Flux::modal('team-editor')->show();
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

        $team = $this->editingTeamId === -1
            ? new Team
            : Team::findOrFail($this->editingTeamId);

        $team->fill([
            'name' => $validated['teamName'],
            'manager_id' => $validated['managerId'],
        ])->save();

        $action = $team->wasRecentlyCreated ? 'created' : 'updated';
        Flux::toast(
            heading: "Team {$action}!",
            text: "The team has been {$action} successfully.",
            variant: 'success'
        );

        $team->users()->sync($validated['selectedUserIds']);

        Flux::modal('team-editor')->close();
        $this->editingTeamId = -1;
        $this->teamName = '';
        $this->managerId = null;
        $this->selectedUserIds = [];
    }

    public function confirmDelete(int $teamId): void
    {
        $this->deletingTeamId = $teamId;
        $this->transferTeamId = null;
        Flux::modal('team-delete')->show();
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

        Flux::modal('team-delete')->close();
        $this->deletingTeamId = null;
        $this->transferTeamId = null;
    }
}
