# Stage 5 — Initial Zoom Out Review

**Target:** `design.md`, full document (lines 1–249)
**Context read:** project README and current-state, decisions, PRD, workflows, data model, acceptance, operations, and feature concepts brief.
**Reviewer status:** `egm-zoom-out` completed; `egm-inventive-catalyst` completed.

## Findings

### 🟡 Major — Notification keys can suppress a later assignment alert

**Affected scope:** In-app notifications; selected notification persistence; implementation sequence, step 6.

Both reviewers identified that a key made from recipient, source, event, due date, and owner cannot distinguish repeated occurrences. If ownership changes A → B → A, the last assignment can reuse the first assignment's key. Retaining the old notification then prevents creation of a new alert for the later assignment. The design also says the event key is derived from notification JSON, which leaves key stability and uniqueness dependent on payload serialization.

**Suggested resolution:** Include a stable occurrence generation or immutable assignment-transition ID in assignment keys. Keep due-date and owner components for recurring reminders. Define the unique key explicitly instead of relying on a generated JSON-derived value.

**Trade-off:** This adds event-version state and requires a defined retry and retention policy.

### 🟢 Nit — “Responsible manager” does not identify an escalation recipient

**Affected scope:** Goals; overdue escalation in “Pause, viewing, and follow-up”; in-app notifications.

The goal names a responsible manager, while the implementation sends to every manager authorized for the department. Authorization grants access but does not select an accountable manager, so this may notify managers who do not own escalation work.

**Suggested resolution:** Clarify whether every department-authorized manager is intentionally a recipient or identify an explicit escalation recipient. A separate recipient assignment adds configuration and needs a fallback when none is configured.

## Reviewer notes

- `egm-zoom-out`: confirmed the assignment-key collision and the need to include an occurrence generation; no other findings.
- `egm-inventive-catalyst`: confirmed the assignment-key collision; additionally raised the escalation-recipient ambiguity above.
- Both reviewers read the target, their assigned persona/standard, and all listed context files. No tests were run.
