<div>
    <div class="flex justify-between items-center">
        <flux:heading size="xl">Edit Team Plans</flux:heading>
        <flux:button size="sm" variant="ghost" href="{{ route('manager.report') }}" wire:navigate icon="arrow-left">Back to Report</flux:button>
    </div>

    <flux:spacer class="mt-6"/>

    {{-- Team selector (only shown if manager has multiple teams) --}}
    @if ($managedTeams->count() > 1)
        <div class="mb-6">
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

    {{-- Team member tabs --}}
    @if ($teamMembers->isNotEmpty())
        <flux:tabs variant="segmented" size="sm" scrollable wire:model.live="selectedUserId">
            @foreach ($teamMembers as $member)
                <flux:tab :name="$member->id">{{ $member->surname }}, {{ substr($member->forenames, 0, 1) }}</flux:tab>
            @endforeach
        </flux:tabs>

        {{-- Plan entry editor for selected user --}}
        @if ($selectedUser)
            <div class="mt-6">
                <flux:callout icon="user" class="mb-4">
                    <flux:text>Editing plan for <strong>{{ $selectedUser->full_name }}</strong></flux:text>
                </flux:callout>

                <livewire:plan-entry-editor
                    :user="$selectedUser"
                    :read-only="false"
                    :created-by-manager="true"
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
