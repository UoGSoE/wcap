# Playbook A — Bootstrap 5/Blade ➜ FluxUI + Tailwind (First Pass, No new Livewire or VueJS) — Revised

**Goal:** Replace Bootstrap markup and styles with FluxUI components + Tailwind utilities while preserving existing Blade/Vue/Livewire code **verbatim**. This is a mechanical UI pass. **Do not** introduce new Livewire or change/patch any JS behaviour apart from the case of the flux:date-picker (as mentioned - the format the date value expects has changed). Vue directives remain in the templates, and Vue is **not initialised** in this pass so broken interactivity reveals where behaviour exists.

---

## 1) Principles

* **Zero Bootstrap:** Remove all Bootstrap classes/components and dependencies. No mixing.
* **Strict preservation of code:** Keep all Blade, Livewire and Vue directives exactly as they were. Do **not** wrap, shim, or introduce Alpine or new Livewire.
* **Non‑disruptive to logic:** Do not change controllers, routes, requests, or any JS data flows.
* **Consistency:** Use Flux components and Tailwind spacing/typography.
* **A11y by design:** Use Flux labels/props to keep controls properly labelled.
* **Keep it simple:** Keep your code simple and readable.  Follow Laravel and Livewire conventions.  Use Eloquent models, query scopes and relations rather than using SQL syntax, joins, sub-selects etc.  The developers like simple, clean code which they could read aloud and have it make sense to a stake-holder rather than 'clever' code.
* **Data Size:** Although the apps are important to the organisation, the data they will be operating on is not.  You don't have to worry about SQL performance or a bit of memory.  Simplicity and readability always trump any concerns.

---

## 2) Target State

* Blade templates render **FluxUI** components and **Tailwind** utilities.
* All prior Vue directives remain present in markup (but Vue is not initialised in this pass).
* **No Bootstrap** anywhere in the codebase.

---

## 3) Mapping Cheatsheet (Authoritative)

| Bootstrap                           | Flux/Tailwind replacement                   |
| ----------------------------------- | ------------------------------------------- |
| `.row` / `.col-*`                   | `grid grid-cols-1 lg:grid-cols-2 gap-8`     |
| `<h3>` / `.display-*`               | `<flux:heading>` / `<flux:text size="lg">`  |
| `.card` / `.card-body`              | `<flux:card>`                               |
| `.alert` / `.alert-*`               | `<flux:callout>`                            |
| `.btn` / `.btn-*`                   | `<flux:button />`                           |
| `<hr>`                              | `<flux:separator />`                        |
| `.form-control` (input/textarea)    | `<flux:input>` / `<flux:textarea>`          |
| `.form-select`                      | `<flux:select>` + `<flux:select.option>`    |
| `.container` / `.container-fluid`   | `max-w-7xl mx-auto px-4` (or appropriate)   |
| Responsive helpers (`.d-*`)         | Tailwind (`hidden sm:block`, etc.)          |

---

## 4) Canonical Form Patterns

* **Shorthand fields:** `<flux:input name="title" label="Title" />`
* **Selects (no raw option tags):**

```blade
<flux:select name="role" label="Role">
  <flux:select.option value="admin" :selected="$role==='admin'">Admin</flux:select.option>
  <flux:select.option value="user"  :selected="$role==='user'">User</flux:select.option>
</flux:select>
```

* **Date fields (always convert):** Replace **all date inputs**—both `type="date"` and any inputs using date pickers (Pikaday, Bootstrap Datepicker, etc.)—with Flux date picker; **values in ISO `Y-m-d`**.

```blade
<flux:date-picker name="date" value="{{ old('date', now()->format('Y-m-d')) }}" label="Date" />
```

* **Email inputs:** Use `type="email"`.
* **File inputs:** `<flux:input type="file" name="file" accept="..." label="..." />`
* **Bindings:** Use modern Blade bindings: `:selected`, `:disabled`, `:required`.

---

## 5) Layout & Spacing

* Ensure each main (not partial) template uses `<x-layouts.app>`; ensure a **single root** node in views.
* Wrap form content in `div.flex-1.space-y-6` for vertical rhythm.
* Use width constraints: `max-w-xl`, `max-w-2xl`, `max-w-4xl`.
* Don't add margins to `<flux:separator />` when inside rhythm wrappers.
* Add `cursor-pointer` to clickable containers (Tailwind reset removes default pointer on non‑button elements).

---

## 6) Vue Coexistence (Strict Preservation in Pass A)

* **Preserve all Vue directives and code verbatim** (`v-if`, `v-for`, `:prop`, `@click`, etc.).
* **Do not initialise Vue** in this pass. Broken interactions are expected and help identify behaviour to migrate later.
* **No shims/wrappers:** Do **not** add Alpine or extra wrappers to make directives work on Flux tags. This pass is a straight Bootstrap ➜ Flux swap.

---

## 7) Safety & A11y

* Ensure every interactive control has an accessible label (use Flux `label` props or `<flux:label>`).
* Preserve semantic structure (headings, lists, buttons vs links) when swapping components.
* **Flux handles focus outlines and dark mode styling**—no extra outline or dark‑mode classes required.

---

## 8) Workflow (Repeatable)

1. **Inventory:** Use the pre‑generated `TEMPLATES_TODO.md` from the finder script.
2. **Convert layout:** Ensure a single root node; keep the Blade layout; apply width constraints (`max-w-*`).
3. **Replace components:** Map Bootstrap → Flux per cheatsheet; use field shorthand and modern Blade bindings.
4. **Dates:** Convert **all date fields** (both `type="date"` and date picker libraries) to `<flux:date-picker>` with ISO `Y-m-d`.
5. **Responsive:** Replace Bootstrap responsive helpers with Tailwind utilities.
6. **Vue left as‑is, uninitialised:** Expect broken interactivity; that's the point.
7. **Commit:** Small, focused diffs per template.

---

## 9) Anti‑Patterns (Blockers)

* **Any Bootstrap left anywhere** (classes, components, or dependencies).
* Raw `<option>` elements nested inside `<flux:select>`.
* Patching or modifying existing JS/Vue (no Alpine, no wrappers, no initialising Vue).
* Multiple root elements in Blade views.

---

## 10) Checklist (Per Template)

* [ ] No Bootstrap classes/components remain.
* [ ] Flux components used with shorthand where possible.
* [ ] Selects use `<flux:select.option>` only; modern bindings applied.
* [ ] **All date fields** converted to `<flux:date-picker>` with ISO `Y-m-d`.
* [ ] Layout has width constraint and `space-y-6` rhythm.
* [ ] Responsive classes are Tailwind equivalents.
* [ ] A11y labels preserved; semantics intact.
* [ ] Vue directives preserved; Vue not initialised.

---

## 11) Ready‑to‑Use System Prompt (Pass A)

**You are performing a first‑pass UI migration.** Replace all Bootstrap markup/classes with FluxUI + Tailwind in Blade templates. Preserve all existing Vue directives and code verbatim and **do not initialise Vue** in this pass. Do not add wrappers or Alpine shims to make things work; broken interactions are expected and help discovery later. Use Flux component shorthand for fields; convert **every date field** (including `type="date"` and date picker libraries like Bootstrap Datepicker or Pikaday) to `<flux:date-picker>` with ISO `Y-m-d`. Use `<flux:select>` with `<flux:select.option>` only and modern Blade bindings (`:selected`, `:disabled`, `:required`). Ensure a single root element, consistent width constraints, spacing wrappers, and responsive Tailwind classes, with accessible labels. Do not modify controllers, routes, or any JS behaviour. Commit small, focused diffs and run the checklist before marking complete.

**CRITICAL:** Do not introduce @php blade blocks in templates.  Always defer to the laravel and livewire conventions of properties, models, attributes on the component.

---

## 12) Bootstrap-Specific Conversion Notes

* **Modals:** Convert Bootstrap modals (`data-bs-toggle="modal"`, `data-bs-target="#id"`) to `<flux:modal>` with `<flux:modal.trigger>`.
* **Validation:** Remove Bootstrap validation classes (`.is-invalid`, `.is-valid`, `.invalid-feedback`) - FluxUI handles this automatically.
* **Spacing:** Replace Bootstrap spacing utilities (`.mb-3`, `.mt-4`, etc.) with Tailwind or Flux's built-in spacing in `space-y-6` containers.
* **Grid:** Convert `.row` and `.col-*` to Tailwind grid utilities (`grid`, `grid-cols-*`, `gap-*`).
* **Container:** Replace `.container` or `.container-fluid` with appropriate Tailwind max-width utilities (`max-w-7xl mx-auto px-4`).
* **Alerts:** Map alert variants: `.alert-primary` → `variant="info"`, `.alert-success` → `variant="success"`, `.alert-warning` → `variant="warning"`, `.alert-danger` → `variant="danger"`.
* **Buttons:** Map button variants: `.btn-primary` → `variant="primary"`, other button styles generally become default `<flux:button>`.
* **Forms:** Replace `.mb-3` form groups with FluxUI shorthand; remove `.form-label`, `.form-control`, `.form-select` classes entirely.

