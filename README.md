# Cible Real Estate CRM

Arabic-first internal CRM for rental and resale operations.

## Project status

The repository contains the Laravel 12 and Filament 5 foundation, separate rental/resale CRM workflows, scoped staff and reference-data management, department-scoped audit history, customer WhatsApp links, request, recommendation, field inspection, viewing, and contact history, deal and commission capture, customer aftercare records with follow-up tracking, XLSX customer import/export and request export, and an operational dashboard scoped to each user's department memberships. It includes archive-only customer/area lifecycle and configurable encrypted off-site database backups. The archive migration must be applied locally; the production backup destination still needs provider credentials and an archive password. The full automated suite currently passes 29 tests and 120 assertions. The approved scope and remaining decisions are documented in [`docs/README.md`](docs/README.md) and [`docs/CURRENT_STATE.md`](docs/CURRENT_STATE.md).

## Local setup

Requirements: PHP 8.2+, Composer, and MySQL.

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env` and set the database credentials.
3. Create the configured MySQL database (default name: `cible`).
4. Generate the app key with `php artisan key:generate`.
5. Run migrations with `php artisan migrate`.
6. Create the first internal user with `php artisan cible:user:create`.
7. Serve the app with `php artisan serve` and open `/admin`.

The Filament panel requires an authorized user account. The account command prompts for employee/manager role; employees choose one or both departments, while company managers automatically receive access to both departments. Managers can manage employee accounts in either department. No demo accounts are seeded.

For XAMPP with this repository at `htdocs/cible`, start Apache and MySQL, then open `http://localhost/cible/public/admin`. The first visit redirects to the login page. Create the first manager from the project directory with `php artisan cible:user:create`; there are no preconfigured login credentials or demo users. The public web entry point is `public/`.
