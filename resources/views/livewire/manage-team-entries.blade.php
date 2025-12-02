<div>
    <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex items-center gap-4">
            <flux:heading size="xl">Edit Team Plans</flux:heading>
            <flux:button href="{{ route('manager.import') }}" variant="ghost" size="sm" icon="arrow-up-tray">
                Import
            </flux:button>
        </div>
        @if ($managedTeams->count() > 1)
        <div class="w-full md:w-1/4">
            <flux:select
                wire:model.live="selectedTeamId"
                placeholder="Select a team..."
                variant="combobox"
            >
                @foreach ($managedTeams as $team)
                    <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @endif
    </div>

    <flux:spacer class="mt-6"/>

    @if ($teamMembers->isNotEmpty())
        <flux:tabs variant="segmented" size="sm" scrollable wire:model.live="selectedUserId">
            @foreach ($teamMembers as $member)
                <flux:tab :name="$member->id">{{ $member->surname }}, {{ substr($member->forenames, 0, 1) }}</flux:tab>
            @endforeach
        </flux:tabs>

        {{-- Plan entry editor for selected user --}}
        @if ($selectedUser)
            <div class="mt-6">
                <livewire:plan-entry-editor
                    :user="$selectedUser"
                    :read-only="false"
                    :created-by-manager="$selectedUser->id !== auth()->id()"
                    :key="$selectedUserId"
                />
            </div>
        @else
            <flux:callout icon="information-circle" class="mt-6">
                <flux:text>Select a team member above to edit their plan.</flux:text>
            </flux:callout>
        @endif
    @elseif ($selectedTeamId)
        <flux:callout icon="exclamation-triangle" variant="warning" class="mt-6">
            <flux:text>This team has no members.</flux:text>
        </flux:callout>
    @else
        <flux:callout icon="information-circle" class="mt-6">
            <flux:text>Select a team to get started.</flux:text>
        </flux:callout>
    @endif
</div>
