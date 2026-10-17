# Nirvana LMS (Pramana Institute)

A production-grade Learning Management System tailored for Yoga, Ayurveda, Philosophy, and Psychology institutes.

[![CI](https://github.com/pratap7/nirvana-lms/actions/workflows/ci.yml/badge.svg)](https://github.com/pratap7/nirvana-lms/actions/workflows/ci.yml)

---

## Quick Start (Under 10 Minutes)

You can run the app locally using either **Docker Compose** or **Local PHP / SQLite / MySQL**.

### Option A: Using Docker Compose (Recommended)

1. **Clone the repository**:
   ```bash
   git clone git@github.com:pratap7/nirvana-lms.git
   cd nirvana-lms
   ```

2. **Setup environment & start containers**:
   ```bash
   cp .env.example .env
   make up
   ```

3. **Install dependencies & run migrations**:
   ```bash
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate:fresh --seed
   ```

4. **Access the application**:
   - **Public Home**: [http://localhost:8000](http://localhost:8000)
   - **Admin Panel**: [http://localhost:8000/admin](http://localhost:8000/admin)
   - **System Health Check**: [http://localhost:8000/health](http://localhost:8000/health)
   - **Mailpit UI (Emails)**: [http://localhost:8025](http://localhost:8025)

---

### Option B: Local Development

1. **Setup dependencies & database**:
   ```bash
   make setup
   ```
   *(Copies `.env.example`, runs `composer install`, generates key, and runs migrations with seeders).*

2. **Start the local server**:
   ```bash
   make serve
   ```
   Server is live at `http://localhost:8000`.

---

## Default Login Credentials

| Role | Email | Password | Access |
|---|---|---|---|
| **Super Admin** | `admin@pramana-institute.org` | `Pramana@2026!` | Full Admin Panel (`/admin`) |
| **Teacher** | `teacher@pramana-institute.org` | `Pramana@2026!` | Teacher Portal / Batch management |
| **Student** | `student@pramana-institute.org` | `Pramana@2026!` | Student Portal / Enrollments |

---

## Tech Stack

- **PHP**: 8.3+
- **Framework**: Laravel 12
- **Database**: MySQL 8.0 (InnoDB, `utf8mb4_unicode_ci`) / SQLite for local tests
- **Cache, Queues, Sessions**: Redis & Laravel Horizon
- **Admin & Moderator Panels**: Filament 3 (with custom saffron/amber theme)
- **Frontend**: Livewire 3 + Alpine.js + Tailwind CSS
- **Testing & Quality**: Pest PHP, Laravel Pint, Larastan (PHPStan Level 6)
- **Key Packages**:
  - `spatie/laravel-permission` (Granular permissions + scoped & expiring roles)
  - `spatie/laravel-activitylog` (Full state-change audit logging)
  - `spatie/laravel-medialibrary` (Private S3/local file storage)
  - `barryvdh/laravel-dompdf` (PDF generation for certificates & invoices)
  - `maatwebsite/excel` (Excel reporting exports)
  - `axllent/mailpit` (Local email testing)

---

## Common Makefile Commands

| Command | Description |
|---|---|
| `make test` | Run entire quality pipeline: Pint (style) + Larastan (Level 6) + Pest (tests) |
| `make up` | Start Docker containers (`app`, `web`, `mysql`, `redis`, `mailpit`) in background |
| `make down` | Stop Docker containers |
| `make logs` | Stream Docker container logs |
| `make fresh` | Drop all database tables, re-run all migrations, and re-seed |
| `make pint` | Automatically fix code styling issues with Laravel Pint |
| `make analyse`| Run Larastan static analysis at Level 6 |
| `make serve` | Start local PHP development server on port 8000 |

---

## Architecture & Conventions

Detailed architectural rules, domain module boundaries, BaseAction patterns, Money value objects, and permission matrices are documented in [app/Domain/README.md](app/Domain/README.md).
