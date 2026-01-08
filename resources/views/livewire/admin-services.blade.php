<div>
    <div class="flex justify-between items-center">
        <div>
        <flux:heading size="xl">Service Management</flux:heading>
        <flux:subheading>Create and manage services, assign members, and set managers.</flux:subheading>
        </div>
        <div>
            <flux:button variant="primary" wire:click="createService">
                Create New Service
            </flux:button>
        </div>
    </div>

    <flux:spacer class="mt-6"/>

    <flux:card class="mt-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Service Name</flux:table.column>
                <flux:table.column>Manager</flux:table.column>
                <flux:table.column>Members</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($services as $service)
                    <flux:table.row :key="$service->id">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $service->name }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ $service->manager->full_name }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ $service->users->count() }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell class="flex gap-2 justify-end">
                            <flux:button size="sm" icon="pencil" wire:click="editService({{ $service->id }})">
                            </flux:button>
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDelete({{ $service->id }})">
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text class="text-center text-zinc-500">No services yet. Create your first service!</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Edit/Create Modal --}}
    <flux:modal name="service-editor" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingServiceId === null ? 'Create New Service' : 'Edit Service' }}
                </flux:heading>
            </div>

            <form wire:submit="save" class="space-y-6">
                <flux:field>
                    <flux:label>Service Name</flux:label>
                    <flux:description>A unique name for this service.</flux:description>
                    <flux:input wire:model="serviceName" placeholder="e.g., Active Directory Service" />
                </flux:field>

                <flux:field>
                    <flux:label>Manager</flux:label>
                    <flux:description>The person who manages this service.</flux:description>
                    <flux:select variant="combobox" placeholder="Select a manager..." wire:model="managerId">
                        @foreach ($users as $user)
                            <flux:select.option value="{{ $user->id }}">{{ $user->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Service Members</flux:label>
                    <flux:description>Select all members who work on this service.</flux:description>
                    <flux:pillbox wire:model="selectedUserIds" multiple placeholder="Select service members...">
                        @foreach ($users as $user)
                            <flux:pillbox.option :value="$user->id">{{ $user->full_name }}</flux:pillbox.option>
                        @endforeach
                    </flux:pillbox>
                </flux:field>

                <div class="flex gap-2">
                    <flux:button variant="primary" type="submit">
                        {{ $editingServiceId === null ? 'Create Service' : 'Save Changes' }}
                    </flux:button>
                    <flux:button variant="ghost" x-on:click="$flux.modal('service-editor').close()">
                        Cancel
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="service-delete" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Service</flux:heading>
                <flux:subheading class="mt-2">
                    Are you sure you want to delete this service? You can transfer all members to another service, or delete the service without transferring.
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label>Transfer Members To (Optional)</flux:label>
                <flux:description>Select a service to transfer all members to, or leave empty to just remove the service.</flux:description>
                <flux:select variant="combobox" placeholder="No transfer (just delete service)" wire:model="transferServiceId">
                    @foreach ($services->where('id', '!=', $deletingServiceId) as $service)
                        <flux:select.option value="{{ $service->id }}">{{ $service->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex gap-2">
                <flux:button variant="danger" wire:click="deleteService">
                    Delete Service
                </flux:button>
                <flux:button variant="ghost" x-on:click="$flux.modal('service-delete').close()">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
