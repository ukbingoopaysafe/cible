# Goldfish Review Report: Request Management and Customer Follow-up

* **Date:** 2026-11-16
* **Mode:** EGM concept review
* **Status:** Seven reviewer lenses completed; design and operational details remain for the formal draft.

## Reviewer summary

| Lens | Main finding |
| --- | --- |
| Design critic | Aftercare ownership, appointment history, and escalation recipients need explicit rules. |
| Codebase details | Rental and Sale share form/service code but have separate schemas; Sale lacks the new category and purchase fields; pause automation and a persistent notification center do not exist. |
| Zoom out | The scope spans several workflows and should be delivered in reviewable slices; aftercare and notification automation should not be hidden inside form cleanup. |
| Comprehension | The first stage for a new request and exact viewing/aftercare behavior must be explicit. |
| Implementation readiness | Data cutover, migration order, validation, scheduler recovery, notification persistence, and observable acceptance criteria must be settled before coding. |
| Structural | Keep Rental and Sale data models separate where their requirements differ; share the four-stage contract and common lifecycle behavior through department-aware boundaries. |
| Inventive catalyst | Model follow-up around its source work item and deduplicate notifications by recipient and source event so repeat changes do not create duplicate work or alerts. |

## Consolidated findings and disposition

### 1. Aftercare is currently customer-level, while the requested classification follows each request outcome

The aftercare tables allow one profile per customer, and activities do not reference a request or an outcome case. That cannot independently represent successful and failed requests belonging to the same customer, or legacy customers with no request. The brief now proposes one follow-up case per closed request, with outcome derived from that request and an associated deal linked when available. A customer with no request recorded in the relevant department gets a separate customer-keyed legacy item. The customer page groups cases without collapsing their request history.

This is a recommended domain rule for the formal design, not an implemented schema. It still needs a precise rule for case ownership, activity history, and whether an existing legacy case changes category after the customer receives a new request.

Evidence: `database/migrations/2026_10_24_220000_create_section_aftercare_tables.php`, `app/Services/CustomerFollowUpService.php`, `RentalCustomer::customerFollowUp`, and `ResaleCustomer::customerFollowUp`.

### 2. Viewing appointment and result are separate facts

The current viewing row requires a scheduled time and stores a result on the same record. There is no appointment lifecycle or reschedule history, and results are not restricted to completed viewings. The brief now proposes an appointment lifecycle with an unknown-time state, scheduling, completion, postponement, and cancellation. A postponed attempt remains in history and leads to a new appointment entry; a result can be entered only after completion.

The formal design must specify no-shows, cancellation reasons, and how a completed appointment differs from a viewing that did not happen.

The owner selected one viewing record per appointment attempt during drafting: a postponed attempt is retained and a new attempt is created; a result is stored only on a completed attempt.

Evidence: `app/Services/RecommendationService.php`, `database/migrations/2026_10_24_230000_create_section_recommendation_viewing_tables.php`, and `app/Filament/Resources/SectionRequestResource.php`.

### 3. The current Sale form does not contain all fields the owner asked to apply to both departments

Rental has a boolean household field and people count. Sale has neither. The four-value category therefore needs persisted fields in both departments, including people count for Sale. Sale purchase requirements also need structured persisted fields. Rental and Sale request tables and enums are separate, so shared form labels do not imply identical storage.

Evidence: `app/Filament/Support/RequestFormSchema.php`, `app/Services/RequestService.php`, `app/Models/RentalRequest.php`, `app/Models/ResaleRequest.php`, and `database/migrations/2026_10_24_140000_create_section_request_tables.php`.

### 4. Request stages, closure, and pause behavior require backend changes

Both departments currently have six stages, and new requests start at `Intake`; the brief specifies four stages and sets the first stage to `بحث عن وحدات`. Budget columns and `closed_at` already exist in both schemas. Unsuccessful closure currently requires free-text reason while its classification is optional. Pause stores `paused_at`, clears normal follow-up, and has no planned resume date or automatic reactivation.

The brief now treats the four stages as the persisted workflow vocabulary, reuses and relabels existing budget values, requires a closure classification, and makes pause reason and planned resume date required. Delayed scheduler runs must not reopen closed requests or issue duplicate reminders. During drafting, the owner approved a targeted forward migration from `intake` and `qualification` to the new initial stage. Other dummy data is not cleaned or mapped; migrations and intermediate deployments must remain safe until the separately planned dummy-data reset.

Evidence: `app/Enums/RentalRequestStage.php`, `app/Enums/ResaleRequestStage.php`, `app/Services/RequestService.php`, `app/Filament/Support/RequestFormSchema.php`, and `routes/console.php`.

### 5. Notifications need persistence, deduplication, and operational ownership

The current app uses Laravel's `Notifiable` trait but has no persistent notification center for request workflow events. The brief limits the first version to in-app notifications, links them to source records, notifies the current owner, and escalates overdue work once. Notifications are deduplicated by recipient, source record, event, due date, and owner; stale reminders are updated or retired after reassignment or rescheduling.

The detailed design still needs the exact overdue threshold, manager resolution, scheduler cadence, missed-run recovery, and read-state behavior when a notification's source changes. Existing manager authorization is company-wide, so notification routing must not assume a department-specific manager already exists.

The owner selected Laravel's database notification channel during drafting, with an event key for idempotent in-app alerts. A separate notification store is not part of the selected direction.

Evidence: `app/Models/User.php`, `User::canManageDepartment()`, `routes/console.php`, and the absence of an existing request-workflow notification center.

### 6. The requested test-campaign values are not in the checked-in source seed

The user named `حملة اختبار الإيجار` and `حملة اختبار إعادة البيع`, while the checked-in source seed contains generic sources and no campaign entries. The formal design must remove those exact choices wherever they are offered without removing unrelated advertising or social-media sources.

Evidence: `database/migrations/2026_10_24_160000_seed_section_lead_sources.php`.

## Scope and delivery recommendation

Keep the requested end state intact, but split implementation into independently reviewable slices:

1. Request fields and labels, stages, Sale requirements, budget display, and source-choice cleanup.
2. Closure classification and pause/resume lifecycle.
3. Viewing appointment lifecycle and department-specific viewing results.
4. Broker request inspection and aftercare case model.
5. In-app notifications for the implemented actionable events.

The formal draft should define dependencies between these slices, field validation, database migrations, data reset/deployment order, scheduler recovery, notification recipients, and acceptance criteria. No code changes or tests were performed for this review.
