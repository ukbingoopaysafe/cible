# Application-wide Date and Time Experience Redesign

* **Author:** ukbingoopaysafe
* **Date:** 2026-11-08
* **Review status:** Concept specification updated after EGM review; no implementation plan.

## Problem

Cible staff report that 24-hour time makes appointment and follow-up scheduling less convenient and can cause confusion. Date/time selection and display also use inconsistent presentation across existing CRM surfaces. This is stakeholder-reported feedback; no frequency or error rate has been measured.

## Proposed experience

Redesign date/time selection and presentation across existing date/time-bearing controls, displays, reports, and exports. This includes pickers, calendars, forms, tables, record details, filters, dashboards, history views, and the current customer/request exports. It does not add new report surfaces.

### Locale, calendar, time, and direction

- Use the browser's **primary preferred locale** only. A primary locale of `ar` or `ar-*` selects Arabic date/time content; `en` or `en-*` selects English. Any other or invalid primary locale falls back to Arabic; secondary browser preferences do not override an unsupported primary locale.
- Retain a recognized regional tag for date ordering and other regional date conventions. Always use the Gregorian calendar and Western numerals (`0–9`), regardless of region.
- Use zero-padded 12-hour `hh:mm` time everywhere. Arabic uses `ص` for AM and `م` for PM (for example, `09:30 ص` and `05:30 م`); English uses `AM` and `PM` (for example, `09:30 AM` and `05:30 PM`). Ensure noon (`12:00 PM`) and midnight (`12:00 AM`) round-trip correctly.
- Keep the CRM interface, field labels, content, and surrounding layout Arabic RTL. Localize only date/time values and picker internals such as month/day names and picker controls. Picker internals follow the selected locale; isolate date/time text bidirectionally so the time and period marker stay in the intended order inside RTL content.
- Render date/time values in `Africa/Cairo` regardless of the device timezone. Date-only values remain calendar dates and must not shift across timezones.

### Central presentation and export behavior

- Apply one shared date/time presentation policy to the existing Filament picker and all current display surfaces. Use the installed Filament picker and shared CRM resources as the starting seams, with a common formatter for non-picker date/time displays. Keep this policy separate from Laravel's Arabic application locale; do not mutate a process-wide locale to format an individual request.
- Keep combined date/time selection in one cohesive workflow with an explicit, convenient AM/PM control. Preserve current machine values, parsing, validation, database columns, APIs, backend representations, and business rules.
- When a user requests an export or generates a report, capture the normalized date/time locale. Reuse that snapshot for every queued chunk and retry. Keep spreadsheet column headings and the CRM interface Arabic.
- Prefer typed Excel date/time cells with locale-specific display formats rather than localized text values, so sorting, filtering, formulas, and downstream imports continue to work. The XLSX writer must be checked to confirm it can preserve typed cells and render both Arabic `ص` / `م` and English `AM` / `PM` formats.

## Goals

- Replace 24-hour presentation throughout existing date/time-bearing surfaces with consistent 12-hour values and explicit period markers.
- Localize date/time content by the browser's primary preferred locale using the rules above; keep all other CRM interface content Arabic.
- Centralize date/time formatting and picker behavior so a shared rule governs forms, tables, details, filters, dashboards, reports, and exports.
- Preserve `Africa/Cairo`, Gregorian dates, Western numerals, stored/API/backend values, parsing, validation, and business behavior.
- Meet the measurable visual and accessibility outcomes below in Arabic and English date/time modes.

## Non-goals and constraints

- No application language switch or translation of CRM labels/content.
- No new date/time-bearing screens or report surfaces.
- No database schema, stored-value, API-contract, timezone, or internal/backend representation changes.
- No change to date validation, business rules, permissions, or workflows.

## Alternatives weighed

- **Use Filament's bundled picker and shared presentation rules — selected starting point.** The installed picker already supports locale and display-format configuration, and Cible has shared Filament schemas. This avoids a new dependency while the visual and accessibility requirements are evaluated.
- **Add a separate picker package or replace Filament's picker — defer.** Reconsider only if the bundled picker cannot meet the acceptance criteria; a replacement adds code and maintenance before that limitation is established.
- **Format each screen independently — reject.** It preserves the current drift between Filament formatting and direct Carbon formatting.
- **Export localized date/time strings — reject in favor of typed Excel cells.** Strings can impair spreadsheet sorting, formulas, and downstream use; use locale-specific cell display formats and verify writer support.

## Risks and mitigations

- **Locale leaks between requests or queued jobs:** keep request formatting scoped; capture one normalized export locale at request time and reuse it for chunks/retries. Test sequential exports with different locales on the same worker.
- **Device timezone changes the displayed instant or date-only value:** force `Africa/Cairo` for date/time rendering and test with a browser timezone different from Cairo; keep date-only values timezone-free.
- **RTL, keyboard, or screen-reader regressions:** test Arabic and English picker internals within the Arabic RTL shell, including period-marker order, visible focus, keyboard operation, validation feedback, and accessible names.
- **XLSX writer cannot localize typed cells:** verify generated values and formats in Excel before rollout. Do not silently convert date/time cells to strings; resolve any writer limitation before implementation.

## Measurable UX and accessibility requirements

- Use the light Cible interface and its gold and deep-green identity colors through contrast-checked tokens. Text and meaningful control states meet WCAG AA contrast; state is not conveyed by color alone.
- At 360px, 390px, 768px, 1024px, 1280px, and 1440px widths, the picker remains within the viewport, all date/time controls remain visible and operable, and no value or period marker is clipped.
- With keyboard only, a user can open and close the picker, navigate months and dates, enter hours/minutes, choose AM/PM, clear the value, and reach validation feedback. Focus stays visible and moves predictably.
- Screen readers announce the selected date, time, AM/PM period, calendar navigation, and validation state in the picker locale. The surrounding CRM labels remain Arabic.
- Verify RTL/LTR ordering, date order, Gregorian calendar output, Western numerals, and 12-hour edge cases for `ar`, `ar-EG`, `en-US`, and `en-GB`. Verify that an unsupported primary locale (for example `fr-FR`) falls back to Arabic even if a secondary preference is English.
- Verify generated XLSX files retain typed date/time cells, show the captured locale's display format, and preserve sorting/filtering. Verify the same format persists through queued chunks and retries.

## Existing implementation context

- The application uses Laravel 12, Filament 5.10.3, Carbon 3.15.0, Arabic application locale, and `Africa/Cairo` timezone.
- Current application inputs include 10 `DateTimePicker` fields and one `DatePicker`; no application `TimePicker` or separate date/time-picker dependency was found.
- Filament's picker exposes locale and display-format configuration. Its current default time display is 24-hour (`H:i`) while its machine state format is separate (`Y-m-d H:i`).
- Date/time output is spread across Filament table/detail formatting and direct Carbon formatting in history views. Customer and request exports use queued Filament export actions and include date/time columns.
- The shared Filament resources and the visual identity plan are linked below; the implementation inventory should cover each existing date/time-bearing surface before work begins.

## Reading context

- [Project context](../../docs/PROJECT_CONTEXT.md)
- [Current state](../../docs/CURRENT_STATE.md)
- [Product requirements](../../docs/PRD.md)
- [Data model](../../docs/DATA_MODEL.md)
- [Workflows](../../docs/WORKFLOWS.md)
- [Permissions](../../docs/PERMISSIONS.md)
- [Visual identity plan](../../docs/UI_UX_VISUAL_IDENTITY_PLAN.md)
- [Acceptance tests](../../docs/ACCEPTANCE_TESTS.md)
- [Decisions](../../docs/DECISIONS.md)
- [Application timezone and locale configuration](../../config/app.php)
- [Installed Filament date/time picker](../../vendor/filament/forms/src/Components/DateTimePicker.php)
- [Filament panel setup](../../app/Providers/Filament/AdminPanelProvider.php)
- [Shared request form schema](../../app/Filament/Support/RequestFormSchema.php)
- [Shared section request resource](../../app/Filament/Support/SectionRequestResource.php)
- [Customer resource](../../app/Filament/Support/CustomerResource.php)
- [Customer follow-up resource](../../app/Filament/Support/CustomerFollowUpResource.php)
- [Spreadsheet request exporter](../../app/Exports/RentalRequestExporter.php)
- [Shared spreadsheet export formatting](../../app/Exports/Concerns/FormatsSpreadsheetData.php)
