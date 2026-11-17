# Goldfish Annotated Review Report

**Target:** `docs/egm/request-management-and-follow-up/design.md` (full file; feature scope lines 1–176, detailed implementation lines 177–249)

**Review source:** `consolidated_review_report.md` and the Stage 5–9 reports
**Status:** Resolved on 2026-11-17.

## Resolution summary

- Applied all 11 `PROPOSE FIX` edits to `design.md`.
- Resolved both escalated decisions with the owner's direction: overdue escalation goes to all managers authorized for the request's department (`D-093`); document status is “Approved for planning; implementation not authorized.”
- Kept 2 contested findings unchanged and declined 2 editorial nits.
- **Counts:** 13 accepted edits/decisions; 2 contested; 2 escalations resolved; 2 declined; 4 findings intentionally left unchanged (the 2 contested items and 2 declined nits).
- Updated `concepts_brief.md` and `docs/DECISIONS.md` to record the decisions and status. No application code was changed and no tests were run.

## Escalated decisions

### [Issue 1] [EGM Goldfish reviewers] — Severity: Major

* **Affected Scope:** [Lines 26, 89, 97, 227–228](../design.md#L26) (Goals; Technical Plan; notification routing)
* **Discrepancy Description:** The goal and concepts brief say to notify “the responsible manager,” while the plan routes overdue escalations to every manager authorized for the department.
* **Rationale:** Department authorization does not identify one accountable recipient. The current application grants company managers access across departments, so this rule also determines notification fan-out.
* ***AI Suggestion:*** ESCALATE
* ***Justification:*** This is a business-recipient choice. The source documents conflict, and the implementation has no existing per-department manager assignment to resolve it.
* ***Proposed Edit:*** None until the recipient rule is confirmed.
* **Outcome:** Resolved by the owner: send the escalation to all managers authorized for the request's department; recorded as `D-093` and aligned in the design and concepts brief.

### [Issue 2] [EGM Style Goldfish] — Severity: Nit

* **Affected Scope:** [Line 241](../design.md#L241) and concepts brief line 5 (implementation status)
* **Discrepancy Description:** The design is marked `Proposed`, the concepts brief says it is awaiting owner review and does not authorize implementation, while the detailed plan calls it approved.
* **Rationale:** Earlier conversation turns approved the design sections, but did not authorize implementation. That approval evidence is outside the files the Goldfish reviewer could read.
* ***AI Suggestion:*** ESCALATE
* ***Justification:*** Preserve the distinction between approval of the documented scope and authorization to start coding. The workspace documents need a status that reflects the owner's intended approval boundary.
* ***Proposed Edit:*** None until the owner confirms the status wording. Suggested wording: “Owner-approved design; implementation not authorized.”
* **Outcome:** Resolved by the owner: use “Approved for planning; implementation not authorized” in the design and record the same boundary in the concepts brief.

## Proposed fixes

### [Issue 3] [Zoom-Out, Inventive, Readiness Goldfish] — Severity: Major

* **Affected Scope:** [Lines 97, 129, 190, 227–228](../design.md#L97) (notification key and persistence)
* **Discrepancy Description:** The key `(recipient, source, event, due time, owner)` collides when ownership changes A → B → A, so the later assignment alert can be suppressed. Deriving the key from notification JSON leaves its canonical representation unspecified.
* **Rationale:** A retained old notification and a recipient-scoped unique constraint can reject a valid new event occurrence.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Each transition needs a stable identity independent of its payload. Reminder recurrences can continue to use owner and due time.
* ***Proposed Edit:*** Define an explicit indexed `event_key`. For assignment events, include an immutable assignment-transition ID or monotonic assignment generation; for due reminders, include source, event, owner, and due-time identity. Define recipient scope and duplicate-insert behavior in the migration and service sections.
* **Outcome:** Applied. The design uses an explicit nullable `workflow_event_key`, a recipient-scoped unique index, the persisted `AuditEvent` ID for assignment transitions, and source/event/owner/due identity for reminders.

### [Issue 4] [Structural, Zoom-Out, Readiness Goldfish] — Severity: Major

* **Affected Scope:** [Lines 97, 172, 228](../design.md#L97) (scheduler recovery and notification processing)
* **Discrepancy Description:** The scheduler catches up every minute and uses locks but does not bound work after downtime or expose backlog health to operators.
* **Rationale:** A large due set and manager fan-out can delay processing and make recovery time unpredictable.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Bounded batches with stable ordering let later runs continue safely; backlog age and count make recovery observable.
* ***Proposed Edit:*** Specify a default maximum of 100 due events per invocation, ordered by `(due_at, id)`; leave unprocessed events eligible for the next run. Record pending count and age of the oldest due event, and alert when the oldest event remains overdue for more than five minutes.
* **Outcome:** Applied in scheduler requirements and the operations-document update plan.

### [Issue 5] [Codebase Details Goldfish] — Severity: Major

* **Affected Scope:** [Lines 199, 212–214](../design.md#L199) (`RequestService::close()` and follow-up case creation)
* **Discrepancy Description:** The plan requires a case for each closed request with a known customer but does not explicitly hook closure to `CustomerFollowUpService`. Existing `RequestService::close()` closes a request without creating that case.
* **Rationale:** Unsuccessful requests without a deal would otherwise never enter the follow-up workflow.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Closing a request is the event that establishes its outcome category and the request-level follow-up case.
* ***Proposed Edit:*** In the close transaction, call an idempotent `CustomerFollowUpService` operation that creates or finds the case for the closed request and known customer. Reuse the same operation when a customer is linked to an already-closed request.
* **Outcome:** Applied to the follow-up service plan.

### [Issue 6] [Implementation Readiness Goldfish] — Severity: Major

* **Affected Scope:** [Line 189](../design.md#L189) (aftercare conversion migration)
* **Discrepancy Description:** Activities without a deal are moved to the existing profile's case after that profile is attached to the earliest successful request with a deal. This invents a request association for customers with multiple requests; tie-breaking and ownership/due-date inheritance for other cases are also unspecified.
* **Rationale:** Historical activity must not be presented as belonging to a request or outcome without evidence.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The project decision says to preserve unmatched history without assigning an outcome that cannot be proven.
* ***Proposed Edit:*** Move an activity to a request case only when its existing deal resolves to that case's request. Keep activities without a provable request association in an uncategorized, history-only record accessible in customer history but excluded from success/failure/no-request groups. Define owner and due-date inheritance for generated cases; do not assign profiles to requests by date.
* **Outcome:** Applied. The existing profile remains a history-only row for customers with requests; it stays accessible in customer history but is excluded from outcome groups. Only a deal-linked activity moves to its proven request case. No date-based profile association is used.

### [Issue 7] [Comprehension, Design, Codebase Details, Readiness Goldfish] — Severity: Major

* **Affected Scope:** [Lines 191, 239](../design.md#L191) (test-campaign migration)
* **Discrepancy Description:** The migration says to deactivate exact Rental and Sale test-campaign rows but does not identify them. The concepts brief names `حملة اختبار الإيجار` and `حملة اختبار إعادة البيع`; the checked-in seed contains no such rows, and catalog entries may be dynamic.
* **Rationale:** The migration must neither miss the intended choices nor disable unrelated sources.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Section-qualified exact labels identify the requested rows while preserving historical request references.
* ***Proposed Edit:*** Name both exact labels in the migration step, scoped respectively to Rental and Sale. If absent, do nothing; if duplicates exist in the same section, deactivate all exact-name matches without deleting rows or changing historical references.
* **Outcome:** Applied to the migration plan.

### [Issue 8] [Design, Readiness Goldfish] — Severity: Major

* **Affected Scope:** [Lines 83, 235–241](../design.md#L83) (closure behavior and documentation updates)
* **Discrepancy Description:** Acceptance cases `AT-136`, `AT-137`, and `AT-153` still require free text and treat classification as optional under superseded `D-050`. `docs/DATA_MODEL.md` likewise says `outcome_reason_text` is required and `outcome_reason_id` optional.
* **Rationale:** The current text does not name these conflicting records even though `D-091` replaces that rule.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Explicitly updating the exact acceptance rows and data-model rule prevents old requirements from surviving beside the new closure contract.
* ***Proposed Edit:*** Add `AT-136`, `AT-137`, `AT-153`, and the closure-field rows in `docs/DATA_MODEL.md` to the update list. Make them require an active classification on unsuccessful closure, leave general notes optional, and state that historical `outcome_reason_text` remains stored but is not written for new closures.
* **Outcome:** Applied to the documentation update plan.

### [Issue 9] [Implementation Readiness Goldfish] — Severity: Major

* **Affected Scope:** [Lines 235–245](../design.md#L235) (test plan)
* **Discrepancy Description:** The plan lists test files and topics but not concrete inputs and expected assertions.
* **Rationale:** An implementer still has to design acceptance behavior for migrations, scheduler recovery, notification delivery, and case history.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The test plan should verify the accepted invariants, not leave their interpretation to the implementer.
* ***Proposed Edit:*** Add explicit assertions for preserved source/aftercare history, targeted stage migration, pause/resume after downtime, no result on postponed/cancelled viewings, case creation on unsuccessful closure, unmatched-activity handling, section isolation, duplicate assignment transitions, escalation recipients, and backlog continuation.
* **Outcome:** Applied as explicit required test assertions.

### [Issue 10] [Implementation Readiness Goldfish] — Severity: Major

* **Affected Scope:** [Lines 177–245](../design.md#L177) (implementation sequence)
* **Discrepancy Description:** Seven ordered work areas are listed, but they are not divided into stable, testable phases and deployment compatibility assumptions are absent.
* **Rationale:** A large schema and workflow change needs intermediate states that can be reviewed and deployed safely.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The concepts brief already calls for independently reviewable slices and dependency order.
* ***Proposed Edit:*** Organize into phases: request fields/stages; closure and pause/resume; viewing lifecycle; broker and follow-up cases; notifications; documentation and operational rollout. For each phase state schema/code compatibility, required migrations, acceptance checks, and tests before the next phase.
* **Outcome:** Applied as six ordered phases with compatibility and acceptance checkpoints.

### [Issue 11] [Design Critic Goldfish] — Severity: Major

* **Affected Scope:** [Lines 137–167](../design.md#L137) (Alternatives Considered)
* **Discrepancy Description:** Some alternatives attribute rejection to “the author” or “owner selected” without a linked issue, discussion, or PR.
* **Rationale:** The feature-spec standard requires concrete trade-offs and prohibits relying on unsupported personal attribution.
* ***AI Suggestion:*** PROPOSE FIX
* **Justification:*** Most alternatives already state technical or scope trade-offs; the personal attribution adds no evidence.
* ***Proposed Edit:*** Remove “Rejected by author” and “owner selected” wording where no link exists. Retain the concrete reason for rejection; cite `D-090`, `D-091`, or `D-092` where a recorded decision is relevant.
* **Outcome:** Applied. Alternatives now state their technical or scope rationale directly.

### [Issue 12] [Comprehension and Readiness Goldfish] — Severity: Nit

* **Affected Scope:** [Lines 83, 183](../design.md#L83) (stage enum mapping)
* **Discrepancy Description:** The plan requests Arabic labels for four stage values but only states the initial label.
* **Rationale:** The enum update should not require the implementer to infer the remaining UI labels.
* ***AI Suggestion:*** PROPOSE FIX
* **Justification:*** The four labels are already recorded in the concepts brief.
* ***Proposed Edit:*** State the mapping: `unit_search` → «بحث عن وحدات», `recommendation` → «ترشيح», `viewing` → «معاينة», `negotiation` → «تفاوض».
* **Outcome:** Applied in the request-stage plan.

### [Issue 13] [Comprehension Goldfish] — Severity: Nit

* **Affected Scope:** [Line 236](../design.md#L236) (`D-084` storage constraint)
* **Discrepancy Description:** The implementation plan refers to `D-084` without naming the storage identifiers it protects.
* **Rationale:** A fresh implementer should be able to identify the preserved tables and columns from the plan.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** `docs/DATA_MODEL.md` identifies them, but the design should link to or repeat the exact constraint.
* ***Proposed Edit:*** Name `rental_aftercare`, `resale_aftercare`, their activity tables, `aftercare_id`, and `next_follow_up_at`, or add a direct link to the `D-084` storage rule.
* **Outcome:** Applied to the DATA_MODEL update plan.

## Contested items

### [Issue 14] [Design Critic Goldfish] — Severity: Major

* **Affected Scope:** [Lines 189, 213–214](../design.md#L189) (follow-up migration and lifecycle)
* **Discrepancy Description:** The Stage 7 review said the design did not explain late customer linking, legacy-case behavior after a request is added, or unmatched historical profiles.
* **Rationale:** The target explicitly says to create the case when a customer is linked to an already-closed request; hide the legacy case from the no-request group after the customer gains a request; and preserve unmatched historical profiles/activities without assigning an unproven category.
* ***AI Suggestion:*** CONTEST
* ***Justification:*** Those parts of the critique are addressed by the cited target clauses. The distinct unresolved history-association issue is captured in Issue 6.
* ***Proposed Edit:*** No edit for this contested portion.

### [Issue 15] [Design Critic Goldfish] — Severity: Major

* **Affected Scope:** [Line 184](../design.md#L184) (Rental household migration)
* **Discrepancy Description:** The review said the plan did not preserve the old household value or specify the new category's nullability.
* **Rationale:** The migration plan explicitly keeps the existing household boolean and occupant count as historical data, prohibits inferring a category from the old boolean, and adds nullable `client_category`.
* ***AI Suggestion:*** CONTEST
* ***Justification:*** The target already states the requested preservation and nullability rules. The design does not need to invent category values from historical booleans.
* ***Proposed Edit:*** No edit for this finding.

## Declined nits

### [Issue 16] [Style Goldfish] — Severity: Nit

* **Affected Scope:** [Lines 13–17, 97–98, 127–129, 179, 183, 249](../design.md#L13)
* **Discrepancy Description:** The report identifies repeated framing, migration safeguards, and notification-persistence details.
* **Rationale:** These are editorial improvements with no effect on business behavior or implementation safety; preserve them for a later copy-edit pass.
* ***AI Suggestion:*** DECLINE
* ***Justification:*** The current review's more consequential correctness and implementation gaps should be resolved first.
* ***Proposed Edit:*** None.

### [Issue 17] [Style Goldfish] — Severity: Nit

* **Affected Scope:** [Line 9](../design.md#L9) (Overview)
* **Discrepancy Description:** Phrases such as “shared request progression and budget language” could be more concrete.
* **Rationale:** The meaning is recoverable from the Goals and Technical Plan; the wording does not block comprehension or implementation.
* ***AI Suggestion:*** DECLINE
* ***Justification:*** Defer wording polish while major design decisions remain open.
* ***Proposed Edit:*** None.
