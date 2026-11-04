# Customer Follow-Up Rename Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Rename the independent post-success customer relationship from Aftercare / ما بعد الصفقة to Customer Follow-Up / متابعة العميل across runtime code and current references while preserving behavior and stored data.

**Architecture:** Rename the service, Eloquent models, customer relationship, Filament resources/pages, and view to the approved Customer Follow-Up vocabulary. Keep the current tables and legacy foreign-key columns explicit in the renamed models, recognize historical audit keys/types, write new audit keys, and redirect old page URLs to the renamed resources.

**Tech Stack:** Laravel, Eloquent, Filament, Blade, PHP, Markdown, CSS.

**Spec:** `docs/superpowers/specs/2026-11-04-customer-follow-up-rename-design.md`

## Global Constraints

- «تظل متابعة العميل مستقلة عن متابعة الطلب النشط.»
- «يبدأ المسار بعد أول صفقة ناجحة مرتبطة بعميل معروف.»
- «يشمل التغيير أسماء الكود والواجهات والوثائق، مع إبقاء أسماء جداول قاعدة البيانات وأعمدتها كما هي.»
- Keep `rental_aftercare`, `resale_aftercare`, `rental_aftercare_activities`, `resale_aftercare_activities`, `aftercare_id`, and `next_follow_up_at` unchanged.
- Preserve historical audit rows and support their existing event keys and model type strings; new events use `customer_follow_up.*`.
- Keep legacy `/admin/rental-aftercares` and `/admin/resale-aftercares` URLs working by redirecting to the new slugs and forwarding query strings.
- Preserve historical planning documents and chronology; update the current reference documents listed in Task 4.
- Do not add or run tests unless the user explicitly asks; update existing test references only where renamed symbols or routes require it. Do not create a commit unless requested.

## Review Focus

1. **Legacy storage mapping:** renamed Eloquent models must continue using the existing singular table names and `aftercare_id`; preserve explicit relationship foreign keys. Review Task 1 and statically inspect the model definitions.
2. **Historical audit data:** old `aftercare.*` event keys and old `*Aftercare` subject type strings must render with the new label; new writes use `customer_follow_up.*`. Review Task 3 and inspect both mappings and write sites.
3. **Old URLs:** old resource paths redirect to their correct new department path and retain query parameters. Review Task 2 with `php artisan route:list` and direct route definitions.
4. **Follow-up distinction:** customer follow-up wording and customer-table columns change, while request follow-up fields and behavior remain untouched. Review Tasks 2 and 4 by searching changed references and the request workflow.
5. **Eligibility and department isolation:** keep successful-deal eligibility, one record per customer per department, and existing permissions. Review Task 1's unchanged call flow and compare the modified code with `D-020` and `D-059`.

---

### Task 1: Rename the domain models, service, and customer relationship

**Files:**
- Rename `app/Services/AftercareService.php` to `app/Services/CustomerFollowUpService.php`.
- Rename `app/Models/RentalAftercare.php` to `app/Models/RentalCustomerFollowUp.php`.
- Rename `app/Models/ResaleAftercare.php` to `app/Models/ResaleCustomerFollowUp.php`.
- Rename `app/Models/RentalAftercareActivity.php` to `app/Models/RentalCustomerFollowUpActivity.php`.
- Rename `app/Models/ResaleAftercareActivity.php` to `app/Models/ResaleCustomerFollowUpActivity.php`.
- Modify `app/Models/RentalCustomer.php` and `app/Models/ResaleCustomer.php`.
- Modify `app/Services/DealService.php`, `app/Services/RequestService.php`, and `app/Services/ContactActivityService.php`.

**Interfaces:**
- Produces `CustomerFollowUpService`, `RentalCustomerFollowUp`, `ResaleCustomerFollowUp`, and corresponding `*Activity` models.
- Customer models expose `customerFollowUp()`; activity models expose `customerFollowUp()`; existing callers use these names.
- Storage stays mapped to the existing tables and the `aftercare_id` activity foreign key.

- [x] Rename declarations, imports, return annotations, and service resolution sites to the approved class names.
- [x] Set explicit table names and relationship foreign keys on renamed models so Eloquent naming conventions cannot change the persisted schema.
- [x] Rename customer and activity relationships while preserving `customer_id` and `aftercare_id` mappings.
- [x] Update deal-success and late-customer-link call sites to invoke `CustomerFollowUpService` without changing when records start.
- [x] Update the closed-request validation copy in `ContactActivityService` to direct users to «متابعة العميل» while preserving request follow-up logic.
- [x] Review the diff and confirm there are no schema or data migrations.

### Task 2: Rename Filament screens and customer-facing actions

**Files:**
- Rename `app/Filament/Support/AftercareResource.php` to `app/Filament/Support/CustomerFollowUpResource.php`.
- Rename the `app/Filament/Resources/RentalAftercares/` resource and page directory/classes to `RentalCustomerFollowUps`, `RentalCustomerFollowUpResource`, and `ListRentalCustomerFollowUps`.
- Rename the `app/Filament/Resources/ResaleAftercares/` resource and page directory/classes to `ResaleCustomerFollowUps`, `ResaleCustomerFollowUpResource`, and `ListResaleCustomerFollowUps`.
- Rename `resources/views/filament/modals/aftercare-history.blade.php` to `customer-follow-up-history.blade.php`.
- Modify `app/Filament/Support/CustomerResource.php`, `app/Filament/Widgets/OperationalRequestsOverview.php`, and `routes/web.php`.

**Interfaces:**
- Resource slugs: `rental-customer-follow-ups` and `resale-customer-follow-ups`.
- Old route aliases redirect to the matching new resource and preserve query parameters.
- Customer table labels are «مسؤول المتابعة» and «المتابعة القادمة»; both columns keep their existing visibility defaults and data.

- [x] Rename resource/page namespaces, labels, actions, relationship eager loads, and view references to Customer Follow-Up terminology.
- [x] Update the customer table's two existing column labels and attribute paths without changing the request table's follow-up column.
- [x] Update dashboard stat labels and links to the renamed resource slugs without changing urgency calculations.
- [x] Register redirects for both old URLs, forwarding the incoming query string to the equivalent new URL.
- [x] Rename the history modal view and update its empty state, headings, and supplied variable to the new vocabulary.
- [x] Inspect the resource definitions and route registrations for matching department/resource pairs.

### Task 3: Preserve audit compatibility and rename UI category metadata

**Files:**
- Modify `app/Services/CustomerFollowUpService.php` and `app/Filament/Support/AuditEventResource.php`.
- Modify `resources/css/filament/admin/theme.css`.

**Interfaces:**
- New audit event keys are `customer_follow_up.started` and `customer_follow_up.activity.recorded`.
- Historical keys `aftercare.started` and `aftercare.activity.recorded`, and historical `*Aftercare*` subject types, continue to display under «متابعة العميل».
- Customer action category becomes `customer-follow-up` and its CSS heading becomes «متابعة العميل».

- [x] Change only new event writes to the new keys; keep old keys in the display map as compatibility entries.
- [x] Update subject-type classification so both historical Aftercare class strings and renamed CustomerFollowUp class strings display as «متابعة العميل» before generic customer matching.
- [x] Rename the action-category metadata and CSS selector/content together.
- [x] Confirm no audit data migration or historical record rewrite is introduced.

### Task 4: Update existing test references and current domain documentation

**Files:**
- Modify `tests/Feature/CustomerActionsMenuStructureTest.php`, `tests/Feature/FilamentUiAccessTest.php`, and `tests/Feature/RequestLifecycleTest.php` only to follow renamed classes, actions, and URLs.
- Modify `README.md`, `GLOSSARY.md`, `docs/README.md`, `docs/DECISIONS.md`, `docs/DATA_MODEL.md`, `docs/WORKFLOWS.md`, `docs/PERMISSIONS.md`, `docs/ACCEPTANCE_TESTS.md`, `docs/PRD.md`, `docs/PROJECT_CONTEXT.md`, and `docs/CURRENT_STATE.md`.
- Leave `docs/planning-docs/`, `docs/OWNER_DECISION_PACK.md`, `docs/READINESS_REVIEW.md`, `docs/IMPLEMENTATION_READINESS_AUDIT.md`, `docs/UI_UX_VISUAL_IDENTITY_PLAN.md`, and `docs/V1_BLUEPRINT_PROPOSAL.md` unchanged as historical/planning snapshots.

**Interfaces:**
- `D-084` records the canonical term and confirms storage and behavior are unchanged.
- Current model and workflow documentation uses Customer Follow-Up names while documenting the unchanged physical table/column names.
- Existing test fixtures/assertions refer to renamed runtime symbols; their historical assertions about storage remain unchanged.

- [x] Update existing references in the three feature test files without adding cases or running tests.
- [x] Add `D-084` and update current decision summaries without rewriting the historical rationale in `D-059`.
- [x] Update current data model, workflow, permission, acceptance, PRD, and project-context wording, retaining the separation from request follow-up and the department-specific success-deal rules.
- [x] Update the product summary in `README.md` and the current workflow description in `docs/README.md`; retain labels describing historical planning sources as historical titles.
- [x] Update the current-state summary and append a dated implementation entry; preserve its prior chronology verbatim.
- [x] Keep `GLOSSARY.md` free of code and storage implementation details.
- [x] Search active runtime and current docs for stale visible terminology, allowing only physical schema identifiers, explicit legacy-route aliases, audit compatibility keys/types, and protected historical documents.

### Task 5: Static verification and final review

**Files:**
- All modified/renamed PHP, Blade, CSS, tests, and current documentation from Tasks 1–4.

- [x] Run `php -l` on each modified PHP file.
- [x] Run `php artisan route:list --path=customer-follow-up` and `php artisan route:list --path=aftercares` to confirm new resources and legacy redirects are registered.
- [x] Run the repository's Blade view compilation command for the renamed modal and clear only generated view cache afterward if needed.
- [x] Run `git diff --check` and inspect the complete diff for unintended schema changes, request-follow-up edits, generated artifacts, or unrelated files.
- [x] Do not run test suites unless the owner asks for test/verification execution.

## Self-review

- **Coverage:** domain names and relationships, call sites, Filament resources/actions, customer columns, dashboard links, route compatibility, audit history, CSS category, root and current docs, glossary, decision record, and existing test references all have explicit tasks.
- **Interfaces:** Task 1's service/models/relationship names are consumed by Tasks 2 and 3; new resource slugs are consumed by Task 2 redirects and dashboard links; the audit-key contract is shared by Tasks 1 and 3.
- **Historical boundary:** old database identifiers, audit values, prior decisions, and designated planning snapshots remain intact.
- **Verification boundary:** only static checks run unless the owner explicitly requests tests.

> **Verification update — 2026-11-04:** The owner explicitly requested “test & Review” after implementation. The full suite and focused customer-follow-up tests were run; see `docs/CURRENT_STATE.md` §87 for current results.
