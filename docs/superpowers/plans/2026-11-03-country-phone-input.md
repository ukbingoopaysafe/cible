# Country Phone Input Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every phone entry flow a country selector with alphabetically ordered flag/name/dial-code labels and country-aware validation.

**Architecture:** `PhoneCountries` remains the source of country metadata and generates sorted labels. `PhoneField` becomes capable of binding a caller-provided phone/country pair. Customer and broker flows retain E.164 storage; resale-deal seller phones gain a country column and are normalized before persistence.

**Tech Stack:** Laravel 12, Filament 5, `propaganistas/laravel-phone`, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-03-country-phone-input-design.md`

## Global Constraints

- Use the existing supported-country list; Egypt (`EG`) remains the default.
- Use `flag Country (+dial)` labels, sorted alphabetically by country name.
- `🌐 Other` is last and accepts only a `+`-prefixed international number.
- Store normalized numbers in E.164 and do not alter existing saved numbers.
- Do not add frontend dependencies.

## Review Focus

- `USA/Canada` and other non-code names must sort by their visible country name.
- `Other` must remain outside the sorted group when countries are added later.
- Numbers valid for one country must fail under another selected country.
- Existing seller phone records without a country must still display safely.
- A blank optional seller phone must persist as two nulls.

---

### Task 1: Country option presentation

**Files:**
- Modify: `app/Support/PhoneCountries.php`
- Modify: `tests/Unit/PhoneCountriesTest.php`

**Interfaces:** Produces `PhoneCountries::options(): array<string, string>` with flag/name/dial labels in the required order.

- [ ] Write failing tests: assert `AU` precedes `EG`, regular labels use `🇪🇬 Egypt (+20)`, and final `ZZ` is exactly `🌐 Other`.
- [ ] Run `php artisan test tests/Unit/PhoneCountriesTest.php`; expect failure because options use ISO prefixes, reference order, and `Other (Other)`.
- [ ] Add emoji flags and sort supported countries by visible country name in `PhoneCountries::options()`; append `self::OTHER => '🌐 Other'` after sorting.
- [ ] Re-run the same test; expect pass.
- [ ] Commit `app/Support/PhoneCountries.php` and its test as `feat: format country phone options`.

### Task 2: Reusable named phone fields

**Files:**
- Modify: `app/Filament/Support/PhoneField.php`
- Modify: `tests/Feature/PhoneFieldWorkflowTest.php`

**Interfaces:** Consumes `PhoneCountries::options()`. Produces `PhoneField::make(string $label = 'رقم الهاتف', bool $required = true, string $phoneField = 'phone', string $countryField = 'phone_country'): FusedGroup`.

- [ ] Write a failing workflow assertion that a named optional phone/country pair submits using existing country-sensitive validation.
- [ ] Run `php artisan test tests/Feature/PhoneFieldWorkflowTest.php`; expect failure because field names are hard-coded.
- [ ] Replace hard-coded state names with the supplied names, preserving Arabic validation copy, LTR digits, accessibility attributes, and country-specific placeholders.
- [ ] Re-run the feature test; expect pass.
- [ ] Commit component and test as `refactor: support named country phone fields`.

### Task 3: Apply validated country input to resale seller phones

**Files:**
- Create: `database/migrations/<timestamp>_add_seller_phone_country_to_section_deals.php`
- Modify: `app/Filament/Support/SectionRequestResource.php`
- Modify: `app/Services/DealService.php`
- Modify: `tests/Feature/RequestLifecycleTest.php`
- Modify: `tests/Feature/FilamentUiAccessTest.php`

**Interfaces:** Consumes `PhoneField::make(..., 'seller_phone', 'seller_phone_country')` and `CustomerPhoneNormalizer::normalize(string, ?string): array{phone_e164: string, phone_country: string}`. Produces resale-deal `seller_phone` in E.164 plus nullable `seller_phone_country`.

- [ ] Write failing workflow tests for a normalized Saudi seller number, rejected invalid Saudi length, and blank seller phone storing both columns as null.
- [ ] Run `php artisan test tests/Feature/RequestLifecycleTest.php tests/Feature/FilamentUiAccessTest.php`; expect failure because the schema and service save an unvalidated seller phone without country.
- [ ] Add nullable two-character `seller_phone_country` columns to both section deal tables; down migration removes only these columns.
- [ ] Replace the resale seller input with the named `PhoneField`; validate allowed countries and normalize supplied seller phones in `DealService`, preserving blank optional behavior.
- [ ] Re-run the seller-phone tests; expect pass.
- [ ] Commit the slice as `feat: validate seller phone by country`.

### Task 4: Whole-flow verification

**Files:** Verify changed implementation and tests only.

- [ ] Run `php artisan test tests/Unit/PhoneCountriesTest.php tests/Unit/CustomerPhoneNormalizerTest.php tests/Feature/PhoneFieldWorkflowTest.php tests/Feature/RequestLifecycleTest.php tests/Feature/FilamentUiAccessTest.php`; expect pass.
- [ ] Run `vendor/bin/pint --dirty` then `php artisan test`; expect both commands to exit successfully.
- [ ] At 320px and desktop width, open a customer and resale deal form; verify label text, alphabetical order, final `🌐 Other`, keyboard selection, placeholders, and errors.
- [ ] Commit any formatter-only changes separately as `style: format country phone input changes`.

## Self-Review

- Tasks 1–3 cover presentation, client/broker flows through the shared field, and the remaining seller-phone entry point; Task 4 verifies UI and validation.
- Task 2 defines the exact named-field signature used by Task 3; the existing normalizer return shape supplies the persisted values.
- The review-focus cases are covered by Tasks 1 and 3.
