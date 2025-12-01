<div>
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-2">
            <flux:heading size="xl">Your Plan</flux:heading>
            <flux:button size="xs" variant="ghost" href="{{ route('profile') }}" wire:navigate icon="user-circle">Profile</flux:button>
        </div>
    </div>

    <flux:callout icon="information-circle" class="mt-4">
        <flux:text>Your manager will update your plan. This view is read-only.</flux:text>
    </flux:callout>

    <flux:spacer class="mt-6"/>

    <livewire:plan-entry-editor :user="$user" :read-only="true" />
</div>
