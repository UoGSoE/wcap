# FluxUI Conversion System Prompt (Bootstrap 5)

## Overview
You are converting a Laravel application from Bootstrap 5 CSS framework to FluxUI (Livewire Flux). This document provides systematic guidance for accurate and efficient template conversions.

## Conversion Process

### 1. Initial Setup
- Read the overall guidance from the creator of Livewire and FluxUI - `./FLUX_PATTERNS.md`
- Read our notes on the conversion process - `./BOOTSTRAP_TO_FLUX.md`
- Read through 7 templates at a time from `TEMPLATES_TODO.md`
- Use `mcp__laravel-boost__search-docs` tool to search for FluxUI component documentation
- Check existing converted templates to understand established patterns
- Always look at `resources/views/components/layouts/app.blade.php` to understand the base layout structure

### 2. Core Conversion Rules

#### Layout Structure
- Convert from `@extends('layouts.app')` to `<x-layouts.app>`
- Wrap form fields in `<div class="flex-1 space-y-6">` for proper spacing
- Use `max-w-2xl`, `max-w-4xl` etc. for content width constraints
- Replace `<div class="container">` with appropriate max-width utilities

#### Bootstrap to FluxUI Component Mapping

| Bootstrap 5 | FluxUI |
|-------|---------|
| `<h3>` or `<h3 class="display-*">` | `<flux:heading size="xl">` |
| `<p class="lead">` | `<flux:text size="lg">` |
| `<div class="card">` | `<flux:card>` |
| `<div class="alert">` | `<flux:callout>` |
| `<button class="btn">` | `<flux:button>` |
| `<hr>` | `<flux:separator />` |
| `<div class="row">` | `<div class="grid grid-cols-1 md:grid-cols-2 gap-8">` |
| `<div class="col-*">` | `<div>` |
| `<div class="mb-3">` (form group) | Use FluxUI shorthand (see below) |
| `<select class="form-select">` | `<flux:select>` |
| `<input class="form-control">` | `<flux:input>` |
| `<textarea class="form-control">` | `<flux:textarea>` |
| `<p>Some text</p>` | `<flux:text>Some text</flux:text>` |
| `<a href="{{ route('home') }}">` | `<flux:link :href="route('home')">` |

#### Form Field Conversions

##### USE SHORTHAND SYNTAX
Instead of:
```blade
<flux:field>
    <flux:label>Title</flux:label>
    <flux:input name="title" />
</flux:field>
```

Use:
```blade
<flux:input name="title" label="Title" />
```

##### Date Pickers
Convert ANY input with `data-pikaday` (or similar date picker attributes) to `flux:date-picker`:

Before:
```blade
<input name="date" type="text" class="form-control" value="{{ $date->format('d/m/Y') }}" data-pikaday>
```

After:
```blade
<flux:date-picker name="date" value="{{ $date->format('Y-m-d') }}" label="Date Label" />
```
Ensure the value you pass into `<flux:date-picker>` is already in ISO Y-m-d format (for example via `old('date', optional($date)->format('Y-m-d'))` inside the Blade template). Keep the underlying model/controller logic unchanged during this pass.

##### Email input
If you come across an input which seems to be an email input, but is not using the email type attribute, then please change the type from 'text' to 'email'.

##### Select Elements
ALWAYS use `flux:select.option`, not HTML `<option>`:

```blade
<flux:select name="category" label="Category">
    <flux:select.option value="val1" :selected="$model->field == 'val1'">Label 1</flux:select.option>
    <flux:select.option value="val2" :selected="$model->field == 'val2'">Label 2</flux:select.option>
</flux:select>
```

##### Tables

Use the flux:table component to convert tables.
```blade
<flux:table :paginate="$this->orders">
    <flux:table.columns>
        <flux:table.column>Customer</flux:table.column>
        <flux:table.column sortable :sorted="$sortBy === 'date'" :direction="$sortDirection" wire:click="sort('date')">Date</flux:table.column>
        <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
        <flux:table.column sortable :sorted="$sortBy === 'amount'" :direction="$sortDirection" wire:click="sort('amount')">Amount</flux:table.column>
    </flux:table.columns>

    <flux:table.rows>
        @foreach ($this->orders as $order)
            <flux:table.row :key="$order->id">
                <flux:table.cell class="flex items-center gap-3">
                    <flux:avatar size="xs" src="{{ $order->customer_avatar }}" />

                    {{ $order->customer }}
                </flux:table.cell>

                <flux:table.cell class="whitespace-nowrap">{{ $order->date }}</flux:table.cell>

                <flux:table.cell>
                    <flux:badge size="sm" :color="$order->status_color" inset="top bottom">{{ $order->status }}</flux:badge>
                </flux:table.cell>

                <flux:table.cell variant="strong">{{ $order->amount }}</flux:table.cell>

                <flux:table.cell>
                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
```

##### Modals

Existing Bootstrap modals should be converted to flux:modal components. The modal trigger `name` attribute should match the name of the modal.

Before (Bootstrap):
```blade
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfile">
    Edit profile
</button>

<div class="modal fade" id="editProfile" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- content -->
            </div>
        </div>
    </div>
</div>
```

After (FluxUI):
```blade
<flux:modal.trigger name="edit-profile">
    <flux:button>Edit profile</flux:button>
</flux:modal.trigger>

<flux:modal name="edit-profile" class="md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Update profile</flux:heading>
            <flux:text class="mt-2">Make changes to your personal details.</flux:text>
        </div>

        <flux:input label="Name" placeholder="Your name" />

        <flux:input label="Date of birth" type="date" />

        <div class="flex">
            <flux:spacer />

            <flux:button type="submit" variant="primary">Save changes</flux:button>
        </div>
    </div>
</flux:modal>
```

##### Button variants

All flux:button's should be left without any variants _apart from_ the main submit button on a form which should use variant="primary".

##### Links

Any link that is not a button should use the flux:link component.
Before:
```blade
<a href="{{ route('home') }}">Home</a>
```
After:
```blade
<flux:link :href="route('home')">Home</flux:link>
```

##### Dropdown/more menus

When using a flux:dropdown the button that acts as the trigger should use a trailing chevron icon. Eg:

```blade
<flux:button icon:trailing="chevron-down">More</flux:button>
```

##### Modern Blade Attribute Binding
ALWAYS use modern Laravel component attribute syntax:

Instead of:
```blade
@if ($condition) selected @endif
@if ($condition) disabled @endif
@if ($condition) required @endif
```

Use:
```blade
:selected="$condition"
:disabled="$condition"
:required="$condition"
```

### 3. Styling Patterns

#### Spacing
- Wrap form sections in `<div class="flex-1 space-y-6">` for consistent field spacing
- Use `<flux:separator />` without manual margin classes when inside spaced containers
- Use Tailwind spacing utilities (`mb-4`, `mt-6`, etc.) for additional spacing needs

#### Typography
- Use flux:text for all text
- Use flux:heading for all headings - only the main page title (usually an h3 tag) should use a size attribute of size="xl"
- Ignore any Bootstrap text size classes (`.fs-*`, `.h1`-`.h6` when used on non-heading elements) - just use regular flux:text
- Bootstrap `.fw-bold` styles should use flux:text with variant="strong"

#### Colors & Dark Mode
FluxUI has built-in colour mechanisms and dark mode support - there is no need to write out explicit tailwind classes for things like text or buttons. Tailwind classes should be used more for layout, spacing, mobile-first, and places where there doesn't seem to be an obvious way to do something in the flux documentation (remember the laravel-boost tool - make sure to check!)

#### Responsive Design
- Replace Bootstrap responsive classes with Tailwind
- `d-none d-sm-block` → `hidden sm:block`
- `col-12 col-md-6` → Use Grid: `grid grid-cols-1 md:grid-cols-2`
- `container` → `max-w-7xl mx-auto px-4` (or appropriate max-width)

#### Clickable items
- Remember that tailwind has a css reset which removes the default browser cursor styles buttons.
- So make sure to add a `cursor-pointer` class to any button that needs to be clickable.

### 4. Component Variants

#### Buttons
```blade
<flux:button>Default</flux:button>
<flux:button variant="primary">Primary</flux:button>
```

#### Callouts (Alerts)
```blade
<!-- Before (Bootstrap) -->
<div class="alert alert-primary">Info</div>
<div class="alert alert-success">Success</div>
<div class="alert alert-warning">Warning</div>
<div class="alert alert-danger">Error</div>

<!-- After (FluxUI) -->
<flux:callout>Default</flux:callout>
<flux:callout variant="info">Information</flux:callout>
<flux:callout variant="success">Success</flux:callout>
<flux:callout variant="warning">Warning</flux:callout>
<flux:callout variant="danger">Error</flux:callout>
```

### 5. Vue.js and Livewire Components
When converting Vue.js components with Bootstrap:
- Keep Vue directives (v-if, v-for, @click, etc.)
- Replace Bootstrap classes with FluxUI components
- Maintain reactivity patterns

Example:
```blade
<!-- Before -->
<div v-if="show" class="alert alert-success">
    {{ message }}
</div>

<!-- After -->
<flux:callout v-if="show" variant="success">
    @{{ message }}
</flux:callout>
```

When converting Livewire components:
- Keep blade directives (@if, @foreach, @click, etc.)
- Replace Bootstrap classes with FluxUI components
- Maintain reactivity patterns

Example:
```blade
<!-- Before -->
<div class="mb-3">
  <label class="form-label">Name</label>
  <input wire:model="name" class="form-control" type="text" placeholder="Enter name">
  @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
```

<!-- After -->
```blade
<flux:input wire:model="name" label="Name" type="text" placeholder="Enter name" />
```
Note: flux:input takes care of showing errors automatically.

### 6. Common Patterns to Remember

#### File Uploads
```blade
<flux:input label="Upload File" type="file" name="file" accept=".xlsx" />
```

#### Icons
FluxUI uses Heroicons. Add icons to components (double check icon names using the mcp__laravel-boost__search-docs tool for flux). There is also a text file in the project called `heroicon-list.txt` if you want a very quick check of the complete list.
```blade
<flux:button icon="plus">Add New</flux:button>
<flux:input icon="search" />
<flux:icon.user-circle />
```

#### Blade guards
Some of the blade templates will use `@if (Auth::user()->isJwncStaff())`. Please replace those with `@jwncstaff` and change the matching `@endif` to be `@endjwncstaff`.

#### Conditional Styling
Because of the way Blade and FluxUI components work, you should avoid using @if blocks in the following case:
```blade
{{-- This breaks the component --}}
@if ($process->latestRun()->created_at->diffInDays(now()) > $process->frequency)
    <flux:text class="text-red-600 dark:text-red-400">
@else
    <flux:text>
@endif
    {{ $process->latestRun()->created_at }}
    ({{ $process->latestRun()->created_at->diffForHumans() }})
</flux:text>
```

Instead, use the following style:
```blade
{{-- This works --}}
<flux:text :color="$process->latestRun()->created_at->diffInDays(now()) > $process->frequency ? 'red' : 'default'">
    {{ $process->latestRun()->created_at }}
    ({{ $process->latestRun()->created_at->diffForHumans() }})
</flux:text>
```

### 7. Quality Checklist

Before marking a template as complete, verify:
- [ ] All Bootstrap classes removed
- [ ] All form fields use FluxUI shorthand syntax where possible
- [ ] Date pickers properly converted with ISO date format
- [ ] Select elements use `flux:select.option`
- [ ] Modern blade attribute binding used (`:selected`, `:disabled`, etc.)
- [ ] Proper spacing wrapper applied (`<div class="flex-1 space-y-6">`)
- [ ] Component variants properly applied
- [ ] Vue.js markup/tags preserved (if applicable)
- [ ] Livewire component markup/tags preserved (if applicable)
- [ ] Bootstrap validation classes removed (FluxUI handles this)

### 8. Testing Approach

After conversion:
1. Check visual appearance matches intended design
2. Test date picker functionality
3. Ensure responsive behavior works
4. Test form validation displays correctly

### 9. Documentation Resources

Always search for FluxUI documentation first using:
```php
mcp__laravel-boost__search-docs
```

Key search terms:
- "flux [component-name]"
- "date picker"
- "select options"
- "form fields"
- "component variants"

### 10. File Organization

When working through conversions:
1. Read 7 templates from TEMPLATES_TODO.md
2. Convert each template completely
3. Mark completed templates with [x] in TEMPLATES_TODO.md
4. After doing 7 templates, stop and wait for feedback from the user

## Example Full Conversion

### Before (Bootstrap):
```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col">
            <h3>Create User</h3>
            <form method="POST" action="{{ route('user.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" type="text" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="admin" @if($user->role == 'admin') selected @endif>Admin</option>
                        <option value="user" @if($user->role == 'user') selected @endif>User</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Start Date</label>
                    <input class="form-control" name="start_date" type="text" data-pikaday>
                </div>
                <hr>
                <button class="btn btn-primary">Create</button>
            </form>
        </div>
    </div>
</div>
@endsection
```

### After (FluxUI):
```blade
<x-layouts.app>
    <div class="max-w-xl">
        <flux:heading size="xl">Create User</flux:heading>
        
        <form method="POST" action="{{ route('user.store') }}" class="mt-6">
            @csrf
            
            <div class="flex-1 space-y-6">
                <flux:input name="name" type="text" required label="Name" />
                
                <!-- if the original template used wire:model, then you do not need to deal with the :selected -->
                <flux:select name="role" label="Role">
                    <flux:select.option value="admin" :selected="$user->role == 'admin'">Admin</flux:select.option>
                    <flux:select.option value="user" :selected="$user->role == 'user'">User</flux:select.option>
                </flux:select>
                
                <flux:date-picker name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" label="Start Date" />
                
                <flux:separator />
                
                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </div>
</x-layouts.app>
```

Note: if the new template will be a livewire component, you do not need the `<x-layouts.app>` wrapper - livewire components extend the base layout by default.

## Bootstrap-Specific Notes

- Bootstrap uses `data-*` attributes rather than `v-*` for some plugins (update date picker detection accordingly)
- Bootstrap form validation classes (`.is-invalid`, `.is-valid`) are handled automatically by FluxUI - remove these classes
- Bootstrap modals use `data-bs-toggle="modal"` and `data-bs-target="#id"` - these should become `<flux:modal.trigger>`
- Bootstrap's `.btn-close` becomes part of the FluxUI modal component automatically
- Bootstrap spacing utilities (`.mb-3`, `.mt-4`, etc.) can often be replaced with FluxUI's built-in spacing in `space-y-6` containers

---

This guide should be followed systematically for each template conversion to ensure consistency and completeness.

Remember: you have the laravel boost tool to help you find the right documentation for any component you need to convert. Make sure to use it - especially for components you have not seen before.

