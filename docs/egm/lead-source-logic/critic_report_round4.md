# Stage 7 — Critic Review (Round 4)

* **Target**: `lead-source-logic.md`
* **Range**: FeatureRange, lines 1–184
* **Reviewers**: `design_critic`, `structural`, and `style` completed.
* **Execution**: Read-only review; no tests or code changes.

## Findings

### Major

#### `design_critic-r4-1` — Verify production database compatibility before the maintenance window

**Affected scope:** Non-Functional Requirements, rollout and rollback (line 179), and Detailed Implementation migration plan (line 194).

The rollout depends on an additive migration and changing the catalog `type` column to non-null. The project README says production database compatibility still needs verification; current migration evidence documents MariaDB 10.4.32 but not Oracle MySQL 8. If production uses a different engine/version, a failure could leave the service unavailable while maintenance mode remains enabled.

Add a predeployment check of the production engine and version, and rehearse the exact migration against a disposable schema using that same engine/version before scheduling the maintenance window.

### Nits

#### `style-r4-1` — Calibrate the historical-value wording

**Affected scope:** Overview problem statement, line 21. “Without evidence” does not say what evidence would justify reclassifying historical call, visit, or WhatsApp entries.

Calibrate the sentence to the migration rule: these historical values remain `legacy_contact_method` unless an approved, record-specific classification establishes otherwise.

#### `style-r4-2` — Use the database null term

**Affected scope:** Open Questions, line 42. Replace “leave it empty” with “leave `lead_source_id` null” for precision.

#### `style-r4-3` — Clarify which requests can select a new source

**Affected scope:** CUJ 1, line 50. “Available to matching requests” leaves “matching” undefined.

Specify that the new source is available to requests in the same department catalog.

#### `style-r4-4` — Break up the dense request-data paragraph

**Affected scope:** Request Data and Form Flow, line 88.

The paragraph combines field storage, validation, historical edits, form visibility, and clearing dependent values. Split it into shorter paragraphs or bullets without changing the rules.

#### `style-r4-5` — Remove repeated decision summaries

**Affected scope:** “Decisions Confirmed for Detailed Implementation,” lines 127–132.

Several items repeat rules already stated in the technical plan and migration section. Trim the section to decisions not recorded elsewhere or make it a concise checklist.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `design_critic` | Completed | 1 Major |
| `structural` | Completed | No findings |
| `style` | Completed | 5 Nits |
