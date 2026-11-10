# Independent Goldfish Review — Date and Time Picker Popup Remediation

**Target:** `concepts_brief.md`<br>
**Mode:** BRAINSTORM / readiness audit<br>
**Isolation:** Seven native Codex subagents were started with `fork_turns: "none"`. Each was asked to read its assigned EGM persona and the target/context files. The parent transcript was not supplied. All seven returned individual findings.

## Individual Findings

### `egm-design` — `picker_design_critic` — completed

* **Major — Browser floor and fallback were unspecified.** Define the supported versions, detect missing Popover support, and specify the unsupported path.
* **Major — Popover and Filament lifecycles could diverge.** Synchronize every open/close route idempotently; show only after Alpine reveals the panel; hide on outside click, Escape, navigation, and teardown.
* **Major — The panel click-stop guard conflicts with restoring normal event ancestry.** Remove it and verify inside clicks, outside clicks, and modal event handling.
* **Major — Full visibility cannot be guaranteed when panel height exceeds available viewport height.** Define the supported viewport and compact-density behavior; position against visible viewport bounds.

### `egm-codebase-details` — `picker_codebase_details` — completed

* **Major — The in-tree design needs a concrete integration point.** Preserve `DateTimePicker` state and focus behavior; do not retain `contains()` monkey patches or portal-only key handlers.
* **Major — Tests currently require the portal.** Replace assertions for `appendChild`, containment patches, and the replaced toggle with assertions for in-tree placement and state synchronization; add browser interaction coverage.
* **Major — Initialization failures are silent.** Define the browser baseline and a non-sensitive diagnostic for unsupported API or failed initialization.
* **Assessment:** The approach can preserve `Y-m-d` / `Y-m-d H:i` and Filament state if Alpine remains authoritative; reviewer did not run tests or a browser.

### `egm-zoom-out` — `picker_zoom_out` — completed

* **Major — Short viewports need a concrete no-scroll rule.** Specify panel bounds, minimum supported size, compact mode, and the viewport model.
* **Major — Interaction and focus behavior need explicit checks.** Cover keyboard and pointer open/close, Escape, outside click, Tab order, focus restore, date selection, time selection, modal close, and field removal.
* **Major — Picker variants are ambiguous.** Name date-only, date-time, seconds-enabled, and time-only scope.
* **Nit — RTL placement needs edge tests.** Check both horizontal edges and ensure time controls remain LTR.

### `egm-comprehension` — `picker_comprehension` — completed

* **Major — Dismissal semantics were unclear.** Define trigger toggle, date selection, Escape, outside click, programmatic close, and modal close.
* **Major — Visual acceptance terms were subjective.** Define viewports, clipping bounds, grid geometry, and which part may scroll.
* **Major — Canonical state was not described precisely.** State the formats and noon/midnight mapping.
* **Nit — Browser versions were unpinned.**
* **Nit — Screenshot evidence was not attached.** The brief now describes the specific visible overlap and compressed weekday row.

### `egm-implementation-readiness` — `picker_readiness_brainstorm` — completed

* **Major — Alpine setup/teardown and field variants were unspecified.** Cover initial render, dynamic modals, navigation, and removal; name supported variants.
* **Major — Popover state authority and ordering were unspecified.** Make Filament state authoritative and verify native state matches it after every interaction.
* **Major — Browser floor and fallback were undecided.**
* **Major — Responsive geometry and small-viewport behavior lacked dimensions.** Define width, inset, placement, compact mode, and visual viewport bounds.
* **Major — No executable browser test plan existed.** Define browser matrix, fixtures, viewports, geometry assertions, interaction cases, and state assertions.

### `egm-structural` — `picker_structural` — completed

* **Major — “Existing modal and page fields” did not identify picker variants.** Enumerate variants and explicitly exclude the time-only path.
* **Major — Browser support was not a resolved decision.** Specify versions and unsupported behavior.
* **Nit — Visual checks were not measurable.** Add viewport sizes and panel/grid thresholds.

### `egm-inventive-catalyst` — `picker_inventive_catalyst` — completed

* **Major — Top-layer placement must not replace Filament's date and focus behavior.** Let Popover solve only clipping; keep Filament's selection/state logic and use its existing placement behavior where possible.
* **Major — The installed Filament markup contains no AM/PM controls.** Verify the application decorator supplies them and that period changes update canonical state without closing the picker.

## Consolidated Resolution

All findings were accepted as useful implementation constraints. The brief now names the date-bearing picker variants and excludes time-only fields, sets an explicit evergreen browser baseline, makes `isPanelOpen` authoritative, defines close/open behavior, requires removal of the panel click-stop and containment patches, specifies viewport sizes and geometry, distinguishes calendar scrolling from long option-list scrolling, and defines canonical state checks for `12 AM` and `12 PM`.

The preferred approach remains the in-tree Popover API because it can lift the existing panel above clipping while preserving its DOM ancestry and Filament's scoped calendar styles. The implementation plan must still verify the interaction between top-layer placement and the installed Filament positioning code in a real browser. No production source files were changed during this review.
