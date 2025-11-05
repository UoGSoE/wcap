<div>
    <div class="flex justify-between items-center">
        <div>
        <flux:heading size="xl">User Management</flux:heading>
        <flux:subheading>Create and manage user accounts, and set admin privileges.</flux:subheading>
        </div>
        <div>
            <flux:button variant="primary" wire:click="createUser">
                Create New User
            </flux:button>
        </div>
    </div>

    <flux:spacer class="mt-6"/>

    <flux:card class="mt-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Email</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $user->full_name }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link href="mailto:{{ $user->email }}">{{ $user->email }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @if ($user->is_admin)
                                    <flux:badge color="blue">Admin</flux:badge>
                                @endif
                                @if ($user->isManager())
                                    <flux:badge color="green">Manager</flux:badge>
                                @endif
                                @if ($user->is_staff)
                                    <flux:badge color="zinc">Staff</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="flex gap-2 justify-end">
                            <flux:button size="sm" icon="pencil" wire:click="editUser({{ $user->id }})">
                            </flux:button>
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDelete({{ $user->id }})">
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <flux:text class="text-center text-zinc-500">No users yet. Create your first user!</flux:text>
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
                    {{ $editingUserId === -1 ? 'Create New User' : 'Edit User' }}
                </flux:heading>
            </div>

            <flux:field>
                <flux:label>Username</flux:label>
                <flux:description>A unique username for login.</flux:description>
                <flux:input wire:model.live="username" placeholder="e.g., jsmith" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:description>The user's email address.</flux:description>
                <flux:input type="email" wire:model.live="email" placeholder="e.g., jsmith@example.com" />
            </flux:field>

            <flux:field>
                <flux:label>Surname</flux:label>
                <flux:description>The user's last name.</flux:description>
                <flux:input wire:model.live="surname" placeholder="e.g., Smith" />
            </flux:field>

            <flux:field>
                <flux:label>Forenames</flux:label>
                <flux:description>The user's first name(s).</flux:description>
                <flux:input wire:model.live="forenames" placeholder="e.g., John" />
            </flux:field>

            <flux:field>
                <flux:label>Permissions</flux:label>
                <flux:description>Set user role and permissions.</flux:description>
                <div class="space-y-2">
                    <flux:checkbox wire:model.live="isAdmin" label="Administrator" />
                    <flux:checkbox wire:model.live="isStaff" label="Staff Member" />
                </div>
            </flux:field>

            <div class="flex gap-2">
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $editingUserId === -1 ? 'Create User' : 'Save Changes' }}</span>
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
                <flux:heading size="lg">Delete User</flux:heading>
                <flux:text class="mt-2">
                    Are you sure you want to delete this user? This will also remove them from all teams, delete their plan entries, and unassign them as manager from any teams they manage.
                </flux:text>
                <flux:text class="mt-2 font-medium">
                    This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:button variant="danger" wire:click="deleteUser" wire:loading.attr="disabled">
                    <span wire:loading.remove>Delete User</span>
                    <span wire:loading>Deleting...</span>
                </flux:button>
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
