# Stage 7 — Critic Review (Round 5)

* **Target**: `lead-source-logic.md`
* **Range**: FeatureRange, lines 1–179
* **Reviewers**: `design_critic`, `structural`, and `style` completed.
* **Execution**: Read-only review; no tests or code changes.

## Findings

No design or structural Major findings. `design_critic` confirmed that the production engine/version check, same-version rehearsal, recorded result, and forward-only maintenance-mode recovery address the prior compatibility risk.

### Nits

#### `style-r5-1` — State all triggers for referral validation

**Affected scope:** Request Data and Form Flow, line 90. The overview says a new request or changed source of type `person_referral` requires exactly one of a name or explicit unknown, but could omit referral details changed while the source stays the same.

Clarify that validation applies on create, when changing the source to `person_referral`, and when explicitly changing referral details on the same source. The historical-null rule for unrelated edits remains unchanged.

#### `style-r5-2` — Separate form and service responsibilities

**Affected scope:** Request Data and Form Flow, line 94. The paragraph combines form filtering/edit display, server validation, and department boundaries.

Split it before the `RequestService` behavior to improve scanability.

#### `style-r5-3` — Condense or structure the migration summary

**Affected scope:** Migration and Historical Values, line 117. The summary repeats mappings and preservation rules from detailed implementation item 1 in a dense paragraph.

Condense it to an overview pointing to item 1 or divide it into short steps.

#### `style-r5-4` — Break up the rollout paragraph

**Affected scope:** Non-Functional Requirements, line 174. The paragraph combines the compatibility precondition, rollout, failure handling, rollback, and old-application limitation.

Break it into ordered steps while retaining the concrete operational rules.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `design_critic` | Completed | No findings |
| `structural` | Completed | No findings |
| `style` | Completed | 4 Nits |
