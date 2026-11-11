# Consolidated EGM Review Report

**Target:** `apartment-inspection-contact-capture.md` (Stages 5–8: lines 1–174; Stage 9: lines 175–215)  
**Review mode:** Consolidated report with all review rubrics  
**Review date:** 2026-11-11

## Overall Result

The design fits the existing shared inspection flow and department-specific records. It keeps a contact snapshot on the request history, avoids a central owner or unit catalog, and preserves historic defect values. The design is not ready to hand to implementation yet: the canonical owner-data boundary, old-writer rollout, migration recovery, service input contract, audit shape, and implementation checkpoints need to be made explicit.

No Blocker findings were reported. The items below consolidate repeated findings; the traceability matrix maps them back to each review stage.

## Prioritized Action Items

### 🟡 Major

#### M1. Reconcile the inspection-only owner-contact exception with both existing decisions

`D-015` and `D-060`, plus the data-model and workflow documents, currently prohibit or limit owner data in inspection records. The design permits a permanent owner/broker contact snapshot and currently plans to narrow only `D-060`.

**Action:** Update the design plan to reconcile both `D-015` and `D-060` and the referenced PRD/model/workflow wording. Keep the exception restricted to inspection history; do not add a central owner, property, listing, or unit entity.

#### M2. Prevent old application versions from creating contact-less inspections during rollout

The migration leaves contact fields nullable for historical rows, while the concepts brief allows an older writer to remain active. That writer can create a new inspection without the required contact snapshot.

**Action:** Specify a deployment/cutover rule that blocks inspection writes from old application versions once the new requirement takes effect. Define how a rollback reads the expanded schema and prevents the old version from resuming contact-less inspection writes. Keep database nullability for historic rows.

#### M3. Define migration refusal behavior and test snapshot preservation

The plan says `down()` refuses to drop the new columns but does not say how the refusal is surfaced or how operators recover. It also lacks an automated test of the forward-only retention guarantee.

**Action:** State that a rollback attempt aborts with a clear error and leaves both schema and saved snapshots intact; application rollback keeps the expanded schema. Add a migration test for both department tables that verifies refusal and retained contact data, and verifies existing `observed_defects` values remain unchanged.

#### M4. Specify the form-to-service phone and broker-link input contract

The design names `PhoneField` and `CustomerPhoneNormalizer` but does not specify the submitted keys, mapping from raw phone to E.164/country columns, or the broker-link flag. A target-only reader also cannot tell where the country comes from or how staff select it.

**Action:** Name the required role, contact-name, raw-phone, country, and link-confirmation input keys; specify how the selected country (defaulted to Egypt by the existing control) is submitted and validated, how normalization maps to `contact_phone_e164` and `contact_phone_country`, and define validation length/format limits. State the exact stored role values and reject missing or invalid values.

#### M5. Define the exact audit metadata contract

The design says “role and link metadata” without requiring specific fields or value encodings. This leaves privacy and audit display choices to the implementer.

**Action:** Specify the exact allowed fields and encodings, for example `contact_role` (`owner`/`broker`), whether the request-broker link was confirmed, and the linked broker ID (null or absent when unlinked). Require Arabic rendering for role values. Continue to exclude contact name, phone, and notes, and test the linked and unlinked event shapes.

#### M6. Add ordered implementation phases and stable checkpoints

The implementation plan is a flat file list followed by a test list. It does not define a safe order or what must pass before proceeding.

**Action:** Add phases such as schema/models; service validation and workflow tests; shared form/history/audit and UI tests; documentation and acceptance criteria; final verification and current-state update. Name the focused verification gate for each phase.

#### M7. Resolve the defects-input contract and revise the contradictory acceptance criterion

The concepts brief says the service stops accepting `observed_defects`, but removing its validation rule may silently ignore a submitted stale key rather than reject it. Existing `AT-086` still says a new Rental inspection records defects.

**Action:** State whether a service payload containing `observed_defects` is rejected or intentionally ignored; align the service tests with that explicit choice. Update `AT-086` to describe new notes and legacy-only defect history, and retain a separate test for read-only display of old values.

#### M8. Make the verification plan account for the known test baseline

`CURRENT_STATE.md` records 18 failures in the last full test run, including two broker-entry cases in `FilamentUiAccessTest`. The plan currently treats running the whole file as a direct pass/fail gate.

**Action:** Name the inspection-specific test methods that must pass and record the known unrelated baseline failures, or explicitly bring those failures into scope. Do not label the entire file or suite as passing while known failures remain.

### 🟢 Nit

#### N1. Guard against a broker reassignment while the form is open

The service could link the broker currently attached to the request even if it changed after staff saw and confirmed the displayed identity.

**Action:** In the same transaction, lock/re-read the request, compare the confirmed broker identity to the current relationship, and reject for reconfirmation if it changed.

#### N2. Carry the approved Arabic labels into the form and audit details

The implementation plan says “role choice,” “name,” and `PhoneField` without listing the approved labels or role mapping.

**Action:** Specify **مالك الوحدة** / **وسيط**, **اسم جهة التواصل**, **رقم هاتف جهة التواصل**, and **ربط بوسيط الطلب**, with `owner`/`broker` value mapping and Arabic role rendering in audit details.

#### N3. Separate the inspection history empty state from inspection-entry details

The current wording could imply that a contact snapshot and notes appear in an empty state.

**Action:** Say that **فحص الشقة** appears in the history heading and empty-state message; show the snapshot, notes, and any legacy defects on each existing inspection entry.

#### N4. Name both workflow sections to update

The plan refers asymmetrically to Rental inspection steps and Resale unit/contact guidance.

**Action:** Identify Rental §2.4 and Resale §3.4 and require both to describe the same contact snapshot and broker-link behavior.

#### N5. Remove the remaining editorial ambiguities and duplication

The review found a repeated broker-link rule, a vague “confirmed during drafting” sentence, an H3 `Additional Resources` heading that appears to be a peer section, and a broad “No source file or database column will be deleted” sentence that repeats the defect-retention rule.

**Action:** Keep one authoritative broker-link rule and cross-reference it; remove or cite the Open Questions sentence; make `Additional Resources` a peer-level heading if intended; remove the broad sentence and state defect-history retention once precisely.

## Findings Addressed During Review

| Finding | Review result |
| --- | --- |
| Phone country source | Stage 8 verified that existing `PhoneField` provides a country selector/default. The target design still does not explain this clearly enough for a target-only reader; see M4. |
| Workload/scalability assumption | Stage 8 found the concepts brief and existing request-history path sufficient for this feature; no additional design edit proposed. |
| Alternatives traceability | Stage 8 found concrete scope or historical-accuracy trade-offs in the alternatives; no additional edit proposed. |
| Architecture reuse and department scope | Stage 8 found the shared action/service and department-specific tables appropriate; no new central entity or abstraction was indicated. |

## Traceability Matrix

| Consolidated item | Raw finding source(s) | Status |
| --- | --- | --- |
| M1 | Stage 5 Zoom Out; Stage 7 Design Critic; Stage 8 Zoom Out | Repeated Major; unresolved in plan |
| M2 | Stage 5 Zoom Out; Stage 8 Zoom Out; Stage 9 Readiness | Repeated Major; unresolved in rollout plan |
| M3 | Stage 9 Readiness; Stage 9 Style | Major; rollback behavior and test absent |
| M4 | Stage 6 Comprehension; Stage 9 Codebase Details | Major; country selector verified in code but target contract remains unclear |
| M5 | Stage 7 Style; Stage 8 Zoom Out; Stage 9 Readiness; Stage 9 Codebase Details; Stage 9 Style | Repeated audit-contract gap |
| M6 | Stage 9 Readiness; Stage 9 Codebase Details | Repeated Major; no phases/checkpoints |
| M7 | Stage 9 Readiness; Stage 9 Codebase Details | Major; service behavior and `AT-086` conflict |
| M8 | Stage 9 Readiness | Major; existing baseline makes whole-file gate ambiguous |
| N1 | Stage 5 Inventive Catalyst; Stage 7 Design Critic; Stage 8 Zoom Out | Nit; stale confirmation race |
| N2 | Stage 9 Readiness; Stage 9 Codebase Details | Nit; form copy/value mapping implicit |
| N3 | Stage 9 Style | Nit; history empty-state wording ambiguous |
| N4 | Stage 9 Style | Nit; workflow sections not named |
| N5 | Stage 7 Structural/Style; Stage 8 Zoom Out; Stage 9 Style | Editorial nits; some earlier candidates resolved in Stage 8 |

## Stage Reports and Reviewer Completion

| Stage | Report | Status |
| --- | --- | --- |
| 5 — Initial Zoom Out | `zoom_out_report.md` | Complete; two fresh-context reviewers |
| 6 — Comprehension | `comprehension_report.md` | Complete; target-only reviewer |
| 7 — Critic Review | `critic_report.md` | Complete; design, structural, and style reviewers |
| 8 — Intermediate Zoom Out | `intermediate_zoom_out_report.md` | Complete; full-design reviewer |
| 9 — Implementation Readiness | `stage9_readiness_reviewer.md`, `stage9_codebase_reviewer.md`, `stage9_style_reviewer.md` | Complete; all three reviewers |

All reviewers confirmed the assigned target/context paths were accessible as recorded in their individual reports. No product source was changed and no tests were run during review.
