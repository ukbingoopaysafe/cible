# Stage 9 Codebase Details Review

**Scope:** Detailed Implementation Plan, lines 175–215 of `apartment-inspection-contact-capture.md`. Reviewed against the implementation-readiness standard and the requested codebase context.

## Findings

### Major — No implementation phases or checkpoints

**Affected scope:** Detailed Implementation Plan, lines 175–215.

The plan gives one combined file list and one verification list, but no ordered phases or checkpoints. The implementation-readiness standard requires logical phases, each leaving the repository stable, buildable, and testable. Add an explicit sequence (for example, additive schema/models, service behavior, shared form/history/audit, then docs/tests) and state what is expected to pass at each checkpoint. As written, the implementer must decide the safe order and intermediate verification.

### Major — Contact input and storage mapping remains an implementation choice

**Affected scope:** Migration, `RecommendationService.php`, and `SectionRequestResource.php` plan rows.

The plan names stored columns and says to use `PhoneField` and `CustomerPhoneNormalizer`, but does not define the form/service payload keys or the mapping into the snapshot columns, nor the key for the boolean request-broker link flag. The existing `PhoneField::make()` accepts configurable phone and country field names; it defaults to `phone` and `phone_country`. `CustomerPhoneNormalizer::normalize()` returns `phone_e164` and `phone_country`. Meanwhile, `RecommendationService::recordInspection()` currently validates fields and spreads the validated payload directly into the inspection create call. A fresh implementer must choose how to name and transform raw phone input into `contact_phone_e164` / `contact_phone_country`, how to name the link flag, and what column widths/validation limits to use. Specify those keys, transformations, and limits in the plan. The existing helper APIs are in `app/Filament/Support/PhoneField.php` and `app/Services/CustomerPhoneNormalizer.php`.

### Major — Existing acceptance criterion still requires entering defects

**Affected scope:** `docs/ACCEPTANCE_TESTS.md` plan row.

Current acceptance case `AT-086` says a Rental inspection records “العيوب” alongside inspection fields (line 78). The planned behavior removes defects from new submissions and keeps old values read-only. The plan says to add cases for defect-field removal, but does not explicitly revise `AT-086`; leaving that criterion active would contradict the planned behavior. Require updating `AT-086` to describe legacy-only defect history and retain a separate case for displaying existing defect values.

### Nit — Audit role values need Arabic rendering instructions

**Affected scope:** `app/Filament/Support/AuditEventResource.php` plan row.

The current audit formatter localizes field labels and booleans, but scalar values such as `owner` and `broker` otherwise render verbatim. “Add Arabic labels” does not say whether role values must also be localized. Specify that these values render as **مالك الوحدة** and **وسيط** so the new audit metadata remains readable in the Arabic interface.

## Codebase checks

The shared service and action exist at the planned paths. The service already checks department access, active request state, and recommendation status (`shared` or `selected`), and maps each department to its own inspection model. Both request models expose department-specific `broker()` relationships; the existing migration defines `rental_brokers` and `resale_brokers`, and the planned nullable department-specific foreign keys with `nullOnDelete` fit that schema. The current inspection migration, both inspection models, history Blade view, audit resource, and named feature tests exist. The proposed `2026_11_10_000000_add_contact_snapshots_to_section_field_inspections.php` does not exist, consistent with its explicit `create` marker.

No tests were run and no implementation files were changed. The only new workspace file is this report.
