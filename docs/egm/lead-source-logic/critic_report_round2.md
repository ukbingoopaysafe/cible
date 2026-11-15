# Stage 7 — Critic Review (Round 2)

* **Target**: `lead-source-logic.md`
* **Range**: FeatureRange, lines 1–184
* **Reviewers**: `design_critic`, `structural`, and `style` completed.
* **Execution**: Read-only review; no tests or project code were changed.

## Findings

### Major

#### `design_critic-2-1` — Define a safe serving order for the application and migration

**Affected scope:** `lead-source-logic.md:115, 179`

The rollout says to pull the updated application and then apply the migration, while the new form and service expect columns created by that migration. Requests between those steps can fail against the old schema. Specify a deployment window that keeps the updated application from receiving work until the migration completes, or define a backward-compatible rollout.

#### `design_critic-2-2` — Approve the additional personal data included in exports

**Affected scope:** `lead-source-logic.md:123, 130, 176`

The design adds referrer names to request exports, while `docs/DECISIONS.md` D-062 limits export fields to those approved by D-030 and D-048. The new referrer snapshot is not covered by that approval. Add a superseding decision for D-062 and update the export acceptance criteria to include the name and explicit unknown state.

### Nits

#### `style-2-1` — Shorten the overview's repeated source description

**Affected scope:** `lead-source-logic.md:9`

“Main source” is repeated in a long sentence. Suggested wording: “Cible stores one optional source per request: the main source the client names when asked how they learned about Cible. If several contributed, staff ask the client to choose one.”

#### `style-2-2` — Replace vague summary terms with the concrete requirements

**Affected scope:** `lead-source-logic.md:21`

“Clear source meaning” and “safe treatment” do not name requirements. Replace them with the concrete rules: source types control conditional fields, historical values are preserved, and an unknown source remains unset.

#### `style-2-3` — State alternatives in terms of the design choice

**Affected scope:** `lead-source-logic.md:136, 160, 166`

“The author selected” repeats and centers the author rather than the technical reason. State the design choice and rationale directly; for example, “Stable source types let the form, validation, and migration share one classification.”

#### `style-2-4` — Calibrate the catalog-growth claim

**Affected scope:** `lead-source-logic.md:178` — `CALIBRATE`

“No unbounded data growth” is broader than the evidence because managers can add options and the design sets no limit. Phrase it as an assumption: each department's catalog is expected to remain small, so no separate scaling work is planned.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `design_critic` | Completed | 2 Majors |
| `structural` | Completed | No findings |
| `style` | Completed | 4 Nits |
