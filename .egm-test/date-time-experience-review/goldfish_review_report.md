# Goldfish Annotated Review Report

**Status:** resolved<br>
**Target:** `.egm-test/date-time-experience-review/feature-hypothesis.md` (`full file`)

## Prioritized Findings & AI Suggestions

### Issue 1 [`egm-design`, `egm-structural`] — Severity: Blocker

* **Affected Scope:** Proposed experience and goals.
* **Discrepancy Description:** The feature specification omitted an alternatives evaluation, required by the Feature Specification Standard. The intermediate Zoom-Out reviewer considered a formal matrix disproportionate for a concept-level hypothesis.
* **Rationale:** The document needs to explain why its initial direction fits Cible, but does not need a detailed implementation comparison.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The user approved the review findings and requested concise alternatives. The severity is retained; the amount of detail is proportionate to this concept document.
* ***Proposed Edit:***
    * Location: `Alternatives weighed`
    * Change: Briefly compare the bundled Filament picker/shared policy with replacing it, per-screen formatting, and localized spreadsheet text. State the tradeoffs and selected starting direction.

### Issue 2 [`egm-comprehension`, `egm-design`, `egm-zoom-out`] — Severity: Major

* **Affected Scope:** Locale, calendar, time, and direction.
* **Discrepancy Description:** Locale precedence, regional tags, unsupported-locale fallback, calendar system, and ownership of RTL/LTR direction were unclear.
* **Rationale:** A fresh reader and the implementation could otherwise choose different locales, switch the overall page direction, use a non-Gregorian calendar, or format using the device timezone.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The user specified primary-locale matching, Arabic fallback, Gregorian dates, Cairo timezone, Arabic RTL shell, regional date ordering, and bidirectional handling.
* ***Proposed Edit:***
    * Location: `Locale, calendar, time, and direction`
    * Change: Define `ar`/`ar-*` as Arabic, `en`/`en-*` as English, all other primary locales as Arabic fallback, preserve regional ordering, force Gregorian/Western numerals, keep the shell Arabic RTL, localize only picker internals and date/time values, and isolate mixed-direction values.

### Issue 3 [`egm-comprehension`, `egm-design`, `egm-zoom-out`] — Severity: Major

* **Affected Scope:** Export behavior.
* **Discrepancy Description:** “Generation starts” did not identify when the locale is captured for queued exports or how it is kept stable through chunks and retries.
* **Rationale:** A worker can run later or process another user's export under a different locale.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The user explicitly selected the export request as the snapshot point and requires the normalized locale to persist across queued chunks and retries.
* ***Proposed Edit:***
    * Location: `Central presentation and export behavior`
    * Change: Capture locale when the user requests an export or generates a report and reuse it for every queued chunk and retry.

### Issue 4 [`egm-comprehension`, `egm-design`, `egm-zoom-out`] — Severity: Major

* **Affected Scope:** XLSX output.
* **Discrepancy Description:** The target did not say whether localized export dates/times should become strings or remain typed spreadsheet values.
* **Rationale:** String conversion can impair sorting, formulas, and downstream imports.
* ***AI Suggestion:*** PROPOSE FIX
* **Justification:** The user prefers typed Excel cells with localized display formatting.
* ***Proposed Edit:***
    * Location: `Central presentation and export behavior`; `Risks and mitigations`
    * Change: Specify typed date/time cells with localized display formats; require XLSX writer verification and prohibit silent string conversion.

### Issue 5 [`egm-design`, `egm-structural`, `egm-zoom-out`] — Severity: Major

* **Affected Scope:** Central presentation and existing implementation context.
* **Discrepancy Description:** “Centralized” did not explain how existing picker and display paths share a policy.
* **Rationale:** Pickers use Filament configuration while other displays use Filament formatting or direct Carbon formatting. A new package or global locale mutation would add avoidable complexity.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Existing Filament components provide locale/display seams and shared resources; the user requires one centralized policy without backend changes.
* ***Proposed Edit:***
    * Location: `Central presentation and export behavior`
    * Change: Describe one shared date/time presentation policy and common display formatter applied to the existing Filament and report/export surfaces, separate from Arabic `app.locale`.

### Issue 6 [`egm-style`, `egm-design`, `egm-zoom-out`] — Severity: Major

* **Affected Scope:** Problem and measurable UX/accessibility requirements.
* **Discrepancy Description:** “Polished,” “modern,” and “keyboard-accessible” did not define observable outcomes.
* **Rationale:** Acceptance needs to cover the intended light Cible appearance, contrast, visible focus, responsive layouts, and interaction across RTL/LTR date/time modes.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The user supplied measurable visual and accessibility constraints and the project visual plan provides a viewport matrix.
* ***Proposed Edit:***
    * Location: `Measurable UX and accessibility requirements`
    * Change: Add the light theme, gold/deep-green contrast-checked tokens, WCAG AA, viewport widths, no clipping, keyboard-only operations, visible focus, screen-reader announcements, and locale/timezone acceptance cases.

### Issue 7 [`egm-comprehension`, `egm-style`, `egm-zoom-out`] — Severity: Nit

* **Affected Scope:** Problem, proposed experience, and scope.
* **Discrepancy Description:** The staff-feedback claim lacked attribution; “other relevant interfaces” and “generated reports” could imply new surfaces; package/count claims lacked traceable context.
* **Rationale:** The scope should be complete for current date/time surfaces without implying new functionality, and claims should match the available evidence.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The user approved limiting scope to existing date/time-bearing controls, displays, reports, and exports. The staff concern is stakeholder-reported.
* ***Proposed Edit:***
    * Location: `Problem`, `Proposed experience`, `Existing implementation context`, `Reading context`
    * Change: Attribute the staff feedback, explicitly limit reports to existing surfaces, state that the picker counts are current inventory findings, and link relevant configuration/component/export sources.

### Issue 8 [`egm-design`, `egm-structural`, `egm-zoom-out`] — Severity: Major

* **Affected Scope:** Risks and mitigations.
* **Discrepancy Description:** The target omitted risks. The intermediate Zoom-Out reviewer considered a broad generic security/scalability section excessive for a presentation change.
* **Rationale:** Relevant risks are locale leakage, queue inconsistency, device timezone drift, bidi/accessibility regression, and typed XLSX formatting support; unrelated infrastructure risks need not be added.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The user requested proportionate alternatives and risks discussion. A short list can address concrete failure modes without expanding scope.
* ***Proposed Edit:***
    * Location: `Risks and mitigations`
    * Change: Add four concise risks with targeted controls/tests; do not add generic security or scalability claims.

## Resolution summary

All eight consolidated items were categorized `PROPOSE FIX` and incorporated as requested. The alternatives and risks discussions remain concise. The document remains a concept specification with no detailed implementation plan. No application source, existing project documentation, or `DECISIONS.md` was modified. No tests or builds were run.
