# Goldfish Review Resolution — Round 2

* **Target**: `lead-source-logic.md`
* **Reviewers**: All requested Stage 5–9 reviewers completed.
* **Resolution**: The author chose **auto accept**. The findings below were accepted and applied to the design and governing documents.
* **Code and tests**: No application code was changed and no tests were run.

## Accepted Findings and Applied Changes

### R2-1 — Keep the application unavailable until the migration succeeds

**Severity:** Major
**Raised by:** Stage 5 `zoom_out`; Stage 7 `design_critic`; Stage 8 `zoom_out`; Stage 9 `implementation_readiness` and `codebase_details`.

**Resolution:** Accepted. The production sequence now enables Laravel maintenance mode before pulling `main`, applies the migration while requests are blocked, and restores service only after success. If the migration fails, maintenance mode remains enabled until a forward fix succeeds. Updated in the design's Non-Functional Requirements and Production verification step.

### R2-2 — Authorize and test the referrer values exported with requests

**Severity:** Major
**Raised by:** Stage 7 `design_critic`; Stage 8 `zoom_out`; Stage 9 `implementation_readiness` and `codebase_details`.

**Resolution:** Accepted. Added `D-087` for exporting a captured name, explicit «غير معروف», or a blank historical state, scoped to the authorized department. `D-062` now identifies this as a limited exception; `D-021`, `D-023`, `Q-024`, and `Q-015` reference it. Added the source-meaning decision `D-088`, updated `D-051` and `Q-011`, and expanded `AT-070` / `AT-158`. Both acceptance criteria are `Not run` until implementation and verification.

### R2-3 — Preserve the historical null referral state on untouched form edits

**Severity:** Major
**Raised by:** Stage 5 `inventive_catalyst` (Nit); Stage 9 `implementation_readiness` and `codebase_details` (Major).

**Resolution:** Accepted. The form plan now defines a non-persisted `lead_source_referrer_details_touched` state. When it is false on edit, the form omits both referral fields so the service preserves stored values, including historical nulls. When referral details are changed, the form submits them and the service enforces the exactly-one rule. `LeadSourceRequestFormTest` now explicitly covers a full form edit that changes an unrelated field while both referral fields remain null.

### R2-4 — Backfill catalog types before enforcing non-null

**Severity:** Major
**Raised by:** Stage 9 `codebase_details`.

**Resolution:** Accepted. The migration plan now adds `type` as nullable, maps every existing catalog row, verifies no null types remain, and then alters the field to non-null.

### R2-5 — Specify exact Arabic catalog type labels

**Severity:** Nit
**Raised by:** Stage 9 `implementation_readiness`.

**Resolution:** Accepted. The implementation plan now specifies Arabic labels for all five source types.

### R2-6 — Tighten wording and calibrate the catalog-size statement

**Severity:** Nits
**Raised by:** Stage 7 `style`.

**Resolution:** Accepted. The overview was shortened; the problem statement now names the concrete rules; alternative rationales no longer center on the author; and catalog scale is stated as an assumption rather than an unbounded-growth claim.

## Findings Classification

No findings were contested. Repeated reports about rollout ordering, export authorization, and null-state preservation were consolidated under R2-1, R2-2, and R2-3.

## Round Status

All Round 2 findings have a recorded resolution and the accepted document updates are applied. This does not establish EGM convergence: no post-fix review round has been run, and the loop must wait for the author's instruction before starting one.
