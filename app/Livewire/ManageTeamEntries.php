<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;

class ManageTeamEntries extends Component
{
    #[Url]
    public ?int $selectedTeamId = null;

    #[Url]
    public ?int $selectedUserId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->isManager()) {
            abort(403, 'You do not manage any teams.');
        }

        if ($this->selectedTeamId === null) {
            $this->selectedTeamId = $user->managedTeams()->orderBy('name')->first()->id;
        }
        if ($this->selectedUserId === null) {
            $this->selectedUserId = Team::find($this->selectedTeamId)->users()->orderBy('surname')->first()->id;
        }
    }

    public function updatedSelectedTeamId(): void
    {
        $this->selectedUserId = Team::find($this->selectedTeamId)->users()->orderBy('surname')->first()->id;
    }

    public function render()
    {
        $user = auth()->user();
        $managedTeams = $user->managedTeams()->orderBy('name')->get();
        $teamMembers = $this->getTeamMembers();
        $selectedUser = $this->getSelectedUser();

        return view('livewire.manage-team-entries', [
            'managedTeams' => $managedTeams,
            'teamMembers' => $teamMembers,
            'selectedUser' => $selectedUser,
        ]);
    }

    private function getTeamMembers()
    {
        if (! $this->selectedTeamId) {
            return collect();
        }

        $team = Team::find($this->selectedTeamId);

        if (! $team || ! $this->canManageTeam($team)) {
            return collect();
        }

        return $team->users()->orderBy('surname')->get();
    }

    private function getSelectedUser(): ?User
    {
        if (! $this->selectedUserId) {
            return null;
        }

        $user = User::find($this->selectedUserId);

        if (! $user || ! $this->canManageUser($user)) {
            $this->selectedUserId = null;

            return null;
        }

        return $user;
    }

    private function canManageTeam(Team $team): bool
    {
        return auth()->user()->managedTeams()->where('teams.id', $team->id)->exists();
    }

    private function canManageUser(User $targetUser): bool
    {
        return auth()->user()->managedTeams()
            ->whereHas('users', fn ($q) => $q->where('users.id', $targetUser->id))
            ->exists();
    }
}
