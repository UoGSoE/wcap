<div>
    <div class="max-w-2xl">
        <flux:heading size="xl">Profile Settings</flux:heading>
        <flux:subheading>Set your default location and work category to save time when planning your week.</flux:subheading>

        <flux:spacer class="mt-6" />

        <flux:card>
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Default Location</flux:label>
                    <flux:description>This location will be pre-filled for new entries.</flux:description>
                    <flux:select variant="combobox" placeholder="Select a default location..." wire:model.live="default_location">
                        @foreach ($locations as $location)
                            <flux:select.option value="{{ $location->value }}">{{ $location->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Default Work Category</flux:label>
                    <flux:description>What do you typically work on? (e.g., "Active Directory", "Support Tickets")</flux:description>
                    <flux:input wire:model.live="default_category" placeholder="e.g., Active Directory, Support Tickets..." />
                </flux:field>

                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>Save Defaults</span>
                    <span wire:loading>Saving...</span>
                </flux:button>
            </div>
        </flux:card>
    </div>
</div>
