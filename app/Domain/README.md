# Domain Architecture & Conventions

This application is built as a **modular monolith** divided into 12 core business domains under `app/Domain/`.

## The 12 Domains

1. **Identity**: Users, authentication, branches, granular permissions, scoped and expiring roles (Guest Teachers).
2. **Catalog**: Programs, Courses, Modules, Lessons, content blocks, and course prerequisites.
3. **Scheduling**: Batches, sessions, class schedules, attendance marking, holidays.
4. **Enrollment**: Student batch enrollments, lifecycle transitions (active, on-hold, completed, withdrawn, transferred).
5. **Billing**: Fee schedules, Razorpay orders, payments, refunds, discounts, invoices.
6. **Learning**: Student lesson progress, course progress aggregation, bookmarks, notes.
7. **Assessment**: Quizzes, questions, multiple attempts, assignments, submissions, grading.
8. **Certification**: Certificate templates, issuance, PDF generation, anti-fraud public QR verification.
9. **Events**: Workshops, seminars, retreats, public registrations.
10. **Communication**: Multi-channel notifications (Email, Meta Cloud WhatsApp API), announcements.
11. **Reporting**: Attendance reports, revenue analytics, enrollment metrics, student progress exports.
12. **Wellbeing**: Ayurvedic Prakriti (Dosha) profiles, wellness check-ins, reflective journals, diet/routine tracking, encrypted counseling notes.

---

## Architectural Rules & Conventions

### 1. Structure Per Domain Module

Each domain module follows a consistent internal structure:

```
Domain/
├── {DomainName}/
│   ├── Actions/         # Single-purpose business operations extending BaseAction
│   ├── DTOs/            # Immutable, typed data containers for input
│   ├── Enums/           # BackedEnums using HasEnumHelpers trait
│   ├── Events/          # Domain events dispatched on state changes
│   ├── Listeners/       # Asynchronous listeners reacting to domain events
│   ├── Models/          # Eloquent models (using HasUlid, HasStateMachine, ScopedByBranch)
│   ├── Policies/        # Authorization policies using granular permissions
│   ├── StateMachines/   # (Optional) Complex state transition rules
│   └── QueryBuilders/   # (Optional) Custom Eloquent query builders
```

### 2. Business Logic in Action Classes

- **Controllers, Livewire components, and Filament resources must remain thin.**
- All state-altering business operations must be implemented as single-purpose **Action** classes extending `App\Support\Actions\BaseAction`.
- Actions receive typed **DTOs** or explicit primitives/models and execute inside database transactions where multiple mutations occur.
- Example:
  ```php
  $user = CreateUser::run($userData);
  ```

### 3. Permission-Based Authorization

- **Never check role names directly in business logic or views** (e.g., avoid `$user->hasRole('admin')`).
- Check granular permissions (e.g., `$user->can('course.publish')`, `$user->can('attendance.mark')`).
- Role assignments support **scope** (course/batch) and **expiry** through `ScopedRoleAssignment`.

### 4. Money & Financial Calculations

- **Never use floating-point numbers for currency.**
- All monetary amounts are stored as unsigned `BIGINT` in the **smallest currency unit** (e.g., paise for INR: 1 ₹ = 100 paise).
- Wrap financial operations in the immutable `App\Support\Money` value object:
  ```php
  $fee = new Money(150000, 'INR'); // ₹1,500.00
  $discount = new Money(20000, 'INR'); // ₹200.00
  $total = $fee->subtract($discount);
  echo $total->format(); // "₹1,300.00"
  ```

### 5. Public Identifiers: ULIDs

- Never expose auto-increment database primary keys in URLs or public APIs.
- Every model uses the `App\Support\Concerns\HasUlid` trait, which generates a 26-character ULID on creation and binds routes to `{model:ulid}`.

### 6. Timezones & UTC

- Store all timestamps in **UTC** in the database.
- Use global helpers `toUserTimezone($carbon)` and `toUtc($carbon)` for rendering dates in user or institute local timezones (`Asia/Kolkata` by default).

### 7. Explicit State Machines

- Statuses must be represented by **BackedEnums**.
- State transitions must use the `App\Support\Concerns\HasStateMachine` trait and throw an `IllegalStateTransitionException` on invalid jumps.
- All status transitions are automatically audit-logged.

### 8. Inter-Module Coupling

- Avoid tight coupling between domain modules.
- Prefer event-driven communication (e.g. `PaymentCaptured` → listener triggers `ActivateEnrollment`).
