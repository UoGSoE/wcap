<div>
    <div class="flex justify-between items-center">
        <flux:heading size="xl">Import Plan Entries</flux:heading>
        <flux:button href="{{ route('manager.entries') }}" variant="ghost" icon="arrow-left">
            Back to Edit Plans
        </flux:button>
    </div>

    <flux:separator class="my-6" />

    @if (! $showPreview)
        {{-- Upload state --}}
        <flux:card class="max-w-xl">
            <flux:heading size="lg">Upload Excel File</flux:heading>
            <flux:text class="mt-2">
                Upload a file with columns: email, date (DD/MM/YYYY), location, note, is_available (Y/N)
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
        <div class="flex gap-4 mb-6">
            <flux:button wire:click="confirmImport" variant="primary" icon="check" :disabled="count($validRows) === 0">
                Import {{ count($validRows) }} Valid Entries
            </flux:button>
            <flux:button wire:click="cancelImport" variant="ghost" icon="x-mark">
                Cancel
            </flux:button>
        </div>

        @if (count($errorRows) > 0)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
                <flux:text>{{ count($errorRows) }} rows have errors and will be skipped.</flux:text>
            </flux:callout>

            <flux:heading size="lg" class="mb-4">Rows with Errors</flux:heading>
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
                            <flux:table.cell>
                                <flux:badge color="red">{{ $row['error'] }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        @if (count($validRows) > 0)
            <flux:heading size="lg" class="mb-4">Valid Entries ({{ count($validRows) }})</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Row</flux:table.column>
                    <flux:table.column>User</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Location</flux:table.column>
                    <flux:table.column>Note</flux:table.column>
                    <flux:table.column>Available</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($validRows as $row)
                        <flux:table.row>
                            <flux:table.cell>{{ $row['row'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['user_name'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['date'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['location'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['note'] }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$row['is_available'] === 'Y' ? 'green' : 'zinc'">
                                    {{ $row['is_available'] }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:text>No valid entries found. Please check your file and try again.</flux:text>
            </flux:callout>
        @endif
    @endif
</div>
