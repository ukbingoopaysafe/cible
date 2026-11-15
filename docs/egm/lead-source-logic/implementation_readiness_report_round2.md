# Stage 9 — Implementation Readiness Test (Round 2)

* **Target**: `lead-source-logic.md`
* **Range**: DetailedRange, lines 185–276
* **Reviewers**: `implementation_readiness`, `codebase_details`, and `style` completed.
* **Execution**: Read-only review; no tests were run and no project code was changed.

## Findings

### Major

#### `implementation_readiness-stage9-round2-1` / `codebase_details-stage9-round2-1` — Export approval and acceptance criteria are incomplete

**Affected scope:** Implementation items 9, 12, and 15; `docs/DECISIONS.md`; `docs/ACCEPTANCE_TESTS.md`.

The plan adds referrer data to exports and proposes tests for it, but updates `D-051` only. `D-062` limits export fields to those approved under `D-030` and `D-048`, which do not cover the new referrer snapshot. The proposed update to `AT-158` also omits explicit export assertions for a captured name, an explicit unknown state, and the blank historical state.

Supersede `D-062` to authorize those exported values and update `AT-158` with the three export outcomes.

#### `implementation_readiness-stage9-round2-2` / `codebase_details-stage9-round2-2` — Deployment leaves an incompatible code/schema interval

**Affected scope:** Migration item and Production verification step.

Both direct the owner to pull the updated application and then run the migration. The new code needs columns created by that migration; requests in between can reach the updated application while the database still has its old schema.

Specify a serving order that keeps requests and writes stopped through the code and schema transition, or define a backward-compatible rollout.

#### `implementation_readiness-stage9-round2-3` / `codebase_details-stage9-round2-3` — Full-form edits do not specify how to preserve historical nulls

**Affected scope:** Implementation items 7, 8, 10, and 16; `RequestFormSchema.php` and `RequestService.php`.

The plan requires historical requests with both referrer fields null to survive unrelated edits, but does not explain how the form distinguishes untouched state from an unchecked checkbox submitted as `false`. That can convert a historical null into an apparent detail change and trigger the exactly-one rule.

Specify null-preserving behavior for untouched referral fields and test a full Filament edit that changes an unrelated field while preserving both nulls.

### Nit

#### `implementation_readiness-stage9-round2-4` — Define exact Arabic catalog labels for source types

**Affected scope:** Implementation items 2 and 5; `LeadSourceType.php` and `LeadSourceResource.php`.

The plan requires the enum to provide catalog labels but leaves the Arabic wording for its five types to the implementer. Add the exact label mapping.

## Other Reviewer Notes

`codebase_details` confirmed that the five new implementation targets marked `Create` do not yet exist, as expected. `style` found no findings in DetailedRange. All three reviewers completed their assigned audits and confirmed they read the target and required context.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `implementation_readiness` | Completed | 3 Majors, 1 Nit |
| `codebase_details` | Completed | 3 Majors |
| `style` | Completed | No findings |
