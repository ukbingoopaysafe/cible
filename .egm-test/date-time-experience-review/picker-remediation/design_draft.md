# Date and Time Picker Popup Remediation

* **Author:** ukbingoopaysafe
* **Status:** Approved for implementation
* **Date:** 2026-11-10

## Overview

The shared Filament calendar popup is visually broken inside CRM modals. The panel is reparented to `document.body`, which removes the ancestor styles Filament uses for its calendar layout. The popup then overlaps content, shows its screen-reader description, crowds weekday labels, and can be clipped or affect modal scrolling.

Keep the existing panel in its original picker tree and use the browser Popover API to render it in the top layer. Filament continues to own its calendar state and interactions. A small adapter synchronizes native popover visibility and viewport placement with Filament's state.

## The Problem

The current portal solves clipping by moving the panel outside its picker. Filament's calendar CSS is nested under `.fi-fo-date-time-picker`, so the moved panel loses the rules for the panel header, hidden calendar description, weekday labels, seven-column calendar, and time controls. The portal then recreates selected rules and patches focus containment and positioning. The latest screenshot still shows the resulting overlap and crowded calendar content.

The existing implementation also adds a blanket `click.stop` guard. It is unnecessary while the panel remains inside the picker and can suppress expected ancestor event handling. The user needs one compact, opaque calendar that floats above a modal without changing its scroll position.

## Goals

* Keep the panel fully inside its original Filament picker subtree and outside modal clipping through the top layer.
* Use a solid surface, max width of 16rem, consistent 40px month/year controls, seven equal weekday/date columns, and no scrolling on the calendar surface.
* Keep the complete panel within an 8px inset from the visible viewport at 360x320, 360x640, 390x844, 768x1024, 1280x720, and 1440x900 CSS pixels. Use a compact density below 420px viewport height.
* Opening and using the popup must not move the modal's scroll position. AM/PM and time-option interactions must keep the popup open; clicking outside, Escape, date selection configured to close, modal closure, and navigation must close it.
* Keep Filament's `isPanelOpen` state authoritative, preserve focus restore and calendar keyboard navigation, and retain the existing 12-hour controls.
* Preserve date-only `Y-m-d`, date-time `Y-m-d H:i`, `Africa/Cairo`, disabled-date rules, validation, and seconds when enabled. Verify `12 AM → 00`, `12 PM → 12`, and that AM/PM changes only the hour.
* Support Chrome/Edge 114+, Firefox 125+, and Safari 17+. Feature-detect the Popover API before adding `popover="manual"` or applying top-layer styles; below that floor, leave the panel and Filament behavior untouched and accept that ancestor clipping may remain.

## Non-Goals

* Change locale resolution, database values, API contracts, timezone behavior, exports, Arabic CRM labels, or business rules.
* Change time-only fields (`hasDate=false`), which use Filament's separate floating path.
* Add a calendar package, rewrite Filament's calendar, or redesign modal content outside the popup.

## Critical User Journeys

### Open and choose a date/time in a modal

* **Given**: A date or date-time field is visible in a Filament modal in a supported browser.
* **When**: The user opens the field, changes the month/year or date, enters hour/minute, and selects AM or PM.
* **Then**: The opaque panel remains fully within the visible viewport; the modal scroll position does not change; inside clicks do not close it; `isPanelOpen` and the native popover state agree; canonical state remains unchanged in format and timezone.

### Dismiss and restore focus

* **Given**: The calendar popup is open in a page or modal field.
* **When**: The user presses Escape, clicks outside, selects a date when selection is configured to close, closes the modal, or navigates with Livewire.
* **Then**: Both Filament and Popover state close, Filament's focus restoration remains active where configured, and no detached handler or stale popover remains.

## Technical Plan

The panel node remains under the Alpine component. The adapter adds `popover="manual"` only when the browser exposes `showPopover()` and `hidePopover()`, then uses the top layer while preserving DOM ancestry and event paths. Filament's `isPanelOpen` is authoritative. The adapter wraps (and delegates to) Filament's existing `togglePanelVisibility()` and `closePanel()` methods; it does not reimplement their state, focus, validation, or generation logic. After the original open method's Alpine/animation-frame work has installed Floating UI's positioning cleanup, the adapter invalidates any pending Floating UI write, stops that positioning cleanup, shows the popover, and takes over only viewport coordinates. Closing hides the native popover before delegating to Filament's close method. This order preserves Filament focus restoration and prevents a closed panel from remaining in the top layer. It does not reparent the node, override `contains()`, stop panel clicks, or install a second focus trap.

The top-layer panel uses `position: fixed` coordinates in the layout viewport. The positioner uses `visualViewport.offsetLeft`, `offsetTop`, `width`, and `height` when available (and the layout viewport dimensions otherwise), keeping its bounds in the same coordinate space as fixed positioning. It reads the trigger and panel rectangles, selects below when it fits and above otherwise, then clamps the panel to an 8px visible-viewport inset. It aligns to the trigger's inline start and listens to document scroll, window resize, `visualViewport` resize/scroll, and trigger/panel size changes. If neither side fits, it clamps the complete panel inside the viewport so it may overlap nearby form content but does not scroll the modal. For viewports shorter than 420px, the compact rules use 8px panel padding, 32px month/year controls, 16px weekday line height, 22px day rows, 2px calendar gaps, and 28px time controls; a six-week month fits within 304px total popup height at 360x320. The calendar surface never gets `overflow:auto`; the hour/minute option list may scroll to expose its full option set.

```text
Filament trigger
    │
    ├── Filament Alpine state: isPanelOpen (authoritative)
    │       ├── existing date/time state, validation, focus, keyboard behavior
    │       └── small adapter → showPopover() / hidePopover()
    │
    └── original panel remains in picker DOM
            ├── scoped Filament CSS remains active
            ├── Popover API renders panel in top layer
            └── viewport positioner keeps the full panel visible
```

### Open and close contract

* Opening calls Filament's existing toggle so it initializes the focused date, day grid, focus generation, and canonical state. After Alpine has applied `x-show`, the adapter calls `showPopover()` and positions the panel.
* Closing calls `hidePopover()` before Filament's close method conceals the panel. Filament remains responsible for focus restoration and internal generation counters.
* Filament's existing root handlers remain responsible for Escape, outside click, focus-out, date-selection close behavior, Livewire navigation, and destruction. The adapter ensures each of these also leaves the popover closed.
* A click inside the panel bubbles normally through its original DOM ancestry. It does not count as outside and does not close the panel.

## Alternatives Considered

* **Keep the portal and recreate all Filament CSS and event containment.** Rejected because the latest screenshot shows the recreated rules remain incomplete, while the approach requires custom positioning and patched containment.
* **Use only a higher z-index or relaxed modal overflow.** Rejected because z-index cannot escape clipping ancestors, and changing modal overflow can change modal scrolling.
* **Replace the calendar with a separate package/custom calendar.** Rejected because it would duplicate Filament's keyboard, disabled-date, validation, and canonical-state behavior for a layout issue.
* **Keep the panel in-tree with the Popover API.** Selected because it preserves Filament's scoped styles and event ancestry while escaping clipping. The trade-off is a small lifecycle/positioning adapter and an explicit evergreen browser floor.

## Non-Functional Requirements

* No date/time values, browser locale, or field contents are added to diagnostics.
* Unsupported-browser detection must avoid a runtime exception and leave Filament's original in-tree behavior available.
* Dynamic Livewire/modal insertion and removal must not accumulate observers or handlers.

## Detailed Implementation Plan

### Target Files to Modify

#### 1. `app/Support/TwelveHourDateTimePickerView.php`

* **Type / Scope**: Filament embedded picker HTML decorator.
* **Proposed Changes**: Add a stable Cible marker only to date-bearing panels, pass `$field->hasDate()` from the Blade override, and add `popover="manual"` in JavaScript only after feature detection. Remove `x-on:click.stop="true"`. Leave the existing hour/minute/AM-PM state mapping and seconds behavior unchanged. Time-only controls keep Filament's existing floating-panel path and receive no marker, popover attribute, or lifecycle wrapper.

#### 2. `resources/js/filament/admin/date-time-picker-portal.js`

* **Type / Scope**: Existing picker lifecycle/positioning adapter; keep the registered Vite entry to avoid unrelated panel-provider churn.
* **Proposed Changes**: Remove `document.body.appendChild`, logical `.contains()` wrappers, portal-specific Tab/Escape/focus-out listeners, and detached-node removal. Keep the panel under its picker root. Discover only marked `hasDate=true` panels. Feature-detect Popover API and do not mutate unsupported browsers. Wrap Filament `togglePanelVisibility()` and `closePanel()` while delegating to each original method; after Filament's opening frame, invalidate its pending Floating UI position result, stop its auto-update cleanup, show the native popover, and run one fixed-position adapter. Hide the popover before Filament closes, so all existing Filament dismissal paths, state, focus restore, and generation counters remain authoritative. Use RTL-aware horizontal alignment, visual viewport bounds, 8px inset, below/above selection, compact-height mode, and cleanup on modal close, navigation, and root removal. Do not replace Filament date-selection, validation, keyboard, or focus-restore behavior.

#### 3. `resources/css/filament/admin/theme.css`

* **Type / Scope**: Shared Filament admin theme.
* **Proposed Changes**: Remove portal-only `.fi-cible-date-time-picker-panel` layout rules. Add solid, opaque top-layer styles to the marked open panel while retaining the original picker ancestor for Filament's scoped calendar description and grid rules. Preserve seven equal columns, align month/year inputs at equal 40px heights, keep the calendar surface overflow-visible, and define the compact-height dimensions specified above. Keep time controls LTR and preserve existing Cible colors/focus styling. The in-tree non-Popover fallback receives no fixed-position overrides.

#### 4. `tests/Feature/DateTimePickerEmbeddedViewTest.php`

* **Type / Scope**: PHP feature tests for rendered picker markup and adapter contract.
* **Proposed Changes**: Replace assertions requiring body reparenting and patched containment with PHP source/markup contract assertions for hasDate marker gating, no server-side click-stop/popover mutation, feature detection, wrapper delegation, lifecycle cleanup, viewport clamping, and no modal scroll writes. Keep assertions for Filament markup, canonical state mapping, date-only behavior, and seconds-enabled rendering. These tests do not prove runtime browser geometry or interaction.

### Verification Plan

* **Targeted PHP tests**: `php artisan test --compact tests/Feature/DateTimePickerEmbeddedViewTest.php`.
* **Full PHP suite**: `php artisan test --compact`.
* **Asset build**: `npm run build`.
* **Static checks**: `node --check resources/js/filament/admin/date-time-picker-portal.js`, `php -l app/Support/TwelveHourDateTimePickerView.php`, and `git diff --check`.
* **Browser acceptance**: Release gate: Chrome/Edge 114+, Firefox 125+, and Safari 17+. Test 360x320, 360x640, 390x844, 768x1024, 1280x720, and 1440x900. For a modal field and a page field, assert computed background is opaque, seven equal weekday/date columns, no calendar-surface scrollbar, panel bounds within 8px of the visible viewport, and identical modal scroll positions before/after open. Exercise trigger toggle, calendar navigation, date selection, typing hour/minute, hour/minute option lists, AM and PM, Escape, outside click, Tab/Shift+Tab, focus restore, modal close, Livewire navigation, and dynamic field teardown. Assert date/time formats and noon/midnight canonical values. Test an unsupported-browser stub to prove no popover attribute, fixed-position class, or thrown error is introduced. If Safari is unavailable in the implementation environment, record it as pending release-gate verification rather than passing it by inference.

### Implementation phases and checkpoints

1. **In-tree markup and lifecycle**: Add `hasDate` plumbing and marker gating, implement the feature-detected Popover lifecycle adapter, and update the PHP markup/source-contract tests. Checkpoint: targeted PHP tests, PHP syntax check, and JS syntax check pass; confirm time-only fields have no marker and date-only fields do.
2. **Popup styling and placement**: Remove portal-only styling, add opaque compact calendar dimensions and fixed viewport positioning, and verify the viewport geometry and interaction matrix in supported browsers. Checkpoint: all six viewport cases and modal/page scenarios meet the browser acceptance criteria before treating visual fit as complete.
3. **Project regression and drift**: Run the full PHP suite and production asset build, inspect the final diff, then run EGM Drift Report against this remediation design. Checkpoint: no unexplained code/design drift; unsupported browser and unavailable Safari checks are explicitly reported as pending where applicable.

## Additional Resources

* [Approved Date & Time Experience design](../date-time-experience-design.md)
* [Cible UI/UX visual identity plan](../../../../docs/UI_UX_VISUAL_IDENTITY_PLAN.md)
* Installed Filament picker source: `vendor/filament/forms/src/Components/DateTimePicker.php`
* Installed Filament picker styles: `vendor/filament/forms/resources/css/components/date-time-picker.css`

*Drafted with EGM*
