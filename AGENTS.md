# Working on Operations Desk

Laravel 13 + Livewire 4 (class-based components) + Tailwind CSS 4. Read `README.md` first: it documents the domain, the business rules and the permission matrix.

## Conventions

- **All writes go through an action** in `app/Actions` and run in one database transaction. Do not update `activity_instances.status`, `latest_update_id` or `version` anywhere except `RecordActivityUpdate`.
- **Never edit or delete history.** `ActivityUpdate` and `AuditEvent` are append-only and throw if modified.
- **Record an audit event** (`App\Services\AuditLogger`) for every state change that matters operationally. Never put passwords, tokens or secrets in audit properties.
- **Authorise on the server.** Abilities live in `App\Support\Ability`; model rules live in `app/Policies`. Check them in the route and again in the Livewire action.
- **Time:** store UTC; use `App\Support\OpsClock` for "today", operational dates and day boundaries, and the `ops_time()`, `ops_datetime()`, `ops_format()` and `ops_date()` helpers in views.
- **Reports:** add a class under `app/Reports` and register it in `ReportRegistry`. The screen and the CSV export both use it, so they cannot disagree.
- **UI:** use the tokens and component classes in `resources/css/app.css` and the Blade components in `resources/views/components/ui`. Status is always shown with text, not colour alone.
- **Blade:** do not put an inline `@php(...)` before a `@php ... @endphp` block in the same file (the compiler mis-parses it).

## Commands

Run the test suite:

```bash
php artisan test
```

Check formatting:

```bash
vendor/bin/pint --test
```

Rebuild the database with demo data:

```bash
php artisan migrate:fresh --seed
```
