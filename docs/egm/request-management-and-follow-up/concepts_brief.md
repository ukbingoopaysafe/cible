# Rental and Sale Request Management and Customer Follow-up

* **Author:** ukbingoopaysafe
* **Date:** 2026-11-16
* **Status:** التصميم معتمد للتخطيط، والتنفيذ لم يُعتمد بعد.

## Overview

Cible's rental and sale request forms and follow-up pages need clearer, consistent choices and a more dependable request lifecycle. Staff should be able to record a client's needs, budget, request stage, viewing, pause, and outcome without mixing unlike concepts. Managers should be able to find a broker's requests, and employees should receive timely reminders for work that needs attention.

The proposal updates the existing request workflow in both departments where the business rules are shared, while keeping rental-specific furnishing terminology and sale-specific property requirements distinct. Follow-up is a case attached to a closed request, with its outcome derived from that request; an associated deal can be linked to a successful case. A legacy customer with no request recorded in the system gets a separate “without request” follow-up item. One customer can have multiple cases and appear under different outcomes. The classification does not depend on whether a rental period or sale period is currently active.

## Analysis

The request is a broad workflow change spanning intake, request lifecycle, viewings, broker visibility, post-request follow-up, and notifications. Implementing every part as a single undifferentiated change could make review and rollout difficult. The design should keep these areas traceable as separate slices while preserving shared rules between Rental and Sale.

The owner has clarified the following product behavior:

- Replace the existing family toggle with a dropdown containing `أسرة`, `شباب`, `طلاب`, and `أخرى`; keep the number-of-people field. Apply both fields to Rental and Sale. The current Sale form has neither field, so it needs both added.
- In Rental only, rename the finishing field to `نوع الفرش` and offer `بلاط`, `عادية`, `لوكس`, `سوبر لوكس`, `فندقية`, and `أخرى`. `بلاط` and `عادية` are separate choices. Sale keeps its current finishing label and options.
- Reuse the existing budget fields in Rental and Sale, relabeling them `ميزانية العميل` and `ميزانية السوق`. The request owner records the client's stated budget and estimates the market budget from comparable current units. Do not add duplicate budget fields.
- Use the four request stages `بحث عن وحدات`, `ترشيح`, `معاينة`, and `تفاوض` in both departments. A new request starts at `بحث عن وحدات`; the four choices replace the current six-stage vocabulary rather than only changing its display labels.
- Show and retain a close timestamp for successful and unsuccessful requests. For an unsuccessful close, require a closure classification, remove the separate free-text closure-reason field, and retain the general additional-notes field.
- A paused request has a required reason and planned resume date. Remind its current assigned employee one week before that date, reactivate the request automatically on that date, and notify the employee. Ordinary follow-up reminders stop while the request is paused. If the request closes before its resume date, cancel the pending resume and reminder. A missed scheduler run must catch up without sending duplicate reminders or reopening a closed request.
- A viewing may be recorded before a time is agreed. Scheduling state and viewing result are separate. The proposed lifecycle is waiting for a time, scheduled, completed, postponed, or cancelled. A postponement or cancellation remains in history; a postponement creates the next appointment record rather than overwriting the previous one. A result is entered only for a completed viewing.
- Rename the Sale department's user-facing `إعادة البيع` label to `البيع`. Remove the exact lead-source choices `حملة اختبار الإيجار` and `حملة اختبار إعادة البيع` from Rental and Sale wherever they are offered. Existing application data is dummy data that will be cleared before launch, so the concept does not require mapping those old test values.
- Let staff open and inspect a broker's requests from the broker page, as they can for customers.
- For Sale viewing results, use `يفكر ويدرس المكان والخدمات`, `التفاوض في السعر`, `معاينة مرة أخرى`, and `محتاج شقة أخرى`.
- Add Sale purchase requirements for bedroom and bathroom counts, building year, elevator, and building services: water meter, electricity meter type (`قديم` or `كارت`), and gas.
- Create one follow-up case per closed request. Derive its successful/failed category from the request outcome and link a deal when one exists; do not create a second case for the same transaction. Create a customer-keyed legacy case only when that customer has no request recorded in the relevant department. Active, unclosed requests stay in request management. Activities, owner, and due date belong to the case; the customer page groups its cases and preserves the full history.
- Start notifications with an in-app notification center. Notify the current assigned employee about assignments, due follow-ups, viewing schedule changes, pause reminders and automatic resumption, closure outcomes, and due post-deal follow-up. Escalate an overdue follow-up once to all managers authorized for that request's department. Give each assignment transition a distinct event identity; due reminders use their recipient, source, event, due date, and current owner so repeated processing is deduplicated. Update or retire stale reminders when the owner or due date changes. Notifications have read/unread state and link back to the relevant request or follow-up case. External delivery is deferred.

The owner has also clarified that current application data is test data and will be removed before the system is put into regular use. Schema changes still need checked-in migration files so they can be applied after pulling changes to another environment. Do not remove migration history or assume a database is empty during intermediate deployments; the final dummy-data reset is a separate planned operation. The owner approved one targeted stage migration: map the obsolete `intake` and `qualification` values to the new initial stage before removing those enum cases. Do not use these migrations to clean other dummy data or translate unrelated old values. No production code changes or data cleanup are authorized by this concept brief.

### What the current code implies

- `RequestFormSchema` and `RequestService` share behavior across Rental and Sale but branch by department. The request tables and stage enums remain separate. Budget columns and `closed_at` already exist in both departments, so they should be reused and relabeled rather than duplicated (`app/Filament/Support/RequestFormSchema.php`, `app/Services/RequestService.php`, `app/Enums/RentalRequestStage.php`, `app/Enums/ResaleRequestStage.php`).
- Rental stores the household choice as a boolean; Sale has no matching category or people-count fields. The requested values require a category field in both departments and people count in Sale (`app/Models/RentalRequest.php`, `database/migrations/2026_10_24_140000_create_section_request_tables.php`).
- The current stage enums have six values. A new request starts at `Intake`, so changing to four stages requires changing initialization and persisted values, not only labels (`app/Services/RequestService.php`, `app/Enums/RentalRequestStage.php`, `app/Enums/ResaleRequestStage.php`).
- Unsuccessful closure currently requires free text while its classification is optional. The requested contract makes classification required, removes the separate reason, and retains general notes (`app/Services/RequestService.php`, `app/Filament/Resources/SectionRequestResource.php`, `app/Filament/Support/RequestFormSchema.php`).
- Pause stores `paused_at`, clears normal follow-up, and resumes manually. It has no planned resume date. The scheduler currently runs backups only (`app/Services/RequestService.php`, `routes/console.php`).
- Viewing rows require `scheduled_at` and store result on the same record; there is no appointment state or retained reschedule history. The new lifecycle needs schema and service changes (`app/Services/RecommendationService.php`, `database/migrations/2026_10_24_230000_create_section_recommendation_viewing_tables.php`).
- Aftercare currently has one profile per customer; activity rows may reference a deal but not a request or an outcome case. The proposed case model changes that relationship (`app/Services/CustomerFollowUpService.php`, `database/migrations/2026_10_24_220000_create_section_aftercare_tables.php`).
- Broker rows show a request count but no request-inspection action. The customer request-history modal is a local UI pattern to follow (`app/Filament/Resources/BrokerResource.php`, `app/Filament/Resources/CustomerResource.php`).
- The application has Laravel's `Notifiable` trait but no persistent in-app notification center or request-workflow scheduler yet (`app/Models/User.php`, `routes/console.php`).

### Main risks and trade-offs

- The scope touches several workflows and may be hard to validate as one release. The design should group changes into independently reviewable slices with explicit dependencies.
- Shared request forms can make Rental and Sale terminology bleed into each other. Department-specific labels and choices must be conditional and covered by acceptance criteria.
- A single customer can have successful and failed requests over time. Case-level follow-up preserves history but can show the same customer in more than one category; each row must identify its request and deal clearly.
- Automatic resumption and reminders depend on scheduled background work. The implementation must define what happens after downtime, when the assigned employee changes, or when a request is closed before its resume date. The deployment must keep the Laravel scheduler running.
- Notifications can become noisy. Limit them to operational events and due work, deduplicate by the underlying work item, and retire stale reminders when assignments or dates change. External email, SMS, and WhatsApp delivery are deferred.
- Overdue follow-up escalations go once to all managers authorized for the request's department (`D-093`). Manager authorization is currently company-wide, so all authorized managers may receive the notice (`User::canManageDepartment()`).
- The campaign choices were not found in the checked-in lead-source seed. They may be dynamic catalog rows, so implementation must locate them by stable catalog identity or exact labels without deleting unrelated advertising sources (`database/migrations/2026_10_24_160000_seed_section_lead_sources.php`).
- Market budget is an employee estimate, not a value stated by the customer. Labels, history, and reports must keep these two amounts distinct.

## Goals vs. Non-Goals

* **In-Scope Goals:** Update Rental and Sale request fields and stages; support rental-specific furnishing choices; record closure category and timestamps; pause and resume requests with reminders; make viewing appointments possible before a time is known; add the stated Sale requirements and Sale viewing outcomes; remove the two named test-campaign sources; expose broker requests; classify aftercare by request/deal case; and provide in-app operational notifications.
* **Out-of-Scope Non-Goals:** Implementing changes in this brief; cleaning current dummy data now; migrating old furnishing values; building external email/SMS/WhatsApp notifications in the first iteration; dividing successful customers by current rental or sale period; or building a separate analytics project for market-budget accuracy.

## Alternatives Weighed

1. **Reuse the existing budget fields and change their labels and meaning.** Selected because the fields already exist in both departments and adding duplicate values would create competing sources of truth.
2. **Add separate fields for every new budget label.** Not selected because the owner clarified that the existing declared and targeted fields are the ones to rename.
3. **Classify aftercare only once per customer.** Not selected because a customer may have multiple successful and failed requests; one customer-level status would hide part of that history. Use a request/deal-level category and show its identifying context.
4. **Split successful customers by active or expired rental/sale periods.** Not selected because the owner clarified that follow-up should be classified by success, failure, or no request, without a current-period distinction.
5. **Send notifications through multiple external channels from the start.** Deferred. An in-app center gives the team one auditable place to manage assignments and due work without requiring external provider credentials and delivery policies in this first scope.
6. **Treat a viewing result as the appointment's status.** Not selected. Appointment scheduling, postponement, cancellation, and the result of a completed viewing describe different facts and should have separate records or states.

## Key Decisions & Staging for Next Steps

- Apply the four request stages and the client-category dropdown to both Rental and Sale. New requests start at `بحث عن وحدات`; Sale also gets the people-count field.
- Apply `نوع الفرش` and its new choices to Rental only; preserve Sale finishing terminology and choices.
- Reuse the existing budget fields; `ميزانية العميل` is reported by the customer and `ميزانية السوق` is estimated by the assigned request owner from comparable units.
- Keep migration files in the repository for any schema changes. Do not create migrations to clean dummy data or map unrelated old values. The owner approved mapping `intake` and `qualification` to `بحث عن وحدات` in the stage migration on 2026-11-16.
- Show closure time for both outcomes; require a closure classification for unsuccessful requests and retain general notes.
- Pause requests with a required reason and resume date, remind the current owner one week in advance, then automatically reactivate and notify. Closed requests must not be reopened by a delayed scheduler run.
- Preserve viewing appointment history and keep appointment lifecycle separate from viewing results; the appointment time may be unknown until agreed. A postponement creates a new appointment entry; only a completed appointment accepts a result.
- Use one follow-up case per closed request, with outcome derived from the request and an optional linked deal. Use a separate customer-keyed item for a legacy customer with no request recorded in that department. Do not use current rental/sale-period status as a category.
- Use one viewing record per appointment attempt. A postponed attempt remains in history and a new attempt is created; only a completed attempt has a result. This model was approved during drafting on 2026-11-16.
- Begin with in-app notifications. Notify the current assigned employee for actionable events and all managers authorized for the request's department once when a follow-up becomes overdue; deduplicate and link each alert to its source record.
- Use Laravel's database notification channel and standard notifications table, adding an event key for deduplication. This storage approach was selected during drafting on 2026-11-16.
- Keep the change set divided into reviewable implementation slices. The detailed design should define dropdown validation, exact closure classifications, service-meter controls, scheduling timezone and catch-up, data-reset/deployment order, and observable acceptance criteria before code changes begin. Overdue escalation is sent to all managers authorized for the request's department under `D-093`.
