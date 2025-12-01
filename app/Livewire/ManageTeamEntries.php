<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;

class ManageTeamEntries extends Component
{
    #[Url(keep: true)]
    public $selectedTeamId = 0;

    #[Url]
    public ?int $selectedUserId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->isManager()) {
            abort(403, 'You do not manage any teams.');
        }

        if ($this->selectedUserId === null) {
            $this->selectedUserId = $this->editingMyOwnPlan()
                ? $user->id
                : Team::find($this->selectedTeamId)?->users()->orderBy('surname')->first()?->id;
        }
    }

    public function updatedSelectedTeamId(): void
    {
        $this->selectedUserId = $this->editingMyOwnPlan()
            ? auth()->id()
            : Team::find($this->selectedTeamId)?->users()->orderBy('surname')->first()?->id;
    }

    public function render()
    {
        $user = auth()->user();
        $selfTeam = Team::make(['id' => 0, 'name' => 'My Plan']);
        $managedTeams = $user->managedTeams()->orderBy('name')->get()->prepend($selfTeam);
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
        if ($this->editingMyOwnPlan()) {
            return collect([auth()->user()]);
        }

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
        if ($targetUser->id === auth()->id()) {
            return true;
        }

        return auth()->user()->managedTeams()
            ->whereHas('users', fn ($q) => $q->where('users.id', $targetUser->id))
            ->exists();
    }

    private function editingMyOwnPlan(): bool
    {
        return (int) $this->selectedTeamId === 0;
    }
}
