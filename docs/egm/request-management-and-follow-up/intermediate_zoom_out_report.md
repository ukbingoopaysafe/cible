# Stage 8 — Intermediate Zoom Out Review

**Target:** `design.md`, full document (lines 1–249)
**Reviewer status:** `egm-zoom-out` completed.
**Cross-stage inputs:** Stages 5, 6, and 7 reports, plus the same project baseline context.

## Findings

### 🟡 Major — Repeated assignment alerts can collide

**Affected scope:** In-app notifications, selected notification persistence, notification migration, and scheduler implementation (approximately lines 97, 129, 190, and 226–228).

The notification key uses recipient, source, event, due time, and owner, while notification history is retained and the key is derived from notification data. An ownership sequence A → B → A can reuse the first assignment's key, causing the unique constraint to suppress the later alert. Define a stable occurrence ID or generation for repeat events and store an explicit canonical key.

The reviewer searched the codebase and found no existing persistent workflow notification service or processor to reuse; the application does use Laravel's `Notifiable` support.

### 🟡 Major — Escalation recipient is unresolved

**Affected scope:** Goals, Technical Plan, and notification implementation (approximately lines 26, 89, 97, and 227).

The goal says “the responsible manager,” while the implementation sends to every manager authorized for the department. Access authorization does not identify an accountable recipient. State whether this fan-out is intentional or define the intended recipient rule.

### 🟡 Major — Scheduler recovery lacks bounded workload and backlog behavior

**Affected scope:** In-app notifications, scheduler recovery requirements, and implementation step 6 (approximately lines 97, 172, and 228).

Minute-by-minute catch-up and row locks do not bound the amount of due work processed after downtime or indicate how an operator detects an increasing backlog. Define bounded processing and an operator-visible backlog signal.

## Reviewer notes

`egm-zoom-out` completed and read the target, Stage 5–7 reports, persona, standard, and all listed project context. It did not edit files or run tests.
