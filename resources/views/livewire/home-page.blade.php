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
        @if ($day->isWeekday())
            <flux:card size="sm" class="mt-6" wire:key="day-{{ $index }}">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <flux:checkbox label="{{ $day->format('l') }} {{ $day->format('jS') }}" wire:model.live="entries.{{ $index }}.is_available" />
                    </div>
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
        @else
            <input type="hidden" wire:model="entries.{{ $index }}.id" />
            <input type="hidden" wire:model="entries.{{ $index }}.entry_date" />
            <input type="hidden" wire:model="entries.{{ $index }}.note" />
            <input type="hidden" wire:model="entries.{{ $index }}.location" />
            <input type="hidden" wire:model="entries.{{ $index }}.is_available" />
        @endif
    @endforeach
    </div>
</div>
