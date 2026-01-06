<div>
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="xl">Location Management</flux:heading>
            <flux:subheading>Create and manage locations where staff can be based.</flux:subheading>
        </div>
        <div>
            <flux:button variant="primary" wire:click="createLocation">
                Create New Location
            </flux:button>
        </div>
    </div>

    <flux:spacer class="mt-6"/>

    <flux:card class="mt-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Short Label</flux:table.column>
                <flux:table.column>Slug</flux:table.column>
                <flux:table.column>Physical?</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($locations as $location)
                    <flux:table.row :key="$location->id">
                        <flux:table.cell>
                            <flux:text>{{ $location->name }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ $location->short_label }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ $location->slug }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($location->isPhysical())
                                Yes
                            @else
                                No
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="flex gap-2 justify-end">
                            <flux:button size="sm" icon="pencil" wire:click="editLocation({{ $location->id }})" />
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDelete({{ $location->id }})" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <flux:text>No locations yet. Create your first location!</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Edit/Create Modal --}}
    <flux:modal wire:model="showEditModal" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingLocationId === -1 ? 'Create New Location' : 'Edit Location' }}
                </flux:heading>
            </div>

            <form wire:submit="save" class="space-y-6">
                <flux:input wire:model="locationName" placeholder="e.g., James Watt South" label="Location Name" />

                <flux:input wire:model="shortLabel" placeholder="e.g., JWS" label="Short Label" />

                <flux:checkbox wire:model="isPhysical" label="Physical Location?" description="Is this an actual building? Non-physical locations (like 'Other') won't show alerts when empty." />

                <flux:separator />

                <div class="flex gap-2">
                    <flux:button variant="primary" type="submit">
                        Save
                    </flux:button>
                    <flux:button variant="ghost" wire:click="cancelEdit">
                        Cancel
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Location</flux:heading>
                <flux:subheading class="mt-2">
                    Are you sure you want to delete this location? This action cannot be undone. Locations that are in use by plan entries or as user defaults cannot be deleted.
                </flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:button variant="danger" wire:click="deleteLocation">
                    Delete Location
                </flux:button>
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
