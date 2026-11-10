# Consolidated EGM Review — Date & Time Experience Redesign

**Target:** `.egm-test/date-time-experience-review/date-time-experience-design.md`<br>
**Review mode:** Consolidated report with all review rubrics<br>
**Feature range:** Lines 1–113<br>
**Detailed implementation range:** Lines 114–198<br>
**Context:** Approved `.egm-test/date-time-experience-review/feature-hypothesis.md` and its linked in-workspace project context<br>
**Completion:** Stages 5–9 completed by nine native, fresh-context Goldfish subagents. No application files were modified; no tests or builds were run.

## Review result

The design is **not implementation-ready yet**. One blocker remains: the installed Filament picker has no AM/PM selection control, and the design does not specify the custom interaction needed to deliver the required 12-hour workflow. Several major details also need to be made concrete before an implementer can produce consistent behavior.

## Findings by priority

### 🔴 Blocker

**B1 — Specify the required 12-hour picker implementation.** The installed Filament `DateTimePicker` uses an hour input from 0–23. `displayFormat()` affects the displayed string but does not add AM/PM selection. Central defaults or CSS alone cannot satisfy the requirement. Name the first-party custom picker surface and its file boundary, preserve the existing `Y-m-d H:i` state, and define interaction and tests for AM/PM plus noon and midnight round-trips.

### 🟡 Major

**M1 — Make centralized picker defaults effective for every field.** Set Filament date and date-time pickers to non-native mode centrally so the existing browser-native `deal_date` control uses the localized calendar. Pass only the picker bundle’s base language (`ar` or `en`) to Filament while retaining the recognized regional tag for server-side date order.

**M2 — Complete the presentation and parsing contract.** Specify exact date output patterns for supported regional locales; Gregorian calendar and Western-digit enforcement; date/time separator; 12-hour markers; relative phrases and thresholds; Arabic status-label treatment; accepted input; and null/invalid-value behavior. Add example-based expected results to the tests, including Arabic fallback and English regional ordering. Keep Cairo for instants and prevent date-only values from shifting.

**M3 — Define the actual typed XLSX conversion path.** Name Filament’s `Exporter::makeXlsxRow()` extension point and explain how the captured locale option reaches it through queued chunks and retries. Define the temporary-row representation, an allowlist distinguishing date-only fields from timestamps, the option key/shape, Arabic and English number formats, and the failure/compatibility path. Keep timestamp time components such as `created_at`; assert cell types, Cairo values, and displayed formats in generated workbooks.

**M4 — Name spreadsheet compatibility targets.** “Supported spreadsheet applications” is currently undefined. List the applications and versions used for acceptance checks, including how Arabic `ص` / `م` formats are validated. This is needed to close the XLSX compatibility risk.

**M5 — Localize dates nested in audit values.** Audit change before/after fields can include scalar timestamps such as `next_follow_up_at`; the current scalar formatter returns them raw. Include nested date/time values in the common presentation behavior.

**M6 — Apply bidi isolation across Filament-rendered content.** The scope must include table cells, detail values, option labels, and Blade history views. Reuse the existing `.bidi-isolate` and `[dir="ltr"]` hooks where they suffice, preserving Arabic surrounding labels.

**M7 — Use one exact locale-precedence rule throughout.** The approved rule is the first listed `Accept-Language` range, regardless of `q` weights; strip its optional parameter, normalize it, map `ar-*` / `en-*`, and otherwise fall back to Arabic without promoting later entries. Replace wording such as “primary preferred locale” if it could imply quality-weight selection.

**M8 — Resolve the PHP Intl requirement consistently.** The selected presenter uses `IntlDateFormatter`, and the implementation plan proposes requiring `ext-intl`, while the architecture text phrases deployment support conditionally. State it as an unconditional runtime requirement and align setup, deployment, and verification steps.

### 🟢 Nits

- Use one decision label (`Selected` or `Preferred`) consistently in Alternatives Considered.
- Replace “civil dates” with “date-only values remain calendar dates and do not shift across time zones.”
- Keep the detailed visual, keyboard, screen-reader, contrast, and viewport checklist in Verification Plan; refer to it instead of repeating it.
- Trim subjective/repeated UX language where it restates measurable accessibility and responsive outcomes. Retain the requested visual intent through concrete interaction and visual criteria.
- Consider moving file-by-file edits and exact test-class names out of the design and into the execution plan. The current detailed work breakdown makes the high-level design harder to scan.

## Cross-review disagreement and accepted scope

Both Zoom-Out reviews questioned whether localized typed XLSX formatting is justified by the original scheduling-pain statement and recommended deferring it. The approved feature hypothesis and the user’s explicit decisions require localized exports/reports, a locale snapshot for queued work, and typed Excel date/time cells. Therefore this is recorded as an accepted scope and compatibility cost, not as a reason to remove or defer export localization. The actionable concern is to specify and validate the bounded conversion path in M3–M4.

## Rubric outcomes

| Stage and rubric | Outcome |
|---|---|
| Stage 5 — Initial Zoom-Out | One major scope challenge and two nits. The export challenge is contested against the approved hypothesis; see accepted scope above. |
| Stage 6 — Comprehension | No findings. The reviewer accurately paraphrased the locale, RTL, Cairo, and queued export behavior. |
| Stage 7 — Design, structural, and style critique | Eight raw major findings and two nits, consolidated into B1 and M1–M4 plus M7–M8 and the relevant nits. |
| Stage 8 — Intermediate Zoom-Out | One repeated export scope challenge and two nits; the reviewer also confirmed the Filament XLSX seam should be named. |
| Stage 9 — Implementation readiness, codebase detail, and style | One blocker, nine raw major findings, and two nits, consolidated into B1–M6 and the related nits. |

## Traceability matrix

| Consolidated item | Raw reviewer findings |
|---|---|
| B1 | Stage 7 `egm-design` 1; Stage 9 `egm-implementation-readiness` 1; Stage 9 `egm-style` 1 |
| M1 | Stage 7 `egm-design` 2; Stage 9 `egm-codebase-details` 1–2 |
| M2 | Stage 9 `egm-implementation-readiness` 2; Stage 9 `egm-codebase-details` 3 |
| M3 | Stage 7 `egm-design` 3; Stage 7 `egm-structural` 1; Stage 8 `egm-zoom-out` feasibility note; Stage 9 `egm-implementation-readiness` 3; Stage 9 `egm-codebase-details` 6 |
| M4 | Stage 7 `egm-style` 3; Stage 9 `egm-implementation-readiness` 3 |
| M5 | Stage 9 `egm-codebase-details` 4 |
| M6 | Stage 5 `egm-zoom-out` 2; Stage 8 `egm-zoom-out` 2; Stage 9 `egm-codebase-details` 5 |
| M7 | Stage 7 `egm-style` 1 |
| M8 | Stage 7 `egm-style` 2 |
| Nit: decision terminology | Stage 7 `egm-style` 6 |
| Nit: date-only wording | Stage 9 `egm-style` 2 |
| Nit: repeated verification | Stage 7 `egm-style` 5; Stage 9 `egm-style` 3 |
| Nit: altitude and detail level | Stage 5 `egm-zoom-out` 3; Stage 8 `egm-zoom-out` 3 |
| Nit: visual copy | Stage 7 `egm-style` 5 |

## Individual reports and activity

- [Agent activity and context record](agent_activity.md)
- [Stage 5 — Initial Zoom-Out](zoom_out_report.md)
- [Stage 6 — Comprehension](comprehension_report.md)
- [Stage 7 — Critique](critic_report.md)
- [Stage 8 — Intermediate Zoom-Out](intermediate_zoom_out_report.md)
- [Stage 9 — Implementation Readiness](implementation_readiness_report.md)
