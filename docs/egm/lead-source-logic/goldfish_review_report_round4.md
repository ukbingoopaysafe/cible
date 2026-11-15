**Status: resolved**

# Goldfish Annotated Review Report — Round 4

**Target:** [lead-source-logic.md](./lead-source-logic.md) (full file; FeatureRange lines 1–184 and Detailed Implementation lines 185–276 at review time)

## Prioritized Findings & AI Suggestions

### Issue 1 — Verify production database compatibility before rollout [`design_critic`, `zoom_out`, `implementation_readiness`, `codebase_details`] — Severity: Major

* **Affected Scope:** Non-Functional Requirements rollout; migration item 1; Production verification.
* **Discrepancy Description:** The plan instructs a production migration but does not require identifying production's database engine/version or rehearsing the exact migration against that same engine/version. The README says production compatibility remains unverified; documented migration evidence covers MariaDB 10.4.32 but not Oracle MySQL 8.
* **Rationale:** A migration failure during the maintenance window could leave production unavailable.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The same concrete compatibility gap was independently reported by the design, zoom-out, readiness, and codebase reviewers. The operation is a necessary precondition to a safe production rollout.
* ***Proposed Edit:***
  * Location: Non-Functional Requirements rollout and Verification Plan.
  * Change: Require confirmation of the production database engine/version and a successful rehearsal of the exact migration on a disposable copy of the pre-change schema using that engine/version before scheduling maintenance. Record the verified version and rehearsal result with deployment evidence. Cross-reference this rollout from the Production verification step.

### Issue 2 — State the legacy contact-method mapping directly [`style`] — Severity: Nit

* **Affected Scope:** The Problem, line 21.
* **Discrepancy Description:** “Without evidence” does not identify what evidence would change how old calls, visits, or WhatsApp values are classified.
* **Rationale:** The migration already defines these catalog rows as inactive `legacy_contact_method`.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** State that the catalog rows for calls, visits, and WhatsApp map to inactive `legacy_contact_method` and existing request selections are preserved.

### Issue 3 — Use precise null terminology [`style`] — Severity: Nit

* **Affected Scope:** Open Questions, line 42.
* **Discrepancy Description:** “Leave it empty” is less precise than the database state described elsewhere.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Replace it with “leave `lead_source_id` null.”

### Issue 4 — Clarify department source availability [`style`] — Severity: Nit

* **Affected Scope:** CUJ 1, line 50.
* **Discrepancy Description:** “Available to matching requests” does not define “matching.”
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Say the new source is available to requests in the same department.

### Issue 5 — Improve the request-data section's scanability [`style`] — Severity: Nit

* **Affected Scope:** Request Data and Form Flow, line 88.
* **Discrepancy Description:** One paragraph combines persisted fields, validation, historical edits, conditional visibility, and dependent-field clearing.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Split it into short paragraphs by data storage, validation/history, and conditional form behavior.

### Issue 6 — Remove the duplicate confirmed-decisions section [`style`] — Severity: Nit

* **Affected Scope:** “Decisions Confirmed for Detailed Implementation,” lines 127–132.
* **Discrepancy Description:** The bullets repeat rules already recorded in the Technical Plan and Migration sections.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Remove the redundant section; its decisions remain in their authoritative sections.

### Issue 7 — Split migration instructions into ordered steps [`style`] — Severity: Nit

* **Affected Scope:** Detailed Implementation item 1.
* **Discrepancy Description:** One paragraph contains schema additions, type mappings, preservation rules, and rollback requirements.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Use nested bullets to separate schema changes, backfill mappings, post-backfill constraints, preserved data, and rollback/rollout requirements.

### Issue 8 — Split request form and service instructions by behavior [`style`] — Severity: Nit

* **Affected Scope:** Detailed Implementation items 7 and 8.
* **Discrepancy Description:** Each item combines create/edit behavior, touched-state handling, validation, and source-change rules in one dense paragraph.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Break each item into behavior-specific bullets while retaining every stated rule.

### Issue 9 — Keep the migration failure caveat in one place [`style`] — Severity: Nit

* **Affected Scope:** Verification Plan, Production bullet.
* **Discrepancy Description:** The failure-mode instruction repeats the rollout rule in Non-Functional Requirements.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Keep the operational sequence and failure action under Non-Functional Requirements; have the Verification Plan point to that section.

## Resolution

The user previously authorized auto-accept for review fixes. The Major and all eight Nits were applied to the design. The earlier catalog-size Nit remains a settled small-catalog assumption. No application code was changed and no tests were run.

## Reviewer Status

All Round 4 reviewers completed: Stage 5 (`zoom_out`, `inventive_catalyst`), Stage 6 (`comprehension`), Stage 7 (`design_critic`, `structural`, `style`), Stage 8 (`zoom_out`), and Stage 9 (`implementation_readiness`, `codebase_details`, `style`).
