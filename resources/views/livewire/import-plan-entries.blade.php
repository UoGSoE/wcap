<div>
    <div class="flex justify-between items-center">
        <flux:heading size="xl">Import Plan Entries</flux:heading>
    </div>

    <flux:separator class="my-6" />

    @if (! $showPreview)
        {{-- Upload state --}}
        <flux:card class="max-w-xl">
            <flux:heading size="lg">Upload Excel File</flux:heading>
            <flux:text class="mt-2">
                Upload a file with columns: email, date (DD/MM/YYYY), location, note, availability (O/R/N for Onsite/Remote/Not available)
            </flux:text>

            <div class="mt-6">
                <flux:file-upload wire:model="file" label="Select file">
                    <flux:file-upload.dropzone
                        heading="Drop file here or click to browse"
                        text="XLSX up to 1MB"
                    />
                </flux:file-upload>
            </div>

            @error('file')
                <flux:callout variant="danger" icon="exclamation-triangle" class="mt-4">
                    <flux:text>{{ $message }}</flux:text>
                </flux:callout>
            @enderror
        </flux:card>
    @else
        {{-- Preview state --}}

        @if (count($errorRows) > 0)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
                <flux:text>{{ count($errorRows) }} rows have errors and will be skipped.</flux:text>
            </flux:callout>

            <flux:table class="mb-8">
                <flux:table.columns>
                    <flux:table.column>Row</flux:table.column>
                    <flux:table.column>Data</flux:table.column>
                    <flux:table.column>Error</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($errorRows as $row)
                        <flux:table.row>
                            <flux:table.cell>{{ $row['row'] }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ implode(', ', $row['data']) }}</flux:table.cell>
                            <flux:table.cell class="flex items-center gap-2">
                                <flux:badge color="red">{{ $row['error'] }}</flux:badge>
                                @if ($this->isEmailNotFoundError($row['error']))
                                    <flux:button size="xs" icon="plus" wire:click="openCreateUserModal({{ $loop->index }}, '{{ $row['data'][0] }}')" class="cursor-pointer" title="Create user" />
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        @if (count($validRows) > 0)
            <div class="flex gap-4 mb-6">
                <flux:button wire:click="confirmImport" variant="primary" icon="check" class="cursor-pointer" :disabled="count($validRows) === 0">
                    Import {{ count($validRows) }} Valid Entries
                </flux:button>
                <flux:button wire:click="cancelImport" icon="x-mark" class="cursor-pointer">
                    Cancel
                </flux:button>
            </div>
        @else
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:text>No valid entries found. Please check your file and try again.</flux:text>
            </flux:callout>

            <flux:separator class="my-6" />

            <flux:button wire:click="cancelImport" icon="x-mark" class="cursor-pointer">
                Retry
            </flux:button>

        @endif
    @endif

    {{-- Create User Modal --}}
    <flux:modal name="create-user" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">Create New User</flux:heading>

            <form wire:submit="saveNewUser" class="space-y-4">
                <flux:input wire:model="newUserForenames" label="Forenames" placeholder="e.g., John" />
                <flux:input wire:model="newUserSurname" label="Surname" placeholder="e.g., Smith" />
                <flux:input type="email" wire:model="newUserEmail" label="Email" placeholder="e.g., jsmith@example.com" />
                <flux:input wire:model="newUserUsername" label="Username" placeholder="e.g., jsmith" />

                <flux:select wire:model="newUserTeamId" label="Team" placeholder="Select a team...">
                    @foreach ($managerTeams as $team)
                        <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="newUserDefaultLocation" label="Default Location (optional)" placeholder="None">
                    <flux:select.option value="">None</flux:select.option>
                    @foreach ($locations as $location)
                        <flux:select.option value="{{ $location->value }}">{{ $location->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="newUserDefaultCategory" label="Default Category (optional)" placeholder="e.g., Support tickets" />

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">Create User</flux:button>
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('create-user').close()">Cancel</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
