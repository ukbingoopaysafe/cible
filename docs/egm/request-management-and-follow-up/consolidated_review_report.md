# Consolidated EGM Review — Rental and Sale Request Management

**Target:** `design.md`

**Review mode:** Uninterrupted, consolidated report
**Result:** All five stages completed; 10 Goldfish reviews completed. No Blocker findings. The reviewers did not edit the design, and no tests were run.

## Major findings

### M1 — Give every repeated notification event a distinct, canonical key

The key made from recipient, source, event, due time, and owner collides when ownership changes A → B → A. Retained history and a unique constraint can then suppress the later assignment alert. A key derived from serialized notification JSON also lacks a defined canonical form.

**Required clarification:** Define an immutable occurrence ID or generation for repeatable events, the explicit indexed key fields, and how duplicate inserts are handled. Keep due-date/owner components for recurring reminders.

### M2 — Bound scheduler recovery and make backlog visible

Row locks and a one-minute schedule do not bound catch-up work after downtime. A large overdue set or manager fan-out may delay other work, and the plan gives operators no signal that the backlog is growing.

**Required clarification:** Define a batch limit, deterministic ordering, continuation behavior, a backlog measure, and an operator response threshold.

### M3 — Choose the overdue escalation recipient rule

The goal says “the responsible manager,” while the implementation sends to every manager authorized for the department. Access authorization does not identify an accountable recipient.

**Required clarification:** State whether all authorized managers are intentionally notified or select a specific department recipient. Require tests to assert that exact recipient set.

### M4 — Create the follow-up case from the request-close flow

The design requires a follow-up case for every closed request with a known customer, including unsuccessful closures without a deal. The current `RequestService::close()` does not create a case, and the plan does not explicitly connect that transaction to `CustomerFollowUpService`.

**Required clarification:** Add an explicit, idempotent case-creation hook to request closure and keep closure plus case creation transactional.

### M5 — Do not invent request links for historical follow-up activities

The migration attaches a profile to the earliest successful request with a deal, then attaches activities without a deal to that profile's case. For a customer with multiple requests, this assigns an unsupported request identity to those activities. The plan also lacks tie-breaking and owner/due-date rules for additional generated cases.

**Required clarification:** Preserve activities whose request cannot be proven as uncategorized historical activity; define when a case may inherit profile ownership and due date, and how ties are resolved. Do not show an unsupported case as an outcome category.

### M6 — Identify the exact test-campaign catalog targets

The concepts brief names `حملة اختبار الإيجار` and `حملة اختبار إعادة البيع`, but the migration step only says “exact” Rental and Sale rows. The checked-in seed has no such campaign rows and the catalog permits dynamic names.

**Required clarification:** Put the section-qualified labels or stable catalog keys in the migration plan, and state behavior when a target is absent or appears more than once. Preserve source rows and historical request references.

### M7 — Update the specific closure rules superseded by D-091

Current acceptance cases `AT-136`, `AT-137`, and `AT-153` still require free text and treat the classification as optional under `D-050`. `docs/DATA_MODEL.md` also describes `outcome_reason_text` as required and `outcome_reason_id` as optional. The implementation plan asks to update acceptance tests generally, but does not name these cases or the stale data-model rule.

**Required clarification:** Name the acceptance cases and data-model entries to update under `D-091`; keep historical `outcome_reason_text` values as specified.

### M8 — Turn the test list into concrete assertions

The plan names test files and topics but leaves input/output expectations to the implementer.

**Required clarification:** Specify assertions for data-preserving migrations, pause/resume catch-up, viewing state transitions, follow-up migration and department isolation, repeated-event deduplication, escalation recipients, and backlog recovery.

### M9 — Split the implementation into stable, verifiable phases

The seven ordered work areas do not define phases that leave the application buildable and testable, nor deployment compatibility assumptions between schema and application changes.

**Required clarification:** Group work into incremental phases with a per-phase acceptance check and tests. State which migration/application combinations are safe during deployment.

### M10 — Remove unsupported author-attribution from alternatives

Several alternatives say “Rejected by author” or “owner selected.” The feature-spec standard requires concrete trade-offs and disallows attributing a rejection to a maintainer without a linked issue, discussion, or PR.

**Required clarification:** Cite the source for each owner decision, or remove the personal attribution and state the technical or scope reason directly.

## Nits

### N1 — Map all stage values to their Arabic display labels

State the complete mapping: `unit_search` → «بحث عن وحدات», `recommendation` → «ترشيح», `viewing` → «معاينة», and `negotiation` → «تفاوض».

### N2 — Make the D-084 storage constraint self-contained

The implementation plan refers to `D-084` without naming the protected storage identifiers. `docs/DATA_MODEL.md` names `rental_aftercare`, `resale_aftercare`, both activity tables, `aftercare_id`, and `next_follow_up_at`; link to or repeat those exact names in the plan.

### N3 — Clarify what “approved” means while status is Proposed

The design is marked `Proposed`, the concepts brief still says it awaits owner review, and the implementation plan calls it approved. Distinguish approval of the recorded business decisions from approval to begin implementation, then synchronize the brief and design status.

### N4 — Remove repeated statements

The problem section repeats its effects in its closing sentence; migration safeguards recur in multiple sections; notification-key behavior is repeated in the migration, service, and scheduler instructions. Keep each general rule in one canonical place and retain only local, migration-specific details elsewhere.

### N5 — Replace broad overview wording with named scope

Replace “shared request progression and budget language” with “shared request stages and budget labels,” and name the notification events instead of “work that needs attention.”

## Cross-stage traceability

| Consolidated item | Raw findings |
|---|---|
| M1 Notification occurrence key | S5-Z1, S5-I1, S8-Z1, S9-IR1 |
| M2 Scheduler backlog | S7-S1, S8-Z3, S9-IR2 |
| M3 Escalation recipients | S5-I2, S7-T1, S8-Z2, S9-IR8 |
| M4 Case creation on close | S7-D1 (partially), S9-CB1 |
| M5 Historical activity attribution | S7-D1 (narrowed), S9-IR3 |
| M6 Test-campaign identifiers | S6-C1, S7-D3, S9-IR4, S9-CB2 |
| M7 Closure documentation | S7-D5, S9-IR7 |
| M8 Test assertions | S9-IR5 |
| M9 Stable implementation phases | S9-IR6 |
| M10 Alternatives attribution | S7-D6 |
| N1 Stage labels | S6-C2, S9-IR9 |
| N2 D-084 storage names | S6-C3 |
| N3 Approval status | S9-ST1 |
| N4 Repetition | S7-T2, S7-T3, S9-ST2, S9-ST3 |
| N5 Overview wording | S7-T4 |

## Reconciled findings

- The reported Rental household migration gap is not an open finding: the design says to retain the old household boolean and occupant count as historical data, make the new category nullable, and not infer a category from the old boolean.
- The broader Stage 7 follow-up concern is partly covered: the design specifies late customer linking and legacy-case visibility. The open gaps are the missing close-flow integration (M4) and unsupported request attribution for activities without a deal (M5).
- The closure-documentation concern is confirmed by the current acceptance and data-model entries listed under M7. The design calls for general updates, but does not identify those specific stale rules.

## Stage artifacts

- Stage 5: `zoom_out_report.md`
- Stage 6: `comprehension_report.md`
- Stage 7: `critic_report.md`
- Stage 8: `intermediate_zoom_out_report.md`
- Stage 9: `implementation_readiness_report.md`

The review is complete. This report records findings only; the design and application code were not changed.

## Resolution update — 2026-11-17

After the review, the owner authorized the proposed design fixes and resolved both escalated decisions. The design and concepts brief now route overdue escalation to all managers authorized for the request's department and state that the design is approved for planning while implementation remains unauthorized. The new `D-093` records the recipient rule. See [`resolution-round-1/goldfish_review_report.md`](resolution-round-1/goldfish_review_report.md) for the item-by-item disposition. The contested findings and declined editorial nits remain unchanged; no application code was changed and no tests were run.
