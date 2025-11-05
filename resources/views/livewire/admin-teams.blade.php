<div>
    <div class="flex justify-between items-center">
        <div>
        <flux:heading size="xl">Team Management</flux:heading>
        <flux:subheading>Create and manage teams, assign members, and set managers.</flux:subheading>
        </div>
        <div>
            <flux:button variant="primary" wire:click="createTeam">
                Create New Team
            </flux:button>
        </div>
    </div>

    <flux:spacer class="mt-6"/>

    <flux:card class="mt-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Team Name</flux:table.column>
                <flux:table.column>Manager</flux:table.column>
                <flux:table.column>Members</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($teams as $team)
                    <flux:table.row :key="$team->id">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $team->name }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ $team->manager->full_name }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ $team->users->count() }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell class="flex gap-2 justify-end">
                            <flux:button size="sm" icon="pencil" wire:click="editTeam({{ $team->id }})">
                            </flux:button>
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDelete({{ $team->id }})">
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text class="text-center text-zinc-500">No teams yet. Create your first team!</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Edit/Create Modal --}}
    <flux:modal wire:model="showEditModal" variant="flyout">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingTeamId === -1 ? 'Create New Team' : 'Edit Team' }}
                </flux:heading>
            </div>

            <flux:field>
                <flux:label>Team Name</flux:label>
                <flux:description>A unique name for this team.</flux:description>
                <flux:input wire:model.live="teamName" placeholder="e.g., Infrastructure Team" />
            </flux:field>

            <flux:field>
                <flux:label>Manager</flux:label>
                <flux:description>The person who manages this team.</flux:description>
                <flux:select variant="combobox" placeholder="Select a manager..." wire:model.live="managerId">
                    @foreach ($users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Team Members</flux:label>
                <flux:description>Select all members who belong to this team.</flux:description>
                <flux:pillbox wire:model.live="selectedUserIds" multiple placeholder="Select team members...">
                    @foreach ($users as $user)
                        <flux:pillbox.option :value="$user->id">{{ $user->full_name }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            </flux:field>

            <div class="flex gap-2">
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $editingTeamId === -1 ? 'Create Team' : 'Save Changes' }}</span>
                    <span wire:loading>Saving...</span>
                </flux:button>
                <flux:button variant="ghost" wire:click="cancelEdit">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Team</flux:heading>
                <flux:text class="mt-2">
                    Are you sure you want to delete this team? You can transfer all members to another team, or delete the team without transferring.
                </flux:text>
            </div>

            <flux:field>
                <flux:label>Transfer Members To (Optional)</flux:label>
                <flux:description>Select a team to transfer all members to, or leave empty to just remove the team.</flux:description>
                <flux:select variant="combobox" placeholder="No transfer (just delete team)" wire:model.live="transferTeamId">
                    @foreach ($teams->where('id', '!=', $deletingTeamId) as $team)
                        <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex gap-2">
                <flux:button variant="danger" wire:click="deleteTeam" wire:loading.attr="disabled">
                    <span wire:loading.remove>Delete Team</span>
                    <span wire:loading>Deleting...</span>
                </flux:button>
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
