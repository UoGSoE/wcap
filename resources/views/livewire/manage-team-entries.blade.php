<div>
    <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex items-center gap-4">
            <flux:heading size="xl">Edit Team Plans</flux:heading>
            <flux:button href="{{ route('manager.import') }}" variant="ghost" size="sm" icon="arrow-up-tray">
                Import
            </flux:button>
            @if ($selectedTeamId > 0)
                <flux:button wire:click="export" variant="ghost" size="sm" icon="arrow-down-tray" class="cursor-pointer">
                    Export
                </flux:button>
            @endif
        </div>
        @if ($managedTeams->count() > 1)
        <div class="w-full md:w-1/4">
            <flux:select
                wire:model.live="selectedTeamId"
                placeholder="Select a team..."
                variant="listbox"
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
                <div class="flex justify-between items-center mb-4">
                    <flux:heading size="lg">{{ $selectedUser->full_name }}</flux:heading>
                    <flux:button
                        wire:click="openEditDefaults({{ $selectedUser->id }})"
                        variant="ghost"
                        size="sm"
                        icon="cog-6-tooth"
                        class="cursor-pointer"
                    >
                        Edit {{ $selectedUser->forenames }}'s Defaults
                    </flux:button>
                </div>
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

    {{-- Edit Defaults Modal --}}
    <flux:modal name="edit-defaults" variant="flyout">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Edit Defaults</flux:heading>
                @if ($editingUser)
                    <flux:text class="mt-2">Set default values for {{ $editingUser->forenames }} {{ $editingUser->surname }}.</flux:text>
                @endif
            </div>

            <flux:field>
                <flux:label>Default Location</flux:label>
                <flux:description>This location will be pre-filled for new entries.</flux:description>
                <flux:select placeholder="Select a default location..." wire:model="default_location_id" :key="'location-select-' . $editingDefaultsForUserId">
                    @foreach ($locations as $location)
                        <flux:select.option value="{{ $location->id }}">{{ $location->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Default Availability Status</flux:label>
                <flux:description>Typical working arrangement for new entries.</flux:description>
                <flux:select wire:model="default_availability_status">
                    @foreach ($availabilityStatuses as $status)
                        <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Default Work Category</flux:label>
                <flux:description>What do they typically work on?</flux:description>
                <flux:input wire:model="default_category" placeholder="e.g., Active Directory, Support Tickets..." />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button wire:click="closeEditDefaults" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="saveDefaults" variant="primary">Save Defaults</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
