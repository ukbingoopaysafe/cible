# Independent Goldfish Review — Picker Remediation Design

**Target:** `design_draft.md`<br>
**Mode:** Final-design readiness review<br>
**Reviewers:** Three independent native Codex subagents, each started with a fresh conversation context and no Elephant history.<br>
**Status:** Findings resolved in the revised design; browser verification remains a release gate.

## Individual findings

### `picker_final_readiness_review` — completed

* **Major — Browser support baseline was inaccurate.** Firefox 114 does not provide the supported Popover API baseline. Use Firefox 125+.
* **Major — Filament and native popover lifecycle synchronization was underspecified.** Explicitly wrap and delegate Filament's open/close methods, cancel stale Floating UI positioning, hide before Filament closes, and cover all dismissal and teardown paths.
* **Major — Short viewport geometry was not measurable.** Specify the fixed-position coordinate basis, compact dimensions, and a six-week month height that fits at 360x320 without calendar scrolling.
* **Major — Time-only fields were not protected.** Pass `hasDate` into the decorator and apply the marker and adapter only to date-bearing fields.

### `picker_final_codebase_review` — completed

* **Major — Browser baseline needed correction.** Use Firefox 125+.
* **Major — The plan had no execution phases or checkpoints.** Add ordered lifecycle, styling/placement, and regression/drift phases with verifiable gates.
* **Major — Compact popup dimensions were not concrete.** Define exact compact sizing and the six-week viewport-height target.
* **Major — PHP source assertions could be mistaken for browser proof.** Label PHP checks as static markup/source-contract tests and keep geometry/interactions in a separate browser acceptance matrix.
* **Major — Coverage omitted fallback and seconds-enabled variants.** Add checks for unsupported-browser behavior and seconds-enabled rendering.

### `picker_final_style_review` — completed

* **Major — Unsupported-browser fallback was not implemented or testable.** Do not add the popover attribute or fixed styles unless the API is available; verify the untouched Filament path in an unsupported-browser stub.
* **Major — The adapter lifecycle hook was not concrete.** Specify the exact Filament methods wrapped, their delegation, Floating UI cancellation, and close ordering.
* **Major — No phases or checkpoints were defined.** Add implementation checkpoints for PHP/JS contracts, visual browser acceptance, and project regression.
* **Major — The plan did not separate static from runtime verification.** State what PHP tests establish and reserve visual/interactions for supported browsers.
* **Major — Safari could be inferred as passing when unavailable.** Make Safari 17+ a release gate and report it as pending when not executed.

## Consolidated resolution

| Finding | Resolution in `design_draft.md` |
|---|---|
| Firefox baseline | Corrected to Chrome/Edge 114+, Firefox 125+, Safari 17+. |
| Lifecycle synchronization | Adapter wraps and delegates `togglePanelVisibility()` and `closePanel()`, invalidates pending Floating UI writes, stops its auto-update cleanup, shows after Filament's open frame, and hides before delegating close. |
| Compact geometry and placement | Added layout-viewport fixed positioning with visual viewport bounds, 8px insets, 32px month/year controls, 16px weekday line height, 22px rows, 2px gaps, 28px time controls, and <=304px target height for six weeks at 360x320. |
| Date-only versus time-only | Added `hasDate` plumbing and marker gating; time-only fields stay on Filament's existing floating path. |
| Unsupported-browser fallback | Feature-detect before adding `popover="manual"` or fixed styling; leave Filament's original path untouched otherwise. Added fallback acceptance check. |
| Test claims and coverage | Separated PHP source-contract checks from runtime browser acceptance; added date-only, seconds-enabled, modal/page, viewport, interaction, and fallback cases. |
| Execution readiness | Added three phases with explicit checkpoints and a Safari release gate. |

The review identified no new product requirement conflicts. The changes above make the design implementation-ready; successful static tests do not replace the browser acceptance matrix.
