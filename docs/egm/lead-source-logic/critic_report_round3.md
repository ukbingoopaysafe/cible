# Stage 7 — Critic Review (Round 3)

* **Target**: `lead-source-logic.md`
* **Range**: FeatureRange, lines 1–184
* **Reviewers**: `design_critic`, `structural`, and `style` completed.
* **Execution**: Read-only review; no tests or code changes.

## Findings

### Major

#### `design_critic-r3-1` — Redact the referrer name in request audit snapshots

**Affected scope:** `lead-source-logic.md:123`; current helper `app/Services/RequestService.php:908–920`.

The design promises that request audit snapshots record that the referrer name changed without copying the literal name. The existing `RequestService::auditSnapshot()` redacts a fixed list of fields that omits `lead_source_referrer_name`. Adding that field to request updates without extending the list could store the personal name in audit before/after data.

Explicitly include `lead_source_referrer_name` in the helper's redaction list and assert that audit values omit the literal name while preserving the changed-field indicator.

### Nit

#### `design_critic-r3-2` — Reassess full-list source loading if catalogs grow

**Affected scope:** `lead-source-logic.md:178`; current form `app/Filament/Support/RequestFormSchema.php:192`.

The design assumes each department catalog remains small, but managers can add options and the form loads all catalog options into the select. The design does not state a limit or when the selection should move to server-side search.

The reviewer recommends stating an expected catalog cap or a threshold for switching to server-side search.

## No Additional Findings

`structural` found no structural issues. `style` found no wording or calibration findings; it classified the unknown-source, historical-value, and forward-only rollout rules as concrete caveats to keep.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `design_critic` | Completed | 1 Major, 1 Nit |
| `structural` | Completed | No findings |
| `style` | Completed | No findings |
