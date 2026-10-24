# Cible Real Estate CRM

Arabic-first internal CRM for rental and resale operations.

## Project status

The repository contains the Laravel 12 and Filament 5 foundation, user department access, and separate rental/resale customer storage. CRM screens, request workflows, and product tests have not been implemented yet. The approved scope and remaining decisions are documented in [`docs/README.md`](docs/README.md) and [`docs/CURRENT_STATE.md`](docs/CURRENT_STATE.md).

## Local setup

Requirements: PHP 8.2+, Composer, and MySQL.

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env` and set the database credentials.
3. Create the configured MySQL database (default name: `cible`).
4. Generate the app key with `php artisan key:generate`.
5. Run migrations with `php artisan migrate`.
6. Create the first internal user with `php artisan cible:user:create`.
7. Serve the app with `php artisan serve` and open `/admin`.

The Filament panel requires an authorized user account with an explicit department assignment. The account command prompts for employee/manager role and one or both departments. No demo accounts are seeded, and account management screens are not implemented yet.
