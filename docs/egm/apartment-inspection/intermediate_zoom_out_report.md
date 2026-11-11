# Stage 8 — Intermediate Zoom Out Review

**Target:** `apartment-inspection-contact-capture.md` (read in full)  
**Review date:** 2026-11-11  
**Lens:** Skeptical Architect / Zoom Out

## Summary

The design addresses a clear workflow gap and stays close to the existing architecture. It reuses the shared `recordFieldInspection` action and `RecommendationService`, the existing `PhoneField` and `CustomerPhoneNormalizer`, and each department’s existing inspection table. Keeping a point-in-time contact snapshot on the inspection, with an optional department-specific link to the request broker, is justified by the stated history and identity requirements. No central owner/listing model, broker-directory search, new service, or speculative scaling mechanism is proposed.

The main outstanding architectural issues are the unresolved conflict with the approved owner-data boundary, and the possibility that an older application writer creates a new inspection without the required snapshot during rollout. Audit metadata requirements and the exact broker identity staff confirmed also need clearer contracts.

## Findings

### 1. Owner-contact exception is not reconciled with the approved boundary — Major

**Affected scope:** Target §Open Questions and storage/history (lines 39–41, 88–90); implementation plan rows for `DECISIONS.md`, `PRD.md`, and `DATA_MODEL.md` (lines 190–192).

The plan proposes narrowing `D-060`, but the existing `D-015` still says owner data belongs only in a resale deal when needed (`docs/DECISIONS.md:35`). `D-060` also says inspections do not store owner data (`docs/DECISIONS.md:80`), and the current model and workflow repeat that boundary (`docs/DATA_MODEL.md:239`; `docs/WORKFLOWS.md:88, 173`). A new permanent owner-contact snapshot is a deliberate exception, so changing only `D-060` leaves the canonical decision trail contradictory.

**Recommendation:** Record the approved inspection-only exception against both `D-015` and `D-060`, then update the related model/workflow/PRD references. Keep the exception confined to the inspection snapshot; the proposed design correctly avoids a central owner or unit entity.

### 2. Mixed-version rollout can still create new inspections without contacts — Major

**Affected scope:** Storage/history and rollout (lines 88, 148–153); migration and service plan (lines 183, 186).

The design keeps the new fields nullable and requires them only in the new form and service. The concepts brief explicitly allows a prior application version to keep reading and writing during the additive migration (concepts brief, “Data, audit, and operations”). That older writer can still create an inspection without contact data, contradicting the goal that every new inspection has a required snapshot. A forward-only migration prevents loss on rollback but does not close this write path.

**Recommendation:** State the deployment/cutover rule that prevents old writers from recording inspections after enforcement begins, or explicitly accept and account for null-contact records during that interval.

### 3. Audit metadata shape remains underspecified — Major

**Affected scope:** Inspection storage/history (line 92), service and audit-resource plan (lines 186, 189).

“The audit event may include” role, link selection, and broker ID does not define which values are required. “Record role and link metadata” in the implementation plan remains ambiguous. This leaves the required audit trail unclear even though the privacy boundary is clear: contact name, phone, and notes must stay out.

**Recommendation:** Specify the exact required audit fields and when the linked broker ID is present; keep contact name, phone, and notes excluded.

### 4. Broker confirmation can become stale before submission — Nit

**Affected scope:** Shared inspection flow and broker-link persistence (lines 71, 96–99); service plan (line 186).

The form asks for a boolean confirmation while showing the request’s current broker. On submission the service is planned to copy the broker ID currently attached to the recommendation’s request. If staff leave the form open while that assignment changes, the saved link may identify a broker they did not confirm. The existing request models provide department-specific `broker()` relations, so no new broker lookup abstraction is needed.

**Recommendation:** Bind the confirmation to the broker identity shown and reject it if that identity has changed by save time, or state the transaction/locking rule that guarantees the same result.

## Stage 6/7 Carry-Forward Status

| Earlier finding | Status |
| --- | --- |
| Reconcile the inspection owner-data exception with `D-015` as well as `D-060` | **Remains — Major.** The plan names only a `D-060` narrowing; see Finding 1. |
| Prevent older application versions from writing inspections without contacts during rollout | **Remains — Major.** Nullable columns plus new-version validation do not prevent legacy writes; see Finding 2. |
| Specify the country source for the normalized phone | **Addressed.** The target names `PhoneField`; the existing component exposes a country selector, defaults to `PhoneCountries::DEFAULT` (`EG`), and validates using the selected country through `CustomerPhoneNormalizer` (`app/Filament/Support/PhoneField.php:21–66`, `app/Support/PhoneCountries.php:13`). |
| Define required versus optional inspection audit fields | **Remains — Major.** See Finding 3. |
| Handle request-broker reassignment between form display and submit | **Remains — Nit.** See Finding 4. |
| Avoid repeating the broker eligibility/validation rule | **Remains — Nit.** The rule appears in both “Shared inspection flow” (line 71) and “Broker link persistence” (line 98); make one section authoritative and cross-reference it. |
| State a scale/workload assumption | **Addressed sufficiently for this design.** The concepts brief says there is no unusual-scale workload; the target retains the request/department path and adds no cache, queue, or external service. |
| Give alternatives a traceable basis | **Addressed sufficiently.** The alternatives now state concrete scope or historical-accuracy tradeoffs, even where the text still attributes a choice to the author. |
| Put “Additional Resources” at peer-section heading level | **Remains — Nit, editorial.** It is still an H3 under Non-Functional Requirements (line 162). |
| Remove or source “confirmed during drafting” in Open Questions | **Remains — Nit, editorial.** The sentence at line 41 gives no decision record or source. |

## Reuse and Scope Check

The inspected code confirms the proposed reuse: the shared action and service already handle both departments; both request models already expose their own broker relation; and the existing phone component owns the country selector and normalizer integration. No equivalent inspection contact snapshot or per-inspection broker link was found in the searched application, migration, or test paths. The separate Rental and Resale inspection tables are consistent with the project’s existing department isolation rather than unnecessary duplication.

The defect change is also narrow: stop accepting new defects while preserving old values as read-only history. This reduces the active form without discarding historical records. The detailed per-file plan is implementation-oriented, but it does not introduce a speculative abstraction. The explicit refusal to drop the new snapshot fields on rollback is consistent with the stated permanent-retention requirement; the rollout write gap above remains separate.

## Files and Verification

All paths supplied for this review were accessible.

- **Read in full:** target design, Stage 5 `zoom_out_report.md`, Stage 7 `critic_report.md`, `concepts_brief.md`, the requested service, inspection models, existing migration, `RecommendationWorkflowTest.php`, `RequestActionsMenuStructureTest.php`, and `request-property-history.blade.php`.
- **Read at relevant sections:** `DECISIONS.md`, `PRD.md`, `DATA_MODEL.md`, `WORKFLOWS.md`, `PERMISSIONS.md`, `ACCEPTANCE_TESTS.md`, `CURRENT_STATE.md`, `SectionRequestResource.php`, `AuditEventResource.php`, and `FilamentUiAccessTest.php`. Their inspection, owner-data, phone, permissions, history, audit, and existing-test passages were reviewed.
- **Code searches:** confirmed existing phone-field/normalizer and request-broker relations; no matching `contact_role` or `linked_broker_id` implementation exists in the searched app/tests/migrations.
- **Tests:** not run, as instructed.
- **Edits:** no target or product files edited; this review report is the only file written.
