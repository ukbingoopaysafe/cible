# Cible Real Estate CRM

Arabic-first internal CRM for rental and resale operations.

## Project status

The repository contains the Laravel 12 and Filament 5 foundation, separate rental/resale CRM workflows, scoped staff and reference-data management, audited reassignment, customer WhatsApp links, customer request history and creation shortcuts, follow-up filters, request contact history, and audited broker deletion. Application migrations are applied to the local `cible` database; product tests have not been executed. The approved scope and remaining decisions are documented in [`docs/README.md`](docs/README.md) and [`docs/CURRENT_STATE.md`](docs/CURRENT_STATE.md).

## Local setup

Requirements: PHP 8.2+, Composer, and MySQL.

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env` and set the database credentials.
3. Create the configured MySQL database (default name: `cible`).
4. Generate the app key with `php artisan key:generate`.
5. Run migrations with `php artisan migrate`.
6. Create the first internal user with `php artisan cible:user:create`.
7. Serve the app with `php artisan serve` and open `/admin`.

The Filament panel requires an authorized user account with an explicit department assignment. The account command prompts for employee/manager role and one or both departments. Managers can manage employee accounts within their department scope. No demo accounts are seeded.
