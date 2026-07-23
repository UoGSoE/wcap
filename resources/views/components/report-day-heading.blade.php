@props(['day', 'compact' => false])

@if ($compact)
    <flux:tooltip content="{{ $day['date']->format('l j F') }}">
        <flux:text variant="strong" class="cursor-help">{{ substr($day['date']->format('D'), 0, 1) }}</flux:text>
    </flux:tooltip>
@else
    <flux:text variant="strong">{{ $day['date']->format('D') }} {{ $day['date']->format('jS') }}</flux:text>
@endif
