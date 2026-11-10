Status: resolved

# Goldfish Annotated Review Report

**Target:** `.egm-test/date-time-experience-review/date-time-experience-design.md` (`full file`; FeatureRange originally lines 1–113, DetailedRange originally lines 114–198)<br>
**Resolution instruction:** User authorized updating the finalized design and implementation plan, preserving approved requirements, and presenting the revised design for approval.<br>
**Scope:** Design artifacts only. No application source was modified and no tests/builds were run.

## Prioritized Findings & AI Suggestions

### [Issue 1] [`egm-implementation-readiness`] — Severity: Blocker

* **Affected Scope:** Original findings covered picker configuration and verification; revised controls are at [Request and component behavior](../date-time-experience-design.md#L63-L67), [picker view target](../date-time-experience-design.md#L146-L148), and [verification](../date-time-experience-design.md#L206-L212).
* **Discrepancy Description:** The installed Filament picker accepts hours 0–23 and has no AM/PM control. `displayFormat()` changes displayed text only; centralized defaults and CSS cannot provide the required interaction.
* **Rationale:** The source `vendor/filament/forms/src/Components/DateTimePicker.php` renders an hour input with `min=0` and `max=23`, and the existing Alpine state stores a 24-hour `hour`. The component declares the published embedded-view lookup path `filament-forms::components.date-time-picker`. An embedded-view override can change the user control while retaining Filament's calendar, field wrapper, and canonical state.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Preserve the approved 12-hour UI while keeping the existing 24-hour machine state and avoiding a new dependency.
* ***Proposed Edit:*** Applied under Technical Plan → Request and component behavior and Detailed Implementation Plan → Target Files. Add `resources/views/vendor/filament-forms/components/date-time-picker.blade.php`; replace visible hour/minute inputs with a 1–12 hour selector, 00–59 minute selector, and accessible AM/PM control. Map 12 AM to 0, 12 PM to 12, 1–11 AM unchanged, and 1–11 PM plus 12. Preserve `Y-m-d H:i`. Verify state boundaries and validation.

### [Issue 2] [`egm-design`, `egm-codebase-details`] — Severity: Major

* **Affected Scope:** Original findings covered picker defaults and locale mapping; revised details are at [Request and component behavior](../date-time-experience-design.md#L63-L67) and [picker configuration plan](../date-time-experience-design.md#L137-L148).
* **Discrepancy Description:** Filament defaults `native` to true, leaving the existing `deal_date` browser-native. The bundled picker looks up exact base-language keys, so region tags such as `ar-EG` and `en-GB` can fall back to English.
* **Rationale:** The picker must use Filament's localized calendar in every existing field, while the presenter retains region data for date ordering.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The installed component source verifies both behaviors. A shared configuration can disable native controls and split the normalized locale into a picker bundle key and a regional formatting tag.
* ***Proposed Edit:*** Applied: central defaults set `native(false)` for both picker types; pass only `ar` or `en` to Filament's locale map and retain the complete normalized region in the request-scoped context.

### [Issue 3] [`egm-implementation-readiness`, `egm-codebase-details`] — Severity: Major

* **Affected Scope:** Original findings covered presenter behavior and tests; revised details are at [presenter contract](../date-time-experience-design.md#L65-L67), [API plan](../date-time-experience-design.md#L139-L140), and [presenter tests](../date-time-experience-design.md#L190-L191).
* **Discrepancy Description:** Date patterns, relative phrases and thresholds, date-time separator, method signatures, input/null/invalid behavior, and expected test output were unspecified.
* **Rationale:** Without a fixed contract, surfaces can diverge and tests cannot establish whether locale output is correct.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The date style can follow ICU's locale-specific medium style; the time pattern can remain explicitly 12-hour and use Latin digits. Relative terms need a deterministic Cairo-calendar rule.
* ***Proposed Edit:*** Applied: define presenter methods and signatures; Gregorian/Latin-numbering locale extensions; `IntlDateFormatter::MEDIUM` regional date output; `hh:mm a`; ` · ` separator; localized today/yesterday/tomorrow on Cairo calendar-day boundaries; absolute output beyond those days; empty output for null; canonical string input formats only; and explicit `en-US`, `en-GB`, and `ar-EG` expected examples and test cases.

### [Issue 4] [`egm-design`, `egm-structural`, `egm-implementation-readiness`, `egm-codebase-details`] — Severity: Major

* **Affected Scope:** Original findings covered XLSX conversion; revised details are at [export design](../date-time-experience-design.md#L71-L79), [export implementation plan](../date-time-experience-design.md#L175-L186), and [export tests](../date-time-experience-design.md#L188-L193).
* **Discrepancy Description:** The design did not name the actual row conversion hook, intermediate representation, typed-column map, locale option shape, Excel number formats, or exact cell assertions.
* **Rationale:** Filament writes intermediate CSV rows as strings. The installed API exposes `Exporter::makeXlsxRow()` and exporter options; installed OpenSpout exposes `Row::fromValuesWithStyles()` for typed cells with per-column styles.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The codebase provides a feasible extension point without changing database values or API contracts. Column names and explicit canonical formats make rehydration bounded and testable.
* ***Proposed Edit:*** Applied: snapshot the normalized tag in `cible_datetime_locale`; define the current timestamp allowlist (`created_at` on both customer exporters; `next_follow_up_at`, `closed_at`, and `created_at` on both request exporters); serialize temporary rows as Cairo `Y-m-d H:i:s` (date-only as `Y-m-d`); override `makeXlsxRow()`; map selected row positions by `ExportColumn` name; create `DateTimeImmutable` values; and use `Row::fromValuesWithStyles()` with a locale-specific style per date/time cell. Assert type, numeric value, format, locale snapshot, chunks, and retry.

### [Issue 5] [`egm-style`, `egm-implementation-readiness`] — Severity: Major

* **Affected Scope:** Original findings covered spreadsheet compatibility; revised targets are at [export design](../date-time-experience-design.md#L77-L79) and [XLSX verification](../date-time-experience-design.md#L211-L211).
* **Discrepancy Description:** “Supported” spreadsheet applications and versions were not defined.
* **Rationale:** The Arabic AM/PM and regional display format require checks against named clients, and a SaaS web client cannot be pinned to a desktop build number.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Defining required clients and recording actual builds/settings gives release QA a clear pass condition.
* ***Proposed Edit:*** Applied: required targets are Microsoft Excel for Microsoft 365 on Windows Current Channel and Excel for the web current production release; record exact build and locale in release evidence. Check typed values and ordering in current stable LibreOffice Calc. A required-client format failure blocks release; do not silently convert the typed value to text.

### [Issue 6] [`egm-codebase-details`] — Severity: Major

* **Affected Scope:** Original finding covered audit event values; the revised touchpoint is [AuditEventResource](../date-time-experience-design.md#L158-L158), with coverage in the [verification plan](../date-time-experience-design.md#L210-L210).
* **Discrepancy Description:** Audit before/after payloads can include raw scalar timestamps such as `next_follow_up_at`; the current scalar formatter returns those values verbatim.
* **Rationale:** Localizing only the audit event's `created_at` leaves date-bearing audit content inconsistent.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The audit payload has known date-bearing keys that can be allowlisted without interpreting arbitrary strings as dates.
* ***Proposed Edit:*** Applied: localize allowlisted date/time keys nested in before/after values, including `next_follow_up_at`, while leaving unrelated scalar values and sortable underlying state unchanged. Add before/after feature assertions.

### [Issue 7] [`egm-codebase-details`] — Severity: Major

* **Affected Scope:** Original finding covered bidi handling; revised policy is at [rendering behavior](../date-time-experience-design.md#L67-L67), [code touchpoints](../date-time-experience-design.md#L83-L88), and [history views](../date-time-experience-design.md#L167-L172).
* **Discrepancy Description:** Bidi markup was described for Blade views but not all Filament-rendered values, including tables, details, and option labels.
* **Rationale:** English date/time content in the Arabic RTL shell can reorder or be announced incorrectly on those surfaces too.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Existing CSS already provides `.bidi-isolate` and `[dir="ltr"]` hooks, so the fix can be centralized without duplicating direction rules.
* ***Proposed Edit:*** Applied: include picker internals, Filament tables, details, option labels, and Blade history views in the bidi policy; reuse the existing CSS hooks and add only missing picker-specific direction. Verify visual order and assistive labels across each surface.

### [Issue 8] [`egm-style`] — Severity: Major

* **Affected Scope:** Original finding covered locale precedence wording; revised rule is at [goals](../date-time-experience-design.md#L17-L19), [resolver](../date-time-experience-design.md#L42-L42), and [alternatives](../date-time-experience-design.md#L102-L104).
* **Discrepancy Description:** “Primary preferred locale” can imply quality-weight selection, while the selected behavior uses the first listed range regardless of `q`.
* **Rationale:** A single explicit rule avoids different resolvers for initial page, Livewire, and export requests.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The approved decision is first-range selection; no preference-weight algorithm is required.
* ***Proposed Edit:*** Applied: describe first-range selection consistently, ignore `q` weights, normalize the first tag, and never promote later ranges.

### [Issue 9] [`egm-style`] — Severity: Major

* **Affected Scope:** Original finding covered PHP Intl deployment; revised requirement is at [runtime architecture](../date-time-experience-design.md#L57-L57), [Composer target](../date-time-experience-design.md#L128-L133), and [runtime verification](../date-time-experience-design.md#L207-L207).
* **Discrepancy Description:** The architecture made `ext-intl` conditional even though the selected presenter and implementation plan require it.
* **Rationale:** A missing extension would otherwise fail only at runtime or produce inconsistent output.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** `IntlDateFormatter` is the selected formatter, so the runtime requirement should be unconditional.
* ***Proposed Edit:*** Applied: require `ext-intl` in every environment and verify it in Composer, deployment images, and runtime checks.

### [Issue 10] [`egm-zoom-out` from Stages 5 and 8] — Severity: Major

* **Affected Scope:** Export goals and plan ([design goals](../date-time-experience-design.md#L17-L23), [Exports](../date-time-experience-design.md#L71-L79)).
* **Discrepancy Description:** The reviews questioned whether locale-aware typed XLSX conversion is needed to solve the stated scheduling problem and recommended deferring it because it adds compatibility work.
* **Rationale:** The substantive implementation-cost concern is valid, but the scope recommendation conflicts with reviewer-readable approved requirements that explicitly include localized exports and typed Excel date/time cells.
* ***AI Suggestion:*** CONTEST
* ***Justification:*** The approved feature hypothesis says to capture locale for generated reports/exports and queued work, and to prefer typed Excel cells with localized formats ([feature hypothesis](../feature-hypothesis.md#L27-L28)). The hypothesis also says not to silently convert typed cells to strings when writer support is uncertain ([feature hypothesis](../feature-hypothesis.md#L57)). Preserve the export scope; bound and test its complexity through Issues 4 and 5.
* ***Proposed Edit:*** No scope reduction. The finalized design retains localized reports/exports and typed cells, and now names the conversion seam, column map, and client acceptance gate.

## Nit findings

### [Nit 1] [`egm-zoom-out` from Stages 5 and 8] — Reuse existing bidi hooks

* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The project already has `.bidi-isolate` and `[dir="ltr"]` rules.
* ***Proposed Edit:*** Applied with Issue 7; use existing hooks and add only missing picker-specific rules.

### [Nit 2] [`egm-style`] — Use one decision label

* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The alternatives table mixed “Selected” and “Preferred.”
* ***Proposed Edit:*** Applied: accepted approaches now use “Selected.”

### [Nit 3] [`egm-style`] — Clarify date-only timezone behavior

* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** “Civil date” is less direct than “calendar date.”
* ***Proposed Edit:*** Applied: use “date-only values remain calendar dates and are never shifted by a timezone.”

### [Nit 4] [`egm-style`] — Remove repeated manual QA checklist

* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Manual visual, keyboard, screen-reader, contrast, and viewport checks were repeated across sections.
* ***Proposed Edit:*** Applied: the overview now points to the Detailed Verification Plan; the full manual checklist appears once there.

### [Nit 5] [`egm-style`] — Make UX direction observable

* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Several adjectives repeated accessibility and responsive criteria without defining what users see or do. The requested distinctive visual intent remains captured through the interaction, hierarchy, selected/current/unavailable states, palette, and focus criteria.
* ***Proposed Edit:*** Applied: describe the month/navigation hierarchy, visible selection states, 12-hour controls, Cible colors, RTL ordering, responsive behavior, and accessibility outcomes directly.

### [Nit 6] [`egm-zoom-out` from Stages 5 and 8] — Move implementation specifics out of the design

* ***AI Suggestion:*** DECLINE
* ***Justification:*** The file already separates its high-level technical design from a clearly headed `Detailed Implementation Plan`. The user expressly asked to update both the finalized design and its implementation plan, and the file-level details make that approved plan reviewable. No extra artifact or move is needed to resolve a blocker or major finding.
* ***Proposed Edit:*** No change.

## Resolution summary

Applied 13 proposed fixes (one blocker, eight major findings, and four unique nit fixes); contested one Zoom-Out scope recommendation based on the approved feature hypothesis; declined one nit about implementation detail placement; escalated none. The revised design remains pending owner approval. This report is marked resolved because all requested document edits are applied; no application implementation has begun.
