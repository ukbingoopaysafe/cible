**Status: resolved**

# Goldfish Annotated Review Report — Round 5

**Target:** [lead-source-logic.md](./lead-source-logic.md) (full file; FeatureRange lines 1–179 and Detailed Implementation lines 180–293 at review time)

## Prioritized Findings & AI Suggestions

### Issue 1 — Preserve submitted dependent values valid for the new source type [`zoom_out`, `implementation_readiness`, `codebase_details`, `style`] — Severity: Major

* **Affected Scope:** Request Data and Form Flow; Detailed Implementation items 7–8 and tests 10/16.
* **Discrepancy Description:** The plan says to clear all dependent values whenever the source ID changes. The service instruction does not distinguish old stored values from newly submitted values valid for the selected type. An unconditional clear before validation can erase a newly entered referrer name/unknown state when switching to `person_referral`, or the new description when switching to `other`.
* **Rationale:** The source-change journeys would fail required validation, and stale data must not leak across source types.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Stage 8 and both implementation-readiness/codebase reviews confirmed the ambiguity against the request update path. The UI can clear old form values on selection change; the service must distinguish persisted stale data from submitted valid values.
* ***Proposed Edit:***
  * Location: Request Data and Form Flow and Detailed Implementation items 7–8 and 10/16.
  * Change: On source selection change, clear old form state so staff can enter new details. On submission, discard dependent values stored for the prior source, preserve submitted values valid for the new type, clear only incompatible or omitted stale values, then validate. Preserve a newly submitted name/unknown for `person_referral`, a newly submitted description for `other`, clear all values for `awareness`/no source, and reject missing required values. Add transition tests for all these cases.

### Issue 2 — Add implementation phases and checkpoints [`implementation_readiness`] — Severity: Major

* **Affected Scope:** Detailed Implementation and Verification Plan.
* **Discrepancy Description:** The plan lists target files and final verification but does not divide work into ordered phases with intermediate validation gates.
* **Rationale:** The implementation-readiness standard requires phases that leave the codebase buildable and testable; without checkpoints, failures could accumulate across migration, catalog, request, and export work.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:***
  * Location: Before the Verification Plan.
  * Change: Group the existing file items into ordered schema/types, catalog, request form/persistence, exports/acceptance, and integrated/production qualification phases, each with an explicit focused test or rehearsal gate.

### Issue 3 — Clarify referral-validation triggers [`style`] — Severity: Nit

* **Affected Scope:** Request Data and Form Flow and Detailed Implementation item 8.
* **Discrepancy Description:** The shorter wording could omit explicitly changing referral details while the same `person_referral` source remains selected.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** State the three triggers consistently: create with a person-referral source, change the source to that type, or explicitly change referral details while that source remains selected.

### Issue 4 — Split request-form responsibility prose [`style`] — Severity: Nit

* **Affected Scope:** Request Data and Form Flow.
* **Discrepancy Description:** The section combines form option/display behavior with server-side validation and department boundaries.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Place `RequestService` responsibilities in a separate paragraph.

### Issue 5 — Condense the migration summary [`style`] — Severity: Nit

* **Affected Scope:** Migration and Historical Values.
* **Discrepancy Description:** The summary repeats mappings and preservation details from migration item 1.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Condense the summary and point to Detailed Implementation item 1 for exact mappings, assertions, and rollback behavior.

### Issue 6 — Break the rollout into ordered steps [`style`] — Severity: Nit

* **Affected Scope:** Non-Functional Requirements, rollout and rollback.
* **Discrepancy Description:** One paragraph combines compatibility preflight, rollout, failure handling, rollback, and old-application behavior.
* ***AI Suggestion:*** PROPOSE FIX
* ***Proposed Edit:*** Use ordered preflight, deployment, failure recovery, post-migration, and older-application compatibility steps while retaining the operational caveats.

## Resolution

The user approved the design at the five-round cap and chose to proceed. Both Majors and all four unique Nits were applied to the design. No post-fix review round was run and EGM convergence is not claimed. Implementation is the next step.

## Reviewer Status

All requested Round 5 reviewers completed: Stage 5 (`zoom_out`, `inventive_catalyst`), Stage 6 (`comprehension`), Stage 7 (`design_critic`, `structural`, `style`), Stage 8 (`zoom_out`), and Stage 9 (`implementation_readiness`, `codebase_details`, `style`).
