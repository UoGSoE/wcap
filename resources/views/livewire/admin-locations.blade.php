<div>
    <div class="flex justify-between items-center">
        <div>
        <flux:heading size="xl">Location Management</flux:heading>
        <flux:subheading>Create and manage locations (buildings) where staff can be based.</flux:subheading>
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
                            <flux:text class="font-medium">{{ $location->name }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ $location->short_label }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-zinc-500">{{ $location->slug }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($location->is_physical)
                                <flux:badge color="green" size="sm">Yes</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">No</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="flex gap-2 justify-end">
                            <flux:button size="sm" icon="pencil" wire:click="editLocation({{ $location->id }})">
                            </flux:button>
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDelete({{ $location->id }})">
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <flux:text class="text-center text-zinc-500">No locations yet. Create your first location!</flux:text>
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

            <form wire:submit="save">
                <flux:field>
                    <flux:label>Location Name</flux:label>
                    <flux:description>The full name of this location/building.</flux:description>
                    <flux:input wire:model="locationName" placeholder="e.g., James Watt South" />
                </flux:field>

                <flux:field>
                    <flux:label>Short Label</flux:label>
                    <flux:description>A short abbreviation for reports and compact views.</flux:description>
                    <flux:input wire:model="shortLabel" placeholder="e.g., JWS" />
                </flux:field>

                <flux:field>
                    <flux:label>Physical Location</flux:label>
                    <flux:description>Is this an actual building? Non-physical locations (like "Other") won't show alerts when empty.</flux:description>
                    <flux:switch wire:model="isPhysical" />
                </flux:field>

                <div class="flex gap-2">
                    <flux:button variant="primary" type="submit">
                        <span wire:loading.remove>{{ $editingLocationId === -1 ? 'Create Location' : 'Save Changes' }}</span>
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
                <flux:text class="mt-2">
                    Are you sure you want to delete this location? This action cannot be undone. Locations that are in use by plan entries or as user defaults cannot be deleted.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:button variant="danger" wire:click="deleteLocation" wire:loading.attr="disabled">
                    <span wire:loading.remove>Delete Location</span>
                    <span wire:loading>Deleting...</span>
                </flux:button>
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
