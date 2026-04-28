<div>
    @if (! $readOnly)
        <div class="flex justify-end items-center gap-2 mb-4">
            <flux:text size="sm" class="text-zinc-500">
                <span wire:loading.remove wire:target="save,copyNext,copyRest,entries">Changes save automatically</span>
                <span wire:loading wire:target="save,copyNext,copyRest,entries">Saving…</span>
            </flux:text>
            <flux:button
                icon="bookmark-square"
                size="sm"
                variant="ghost"
                wire:click="save"
                wire:loading.attr="disabled"
                wire:loading.class="animate-pulse"
            />
        </div>
    @endif

    <flux:fieldset :disabled="$readOnly">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($days as $index => $day)
            @if ($day->isWeekday())
                <flux:card size="sm" class="mt-2" wire:key="day-{{ $index }}">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">{{ $day->format('l') }} {{ $day->format('jS') }}</flux:heading>
                            <flux:select size="sm" wire:model.live="entries.{{ $index }}.availability_status">
                                @foreach ($availabilityStatuses as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        @if (! $readOnly)
                            <div class="flex gap-2">
                                <flux:button size="xs" wire:click="copyNext({{ $index }})">Copy next</flux:button>
                                <flux:button size="xs" wire:click="copyRest({{ $index }})">Copy rest</flux:button>
                            </div>
                        @endif
                    </div>
                    <flux:spacer class="mt-2"/>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input placeholder="What..." wire:model.live="entries.{{ $index }}.note" />
                        <flux:select placeholder="Where?" wire:model.live="entries.{{ $index }}.location_id">
                            @foreach ($locations as $location)
                                <flux:select.option value="{{ $location->id }}">{{ $location->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </flux:card>
            @else
                <input type="hidden" wire:model="entries.{{ $index }}.id" />
                <input type="hidden" wire:model="entries.{{ $index }}.entry_date" />
                <input type="hidden" wire:model="entries.{{ $index }}.note" />
                <input type="hidden" wire:model="entries.{{ $index }}.location_id" />
                <input type="hidden" wire:model="entries.{{ $index }}.availability_status" />
            @endif
        @endforeach
        </div>
    </flux:fieldset>
</div>
