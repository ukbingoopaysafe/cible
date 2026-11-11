# Stage 7 — Critic Review

**Target:** `apartment-inspection-contact-capture.md` (full design; feature specification lines 1–174 and implementation plan lines 175–215)

## Reviewer Status

| Reviewer | Status | Context access |
| --- | --- | --- |
| `design_critic` | completed | All supplied paths accessible; target and required materials read |
| `structural` | completed | All supplied paths accessible; target range read |
| `style` | completed | Target read in full; some long context output was clipped |

No reviewer edited files or ran tests.

## Findings

### Owner-contact exception is not yet recorded as an approved decision — Severity: Major

* **Reviewer lens:** `design_critic`
* **Affected scope:** Open Questions; contact snapshot requirements; `D-060`, `WORKFLOWS.md` §2.3, and `DATA_MODEL.md` §4.5.
* **Discrepancy:** The design describes permanent owner-contact snapshots as settled, while current canonical decisions and domain docs prohibit storing unit-owner data in inspection records. The plan proposes updating `D-060` only, leaving the existing `D-015` boundary and related references unreconciled.
* **Recommendation:** Record the owner-approved inspection-only exception in `DECISIONS.md`, reconcile both decision boundaries and related docs, and preserve the prohibition on a central owner/property entity.

### Rollout permits older writers to omit required contact snapshots — Severity: Major

* **Reviewer lens:** `zoom_out` / `inventive_catalyst` (carried forward from Stage 5).
* **Affected scope:** Concepts Brief rollout; nullable additive migration; enforcement of required contact fields.
* **Discrepancy:** An older application version may continue creating inspections during rollout without the newly required contact data.
* **Recommendation:** Define an atomic cutover or compatibility step before enforcing the requirement, or explicitly permit and account for null-contact records during rollout.

### Phone country source is unspecified — Severity: Major

* **Reviewer lens:** `comprehension` (carried forward from Stage 6).
* **Affected scope:** Phone form input and service contract.
* **Discrepancy:** The service persists a country value, but the design does not explain whether the form selects it, infers it, or obtains it from `PhoneField`.
* **Recommendation:** Specify the required country selector/default and whether staff must verify the country.

### Audit-field requirements are ambiguous — Severity: Major

* **Reviewer lens:** `style`
* **Affected scope:** §Inspection storage and history, line 92.
* **Discrepancy:** “May include” does not say which audit fields are mandatory versus optional.
* **Recommendation:** List the required and optional audit fields explicitly.

### Broker link can become stale before submission — Severity: Nit

* **Reviewer lens:** `design_critic`
* **Affected scope:** §Shared inspection flow and §Broker link persistence.
* **Discrepancy:** A broker reassignment concurrent with inspection submission can make the saved link differ from the identity staff confirmed.
* **Recommendation:** Re-read/lock the request and validate the confirmed broker while saving, or define which concurrent change takes effect.

### Broker-link eligibility and validation are repeated — Severity: Nit

* **Reviewer lens:** `style`
* **Affected scope:** §Shared inspection flow and §Broker link persistence.
* **Discrepancy:** The same request-broker rule appears in both sections.
* **Recommendation:** State it once and cross-reference it from the other section.

### Alternative rejections need a traceable basis — Severity: Nit

* **Reviewer lens:** `design_critic`
* **Affected scope:** Alternatives Considered, lines 106, 118, and 124.
* **Discrepancy:** Some rejections are attributed to the author without a linked decision source.
* **Recommendation:** Link the supporting decision discussion or state the technical/scope trade-off directly.

### Performance section lacks an operating-volume assumption — Severity: Major

* **Reviewer lens:** `structural`
* **Affected scope:** Non-Functional Requirements, lines 155–158.
* **Discrepancy:** The section says no new service or cross-department lookup is introduced but does not state a scalability limit or volume assumption and mitigation.
* **Recommendation:** State expected workload, or clarify that no new scale-specific limit is introduced under the existing request-history model and name the assumption.

### Additional Resources heading is nested at the wrong level — Severity: Nit

* **Reviewer lens:** `structural`
* **Affected scope:** Line 162.
* **Discrepancy:** `### Additional Resources` appears to be a standalone section but is nested under Non-Functional Requirements by heading level.
* **Recommendation:** Change it to an H2 heading if it is a peer section.

### Open Questions wording is vague — Severity: Nit

* **Reviewer lens:** `style`
* **Affected scope:** §Open Questions, line 41.
* **Discrepancy:** “Confirmed during drafting” adds no specific traceability after “None.”
* **Recommendation:** Remove it or cite the decision record.
