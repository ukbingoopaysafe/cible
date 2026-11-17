# Stage 9 — Implementation Readiness Test

**Target:** `design.md`, DetailedRange lines 177–249
**Reviewer status:** `egm-implementation-readiness` completed; `egm-codebase-details` completed; `egm-style` completed.
**Context read:** project baseline documents and Stages 5–8 reports; codebase details reviewer inspected request, follow-up, deal, lead-source, broker, user, viewing, and scheduler code/migrations.

## Findings

### 🟡 Major — Request closure does not explicitly create its follow-up case

**Affected scope:** Request close workflow and follow-up service (approximately lines 199 and 212–214).

The existing `RequestService::close()` closes the request but does not create a follow-up case. The design requires one case per closed request, including unsuccessful closures without a deal, but it does not explicitly connect the close transaction to `CustomerFollowUpService`. Add a closure-flow hook that creates the case transactionally and preserves idempotency.

**Reviewer:** `egm-codebase-details`

### 🟡 Major — Historical activities without a deal can be assigned to the wrong request

**Affected scope:** Migration `2026_11_16_050000_convert_aftercare_to_follow_up_cases.php` (approximately line 189).

The migration attaches a profile to the earliest successful request with a deal, then places activities without a deal on that profile's case. For a customer with multiple requests, this invents an association between an activity and one request. The plan also needs to define tie-breaking and how owner/due-date values are assigned to additional generated cases. Keep unmatched history outside outcome cases unless the relationship is supported by stored evidence.

**Reviewer:** `egm-implementation-readiness`

### 🟡 Major — Scheduler catch-up is unbounded and lacks a backlog signal

**Affected scope:** Notification command and scheduler requirements (approximately lines 172 and 228).

Locks and minute-by-minute scheduling do not cap post-downtime work or tell operators when backlog is growing. Define a batch limit, deterministic order, continuation behavior, backlog monitoring, and a response threshold.

**Reviewers:** `egm-implementation-readiness`, `egm-codebase-details` (Stage 8)

### 🟡 Major — Repeated notifications can be suppressed by key collisions

**Affected scope:** Notification migration and routing (approximately lines 190 and 226–228).

The key components recipient, source, event, due time, and owner collide for repeated transitions such as A → B → A. The design also derives the key from notification JSON without defining a canonical representation. Specify an occurrence ID or generation for repeatable events, explicit indexed key columns, and duplicate-insert behavior.

**Reviewers:** `egm-implementation-readiness`, `egm-codebase-details` (Stage 8), `egm-zoom-out` (Stages 5 and 8), `egm-inventive-catalyst` (Stage 5)

### 🟡 Major — Campaign source migration targets are unspecified

**Affected scope:** Migration `2026_11_16_070000_disable_test_campaign_lead_sources.php` (approximately line 191).

The feature brief names `حملة اختبار الإيجار` and `حملة اختبار إعادة البيع`, but the migration plan says only “exact” Rental and Sale test-campaign rows. The codebase allows catalog names to be added dynamically, and the seed has no campaign rows. Put the exact section-qualified labels or stable keys in the migration instructions, and define behavior when a target is absent or duplicated.

**Reviewers:** `egm-implementation-readiness`, `egm-codebase-details`, `egm-comprehension`, `egm-design`

### 🟡 Major — The feature-test plan does not give concrete assertions

**Affected scope:** Step 7 test updates and additions (approximately lines 235–245).

The plan names test files and broad topics but not concrete inputs and expected outcomes. Specify assertions for migration preservation, pause/resume catch-up, viewing transitions, follow-up migration and department isolation, repeat-event deduplication, escalation recipients, and backlog recovery.

**Reviewer:** `egm-implementation-readiness`

### 🟡 Major — The implementation sequence lacks stable, testable phase boundaries

**Affected scope:** Detailed Implementation Plan, steps 1–7.

The ordered tasks do not divide work into slices that leave the application buildable and testable, nor state schema/code deployment compatibility assumptions. Group the work into stable phases and define each phase's acceptance checks.

**Reviewer:** `egm-implementation-readiness`

### 🟡 Major — Closure acceptance and data-model documentation remain stale

**Affected scope:** Step 7 documentation updates and unsuccessful closure rule (approximately lines 205–206 and 234–241).

Existing acceptance cases `AT-136`, `AT-137`, and `AT-153` require free-text reasons and make classification optional under superseded `D-050`. `docs/DATA_MODEL.md` also still marks `outcome_reason_text` required and `outcome_reason_id` optional. The plan broadly says to update acceptance tests, but does not name these cases or update the stale data-model rule under `D-091`.

**Reviewer:** `egm-implementation-readiness`; also raised in Stage 7.

### 🟡 Major — Escalation recipient rule is inconsistent

**Affected scope:** Goal and notification implementation (approximately lines 26 and 227).

The goal says “the responsible manager,” while the implementation sends to all department-authorized managers. Choose the intended rule and require tests to assert the recipient set.

**Reviewers:** `egm-implementation-readiness`, `egm-codebase-details` (Stage 8), `egm-style` (Stage 7), `egm-inventive-catalyst` (Stage 5)

### 🟢 Nit — Stage labels are not mapped to their stored values

**Affected scope:** Stage enum updates (approximately line 183).

The plan requests Arabic labels but does not map each stored stage. State: `unit_search` → «بحث عن وحدات», `recommendation` → «ترشيح», `viewing` → «معاينة», and `negotiation` → «تفاوض».

**Reviewers:** `egm-implementation-readiness`, `egm-comprehension`

### 🟢 Nit — “Approved design” status is ambiguous

**Affected scope:** Step 7 EGM artifact status (approximately line 241).

The target is marked `Proposed`, while the concepts brief says the design is awaiting owner review, and the implementation plan calls it approved. Distinguish approval of the documented decisions from approval to begin implementation, and synchronize the concepts brief and design status.

**Reviewer:** `egm-style`

### 🟢 Nit — Migration safeguards are repeated

**Affected scope:** Steps 1 and 7; operational invariants (approximately lines 179, 183, and 249).

The general rule not to edit applied migrations or remove history appears in several places. State it once and keep only migration-specific preservation behavior beside each migration.

**Reviewer:** `egm-style`

### 🟢 Nit — Notification key rules are repeated

**Affected scope:** Notification migration, notification class, service, and scheduler (approximately lines 190 and 226–228).

The unique event key and duplicate-prevention behavior are repeated. Define the canonical key once and let other steps describe only their specific responsibility.

**Reviewer:** `egm-style`

## Reviewer notes

- `egm-implementation-readiness`: completed; nine findings.
- `egm-codebase-details`: completed; two findings, including one cross-checked against current service code and migrations.
- `egm-style`: completed; one major and two nits.
- No files were modified by reviewers and no tests were run.
