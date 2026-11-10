# Stage 9 — Implementation Readiness Review

**Target range:** Detailed Implementation Plan, lines 114–198<br>
**Context:** Approved feature hypothesis and its linked project context<br>
**Isolation:** Three native Codex subagents, each spawned with `fork_turns: "none"`; no Elephant conversation history was passed<br>
**Completion:** All three reviewers returned findings. No tests or builds were run, and no project files were modified.

## `egm-implementation-readiness` — `/root/egm_final_stage9_readiness` — completed

1. **Blocker — 12-hour picker interaction is not implementable from the listed configuration.** The installed Filament picker accepts hours 0–23 and has no AM/PM control; changing `displayFormat()` changes displayed text only. The proposed central defaults and CSS cannot meet the required interaction. Name a first-party custom picker implementation, its exact files, and tests for AM/PM selection and noon/midnight round-trips.
2. **Major — Presenter API and expected output are underspecified.** The plan does not set exact date patterns, relative phrases or thresholds, date-time separator, method signatures, or test outputs. Define behavior for supported locales, including “today” and past/future dates.
3. **Major — Typed XLSX conversion is underspecified.** Name the Filament extension point, define the date/time column map and typed conversion, specify Arabic and English Excel number formats, and assert cell types, displayed formats, Cairo values, chunks, and retries. The writer limitation path also needs definition.

**Read status:** No listed files were entirely skipped. Long output for `DECISIONS.md`, `RequestFormSchema.php`, `SectionRequestResource.php`, and `CustomerResource.php` was truncated; relevant date/time sections and search hits were reviewed. No phase-stability finding was reported.

## `egm-codebase-details` — `/root/egm_final_stage9_codebase` — completed

No Blocker findings.

1. **Major — Native picker behavior is unspecified.** Filament defaults `native` to `true`; the existing `deal_date` field therefore stays browser-native and bypasses localized Filament behavior. Set `native(false)` in the centralized defaults.
2. **Major — Regional tags do not match Filament's locale keys.** The bundled picker looks up exact keys such as `ar` and `en`, so `ar-EG` or `en-GB` can fall back to English. Map the picker locale to the base language while retaining the region for server-side date ordering.
3. **Major — Presentation and parsing rules are open.** Specify exact date patterns, Gregorian and Western-digit enforcement, relative output and Arabic status-label handling, accepted input, and null/invalid-value behavior. Add expected outputs to the tests.
4. **Major — Audit before/after values can show raw timestamps.** `AuditEventResource::formatValue()` returns scalar values verbatim; include nested date/time fields such as `next_follow_up_at` in localization.
5. **Major — Bidi isolation does not cover every Filament surface.** Define how presenter output is isolated in tables, details, and option labels, as well as Blade history views, while retaining Arabic surrounding labels.
6. **Major — XLSX cell mapping and formatting remain unspecified.** State the temporary-row representation, date-only versus timestamp map, option key and shape, and per-locale number formats. Preserve the time component in fields such as `created_at`.

The reviewer confirmed the Filament exporter options and `makeXlsxRow()` APIs, OpenSpout typed date support, and planned file boundaries. Existing modification targets were present; new classes and tests were absent as expected. **Unread files:** none. No tests or builds were run.

## `egm-style` — `/root/egm_final_stage9_style` — completed

1. **Major — AM/PM behavior is not identified.** Name where users select AM/PM and test both periods plus noon and midnight round-trips.
2. **Nit — “Civil dates” is unclear.** Say date-only values remain calendar dates and do not shift across time zones.
3. **Nit — Release QA checks are repeated.** Keep the detailed visual, keyboard, screen-reader, contrast, and viewport checklist under Verification Plan and refer to it from earlier sections.

**Read status:** No listed file was inaccessible. The reviewer read the target range, feature hypothesis, `PROJECT_CONTEXT.md`, and selected source/context files; longer files were inspected through relevant excerpts.
