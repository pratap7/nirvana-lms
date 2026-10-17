# Nirvana LMS (Pramana Institute)

A production-grade Learning Management System tailored for Yoga, Ayurveda, Philosophy, and Psychology institutes.

## Tech Stack

- **Framework**: Laravel 12 (PHP 8.3+)
- **Database**: MySQL 8 / SQLite (InnoDB, `utf8mb4_unicode_ci`)
- **Cache, Queues, Sessions**: Redis / Horizon
- **Admin & Moderator Panels**: Filament 3
- **Frontend / Student & Teacher Portals**: Livewire 3 + Alpine.js + Tailwind CSS
- **Testing & Code Quality**: Pest PHP, Laravel Pint, Larastan (PHPStan Level 6)
- **Key Packages**:
  - `spatie/laravel-permission` (Granular permissions + scoped role assignments)
  - `spatie/laravel-activitylog` (Full state-change audit logging)
  - `spatie/laravel-medialibrary` (S3/local media management)
  - `barryvdh/laravel-dompdf` (Certificate & invoice PDF generation)
  - `maatwebsite/excel` (Report exports)
  - `laravel/horizon` & `laravel/sanctum`

---

## Architecture Principles

- **Modular Monolith**: Organized under `app/Domain/{Identity, Catalog, Scheduling, Enrollment, Billing, Learning, Assessment, Certification, Events, Communication, Reporting, Wellbeing}`.
- **Permission-Based Authorization**: Over 86 granular permissions (`course.publish`, `attendance.mark`, etc.). Code never hardcodes role names.
- **Scoped & Expiring Roles**: Guest teachers and branch-specific roles are assigned with scopes and expiry timestamps.
- **Financial Precision**: All monetary values are handled using the immutable `Money` Value Object stored as unsigned `BIGINT` in **paise** (1 ₹ = 100 paise), avoiding floating-point inaccuracies.
- **Public Security**: Every model features ULIDs (`HasUlid`) for route-model binding to never expose auto-increment IDs.
- **Explicit State Machines**: Core entities (Course, Batch, Enrollment, Payment) enforce typed enum state transitions via `HasStateMachine`.

---

## Getting Started

### 1. Clone & Install Dependencies

```bash
git clone https://github.com/pratap7/nirvana-lms.git
cd nirvana-lms

composer install
npm install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Migration & Seeding

```bash
php artisan migrate:fresh --seed
```

### Default Credentials

- **Admin Portal**: `http://localhost:8000/admin`
  - **Email**: `admin@pramana-institute.org`
  - **Password**: `Pramana@2026!`
- **Teacher Account**: `teacher@pramana-institute.org` (Password: `Pramana@2026!`)
- **Student Account**: `student@pramana-institute.org` (Password: `Pramana@2026!`)

---

## Quality Checks & Testing

```bash
# Run Pest test suite
./vendor/bin/pest

# Run Larastan (PHPStan Level 6)
./vendor/bin/phpstan analyse --memory-limit=512M

# Run Laravel Pint code style checks
./vendor/bin/pint --test
```
