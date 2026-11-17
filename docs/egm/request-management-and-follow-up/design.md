# Rental and Sale Request Management and Customer Follow-up

* **Author:** ukbingoopaysafe
* **Status:** Implemented on `main`; see [implementation status report](implementation_status_report.md).
* **Date:** 2026-11-17

## Overview

This design updates how employees record and manage Rental and Sale requests, viewings, outcomes, and customer follow-up. It gives both departments a shared request progression and budget language while keeping Rental furnishing choices and Sale property requirements specific to their workflows. It also gives employees a way to inspect broker requests and receive in-app reminders for work that needs attention.

## The Problem

The current request workflow does not consistently reflect how the team works. Rental and Sale need some of the same information, but their forms and stored values do not consistently support the requested categories. The current stages do not match the four steps staff want to use, and budget labels do not distinguish the customer's stated budget from the employee's estimate of the market.

Some important work also depends on memory or manual handling. A paused request has no planned resume date or automatic return to active status. A viewing cannot be recorded until a time is agreed, and appointment changes are mixed with viewing results. Staff can see how many requests a broker has but cannot inspect those requests from the broker page. Follow-up must distinguish successful requests, failed requests, and legacy customers with no request, while preserving each customer's history. A central in-app notification workflow does not currently cover these actions.

Without these changes, employees may record similar requests differently, lose the history of changed appointments, overlook a due follow-up, or have difficulty finding the request behind a customer or broker interaction.

## Goals

* Give Rental and Sale employees consistent client-category, people-count, budget, and request-stage fields, with the department-specific fields each workflow needs.
* Record successful and unsuccessful closure with a timestamp, require a closure classification for an unsuccessful request, and retain general notes.
* Let employees pause a request with a reason and resume date, receive a reminder before that date, and have the request return to active status automatically on the planned date.
* Record a viewing before its time is agreed, retain postponed and cancelled appointment history, and record a viewing result only after the appointment is completed.
* Let employees inspect a broker's requests and group customer follow-up by each request's outcome or by a legacy customer's lack of a recorded request.
* Notify the responsible employee in the application about assignments and due work, and route overdue follow-up to all managers authorized for the request's department without duplicate alerts.
* Keep every database change in a checked-in migration file so it can be applied after pulling the code to another environment.

## Non-Goals

* Delivering notifications through email, SMS, or WhatsApp in the first version.
* Separating follow-up by whether a rental or sale period is active or expired.
* Changing Sale's current finishing label or options.
* Building a separate marketing attribution or market-budget analytics system.
* Deleting current dummy data or migration history as part of these workflow changes; the planned final data reset is separate.

## Open Questions

No business decisions remain open for this scope. Unsuccessful requests reuse the existing active closure-classification catalog; this design does not add or replace its entries. Scheduler times use the application's Cairo timezone.

## Critical User Journeys

### Create and update a Rental or Sale request

* **Given** an employee is working in an authorized department and has at least one valid request contact.
* **When** the employee records the client category, people count, unit requirements, customer budget, market estimate, and request stage.
* **Then** the system validates and stores the department-specific fields, shows the approved labels and choices, and keeps the request within that department.

### Pause and automatically resume a request

* **Given** an active request has an owner and a next follow-up date.
* **When** the employee pauses it with a reason, resume time, and first follow-up date after resumption.
* **Then** the system stops ordinary follow-up notices, reminds the current owner one week before the resume time, and reactivates the request at the same stage when due. If the scheduler missed the time, it catches up without reopening a closed request or duplicating notices.

### Record a viewing attempt

* **Given** an active request has a recommended unit.
* **When** the employee records an appointment before the time is known, schedules it, completes it, postpones it, or cancels it.
* **Then** each attempt remains in history; only a completed attempt accepts a result, and Sale offers its four approved result choices.

### Close a request and follow up with its customer

* **Given** a request is ready to close.
* **When** the employee records a successful or unsuccessful outcome.
* **Then** the system timestamps the closure, requires an active classification for an unsuccessful outcome, and creates a separate case for the known customer and that closed request. A legacy case is available only when the department has no request for that customer.

### Inspect broker requests and act on notifications

* **Given** an employee is authorized for a department and opens one of its broker records.
* **When** the employee opens the broker's request history or selects an in-app notification.
* **Then** the employee sees only authorized requests; the notification links to its source record and can be marked read without granting edit access.

## Technical Plan

### Request forms and shared workflow

Keep the Rental and Sale request tables and models separate because their property requirements differ. Continue using the shared request form and request service for common behavior, with department-specific field rules.

Add a four-value client category and people count to both request types. Rental's current `household` boolean cannot represent these values, and Sale has neither field. Add the Sale purchase requirements as structured request data: bedroom count, bathroom count, building year, elevator, water meter, electricity meter type (`قديم` or `كارت`), and gas. Represent unanswered preferences separately from an explicit “no” so a blank value does not become a false requirement. Keep these fields nullable unless the request workflow explicitly asks the client and records an answer.

Keep the current budget columns and change their labels in create, edit, detail, list, and export views. The customer budget stores the amount stated by the customer; the market budget stores the request owner's estimate. In Rental, update the finishing field's label and choices to the approved furnishing vocabulary. Leave Sale's finishing vocabulary unchanged. Remove only the two named test-campaign lead sources wherever the section catalogs expose them.

Update both stage enums and the stage selector to the four approved values and labels: `unit_search` → «بحث عن وحدات», `recommendation` → «ترشيح», `viewing` → «معاينة», and `negotiation` → «تفاوض». New requests start at `unit_search`. In a checked-in forward migration, map only the obsolete `intake` and `qualification` values to `unit_search` before removing those enum cases. The owner approved this targeted stage conversion; unrelated dummy data is not cleaned or mapped by these migrations. Keep `closed_at`; display its date and time for both outcomes. For unsuccessful closure, require the existing classification field, remove the separate free-text reason input and validation, and retain general outcome notes.

### Pause, viewing, and follow-up

Extend the request lifecycle with a required pause reason, planned resume time, and first follow-up date after resumption. The scheduler sends one reminder to the current request owner one week before resumption, resumes only requests that are still paused and open, restores the saved follow-up date, and catches up after missed scheduler runs without duplicate notices. A close or manual resume cancels pending pause work. Stop ordinary follow-up reminders while paused. Escalate an active request's overdue follow-up once to all managers authorized for that request's department, after 24 hours overdue; retire the escalation if the request is closed, paused, reassigned, or given a new follow-up date.

Keep Sale and Rental viewing result choices department-specific. Make appointment time optional until agreed, represent scheduling state separately from the outcome, retain cancelled and postponed attempts, and allow a result only after a completed viewing. The author selected one record per appointment attempt: a completed attempt stores its result, a postponed attempt remains in history and leads to a new attempt, and a cancelled attempt has no result.

Replace the current one-profile-per-customer aftercare shape with outcome cases under `D-090`. Create one case per closed request linked to a known customer, derive success/failure from the request outcome, link the deal when available, and attach activity, owner, and due date to that case. Create a customer-keyed legacy case only when the customer has no request in the relevant department. Group cases by customer in the UI while showing each case's request or deal identity. Keep unmatched historical profiles and activities stored without assigning an outcome that cannot be proven.

Add a read-only broker request action using the existing broker-to-request relationship and the customer request-history interaction as a UI reference. Keep the sale department label change in navigation and other user-facing labels.

### In-app notifications

Add a persistent in-app notification center for assignment, reassignment decisions, contact-change approvals, follow-up due/overdue, viewing creation/schedule/result changes, pause reminder/resumption, request closure, and customer-case assignment/follow-up. Pending approval alerts go to company managers; decisions go to the requester, and cancel/decision marks the related pending alerts read. Keep the standard Laravel `notifications` table and payload, and add an explicit nullable `workflow_event_key` column for workflow notifications. A narrow Laravel database-channel adapter writes that column; do not derive it from serialized JSON. Enforce uniqueness on `(notifiable_type, notifiable_id, workflow_event_key)`. Add a separate `workflow_event_key` lookup index for prefix-based retirement; recipient-scoped deduplication queries must also use the existing composite unique index. Each item links to its source workflow and has read/unread state. Escalate an active request's overdue follow-up once, after 24 hours overdue, to all managers authorized for that request's department. An assignment event key includes the immutable `AuditEvent` ID created for that assignment transition, so A → B → A creates three distinct events. A due-reminder key includes the recipient, source record, event, due time, and owner. Reprocessing the same key is a no-op. Retire stale reminders after reassignment, rescheduling, closure, cancellation, or resumption. A scheduled process must recover missed due events and avoid sending duplicate notifications. This keeps persistence in Laravel's in-app notification system while storing deduplication identity as an explicit indexed value.

### Data flow

```mermaid
flowchart TD
  Staff[Rental or Sale employee] --> Forms[Filament request forms and actions]
  Forms --> Service[RequestService]
  Service --> RDB[(Rental request tables)]
  Service --> SDB[(Sale request tables)]
  Forms --> Viewing[RecommendationService]
  Viewing --> VDB[(Appointment and viewing records)]
  Service --> FollowUp[Customer follow-up cases]
  FollowUp --> FDB[(Case and activity records)]
  Service --> Events[Workflow events]
  Viewing --> Events
  FollowUp --> Events
  Scheduler[Laravel scheduler] --> Resume[Pause reminder and resume processing]
  Resume --> Events
  Events --> Notify[Notification routing and deduplication]
  Notify --> NDB[(Persistent in-app notifications)]
  NDB --> Center[Employee notification center]
```

Add new database changes as checked-in Laravel migrations. Do not edit previously applied migration files or erase them. The planned deletion of dummy data happens separately; until then, migrations and deployed code must safely handle the current rows. Keep Rental and Sale data boundaries explicit, while sharing common workflow rules through the existing department-aware services.

### Selected viewing storage model

Use the existing viewing record family with one row per appointment attempt. Add a nullable appointment time and appointment state while keeping the result in its own field. A row with state `completed` may have one result; rows with state `postponed` or `cancelled` have no result. When a postponed appointment is arranged again, create a new row linked to the prior attempt. This fits the approved workflow: one completed appointment yields at most one result, while schedule changes remain visible as separate attempts.

### Selected notification persistence

Use Laravel's standard `notifications` table and keep its recipient, payload, read timestamp, and UUID fields. Add a nullable `workflow_event_key` column and a unique index on `(notifiable_type, notifiable_id, workflow_event_key)`; non-workflow notifications leave the key null. A narrow Laravel database-channel adapter writes the explicit key column alongside the standard payload. Assignment keys use the persisted `AuditEvent` ID for each assignment transition; due-reminder keys include recipient, source model and ID, event, due time, and owner. Reprocessing the same key is a no-op. Store event type, source, due time, current owner, and navigation link in notification data. If the owner or due time changes, mark the prior reminder superseded/read and create one for the current owner and due time. Use Laravel's `read_at` field for read/unread state. Do not add email, SMS, or WhatsApp channels in this phase.

## Alternatives Considered

### Duplicate budget fields instead of relabeling the existing fields

* **Pros**: The old declared and targeted values could remain separately available while the new client and market amounts are recorded.
* **Cons**: Employees would have four budget fields to interpret, and reports could disagree about which pair is current.
* **Reason for Rejection**: Separate values would duplicate the customer's stated budget and the employee's market estimate, while making reports ambiguous about which pair is current. The existing columns already store those two amounts.

### One aftercare status per customer

* **Pros**: One row per customer is simple to list and prevents the same person appearing in multiple groups.
* **Cons**: A customer can have several successful and failed requests. A single status would hide earlier or concurrent request outcomes and make case activities share a schedule that belongs to different requests.
* **Reason for Rejection**: A single customer-level status would hide other successful or failed requests and force unrelated activities to share one owner and due date. Follow-up is therefore categorized per request, with a separate legacy item only when no request is recorded.

### Separate viewing appointments and viewing results

* **Pros**: Appointment scheduling and the outcome of an actual viewing each have an independent record and lifecycle.
* **Cons**: This adds tables and joins, and can duplicate data when each completed appointment has one result.
* **Reason for Rejection**: Separate appointment and result tables add joins for a one-to-one outcome and duplicate the appointment identity. One row per attempt keeps its schedule state and result together while allowing postponements to create a new linked attempt.

### A custom notification store instead of Laravel database notifications

* **Pros**: A purpose-built table can store workflow delivery and recovery states directly, with domain-specific queries.
* **Cons**: It requires a custom notification model and center, duplicating Laravel's in-app notification facilities and adding another persistence model.
* **Reason for Rejection**: A custom notification store duplicates Laravel's in-app persistence, read-state, and panel integration. The selected first phase uses Laravel notifications with an explicit workflow event key.

### External notification channels in the first release

* **Pros**: Email, SMS, or WhatsApp could reach employees while they are away from Cible.
* **Cons**: Each channel needs provider setup, delivery rules, retry behavior, and controls for duplicate or sensitive messages.
* **Reason for Rejection**: External delivery requires provider credentials, delivery retries, and rules for duplicate or sensitive messages. Keep the first release in-app and revisit external channels if staff need alerts outside the system.

### One shared request table for Rental and Sale

* **Pros**: Shared request behavior and reports could use one schema and a single stage definition.
* **Cons**: Rental and Sale have different requirements, and a unified table would accumulate department-specific nullable fields and conditional validation.
* **Reason for Rejection**: Rental and Sale have different required fields and validation. Separate tables avoid accumulating department-specific nullable fields; shared services can still implement common workflow rules.

## Non-Functional Requirements

* **Access control:** All request, broker, follow-up case, activity, and notification links must enforce existing department authorization on the server. Hidden UI controls are not an access boundary.
* **Data integrity:** Request closure and case creation must be transactional. Unique request and legacy-customer keys prevent duplicate follow-up cases. A unique `(notifiable_type, notifiable_id, workflow_event_key)` index prevents duplicate workflow notifications while allowing non-workflow notifications to leave the key null.
* **Scheduler recovery:** Scheduled processing runs every minute, uses Africa/Cairo application time, and processes at most 100 due events per invocation ordered by `(due_at, id)`. Remaining events stay eligible for the next run. Emit the pending-event count and oldest due-event age; alert when the oldest due event remains overdue for more than five minutes. Lock due requests before resuming or notifying. Closed or manually resumed requests cannot be reopened by a stale scheduler run.
* **Migration safety:** New migrations are committed and forward-only when a reverse operation would lose data. They preserve existing history and do not reset dummy data or edit previously applied migrations.
* **Section isolation:** Rental and Sale keep separate request, customer, broker, deal, and follow-up records. Shared code may not expose the other section's records or counts.

## Detailed Implementation Plan

The approved planning scope is recorded as `D-090` for customer follow-up cases, `D-091` for unsuccessful closure, `D-092` for request fields and stages, and `D-093` for overdue-escalation recipients. The owner authorized implementation on 2026-11-17. These decisions replace the older one-profile-per-customer aftercare rule, free-text closure-reason rule, and request-field/stage baseline. Implementation is on `main`; EGM records remain in `docs/egm`. All database changes use new forward migrations; no prior migration or historical request, viewing, activity, or notification data is deleted.

### Implementation sequence

1. **Add forward-only schema and data migrations.** Create the following files. Do not edit an applied migration. A rollback must not drop populated fields or erase transformed history; the stage conversion is intentionally forward-only because the two old stages map to one new stage. Correct mistakes with a new forward migration.

   * `database/migrations/2026_11_16_010000_add_request_requirement_fields.php` — add nullable `client_category` to both request tables; add `occupants` to Sale; add Sale `bedrooms`, `bathrooms`, `building_year`, nullable boolean `elevator_required`, `water_meter`, and `gas_available`, and nullable `electricity_meter_type`. Keep unanswered yes/no preferences null. Keep the existing Rental `household` boolean and occupant count as historical data; do not infer a new category from the old boolean. Rental furnishing uses the existing storage columns, so this migration does not translate old dummy furnishing values.
   * `database/migrations/2026_11_16_020000_align_rental_and_resale_request_stages.php` — update both stage columns and defaults to `unit_search`, `recommendation`, `viewing`, and `negotiation`. Convert only `intake` and `qualification` rows to `unit_search`; preserve every other value and its history. This is the only approved stage data conversion.
   * `database/migrations/2026_11_16_030000_add_request_pause_schedules.php` — add `pause_reason`, `resume_at`, and `resume_follow_up_at` to both request tables. Keep these values nullable for requests that were never paused, and do not rewrite existing pause history.
   * `database/migrations/2026_11_16_040000_add_viewing_attempt_state.php` — make `scheduled_at` nullable, add `appointment_status` and nullable `rescheduled_from_id` with a self-reference to the prior attempt in each viewing table. Convert historical cancellation result markers into cancelled appointment state with no result; treat rows with a result as completed and rows without one as scheduled. Preserve feedback, notes, actor, and timestamps.
   * `database/migrations/2026_11_16_050000_convert_customer_follow_ups_to_cases.php` — change each existing `*_aftercare` table to support request cases while retaining profile history: remove the unique constraint on `customer_id`, add nullable `request_id` and category columns, add a unique request constraint, and add nullable unique `legacy_customer_id` so a request case or no-request legacy case cannot be duplicated. This migration changes the schema only.
   * `database/migrations/2026_11_16_090000_backfill_customer_follow_up_cases.php` — forward-only data backfill for the already-applied `050000`: create one case for each closed request with a known customer; convert an existing profile into a legacy case only when its customer has no request in that department. When its customer has requests, retain the original profile row as an uncategorized history-only record with null `request_id`, category, and `legacy_customer_id`, preserving its owner, due date, and unmatched activities, and exclude it from outcome groups. If a customer with a legacy case later gains a request, keep that case accessible as read-only history, retire its due reminder, and do not treat it as an active no-request case. Move an activity to a request case only when its existing deal identifies that case's request. Never attach a profile or an activity to the earliest request by date alone. Backfilled request cases use the request's current owner and have no due date until an employee sets one; do not copy a profile-level due date across request cases. Preserve unmatched historical profiles and activities without assigning an unsupported request or outcome. Do not drop or truncate the existing aftercare tables or activity rows.
   * `database/migrations/2026_11_16_060000_create_workflow_notifications_table.php` — create Laravel's database notifications table with its standard recipient, payload, read timestamp, and UUID fields. Add a nullable `workflow_event_key` column and a unique index on `(notifiable_type, notifiable_id, workflow_event_key)`. The application writes the explicit key through a narrow Laravel database-channel adapter; do not generate it from serialized notification JSON. Keep notification history when request records change.
   * `database/migrations/2026_11_16_060100_allow_system_workflow_audit_actors.php` — allow null actor references in request status history and audit events so scheduled resumptions are represented as system actions, not attributed to an employee.
   * `database/migrations/2026_11_16_070000_disable_test_campaign_lead_sources.php` — deactivate rows only when both department and exact lead-source name match: Rental `حملة اختبار الإيجار` and Sale `حملة اختبار إعادة البيع`. If a target is absent, do nothing; if duplicate exact-name rows exist within that department, deactivate all of them. Do not delete source rows, rename other options, or change historical request source values.

2. **Update request types, validation, lifecycle services, and forms.**

   * Update `app/Enums/RentalRequestStage.php` and `app/Enums/ResaleRequestStage.php` to expose the four shared stages and their Arabic labels. Add `app/Enums/RequestClientCategory.php` for family, youth, students, and other; add `app/Enums/RentalFurnishingType.php` for «بلاط», «عادية», «لوكس», «سوبر لوكس», «فندقية», and «أخرى»; add `app/Enums/ElectricityMeterType.php` for old and card meters. Leave `app/Enums/FinishingLevel.php` and Sale finishing options unchanged. Update `app/Enums/Department.php` so the Sale label shown to users is «البيع», while keeping its stored enum value unchanged.
   * Update `app/Models/RentalRequest.php` and `app/Models/ResaleRequest.php` for the new request fields, date casts, and typed enum casts. Keep the Rental and Sale models and tables independent.
   * Update `app/Filament/Support/RequestFormSchema.php`, `app/Filament/Support/SectionRequestResource.php`, and `app/Services/RequestService.php` so create, edit, detail, request-list columns, filters, and server-side validation use the approved categories, Sale requirements, four stages, and budget labels. New requests default to `unit_search`. Return the persisted `AuditEvent` from the assignment audit path so the notification service can use its ID as the stable assignment-event identity. Keep existing values when an edit does not touch them; historical Rental furnishing values remain visible as legacy values for their current record but are not offered for a new selection.
   * In the same schema and service, remove the household toggle from active forms, retain the count field, and validate optional Sale property preferences without treating an unanswered value as “no”. Use the Rental-specific furnishing list only for Rental. Relabel the existing budget columns as customer budget and market budget; the customer amount is the amount stated by the client and the market amount is the responsible employee's estimate for comparable current units.
   * Update unsuccessful close validation and its action in `app/Services/RequestService.php` and `app/Filament/Support/SectionRequestResource.php`: require `outcome_reason_id` only for an unsuccessful outcome, remove `outcome_reason_text` from the active close form and new-write validation, and leave general outcome notes optional. Keep historical `outcome_reason_text` values stored; do not write or require them for new closures. Show the existing `closed_at` date and time for both outcomes.
   * Update pause and resume actions in `app/Services/RequestService.php` and `app/Filament/Support/SectionRequestResource.php`. Pausing requires a reason and resume date/time. The form also captures the first follow-up date after resumption so automatic activation cannot create an active request without the follow-up required by `D-008`. On scheduled resumption, restore the request to active at the same stage, apply that saved next follow-up, clear pause fields, and audit the transition. Manual resume and closure clear the planned resume values and retire pending pause notices.
   * Update `app/Exports/RentalRequestExporter.php` and `app/Exports/ResaleRequestExporter.php` to export the new requirements and the customer/market budget labels. Keep department-specific columns within their own export.

3. **Separate viewing appointment state from viewing result.**

   * Add `app/Enums/ViewingAppointmentStatus.php` with `pending_schedule`, `scheduled`, `completed`, `postponed`, and `cancelled` states. Update `app/Models/RentalViewing.php` and `app/Models/ResaleViewing.php` to cast the optional time and expose the `rescheduled_from_id` relationship.
   * Update `app/Services/RecommendationService.php` and the viewing actions in `app/Filament/Support/SectionRequestResource.php` to allow creation before a time is known. A completed attempt may have a result; pending, scheduled, postponed, and cancelled attempts may not. Postponement closes the current attempt as postponed and creates a new attempt linked to it; cancellation keeps the attempt and has no result. Show the prior and current attempts in history.
   * Keep Rental result choices unchanged. For Sale, offer only «يفكر ويدرس المكان والخدمات», «التفاوض في السعر», «معاينة مرة أخرى», and «محتاج شقة أخرى». Show the result field only when the appointment is completed.
   * Update `resources/views/filament/modals/request-property-history.blade.php` so missing appointment time is displayed as not scheduled and the history distinguishes scheduled, postponed, cancelled, and completed attempts without presenting cancellation as a result.

4. **Implement request-based customer follow-up while preserving the existing records.**

   * Add `app/Enums/CustomerFollowUpCategory.php` with successful, unsuccessful, and legacy-without-request categories. Update `app/Models/RentalCustomerFollowUp.php` and `app/Models/ResaleCustomerFollowUp.php` to link one case to its request or through `legacy_customer_id`, and expose the case category. Update `app/Models/RentalCustomerFollowUpActivity.php` and `app/Models/ResaleCustomerFollowUpActivity.php` to retain their existing history and resolve the parent case.
   * Update `app/Services/CustomerFollowUpService.php` to create or find one case per closed request with a known customer, using the request result for the category and the request owner as the initial case owner. Call this idempotent operation from `RequestService::close()` inside the same database transaction, so successful and unsuccessful closures create their cases atomically. If a customer is linked to a request after it has closed, call the same operation without reopening the request. If a deal is linked later, attach it to the existing successful case for that request rather than creating another case. Create a legacy case from the customer page only when that customer has no request in that department. If a legacy customer later gains a request, keep the old activity history but stop showing the legacy case in the no-request group; create a new request case when that request closes.
   * Change activity recording and history lookup to require a specific case ID. Keep activities manual and immutable, preserve optional activity notes and next follow-up date, and validate that an optional deal belongs to the case's customer and department. Keep each case's responsible employee and next follow-up date independent; a customer's different request cases must not share one schedule.
   * Update `app/Filament/Support/CustomerFollowUpResource.php`, `app/Filament/Support/CustomerResource.php`, `app/Filament/Resources/RentalCustomerFollowUps/RentalCustomerFollowUpResource.php`, and `app/Filament/Resources/ResaleCustomerFollowUps/ResaleCustomerFollowUpResource.php` to group cases by successful, unsuccessful, and no-request categories and show the request or deal identity. Add the customer-page action that creates a legacy case only when no request exists. Keep history-only profiles accessible through a neutral customer-history view, exclude them from all outcome groups, and never label them as “without request.”
   * Update `resources/views/filament/modals/customer-follow-up-history.blade.php` to show history for the selected case, its request/deal identity, and its independent next follow-up date.
   * Update `app/Filament/Widgets/OperationalRequestsOverview.php` to query case-level due follow-ups and keep Rental and Sale counts separate.

5. **Expose broker requests and apply Sale terminology consistently.**

   * Update `app/Filament/Support/BrokerResource.php`, `app/Filament/Resources/RentalBrokers/RentalBrokerResource.php`, and `app/Filament/Resources/ResaleBrokers/ResaleBrokerResource.php` to add a read-only request-history action and scope the query to the broker's own section. Add `resources/views/filament/modals/broker-requests.blade.php` to show the broker's requests without granting edit actions.
   * Replace user-facing «إعادة البيع» wording with «البيع» in `app/Filament/Support/AuditEventResource.php`, `app/Filament/Resources/Areas/AreaResource.php`, `app/Filament/Resources/ResaleRequests/ResaleRequestResource.php`, `app/Filament/Resources/ResaleCloseReasons/ResaleCloseReasonResource.php`, `app/Filament/Resources/ResaleBrokers/ResaleBrokerResource.php`, `app/Filament/Resources/ResaleAreas/ResaleAreaResource.php`, `app/Filament/Resources/ResaleLeadSources/ResaleLeadSourceResource.php`, `app/Filament/Resources/ResaleAuditEvents/ResaleAuditEventResource.php`, `app/Filament/Resources/ResaleCustomerFollowUps/ResaleCustomerFollowUpResource.php`, and `app/Filament/Resources/ResaleCustomers/ResaleCustomerResource.php`. Keep internal class, route, table, enum, and database identifiers stable.

6. **Add persistent, deduplicated in-app workflow notifications.**

   * Add `app/Notifications/WorkflowEventNotification.php` to serialize a short Arabic message, workflow event key, department, source type and ID, due time, current owner, and authorized in-app link. Use the persisted `AuditEvent` ID as the stable occurrence ID for each assignment transition.
   * Add `app/Notifications/Channels/WorkflowDatabaseChannel.php` as a narrow Laravel database-channel adapter that writes the standard notification payload and explicit `workflow_event_key`; `WorkflowEventNotification` selects this class as its Laravel channel. Add `app/Services/WorkflowNotificationService.php` to route and deduplicate request assignment/reassignment, contact-change request/decision, follow-up due and overdue, viewing created/scheduled/rescheduled/cancelled/result-recorded, pause reminder, automatic resumption, closure, and customer-case assignment/follow-up notifications. Wire it to `RequestService`, `RecommendationService`, `CustomerFollowUpService`, and `ContactChangeApprovalService` after successful state changes. Notify the current request or case owner; pending approval alerts go to company managers and outcomes go to the requester. Escalate a request follow-up once after it has been overdue for 24 hours to all managers authorized for that department; do not escalate if the request was closed, paused, or its due time changed. For due reminders, include recipient, source, event, due time, and owner. A repeated insert for the same key is a no-op; changed owners or due times retire stale reminders and produce one current notice.
   * Add `app/Console/Commands/ProcessWorkflowNotifications.php` to process at most 100 due events per invocation, ordered by `(due_at, id)`, in transactions with row locks. Leave remaining events eligible for the next run. Emit pending-event count and oldest due-event age; alert when the oldest event remains overdue for more than five minutes. Run it every minute from `routes/console.php`. Send the pause reminder seven days before resume; if the pause is created less than seven days before resumption, send the reminder immediately. If the scheduler was stopped until after the resume time, resume the request first and skip the obsolete pause reminder. Use Cairo application time, catch up overdue work on the next run, and use the event-key uniqueness constraint to prevent duplicate notifications.
   * Update `app/Providers/Filament/AdminPanelProvider.php` to enable Filament's database notification bell and unread list. Keep each notification link subject to the existing section authorization checks. No email, SMS, or WhatsApp delivery is added.

7. **Update project records and verify the feature.**

   * `docs/DECISIONS.md` has been updated in this drafting step with `D-090` through `D-093`; implementation must keep these decisions and their superseded references aligned.
   * Update `docs/PRD.md` for the approved request fields, stage list, Sale label, closure rule, viewing lifecycle, case categories, and in-app notifications.
   * Update `docs/WORKFLOWS.md` with the create/edit, close, pause/resume, viewing attempt, broker history, and follow-up case flows, including the saved post-resumption follow-up date, 24-hour manager escalation, and approval notification paths.
   * Update `docs/DATA_MODEL.md` with the new Rental and Sale request fields, viewing attempt state, follow-up case relationships, `workflow_event_key` and its lookup index, and all forward migration names. Under `D-084`, explicitly retain `rental_aftercare`, `resale_aftercare`, `rental_aftercare_activities`, `resale_aftercare_activities`, `aftercare_id`, and `next_follow_up_at`. Replace the stale closure-field description: `outcome_reason_id` is required for new unsuccessful closures, `outcome_reason_text` is historical and not written for new closures, and general notes remain optional.
   * Update `docs/ACCEPTANCE_TESTS.md` with field validation, targeted stage migration, pause/resume, viewing lifecycle, per-request follow-up, broker visibility, notification behavior, and department isolation. Replace `AT-136`, `AT-137`, and `AT-153` because they encode the superseded `D-050` rule; retain `AT-138` only if it is made consistent with required classification and optional general notes under `D-091`.
   * Update `docs/OPERATIONS.md` with the new scheduler task, the batch size and oldest-due-age monitoring, the five-minute backlog alert threshold, and the deployment sequence: pull the commit, run `php artisan migrate --force`, then confirm scheduler execution and notification processing. Keep every migration file committed for the separate production environment.
   * Update `tests/Feature/RequestLifecycleTest.php`, `tests/Feature/RequestTableSemanticUxTest.php`, `tests/Feature/RecommendationWorkflowTest.php`, `tests/Feature/ViewingAuditAtomicityTest.php`, `tests/Feature/BrokerWorkflowTest.php`, `tests/Feature/BrokerActionsMenuStructureTest.php`, `tests/Feature/CustomerActionsMenuStructureTest.php`, `tests/Feature/RequestActionsMenuStructureTest.php`, `tests/Feature/LeadSourceCatalogTest.php`, `tests/Feature/LeadSourceRequestFormTest.php`, `tests/Feature/LeadSourceRequestServiceTest.php`, `tests/Feature/LeadSourceExportTest.php`, `tests/Feature/FilamentUiAccessTest.php`, and `tests/Feature/DepartmentIsolationAcceptanceTest.php` for changed existing behavior.
   * Verification was added in `tests/Feature/RequestRequirementsTest.php`, `tests/Feature/RequestStageMigrationTest.php`, `tests/Feature/RequestOutcomeClosureTest.php`, `tests/Feature/PauseResumeLifecycleTest.php`, `tests/Feature/ViewingAttemptLifecycleTest.php`, `tests/Feature/CustomerFollowUpCasesTest.php`, `tests/Feature/WorkflowNotificationsTest.php`, and `tests/Feature/DisableTestCampaignLeadSourcesMigrationTest.php`, alongside updates to existing feature tests. The full automated suite passed; see the [implementation status report](implementation_status_report.md) for the run result and remaining manual acceptance.
   * Keep the EGM brief, design, and Goldfish reports in `docs/egm/request-management-and-follow-up/`. Implementation was authorized and completed on `main`; the linked implementation status report records verification and deployment notes.

### Required test assertions

The tests named above and the updated acceptance cases must assert these outcomes:

* Migrations change only `intake` and `qualification` to `unit_search`; preserve unrelated stage values, profile history, and activity rows; and disable only the exact section-qualified campaign labels.
* Unsuccessful closure fails without an active classification, succeeds with one, keeps general notes optional, and creates exactly one follow-up case for a known customer in the same transaction. Repeating closure or linking a customer later does not create a duplicate.
* Pause/resume after downtime reactivates an open paused request at its saved stage with its saved follow-up date; closed or manually resumed requests remain closed/active and receive no stale pause reminder.
* Only completed viewings may have results. Postponed attempts create a linked next attempt, while cancelled attempts remain in history without a result.
* Deal-linked historical activities move only to the request case identified by that deal. Activities without a provable request remain accessible in customer history as history-only and uncategorized, stay out of all outcome groups, and are never labeled “without request”; no cross-department case or activity is visible.
* Assignment A → B → A creates three distinct assignment events, while processing the same transition twice creates one notification. Changing owner or due time retires the stale reminder and creates one current reminder.
* Contact-change submissions notify each authorized company manager; cancellation or a decision retires the pending alerts. Approval/rejection notifies the requester with the final status and rejection reason; links open the relevant filtered review list.
* A customer-case owner change notifies the new owner and retires the old due reminder. Recording a viewing result notifies the request owner with the department-specific result.
* An overdue escalation is delivered to exactly the authorized manager recipient set defined by the accepted routing rule, once after 24 hours. A batch of 101 due events processes 100 in the first invocation and the remaining event on the next invocation; the pending count and oldest-due age are emitted.

### Stable implementation phases and compatibility checkpoints

The seven implementation areas above were delivered in these reviewable phases; automated results and outstanding manual acceptance are recorded in the [implementation status report](implementation_status_report.md):

1. **Request fields and stages:** migrations `010000` and `020000`, campaign-source deactivation `070000`, request enums, forms, validation, lists, and exports. Verify that only the approved stage values are converted and each department shows its own choices.
2. **Closure and pause/resume:** migration `030000`, closure classification, transactional case creation hook, pause fields, resume command, and reminder behavior. Verify closure idempotency and scheduler catch-up.
3. **Viewing lifecycle:** migration `040000`, appointment states, results, and viewing history. Verify time may be unknown, results require completion, and rescheduling preserves prior attempts.
4. **Follow-up cases and broker visibility:** schema migration `050000`, data backfill `090000`, case resources, activity history, and read-only broker request history. Verify historical relationships, case uniqueness, and department isolation.
5. **Workflow notifications:** migrations `060000`, `060100`, and `080000`, event-key channel adapter and lookup index, notification routing, bounded processor, and Filament notification center. Verify deduplication, recipient scope, pending approval lifecycle, backlog continuation, and stale-event retirement.
6. **Project documentation and rollout:** update decisions and product/operations records, then deploy matching schema and application code as one maintenance release. Do not serve old application code against the new stage or case schema; run the checked-in migrations before reopening the application and confirm the scheduler is active.

Each phase leaves the application buildable and its included workflow reviewable. Keep the existing migration history intact; if a deployed migration needs correction, add a forward migration rather than editing it.

### Operational invariants

* New active requests always have a next follow-up date. A pause stores the date to use after resumption; automatic resumption restores the same stage and does not create a new request or deal.
* New unsuccessful closures always have a catalog classification. The old free-text column remains for historical values but is no longer written or required.
* A viewing result exists only on a completed attempt. A cancelled or postponed attempt remains visible in history with no result.
* A request case is unique per closed request. A legacy case is unique per customer and is shown in the no-request group only while that department has no request for the customer. All case and notification reads remain section-scoped.
* No applied migration is edited, no production data is reset, and no old lead source, aftercare activity, request, or viewing row is hard-deleted by these migrations.
