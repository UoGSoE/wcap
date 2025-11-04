# FluxUI Component Library Documentation

## Core Principles

### 1. Simplicity
Flux prioritizes simple syntax above all else.

**Simple approach:**
```blade
<flux:input wire:model="email" label="Email" />
```

**Complex approach (avoided):**
```blade
<flux:form.field>
    <flux:form.field.label>Email</flux:form.field.label>
    <div>
        <flux:form.field.text-input wire:model="email" />
    </div>
    @error('email')
        <p class="mt-2 text-red-500 dark:text-red-400 text-xs">{{ $message }}</p>
    @enderror
</flux:form.field>
```

### 2. Complexity Control
For cases requiring more control, Flux offers composable alternatives:

**Simple version:**
```blade
<flux:input wire:model="email" label="Email" />
```

**Composable version:**
```blade
<flux:field>
    <flux:label>Email</flux:label>
    <flux:input wire:model="email" />
    <flux:error name="email" />
</flux:field>
```

### 3. Friendliness
Uses familiar terms instead of technical jargon:
- "form inputs" instead of "form controls"
- "dropdown" instead of "popover"
- "accordion" instead of "disclosure"

### 4. Composition
Components can be mixed and matched:

**Basic button:**
```blade
<flux:button>Options</flux:button>
```

**Button in dropdown:**
```blade
<flux:dropdown>
    <flux:button>Options</flux:button>
    <flux:navmenu>
        <!-- ... -->
    </flux:navmenu>
</flux:dropdown>
```

**System menu:**
```blade
<flux:dropdown>
    <flux:button>Options</flux:button>
    <flux:menu>
        <!-- ... -->
    </flux:menu>
</flux:dropdown>
```

**Context menu:**
```blade
<flux:context>
    <flux:button>Options</flux:button>
    <flux:menu>
        <!-- ... -->
    </flux:menu>
</flux:context>
```

### 5. Consistency
Repeated syntax patterns throughout. Example: consistent use of "heading":

```blade
<flux:heading>...</flux:heading>
<flux:menu.submenu heading="...">
<flux:accordion.heading>...</flux:accordion.heading>
```

### 6. Brevity
- Avoids compound words requiring hyphens
- Avoids deep nesting levels
- Uses simple words like "input" instead of "text-input"

### 7. Use the Browser
Leverages native browser features:
- Uses `popover` attribute for dropdowns
- Uses `<dialog>` element for modals

### 8. Use CSS
Prefers CSS solutions over JavaScript when possible. Example focus styling:
```css
[&:has(+input:focus)]:text-zinc-800
```

### 9. "We Style, You Space"
Flux provides padding, you provide margins:

```blade
<form wire:submit="createAccount">
    <div class="mb-6">
        <flux:heading>Create an account</flux:heading>
        <flux:text class="mt-2">We're excited to have you on board.</flux:text>
    </div>
    <flux:input class="mb-6" label="Email" wire:model="email" />
    <div class="mb-6 flex *:w-1/2 gap-4">
        <flux:input label="Password" wire:model="password" />
        <flux:input label="Confirm password" wire:model="password_confirmation" />
    </div>
    <flux:button type="submit" variant="primary">Create account</flux:button>
</form>
```

## Component Patterns

### Props vs Attributes
- **Props**: Flux-provided properties (e.g., `variant`)
- **Attributes**: Forwarded to HTML elements (e.g., `x-on:change.prevent`)

```blade
<flux:button variant="primary" x-on:change.prevent="...">
```

Renders as:
```html
<button type="button" class="bg-zinc-900 ..." x-on:change.prevent="...">
```

### Class Merging
Custom classes are merged with Flux classes:

```blade
<flux:button class="w-full">
```

Renders as:
```html
<button type="button" class="w-full border border-zinc-200 ...">
```

**Handling conflicts:**
Use Tailwind's `!` modifier:
```blade
<flux:button class="bg-zinc-800! hover:bg-zinc-700!">
```

### Split Attribute Forwarding
For complex components with multiple elements, attributes are split appropriately:

```blade
<flux:input class="w-full" autofocus>
```

Renders as:
```html
<div class="w-full ...">
    <input type="text" class="..." autofocus>
</div>
```

## Common Props

### Variant
For alternate visual styles:
```blade
<flux:button variant="primary" />
<flux:input variant="filled" />
<flux:modal variant="flyout" />
<flux:badge variant="solid" />
<flux:select variant="combobox" />
<flux:separator variant="subtle" />
<flux:tabs variant="segmented" />
```

### Icon
Uses Heroicons:
```blade
<flux:button icon="magnifying-glass" />
<flux:input icon="magnifying-glass" />
<flux:tab icon="cog-6-tooth" />
<flux:badge icon="user" />
```

**Trailing icons:**
```blade
<flux:button icon:trailing="chevron-down" />
<flux:input icon:trailing="credit-card" />
```

### Size
```blade
<!-- Smaller -->
<flux:button size="sm" />
<flux:select size="sm" />
<flux:input size="sm" />
<flux:tabs size="sm" />

<!-- Larger -->
<flux:heading size="lg" />
<flux:badge size="lg" />
```

### Keyboard Hints
```blade
<flux:button kbd="⌘S" />
<flux:tooltip kbd="D" />
<flux:input kbd="⌘K" />
<flux:menu.item kbd="⌘E" />
```

### Inset
For inline elements:
```blade
<flux:badge inset="top bottom">
<flux:button variant="ghost" inset="left">
```

### Prop Forwarding
Simple prop:
```blade
<flux:button icon="bell" />
```

With nested props:
```blade
<flux:button icon="bell" icon:variant="solid" />
```

### Opt-out Props
Force a prop to false:
```blade
<flux:navbar.item :current="false">
```

### Shorthand Props
**Full syntax:**
```blade
<flux:field>
    <flux:label>Email</flux:label>
    <flux:input wire:model="email" type="email" />
    <flux:error name="email" />
</flux:field>
```

**Shorthand:**
```blade
<flux:input type="email" wire:model="email" label="Email" />
```

**Tooltip example:**
```blade
<!-- Long form -->
<flux:tooltip content="Settings">
    <flux:button icon="cog-6-tooth" />
</flux:tooltip>

<!-- Shorthand -->
<flux:button icon="cog-6-tooth" tooltip="Settings" />
```

## Data Binding

### Common wire:model Components
```blade
<flux:input wire:model="email" />
<flux:checkbox wire:model="terms" />
<flux:switch wire:model.live="enabled" />
<flux:textarea wire:model="content" />
<flux:select wire:model="state" />
```

### Additional Bindable Components
```blade
<flux:checkbox.group wire:model="notifications">
<flux:radio.group wire:model="payment">
<flux:tabs wire:model="activeTab">
```

## Component Groups

### Components with .group Suffix
Can be used standalone or grouped:
```blade
<flux:button.group>
    <flux:button />
</flux:button.group>

<flux:input.group>
    <flux:input />
</flux:input.group>

<flux:checkbox.group>
    <flux:checkbox />
</flux:checkbox.group>

<flux:radio.group>
    <flux:radio />
</flux:radio.group>
```

### Components with .item Suffix
Cannot be used standalone:
```blade
<flux:accordion>
    <flux:accordion.item />
</flux:accordion>

<flux:menu>
    <flux:menu.item />
</flux:menu>

<flux:breadcrumbs>
    <flux:breadcrumbs.item />
</flux:breadcrumbs>

<flux:navbar>
    <flux:navbar.item />
</flux:navbar>

<flux:navlist>
    <flux:navlist.item />
</flux:navlist>

<flux:navmenu>
    <flux:navmenu.item />
</flux:navmenu>

<flux:command>
    <flux:command.item />
</flux:command>

<flux:autocomplete>
    <flux:autocomplete.item />
</flux:autocomplete>
```

### Root Components
Larger components without common prefixes:
```blade
<flux:field>
    <flux:label></flux:label>
    <flux:description></flux:description>
    <flux:error></flux:error>
</flux:field>
```

### Anomalies
Some components break conventions:
```blade
<flux:tab.group>
    <flux:tabs>
        <flux:tab>
    </flux:tabs>
    <flux:tab.panel>
</flux:tab.group>
```

## Slots

Used when composition isn't sufficient:

**Simple icon:**
```blade
<flux:input icon:trailing="x-mark" />
```

**Icon with functionality:**
```blade
<flux:input>
    <x-slot name="iconTrailing">
        <flux:button icon="x-mark" size="sm" variant="subtle" wire:click="clear" />
    </x-slot>
</flux:input>
```

## Important Notes

### Blade Component Limitations
**HTML elements (works):**
```blade
<input @if ($disabled) disabled @endif>
```

**Blade/Flux components (use dynamic syntax):**
```blade
<flux:input :disabled="$disabled">
```

