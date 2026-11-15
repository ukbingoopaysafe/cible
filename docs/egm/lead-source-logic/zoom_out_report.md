# Stage 5 — Initial Zoom Out Review

* **Target**: `lead-source-logic.md`
* **Feature range**: 1–183
* **Detailed range**: 184–260
* **Reviewers**: `zoom_out` completed; `inventive_catalyst` completed on retry with no findings.

## Findings

### Major

#### `zoom_out-1-1` — The approved source-list change is missing from the governing decision record

The design removes phone calls, direct visits, and WhatsApp from new source choices and deactivates those catalog rows (`lead-source-logic.md:11, 26, 113`). The current decision `D-051` still approves all three as initial source values (`docs/DECISIONS.md:71`), and acceptance test `AT-158` still expects the approved initial source list (`docs/ACCEPTANCE_TESTS.md:240`). The decision log requires approved changes to be recorded with their reason and effect (`docs/DECISIONS.md:221`).

**Recommended remedy**: Record a superseding decision for `D-051` with the awareness-versus-contact-method rationale, and update `AT-158` so the decision and acceptance contract match this design.

### Nits

#### `zoom_out-1-2` — «أخرى» is represented twice

The design adds `type = other` and also requires `is_other` to remain synchronized (`lead-source-logic.md:193, 218`). The existing form and request service use `is_other` to decide whether to show and require the description (`app/Filament/Support/RequestFormSchema.php:202–203`; `app/Services/RequestService.php:374–377`). Keeping two fields for the same behavior can let them drift.

**Recommended remedy**: Make the new type authoritative for the «أخرى» behavior and avoid maintaining duplicate state, unless an explicit compatibility requirement justifies retaining `is_other`.

#### `zoom_out-1-3` — The manager can create more `other` sources although `other` is defined as the built-in «أخرى» option

The type table defines `other` as the built-in option (`lead-source-logic.md:80`), while manager-created source types include `other` (`lead-source-logic.md:84, 218`). The design does not say whether additional `other` labels each require a description or whether the type is reserved for the built-in row.

**Recommended remedy**: Reserve `other` for the built-in «أخرى» option, or explicitly define the behavior and validation for manager-created `other` options.
