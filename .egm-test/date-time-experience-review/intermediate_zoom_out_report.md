# Stage 8 — Intermediate Zoom-Out Review

**Reviewer:** `egm_stage8_intermediate_zoom_out` (`egm-zoom-out` persona)<br>
**Execution:** Native Codex subagent; `fork_turns: none`; completed.<br>
**Target:** Entire `feature-hypothesis.md`<br>
**Standard:** `zoom-out-standard.md`<br>
**Prior reports read:** Stage 5 Zoom-Out, Stage 6 Comprehension, Stage 7 Critic.

## Findings

1. **Major — Locale resolution remains ambiguous.** Define which ordered browser language wins, how regional tags map (for example, `ar-*` to Arabic and `en-*` to English), and fallback to Arabic if neither is recognized. State whether date ordering varies by region.
2. **Major — Separate date locale, page direction, calendar, and timezone.** Keep the CRM labels and surrounding layout Arabic RTL while localizing only date/time presentation and picker labels. Preserve Gregorian dates and Cairo time regardless of device timezone, and isolate mixed-direction date text where needed.
3. **Major — Give queued exports a stable locale point.** Define generation start as the user's export request and reuse the captured locale for every chunk and retry. The hypothesis need not prescribe transport details.
4. **Major — Preserve usable Excel values.** Prefer typed Excel date/time values with locale-appropriate display formatting rather than localized text, preserving sorting, formulas, and downstream use; leave the exact number format to implementation.
5. **Major — Centralize through existing Filament seams.** Reuse shared request/customer resources and Filament's bundled picker locale/display options; add a common formatter for the currently scattered table, detail, and Blade date displays. Do not add another picker package, a generic localization framework, or global request-level mutation of the app locale.
6. **Nit — Bound reports, scope, and accessibility requirements.** Limit scope to existing date/time-bearing surfaces. Replace “other relevant interfaces” and subjective “polished/modern” language with observable requirements. Keep the existing combined picker/value concept and define a basic keyboard acceptance outcome without a needlessly detailed key protocol.

## Dispositions of earlier findings

- **Accepted/refined:** Locale resolution; separation of Arabic page direction from date/time locale; queued-export locale snapshot; typed Excel cells; reusable Filament seams; Gregorian calendar and Cairo timezone; measurable keyboard accessibility; scope limited to existing surfaces.
- **Contested:** The Critic's Blocker for a formal alternatives evaluation. This reviewer considers a concise rationale for retaining Filament's bundled picker sufficient; a formal matrix would add weight to a concept hypothesis.
- **Contested:** A separate general security/scalability section. The concrete risks for a presentation-only change are locale leakage and queued-export inconsistency; these should be addressed without inventing unrelated infrastructure concerns.
- **Stage 5 conclusion upheld:** Cross-screen consistency addresses the stated need. Existing Filament components are reusable; a new picker package or broad reporting framework is not indicated.

## Isolation and evidence

The reviewer reported reading its persona, Zoom-Out standard, target, all assigned project context files, and all three prior stage reports. It also inspected the installed Filament picker source and searched date/time displays and exporters. It reported no parent-only conversational details. No tests, builds, or file edits were performed.
