# Style Review — Round 5

* **Target**: [lead-source-logic.md](./lead-source-logic.md)
* **Ranges**: FeatureRange lines 1–179 and Detailed Implementation lines 180–293 at review time
* **Reviewers**: `style` completed at Stages 7 and 9.
* **Execution**: Read-only review; no tests or edits.

## FeatureRange Findings

1. **Nit — Request Data and Form Flow:** The sentence beginning “On a new request or changed source…” could omit explicit referral-detail changes while the same source stays selected. State all three triggers: create with `person_referral`, change source to that type, or explicitly change referral details on that source. The historical-null rule is **KEEP**.
2. **Nit — Request Data and Form Flow:** Split the paragraph combining option filtering, edit behavior, server validation, and department boundaries before the `RequestService` sentence.
3. **Nit — Migration and Historical Values:** Condense the repetitive mapping summary or break it into short steps.
4. **Nit — Rollout and rollback:** Break the precondition, deploy sequence, failure handling, rollback policy, and older-application limitation into ordered steps. The compatibility and failure caveats are concrete and **KEEP**.

## Detailed Implementation Findings

1. **Major — Items 7–8:** Same dependent-value preservation issue as above. **CALIBRATE** the service rule to distinguish stale stored values from submitted new values.
2. **Nit — Item 8:** Make validation triggers easier to parse: “Require exactly one non-empty name or `lead_source_referrer_unknown = true` when creating a request with a `person_referral` source, changing a request’s source to `person_referral`, or explicitly changing referral details for that source. Reject both values. Selecting unknown clears the name; entering a name clears the unknown flag.”

## Caveat Dispositions

* **KEEP:** The no-null precondition before making `type` non-null; preserving an unchanged inactive source on unrelated edits; the production same-engine/version rehearsal and maintenance-mode failure procedure.
* **CALIBRATE:** Source-change behavior as described above so compatible submitted values survive.
* No `DROP` action was identified.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `style` — Stage 7 | Completed | 4 Nits |
| `style` — Stage 9 | Completed | 1 repeated Major, 1 Nit |
