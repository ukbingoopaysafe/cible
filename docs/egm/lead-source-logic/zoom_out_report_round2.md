# Stage 5 — Initial Zoom Out Review (Round 2)

* **Target**: `lead-source-logic.md`
* **Range**: Full file, lines 1–276
* **Reviewers**: `zoom_out` and `inventive_catalyst` completed.
* **Execution**: Read-only review; no code or design edits were made.

## Findings

### Major

#### `zoom_out-2-1` — Keep the application unavailable until the migration is applied

The rollout instructions say to pull the updated application and then run the migration (`lead-source-logic.md:179, 191–194`). The new application expects the source `type` and referral columns (`:90, 221–229`), so requests that run after the code update but before the migration may fail against the old schema.

Specify a deployment sequence that keeps traffic and staff actions stopped between replacing the application and completing the migration, such as a maintenance window covering both steps. Alternatively, define a compatibility rollout. The current pull-then-migrate instruction alone leaves the incompatible interval open.

### Nit

#### `inventive_catalyst-2-1` — Preserve SQL `NULL` when the referral checkbox was not changed

The design preserves a historical referral whose name and unknown flag are both null during unrelated edits (`lead-source-logic.md:88, 224, 229`), but it does not say how the edit form distinguishes that state from an unchecked checkbox submitted as `false`. A full-form save that changes an unrelated field could therefore appear to change referral details and trigger the exactly-one validation.

Specify null-preserving hydration/dehydration or an explicit touched-state rule, and cover an edit that changes only an unrelated field.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `zoom_out` | Completed | 1 Major |
| `inventive_catalyst` | Completed | 1 Nit |
