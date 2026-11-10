# Cible CRM Date and Time Redesign — EGM Consolidated Review

**Target:** `feature-hypothesis.md` (concept-level feature specification)<br>
**Execution mode:** Consolidated report with all applicable review rubrics<br>
**Review date:** 2026-11-08<br>
**Result:** Stages 5–8 completed. Stage 9 (implementation readiness) was not run because this target contains no detailed implementation plan.

## Scope and safety

Six genuine Codex native subagents reviewed the hypothesis across four stages, each with `fork_turns: none`. They received the target and their assigned persona; all stages except comprehension also received the selected project context. No reviewer edited files or ran tests/builds. No application source or existing documentation was changed; this report and the stage reports are new artifacts under `.egm-test`.

## Consolidated findings

### C-01 — Add a concise alternatives evaluation

**Severity:** Blocker under the Feature Specification Standard; severity contested by Stage 8.<br>
**Evidence:** Stage 7 design and structural reviewers found no alternatives evaluation. The standard requires alternatives and says a feature specification without concrete tradeoffs fails. Stage 8 argues a formal comparison matrix would be excessive for a concept hypothesis; a short rationale for reusing Filament's bundled picker is sufficient.

**Resolution to consider:** Briefly weigh the existing Filament picker and formatting APIs against a separate picker package or a fully custom control. Explain why centralizing and styling existing seams is the initial direction, while leaving implementation choice open where inspection is still needed. Include the export choice in C-04.

### C-02 — Define browser locale resolution

**Severity:** Major.<br>
**Evidence:** Comprehension, Design Critic, and Intermediate Zoom-Out all found that “browser's preferred locale” does not define ordered preferences or regional tags.

**Resolution to consider:** Define how `navigator.languages` or the browser's equivalent is interpreted; map Arabic regional tags to Arabic and English regional tags to English; choose a deterministic precedence rule; and fall back to Arabic if no supported language is present. Decide whether date ordering is fixed per language or varies by region.

### C-03 — Separate date locale from CRM language, direction, calendar, and timezone

**Severity:** Major.<br>
**Evidence:** Comprehension could not tell whether English formatting flips a picker or the surrounding page. Design Critic and Intermediate Zoom-Out noted that “calendar names” might imply a different calendar system, and client-side formatting could accidentally use the device timezone.

**Resolution to consider:** State that all CRM labels/content and surrounding layout remain Arabic RTL; only date/time-specific labels and displayed values follow the resolved locale. Explicitly preserve Gregorian dates and `Africa/Cairo` time regardless of device timezone. Specify bidi isolation/alignment for mixed Arabic and Latin values, and include tests with a device timezone different from Cairo.

### C-04 — Snapshot export locale when the user requests the export

**Severity:** Major.<br>
**Evidence:** Comprehension, Design Critic, and Intermediate Zoom-Out found “generation starts” ambiguous. The existing Filament export actions are queued, so a worker may execute later under a different locale or process retries/chunks separately.

**Resolution to consider:** Define the export request as the snapshot point. Every queued chunk and retry should use that same normalized locale, including when one worker handles exports requested under different locales. Keep the exact transport mechanism for the implementation design; do not mutate the Arabic application locale globally.

### C-05 — Preserve machine-usable Excel date/time values while localizing their display

**Severity:** Major.<br>
**Evidence:** Comprehension, Design Critic, and Intermediate Zoom-Out agree that the target does not distinguish typed Excel date/time values with locale-specific display formatting from localized text. This affects sorting, formulas, and downstream imports.

**Resolution to consider:** Prefer typed date/time cells with a localized number format for display, then verify the installed XLSX writer can preserve those types and render Arabic `ص` / `م` and English `AM` / `PM`. If it cannot, document a compatible fallback without changing database or backend values.

### C-06 — Describe centralization at the policy level and reuse Filament seams

**Severity:** Major.<br>
**Evidence:** Design Critic and Structural found “centralized” does not explain ownership or data flow. Intermediate Zoom-Out confirmed reusable shared schemas/resources and Filament picker locale/display-format options, but found scattered direct Carbon and table formatting with no common display formatter. Stage 5 found no need for a new picker dependency or infrastructure.

**Resolution to consider:** Specify one shared date/time presentation policy and formatter applied to the existing picker, table, detail, modal, dashboard/report, and export surfaces. Keep the policy separate from Laravel's Arabic application locale. Reuse existing Filament components; avoid a new picker package, generic localization framework, global per-request locale mutation, and premature class-by-class implementation detail.

### C-07 — Replace subjective UX claims with observable acceptance outcomes

**Severity:** Major.<br>
**Evidence:** Style and Design Critic found “polished,” “modern,” “clear,” “understandable,” “readable,” and “keyboard-accessible” are not testable as written. The project visual plan already sets responsive, WCAG AA, keyboard-focus, and RTL expectations.

**Resolution to consider:** Define outcomes for supported viewport widths, calendar navigation, AM/PM selection, keyboard-only operation, focus and validation feedback, screen-reader names, contrast, and mixed-direction ordering. Avoid prescribing a full keystroke protocol in the concept brief.

### C-08 — Bound the inventory and calibrate evidence claims

**Severity:** Nit, with one Major wording concern.<br>
**Evidence:** Comprehension and Intermediate Zoom-Out found “other relevant interfaces” and “generated reports” could imply future surfaces beyond existing date/time-bearing CRM surfaces. Style noted the 24-hour usability claim is stakeholder-reported and should be attributed, while the “modern/polished” wording is subjective. The style reviewer also requested a traceable inventory source for picker counts and package defaults.

**Resolution to consider:** Scope the work to every existing date/time-bearing control and display, including current XLSX exports and reports. State that the staff pain is owner-reported. Link the implementation inventory or narrow the package/count assertions to their inspected source.

### C-09 — Record relevant risks without adding unrelated process

**Severity:** Major under the Feature Specification Standard; whether a separate section is needed is contested.<br>
**Evidence:** Design Critic and Structural found no risk/mitigation section. Intermediate Zoom-Out argues a broad security/scalability section would add weight for a presentation-only change; the real risks are locale leakage and queued-export inconsistency.

**Resolution to consider:** Add a short, scoped risk note for locale bleed across requests/workers, browser/server timezone disagreement, bidi/accessibility regressions, and XLSX display/type support. State how each is detected or tested. Do not invent unrelated infrastructure risks.

## Reviewer traceability

| Stage | Native subagent | Persona | Status | Main result |
|---|---|---|---|---|
| 5 — Initial zoom-out | `egm_stage5_zoom_out` | `egm-zoom-out` | Completed | No blocker; use existing Filament seams; export locale must follow queued job. |
| 6 — Comprehension | `egm_stage6_comprehension` | `egm-comprehension` | Completed | Major gaps: locale precedence, RTL/LTR ownership, export snapshot point, Excel cell type. |
| 7 — Critic | `egm_stage7_design_critic` | `egm-design` | Completed | Alternatives blocker; locale/timezone/queue/Excel/accessibility risks. |
| 7 — Critic | `egm_stage7_structural` | `egm-structural` | Completed | Alternatives blocker; missing architecture and risk sections; structure and links valid. |
| 7 — Critic | `egm_stage7_style` | `egm-style` | Completed | Major subjective/unmeasured claims; scope and inventory evidence nits. |
| 8 — Intermediate zoom-out | `egm_stage8_intermediate_zoom_out` | `egm-zoom-out` | Completed | Confirms key gaps; contests formal alternatives matrix and broad risk section; recommends reuse and narrow scope. |

## Isolation evidence and limits

All six reviewers were spawned with native Codex `spawn_agent` and `fork_turns: none`. Each reported no parent-only conversational details. A unique parent-only marker was not included in the target or reviewer prompts and did not appear in the returned reports. This supports parent-turn isolation for this run. Codex may still supply host or instruction files to child agents; exclusion of those files is not established by `fork_turns: none` and was not claimed here. Some reviewers reported reading the workspace `RTK.md` instruction.

## Individual stage reports

- [Initial zoom-out report](zoom_out_report.md)
- [Comprehension report](comprehension_report.md)
- [Critic report with individual design, structural, and style findings](critic_report.md)
- [Intermediate zoom-out report](intermediate_zoom_out_report.md)

## Review boundary

This is a concept-level review, not an implementation-readiness assessment. Stage 9 did not run because there is no detailed implementation plan. The user approved the findings; the approved recommendations are incorporated in `feature-hypothesis.md`. See `goldfish_review_report.md` for the resolution record.
