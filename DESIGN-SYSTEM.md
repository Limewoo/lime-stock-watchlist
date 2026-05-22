# Limewoo Admin UI Design System

> **Portable section.** Copy this entire file into any future Limewoo plugin's repo. It defines the shared visual language so all admin pages look like one product family.

## Guiding principles

- Match WordPress admin norms (`.wrap` container, WP typography scale) — don't fight them.
- Use native HTML `<input>`/`<select>` instead of WP `SearchControl`/`SelectControl` for filter controls — WP wrappers make consistent height impossible.
- No full-width overrides. Let WP's `.wrap` class provide standard page margins.
- All colours are WP-standard greys (`#dcdcde`, `#f6f7f7`, `#1d2327`, `#646970`) plus the brand accent. Never use green-tinted greys for borders or backgrounds.

### Brand tokens

| Token | Value | Use |
|-------|-------|-----|
| `$lswl-lime` | `#5d9e3f` | Primary accent — active states, buttons, focus rings |
| `$lswl-lime-dark` | `#4a8030` | Button hover bg |
| `$lswl-lime-darker` | `#3a6626` | Text links, icon fills |
| `$lswl-lime-light` | `#ecf7e4` | Badge/icon background tint |
| `$lswl-lime-mid` | `#c5e6a8` | Subtle borders on tinted surfaces |

### Neutral tokens (WP-standard)

| Value | Use |
|-------|-----|
| `#dcdcde` | All borders — cards, inputs, table, pagination, dividers |
| `#f6f7f7` | Surface 2 — card headers, table `thead`, input background, pagination bg |
| `#f0f0f1` | Row dividers inside tables and toggle lists |
| `#1d2327` | Primary text |
| `#646970` | Secondary text, help text, column labels |
| `#fff` | Card / table body background |

### Shape & shadow

| Property | Value |
|----------|-------|
| Card / table border-radius | `6px` |
| Input / button border-radius | `3px` |
| Icon container border-radius | `6px` |
| Card box-shadow | `0 1px 3px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.04)` — `$lswl-shadow-card` |
| Input inset shadow | `inset 0 1px 2px rgba(0,0,0,0.04)` |
| Focus ring colour | `rgba(93, 158, 63, 0.2)` — `$lswl-lime-ring` |
| Transition | `160ms cubic-bezier(0.25,0.46,0.45,0.94)` on border-color, box-shadow, background |

### Focus ring (all interactive elements)

```scss
border-color: $lswl-lime;
box-shadow: 0 0 0 2px $lswl-lime-ring;
outline: none;
```

### Page layout

PHP `render_page()` must output `<div class="wrap"><div id="plugin-root"></div></div>` — the `.wrap` class provides standard WP admin margins and max-width.

```scss
.plugin-admin {
    margin-top: 24px;  // matches WP admin spacing
}
```

### Page header

```scss
.plugin-admin__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px solid #dcdcde;
}

.plugin-admin__title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-size: 1.375rem;
    font-weight: 600;
    color: #1d2327;

    &::before {                   // 4px lime accent bar
        content: '';
        display: block;
        width: 4px;
        height: 1.3em;
        background: $lswl-lime;
        border-radius: 2px;
        flex-shrink: 0;
    }
}
```

### Tab panel (WP TabPanel override)

```scss
.plugin-admin .components-tab-panel__tabs {
    padding: 0;
    border-bottom: 1px solid #dcdcde;
    margin-bottom: 20px;

    button.components-tab-panel__tabs-item {
        border: none;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        margin-bottom: -1px;
        padding: 0 18px;
        height: 40px;
        font-size: 13px;
        font-weight: 500;
        color: #646970;
        background: none;
        box-shadow: none;

        &:hover:not(.is-active) { color: #1d2327; background: none; }
        &.is-active { color: #1d2327; border-bottom-color: $lswl-lime; font-weight: 600; }
    }
}

.plugin-admin .components-tab-panel__tab-content { padding: 0; }
```

### Filter controls row (native HTML)

Use native `<input type="search">` and `<select>` — never WP `SearchControl`/`SelectControl`.

```scss
// Shared base — apply to search, select, and reset button
.plugin-filter-search,
.plugin-filter-select,
.plugin-filter-reset {
    height: 40px;
    border: 1px solid #dcdcde;
    border-radius: 3px;
    padding: 0 8px;
    font-size: 13px;
    background: #f6f7f7;
    color: #1d2327;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
    transition: border-color 160ms, box-shadow 160ms;
    outline: none;
    box-sizing: border-box;

    &:focus {
        border-color: $lswl-lime;
        box-shadow: 0 0 0 2px $lswl-lime-ring;
    }
}

.plugin-filter-search { width: 200px; flex-shrink: 0; }
.plugin-filter-select { min-width: 150px; flex-shrink: 0; cursor: pointer; }
.plugin-filter-reset  { cursor: pointer; white-space: nowrap; }

// Reset button — shown only when filters are active (inputValue !== '' || status !== 'all')
// Rendered as plain <button> to the left of the search input, inside the __filters group.

// Layout: toggle buttons left, filters right
.plugin-controls-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;

    &__filters { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-shrink: 0; }
}
```

### Data table

```scss
.plugin-table-wrap {
    position: relative;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.04);

    &--fetching { pointer-events: none; opacity: 0.6; transition: opacity 0.15s; }
}

.plugin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin: 0;
    border: none;

    thead tr { background: #f6f7f7; }

    thead th {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #646970;
        padding: 14px;
        border-bottom: 1px solid #dcdcde;
        text-align: left;
        white-space: nowrap;
    }

    th, td {
        padding: 12px 14px;
        border-bottom: 1px solid #f0f0f1;
        vertical-align: middle;
    }

    tbody {
        tr:last-child td { border-bottom: none; }

        tr {
            transition: background 160ms cubic-bezier(0.25,0.46,0.45,0.94);
            &:nth-child(odd) td  { background: #f6f7f7; }
            &:nth-child(even) td { background: #fff; }
            &:hover td           { background: #f5fbf2; }  // lime tint on hover
        }
    }
}
```

### Pagination (number-based, no per-page select)

Layout: total count left, numbered buttons right. Use a delta=2 range-with-dots algorithm.

```scss
.plugin-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-top: 1px solid #dcdcde;
    background: #f6f7f7;

    &__total { font-size: 12px; color: #646970; }
    &__right  { display: flex; align-items: center; gap: 4px; }

    &__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 6px;
        font-size: 12px;
        color: #1d2327;
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 3px;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s, color 0.15s;

        &:hover:not(:disabled) { background: $lswl-lime-light; border-color: $lswl-lime; color: $lswl-lime; }
        &.is-current           { background: $lswl-lime; border-color: $lswl-lime; color: #fff; cursor: default; }
        &:disabled:not(.is-current) { opacity: 0.4; cursor: default; }
    }

    &__dots { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 30px; font-size: 12px; color: #646970; user-select: none; }
}
```

Default page size: **20**. No per-page selector.

### Settings cards

```scss
.plugin-settings-card {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.04);

    &__header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 20px;
        border-bottom: 1px solid #dcdcde;
        background: #f6f7f7;
    }

    &__icon {
        width: 28px; height: 28px;
        background: $lswl-lime-light;
        border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        color: $lswl-lime-darker;
        flex-shrink: 0;
    }

    &__title { font-size: 13px; font-weight: 600; color: #1d2327; margin: 0; }

    &__body { padding: 20px 24px; }
}
```

WP component overrides inside `.plugin-settings-card__body`:

```scss
// Labels & help
.components-base-control__label { font-size: 13px; font-weight: 500; color: #1d2327; margin-bottom: 5px; }
.components-base-control__help  { font-size: 12px; color: #646970; margin-top: 5px; }

// Text inputs
.components-text-control__input {
    height: 36px; padding: 0 10px;
    border: 1px solid #dcdcde; border-radius: 3px;
    background: #f6f7f7; color: #1d2327; font-size: 13px;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
    transition: border-color 160ms, box-shadow 160ms;
    outline: none; width: 100%; box-sizing: border-box;
    &:focus { border-color: $lswl-lime; box-shadow: 0 0 0 2px rgba(93,158,63,0.2); }
}

// Textareas
.components-textarea-control__input {
    padding: 9px 10px;
    border: 1px solid #dcdcde; border-radius: 3px;
    background: #f6f7f7; color: #1d2327; font-size: 13px;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
    resize: vertical; min-height: 96px; width: 100%;
    &:focus { border-color: $lswl-lime; box-shadow: 0 0 0 2px rgba(93,158,63,0.2); }
}

// Toggle rows
.components-toggle-control {
    padding: 14px 0;
    border-bottom: 1px solid #f0f0f1;
    margin-bottom: 0 !important;
    &:last-child  { border-bottom: none; padding-bottom: 0; }
    &:first-child { padding-top: 0; }
}

// Toggle track colour
.components-form-toggle__track                              { background: #c8cace; }
.components-form-toggle.is-checked .components-form-toggle__track { background: $lswl-lime; }
```

### ColorField component (Gutenberg colour picker)

Use `Dropdown` + `ColorPicker` + `ColorIndicator` from `@wordpress/components` — not a plain `<input type="color">`.

```jsx
<Dropdown
    popoverProps={ { placement: 'bottom-end' } }
    renderToggle={ ( { isOpen, onToggle } ) => (
        <Button onClick={ onToggle } aria-expanded={ isOpen }
            className={ `lswl-color-field__trigger${ isOpen ? ' is-open' : '' }` }>
            <ColorIndicator colorValue={ value } />
            <span className="lswl-color-field__hex">{ value }</span>
        </Button>
    ) }
    renderContent={ () => (
        <ColorPicker
            color={ value }
            onChange={ ( color ) => {
                const hex = typeof color === 'string' ? color : color?.hex;
                if ( hex ) onChange( hex );
            } }
            enableAlpha={ false }
            copyFormat="hex"
        />
    ) }
/>
```

`onChange` must handle both modern WP (hex string) and legacy (object with `.hex`) API.

Reset button: show when `value !== resetTarget` (where `resetTarget = allowEmpty ? '' : defaultValue`). Reset calls `onChange( resetTarget )`.

```scss
.lswl-color-field {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
    &:last-child { border-bottom: none; }

    &__row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    &__label { font-size: 13px; font-weight: 500; color: #1d2327; }
    &__controls { display: flex; align-items: center; gap: 4px; }

    &__trigger {
        display: flex; align-items: center; gap: 6px;
        height: 28px; padding: 0 8px;
        border: 1px solid #dcdcde; border-radius: 3px;
        background: #f6f7f7; font-size: 12px; font-family: monospace;
        cursor: pointer; color: #1d2327;
        &.is-open { border-color: $lswl-lime; box-shadow: 0 0 0 2px rgba(93,158,63,0.2); }
    }
}
```

### RangeControl lime accent override

WP `RangeControl` reads `--wp-admin-theme-color` internally for the slider fill and thumb. Override at the wrapper scope; also reset `-webkit-appearance` on the thumb for WebKit:

```scss
.lswl-range-wrap {
    padding: 8px 0 0;
    border-bottom: 1px solid #f0f0f1;
    --wp-admin-theme-color: #{$lswl-lime};
    --wp-admin-theme-color--rgb: 93, 158, 63;

    &--last,
    &:last-child { border-bottom: none; }

    .components-range-control { margin-bottom: 0; padding-bottom: 12px; }
    .components-range-control__wrapper { margin-top: 4px; }

    .components-range-control__slider {
        accent-color: $lswl-lime !important;

        &::-webkit-slider-thumb {
            -webkit-appearance: none !important;
            appearance: none !important;
            width: 14px; height: 14px; border-radius: 50%;
            background-color: $lswl-lime !important;
            border: 2px solid $lswl-lime-dark !important;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }

        &::-moz-range-thumb {
            background-color: $lswl-lime !important;
            border-color: $lswl-lime-dark !important;
        }
    }

    .components-range-control__number:focus {
        border-color: $lswl-lime !important;
        box-shadow: 0 0 0 2px rgba(93,158,63,0.2) !important;
        outline: none !important;
    }
}
```

Always add `__nextHasNoMarginBottom` prop to `RangeControl` (deprecation since `@wordpress/components` 6.7).

### Primary button (WP `is-primary` override)

```scss
.plugin-admin .components-button.is-primary {
    background: $lswl-lime;
    border-color: $lswl-lime-dark;
    color: #fff;
    box-shadow: none;
    font-weight: 500;
    border-radius: 6px;

    &:hover:not(:disabled) {
        background: $lswl-lime-dark;
        border-color: $lswl-lime-darker;
        color: #fff;
        box-shadow: 0 2px 8px rgba(93,158,63,0.26);
    }

    &:focus:not(:disabled) { box-shadow: 0 0 0 2px #fff, 0 0 0 4px $lswl-lime; }
    &:disabled { opacity: 0.45; }
}
```

### SCSS file structure

```
src/admin/scss/
├── _variables.scss   ← brand + neutral tokens, shadows, radii
├── _mixins.scss      ← shared patterns: lswl-card, lswl-input-base, lswl-lime-focus, lswl-lime-pill
└── index.scss        ← all component styles
```

Import order at top of `index.scss`:
```scss
@use 'variables' as *;
@use 'mixins' as *;
```

### Shared SCSS mixins

Four mixins in `_mixins.scss` eliminate repetition across `index.scss`:

| Mixin | Use |
|-------|-----|
| `@include lswl-card` | White bg, `#dcdcde` border, 6px radius, `$lswl-shadow-card` — used on table wrap and settings cards |
| `@include lswl-input-base` | Border, bg, inset shadow, transition, outline reset — base for all filter inputs and color picker trigger |
| `@include lswl-lime-focus` | `border-color: $lswl-lime; box-shadow: 0 0 0 2px $lswl-lime-ring` — applied on `:focus` |
| `@include lswl-lime-pill` | `$lswl-lime-darker` text on `$lswl-lime-light` bg, 20px radius, fw 600 — selection counts, product badges |
