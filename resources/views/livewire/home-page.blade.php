<div>
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-2">
            <flux:heading size="xl">What are you working on?</flux:heading>
            <flux:button size="xs" variant="ghost" href="{{ route('profile') }}" wire:navigate icon="user-circle">Profile</flux:button>
        </div>
        <flux:button size="sm" variant="primary" wire:click="save" wire:loading.attr="disabled">
            <span wire:loading.remove>Save</span>
            <span wire:loading>Saving...</span>
        </flux:button>
    </div>
    <flux:spacer class="mt-6"/>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach ($days as $index => $day)
        <flux:card size="sm" class="mt-6" wire:key="day-{{ $index }}">
            <div class="flex justify-between items-center">
                <flux:heading size="lg">{{ $day->format('l') }} / {{ $day->format('jS') }}</flux:heading>
                <div class="flex gap-2">
                    <flux:button wire:click="copyNext({{ $index }})">Copy next</flux:button>
                    <flux:button wire:click="copyRest({{ $index }})">Copy rest</flux:button>
                </div>
            </div>
            <flux:spacer class="mt-2"/>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input placeholder="What..." wire:model.live="entries.{{ $index }}.note" />
                <flux:select variant="combobox" placeholder="Where?" wire:model.live="entries.{{ $index }}.location">
                    @foreach ($locations as $location)
                        <flux:select.option value="{{ $location->value }}">{{ $location->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </flux:card>
    @endforeach
    </div>
</div>
