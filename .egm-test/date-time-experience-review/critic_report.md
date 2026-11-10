# Stage 7 — Critic Review

**Target range:** Entire `feature-hypothesis.md`; DetailedRange N/A.<br>
**Standard:** `feature-spec-standard.md` for design and structural reviewers.<br>
**Execution:** Three native Codex subagents, each with `fork_turns: none`; all completed.

## egm-design — Design Critic Goldfish

- **Blocker — Lines 13–27:** The hypothesis chooses a centralized locale-based experience but does not evaluate alternatives; the feature-spec standard requires alternatives and says a specification fails without them. Recommendation: compare high-level options such as browser-native versus server-side formatting and localized text versus typed Excel dates, with tradeoffs. The concept-level status does not remove this requirement.
- **Major — Lines 13–27:** “Centralized” does not describe architecture, locale-resolution contract, formatter ownership, or data flow through Filament pickers, tables, details, and exports. Existing code mixes Filament formatting with direct Carbon formatting while application locale remains Arabic. Recommendation: identify shared responsibilities and consumers, separate date/time locale from application locale, and scope/restore locale state in requests and workers.
- **Major — Lines 13, 22, and 26:** Locale reduction, Arabic fallback, “calendar names,” calendar system, and Cairo-time rendering are underspecified. Recommendation: define supported regional locales and unsupported-preference fallback, state whether only labels or calendar system changes, define Cairo-time client rendering, and include regional-locale and device-timezone acceptance cases.
- **Major — Lines 17 and 27:** The locale snapshot for queued exports has no transport or persistence mechanism. Existing customer/request export actions are queued and a date-time column currently has no explicit locale formatting. Recommendation: snapshot a normalized locale on dispatch; ensure chunks, retries, and workers reuse it, including sequential exports with different locales on one worker.
- **Major — Line 17:** The Excel cell type and display behavior are unspecified. Recommendation: choose typed date/time cells with display formats or localized text, then verify display, sorting/filtering, and opening under a different spreadsheet locale.
- **Major — Line 15:** “Keyboard-accessible” is not testable. Recommendation: define keyboard-only open/edit/AM-PM/select/clear/close behavior, focus, error, and screen-reader feedback in RTL and LTR.

## egm-structural — Structural Goldfish

- **Blocker — Entire document; lines 13 and 24:** No alternatives evaluation. The feature-spec standard requires concrete alternatives and rejection reasons.
- **Major — Lines 11–17 and 36–42:** The target states desired behavior and current implementation but omits proposed component layout, data flow, and integration across pickers, screens, reports, and exports.
- **Major — Lines 29–34:** Scope limits are present, but failure modes, security implications, scalability limits, and mitigations are absent.
- Heading hierarchy and list formatting are consistent. All linked paths resolve to existing files.

## egm-style — Style Goldfish

- **Major — Problem, line 9:** “Staff find 24-hour time less intuitive” presents a perception without its source or scope. “The desired clear, modern, consistent experience” is subjective. Attribute the observation or describe a concrete issue.
- **Major — Anchored hypothesis line 15; Goals line 25:** “Visually polished,” “clear,” “one understandable workflow,” and “readable alignment” lack testable definitions. Replace them with observable acceptance criteria (for example screen widths, keyboard interactions, and RTL/LTR order).
- **Major — Anchored hypothesis line 17; Goals line 27:** “Compatible with Excel and other systems” is unbounded. Calibrate to named formats/software and defined checks for value accuracy and display.
- **Nit — Anchored hypothesis line 15:** “Other relevant interfaces” is an undefined catchall after a list of surfaces.
- **Nit — Existing implementation context lines 39–40:** Picker count, dependency absence, and defaults lack an inventory method or source reference. Add a scoped inventory citation or narrow the claim.

## Reviewer completion and isolation

All three reviewers reported reading their assigned persona, target, and complete assigned GoldfishContext. The design and structural reviewers also read the feature-spec standard. Each reported no inaccessible assigned files and no parent-only conversational details. No tests, builds, target edits, or application edits were performed.
