# Cible Real Estate CRM

Arabic-first internal CRM for rental and resale operations.

## Project status

The repository contains the Laravel 12 and Filament 5 CRM for rental and resale, with separate department workflows, audit history, request follow-up, deals and aftercare, XLSX import/export, and encrypted off-site backup support. The latest full suite passed 37 tests and 146 assertions on SQLite in-memory. The two commission migration tests also passed on MariaDB 10.4.32 in a disposable schema; this does not establish Oracle MySQL 8 compatibility. GitHub Actions was triggered for commit `072063bf1db76e608d1d0d0a54d632543e98541f`, but its job did not start because GitHub reported an account billing lock ([run details](https://github.com/ukbingoopaysafe/cible/actions/runs/40306607732)). Neither production deployment nor production backup operations have been verified. See [`docs/ACCEPTANCE_TESTS.md`](docs/ACCEPTANCE_TESTS.md) and [`docs/CURRENT_STATE.md`](docs/CURRENT_STATE.md) for evidence and remaining gaps.

## Local setup

Requirements: PHP 8.2+, Composer, Node.js 22+, npm, and a MySQL-compatible database. Production database version compatibility still needs verification.

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env` and set the database credentials.
3. Create the configured MySQL database (default name: `cible`).
4. Generate the app key with `php artisan key:generate`.
5. Run migrations with `php artisan migrate`.
6. Create the first internal user with `php artisan cible:user:create`.
7. Serve the app with `php artisan serve` and open `/admin`.

For frontend assets, run `npm ci` followed by `npm run build`.

The Filament panel requires an authorized user account. The account command prompts for employee/manager role; employees choose one or both departments, while company managers automatically receive access to both departments. Managers can manage employee accounts in either department. No demo accounts are seeded.

For XAMPP with this repository at `htdocs/cible`, start Apache and MySQL, then open `http://localhost/cible/public/admin`. The first visit redirects to the login page. Create the first manager from the project directory with `php artisan cible:user:create`; there are no preconfigured login credentials or demo users. The public web entry point is `public/`.
