# Implementation Status — Request Management and Customer Follow-up

**Date:** 2026-11-17

**Branch:** `main`
**Status:** Implemented; automated verification passed. Manual UI acceptance remains outstanding where marked in `docs/ACCEPTANCE_TESTS.md`.

## Delivered

- Updated Rental and Sale request fields, approved stages, budget labels, client categories, and Sale purchase requirements.
- Added unsuccessful closure classification, closure timestamps, pause/resume scheduling, optional-time viewing attempts, and viewing history/results.
- Converted customer aftercare into request-linked follow-up cases while retaining uncategorized historical profiles and their activity history. Added forward migration `090000` after review found that the already-applied `050000` changed schema without running the planned historical data backfill. The backfill is idempotent, preserves history-only records, links activities only through their recorded deal/request, and handles Rental and Sale.
- Added department-scoped broker request history and disabled the exact test campaign sources.
- Added persistent in-app notifications for request assignments and reassignment decisions, customer/broker contact-change approvals, due work, viewing lifecycle and results, pause/resume, closure, and customer follow-up assignment/due events. Pending approval notices go to company managers; the requester receives the decision, and stale alerts are marked read using literal event-prefix matching. Overdue follow-up escalates once after 24 hours to managers authorized for the relevant department. Legacy follow-up becomes read-only and its due alert is retired when the customer gets a request. The scheduler processes up to 100 due events per run and records backlog warnings.
- Added a standalone workflow event-key index for prefix lookups; event deduplication queries scope by recipient and event key.
- Updated the relevant project and EGM records. The EGM planning documents and prior review reports remain under `docs/egm/request-management-and-follow-up/`.

## Verification

`php artisan test --compact` completed successfully on SQLite in-memory after the review fixes:

- **242 passed**
- **0 failed**
- **1,828 assertions**

This automated result does not mark manual acceptance criteria as passed. In particular, `AT-196` still calls for checking the Filament notification center, read state, and authorized links in the browser.

The local MySQL development database has the original nine feature migrations through `080000` applied; `migrate:status` showed them as `Ran`, and `db:table notifications` confirmed the standalone lookup index and recipient/event unique index. Applying them exposed an InnoDB dependency between the old unique customer index and its foreign key; `050000` adds a replacement non-unique index before dropping the old unique index. Review then found that this applied migration did not perform its planned data backfill, so `090000` now performs it without editing `050000`. The new backfill was verified by an idempotency and history-preservation test on SQLite; it has not yet been run against local MySQL or production. Production has not been changed.

## Production deployment

All schema changes are checked in as forward migrations. After pulling the changes on the production machine, apply them with:

```powershell
php artisan migrate --force
php artisan migrate:status
```

The migrations are:

1. `2026_11_16_010000_add_request_requirement_fields`
2. `2026_11_16_020000_align_rental_and_resale_request_stages`
3. `2026_11_16_030000_add_request_pause_schedules`
4. `2026_11_16_040000_add_viewing_attempt_state`
5. `2026_11_16_050000_convert_customer_follow_ups_to_cases`
6. `2026_11_16_060000_create_workflow_notifications_table`
7. `2026_11_16_060100_allow_system_workflow_audit_actors`
8. `2026_11_16_070000_disable_test_campaign_lead_sources`
9. `2026_11_16_080000_add_workflow_event_key_lookup_index`
10. `2026_11_16_090000_backfill_customer_follow_up_cases`

Laravel's scheduler must run every minute for due reminders and pause auto-resumption. Confirm it runs with the existing deployment scheduler and monitor `storage/logs/workflow-notifications.log` for backlog warnings.

## Files and records

- Implementation plan and design: `docs/egm/request-management-and-follow-up/design.md`
- Automated and manual acceptance status: `docs/ACCEPTANCE_TESTS.md`
- Migration/deployment details: `docs/DATA_MODEL.md` and `docs/OPERATIONS.md`
- Prior EGM reviews remain historical records; this report records the implementation result.
