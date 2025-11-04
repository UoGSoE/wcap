<div>
    <div class="flex justify-between items-center">
        <flux:heading size="xl">What are you working on?</flux:heading>
        <flux:button size="sm" variant="primary">Save</flux:button>
    </div>
    <flux:spacer class="mt-6"/>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach ($days as $day)
        <flux:card size="sm" class="mt-6">
            <div class="flex justify-between items-center">
                <flux:heading size="lg">{{ $day->format('l') }} / {{ $day->format('jS') }}</flux:heading>
                <div class="flex gap-2">
                    <flux:button >Copy next</flux:button>
                    <flux:button >Copy rest</flux:button>
                </div>
            </div>
            <flux:spacer class="mt-2"/>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input placeholder="What..." />
                <flux:select variant="combobox" placeholder="Where?">
                    @foreach ($locations as $location)
                        <flux:select.option value="{{ $location->value }}">{{ $location->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </flux:card>
    @endforeach
    </div>
</div>
