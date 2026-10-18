# Operations Desk

**Applications Support · Daily Operations & Handover**

An internal web application for an Applications Support team. It records the daily support checks (such as *"Daily SMS count in comparison to SMS count from logs"*), who updated each one, what they found and when, and turns that history into a clear shift handover and custom-period reports.

Built with Laravel 13, Livewire 4, Tailwind CSS 4 and Alpine.js. The database is the only source of truth.

---

## Contents

1. [Assignment requirements and where they are met](#1-assignment-requirements-and-where-they-are-met)
2. [Architecture](#2-architecture)
3. [Features](#3-features)
4. [Database schema](#4-database-schema)
5. [Roles and permissions](#5-roles-and-permissions)
6. [Routes and principal workflows](#6-routes-and-principal-workflows)
7. [Business rules and definitions](#7-business-rules-and-definitions)
8. [Local setup](#8-local-setup)
9. [Automated tests](#9-automated-tests)
10. [Deployment](#10-deployment)
11. [Assumptions](#11-assumptions)
12. [Known limitations](#12-known-limitations)

---

## 1. Assignment requirements and where they are met

| # | Requirement | Implementation |
|---|---|---|
| 1 | Input activities such as "Daily SMS count in comparison to SMS count from logs" | **Activity definitions** (`/definitions`) with code, title, procedure, category, priority, recurrence and schedule. The scheduler creates a **daily instance** for each date a definition is due. Supervisors can also schedule ad-hoc instances from the daily register. |
| 2 | Update each activity to Done or Pending with a remark | **Record update form** (daily register side panel and `/activities/{id}`). Status must be chosen explicitly; a remark is mandatory (min. 10 characters); optional supporting details and a follow-up flag. Every update is appended, never overwritten. |
| 3 | Capture the bio details of the person making the update and the time | Each update stores the authenticated user's id **and** a point-in-time snapshot of name, email, staff number, designation and role. The timestamp is set by the server. Neither can come from the browser. |
| 4 | A daily view of updates to support handover of pending activities | **Daily register** (`/daily-register`) for any date, and the **Handover centre** (`/handover`): outstanding items with latest and earlier remarks, update journal, completed work, a persisted submit/acknowledge handover workflow and a printable summary. |
| 5 | Reporting view to query activity histories over custom durations | **Reporting centre** (`/reports`) with five reports (daily, history, pending at cutoff, personnel, completion trend), date-range validation, URL-persisted filters, CSV export and print layouts. |
| 6 | Authentication before access | Every page except sign-in and password recovery requires an active account. Public registration is disabled; administrators create accounts. Rate-limited sign-in, password reset, session regeneration, deactivation that ends sessions. |
| — | Built with Laravel | Laravel 13.37 (PHP 8.3), Livewire 4.4. |

---

## 2. Architecture

```
Browser ──► Laravel routes ──► middleware (auth, active, security headers, throttles)
                │
                ├── Controllers (auth, profile, CSV exports, printable handover)
                └── Livewire page components (dashboard, register, handover, reports, admin)
                          │
                          ├── Actions        app/Actions      — every write: one transaction each
                          ├── Read services  app/Services     — DeskOverview, HandoverBoard
                          ├── Reports        app/Reports      — one class per report, shared by screen + CSV
                          ├── Policies/Gates app/Policies, app/Support/Ability.php
                          └── AuditLogger    app/Services     — hash-chained audit log
                                   │
                                   ▼
                         Eloquent models ──► MySQL / PostgreSQL / SQLite
```

Key decisions:

- **Definitions vs instances.** A definition describes a recurring check. An instance is that check on one operational date. History is never reset: a new day gets a new instance.
- **Append-only updates.** `activity_updates` rows cannot be edited or deleted. The model throws if anyone tries, and no route allows it.
- **Current state kept in sync in one transaction.** `RecordActivityUpdate` appends the update, moves the instance's current status, latest-update pointer and version, and writes the audit event inside one database transaction. If any step fails, none of them persist (covered by a test that forces a failure).
- **Optimistic locking.** Each instance has a `version`. A save based on a stale version is rejected with a message naming who updated it and when. The person's text is kept, and they can deliberately resubmit. This also blocks double submissions.
- **Idempotent generation.** A unique index on `(activity_definition_id, operational_date)` plus `createOrFirst` means the scheduler, a retry, or the lazy generation when today's register is opened can never create duplicates.
- **Time.** All timestamps are stored in UTC. The team's timezone (`OPERATIONS_TIMEZONE`, default `Africa/Accra`) defines "today", operational dates, report day boundaries and display. See `app/Support/OpsClock.php`.
- **Reports query history, not today's state.** "Status at close of day" and "status at cutoff" use the last update recorded before that moment.
- **Tamper-evident audit log.** Each audit event stores a SHA-256 hash of its content chained to the previous event's hash. Administrators can verify the chain in the UI or with `php artisan operations:verify-audit-chain`.

### Technology versions (as installed)

| Layer | Package | Version |
|---|---|---|
| Runtime | PHP | 8.3 (tested on 8.3.35) |
| Framework | laravel/framework | 13.37 |
| Interactivity | livewire/livewire (bundles Alpine.js) | 4.4 |
| Styling | tailwindcss / @tailwindcss/vite | 4.3 |
| Build | vite / laravel-vite-plugin | 8.3 / 3.2 |
| Charts | chart.js (lazy-loaded on the trend report only) | 4.5 |
| Icons | mallardduck/blade-lucide-icons (Lucide) | 2.0 |
| Typeface | Times New Roman throughout (system font; falls back to Liberation Serif / Tinos / serif) | — |
| Tests | phpunit/phpunit | 12.5 |
| Database | MySQL 8 / PostgreSQL 14+ in production; SQLite for local and tests | — |

---

## 3. Features

**Overview dashboard** (`/`)
- Greeting, date, operational period and current shift, plus "Record activity" and "View handover" actions.
- Expected today, completed (with progress bar), still pending (updated vs not yet updated) and updated today. Definitions are in [section 7](#7-business-rules-and-definitions).
- *Requires attention*: pending work that is critical, flagged for follow-up, reopened, past its due time, or carried over from the last 7 days. Each item shows the reason chips, latest remark, updater and time.
- Today's activity table with tabs (All / Not updated / Pending / Done), a 14-day completion strip and recent updates. The page refreshes itself every minute while visible.

**Daily register** (`/daily-register`)
- Date picker, previous and next day, "Today", search (title, code, latest remark), and filters for status (Not updated / Pending / Flagged / Done), category and responsible person. All filters are kept in the URL.
- Counts for the day, a dense table on desktop, fewer columns on tablet, a card list on phones.
- Selecting a row opens a side panel: procedure, schedule, attention reasons, the update form and recent history. "Never updated" is visibly distinct from "updated and still pending".
- Supervisors can schedule an ad-hoc activity for today or a future date.

**Activity record** (`/activities/{id}`): full details, procedure, reassignment (supervisors), update form and the complete audit timeline (creation, every update with the updater's details at the time, and reassignments).

**Activity definitions** (`/definitions`): search, filters, sortable columns, pagination, archive and restore with confirmation, category management, a detail page with recent entries, next scheduled dates and change history, and create/edit forms with recurrence (daily, weekdays, weekly on chosen days, monthly, ad-hoc), scheduled and due times, default owner and active dates.

**Handover centre** (`/handover`)
- Briefing banner with counts and items to carry forward.
- Outstanding items sorted: flagged first, then priority, reopened, oldest date. Each shows the latest remark with updater and time, earlier updates on demand, and the responsible person.
- Update journal for the day or one shift, filterable by person, plus completed work.
- **Persisted handover workflow:** the outgoing person submits a note for a date and shift, with a frozen snapshot of outstanding items; a different person acknowledges it. Both steps are audited.
- **Printable summary** (`/handover/print`) with no navigation or controls.

**Reporting centre** (`/reports`): five reports, presets, start/end validation (max 366 days), filters for activity, category, status and personnel, applied-filter summary, totals, paginated results, print styles, "How this report is calculated" notes and CSV export (supervisors and administrators).

**Personnel & access** (`/personnel`, administrators): create accounts (the person receives a set-password link), edit details and role, activate or deactivate (sessions are ended; history is kept), send reset links, view recent activity and account history, and the live permission matrix.

**System audit log** (`/audit-log`, administrators): filter by date, category, actor and text; change inspector with before/after values, IP and hashes; integrity verification; CSV export.

**Account:** profile page with the details recorded on every update, and change password (signs out other sessions).

**Error and state pages:** access denied (403), not found (404), session expired (419, plus an in-app dialog for Livewire requests), too many requests (429), server error (500), maintenance (503). Inline validation, toasts, loading indicators and empty states throughout.

---

## 4. Database schema

```
users ─┬─< activity_definitions.created_by / updated_by / archived_by / default_assignee_id
       ├─< activity_instances.assigned_to / created_by / last_updated_by
       ├─< activity_updates.user_id
       ├─< handovers.outgoing_user_id / incoming_user_id
       └─< audit_events.actor_id

categories ─< activity_definitions ─< activity_instances ─< activity_updates
                                           └── latest_update_id ──► activity_updates
```

| Table | Purpose | Notable columns and constraints |
|---|---|---|
| `users` | Personnel accounts | `email` unique, `staff_number` unique nullable, `role`, `designation`, `team`, `is_active`, `deactivated_at`, `last_login_at`, `password_changed_at` |
| `categories` | Activity categories | `name` unique |
| `activity_definitions` | Recurring or ad-hoc checks | `code` unique (ACT-001), `priority`, `recurrence`, `recurrence_days` JSON, `scheduled_time`, `due_time`, `starts_on`, `ends_on`, `archived_at` |
| `activity_instances` | One definition on one operational date | **unique (`activity_definition_id`, `operational_date`)**, `status`, `assigned_to`, `origin` (schedule/manual), `scheduled_at`, `due_at`, `closes_at`, `latest_update_id`, `last_updated_*`, `requires_follow_up`, `version`; indexes on (`operational_date`, `status`) and `status` |
| `activity_updates` | Append-only Done/Pending updates | `user_id` plus snapshot `updater_name/email/staff_number/designation/role`, `previous_status` (null = first update), `new_status`, `remark`, `supporting_details`, `requires_follow_up`, server `created_at`; indexes on (`activity_instance_id`, `id`), (`user_id`, `created_at`), `created_at` |
| `handovers` | Submitted and acknowledged shift handovers | unique (`operational_date`, `shift`), `outgoing_user_id`, `outgoing_note`, `snapshot` JSON, `incoming_user_id`, `acknowledged_at` |
| `audit_events` | System audit log | `actor_id`, `event`, `subject_type/id/label`, `properties` JSON (before/after), `ip_address`, `user_agent`, `created_at`, `previous_hash`, `hash` unique |

**Deletion rules.** Every foreign key is `RESTRICT`. Users are deactivated, never deleted, and definitions are archived, never deleted. Every update, handover and audit event therefore keeps resolving to the person and activity it refers to. Deactivated people still appear by name in history and reports, marked "inactive" in filters.

**Current state vs history.** `activity_instances.status / latest_update_id / version` is the current state. `activity_updates` is the history. They change only together, in the `RecordActivityUpdate` transaction. The seeded data is checked for drift in tests (pointer, version and status all match).

---

## 5. Roles and permissions

Defined once in `app/Support/Ability.php`, registered as gates in `AppServiceProvider`, enforced by route middleware, policies and again inside every component action. The Personnel page renders this same matrix.

| Capability | Administrator | Support Supervisor | Support Team Member |
|---|:-:|:-:|:-:|
| View dashboard, daily register, handover, activity records and definitions | ✓ | ✓ | ✓ |
| Record Done/Pending updates and remarks | ✓ | ✓ | ✓ |
| Submit and acknowledge shift handovers | ✓ | ✓ | ✓ |
| Daily, history and pending reports (on screen and print) | ✓ | ✓ | ✓ |
| Personnel report | any person | any person | **own activity only** (enforced in the query) |
| Assign / reassign the responsible person | ✓ | ✓ | — |
| Schedule ad-hoc activity instances | ✓ | ✓ | — |
| Completion trend (management) report | ✓ | ✓ | — |
| Export reports to CSV | ✓ | ✓ | — |
| Create, edit, archive activity definitions and categories | ✓ | — | — |
| Manage personnel accounts and roles | ✓ | — | — |
| View and export the system audit log, verify integrity | ✓ | — | — |

Additional rules: a deactivated account fails every ability; an administrator cannot deactivate themselves; the last active administrator cannot be demoted or deactivated; a handover must be acknowledged by someone other than its author; updates open on an activity's operational date (tomorrow's check cannot be completed today).

---

## 6. Routes and principal workflows

| Method | URI | Name | Access |
|---|---|---|---|
| GET/POST | `/login` | `login` | guest (throttled) |
| GET/POST | `/forgot-password` | `password.request`, `password.email` | guest (throttled) |
| GET | `/reset-password/{token}` · POST `/reset-password` | `password.reset`, `password.store` | guest |
| POST | `/logout` | `logout` | signed in |
| GET | `/` | `dashboard` | signed in |
| GET | `/daily-register` | `register` | signed in |
| GET | `/activities/{instance}` | `activities.show` | signed in |
| GET | `/definitions` · `/definitions/{definition}` | `definitions.index`, `definitions.show` | signed in |
| GET | `/definitions/create` · `/definitions/{definition}/edit` | `definitions.create`, `definitions.edit` | administrator |
| GET | `/handover` · `/handover/print` | `handover.index`, `handover.print` | signed in |
| GET | `/reports` · `/reports/{daily\|history\|pending\|personnel\|trend}` | `reports.index`, `reports.show` | signed in (trend: supervisor+) |
| GET | `/reports/{type}/export` | `reports.export` | supervisor+ (throttled) |
| GET | `/personnel` · `/personnel/create` · `/personnel/{user}` | `personnel.*` | administrator |
| GET | `/audit-log` · `/audit-log/export` | `audit.index`, `audit.export` | administrator |
| GET | `/profile` · `/profile/password` · PUT `/profile/password` | `profile.show`, `password.edit`, `password.update` | signed in |
| GET | `/up` | — | health check |

**Daily workflow:** at 00:01 local time the scheduler creates today's instances. The morning person opens the register, records each check (Done, or Pending with an explanation, optionally flagged for follow-up). Pending items carry into the next days' attention list. At shift end the outgoing person submits a handover note; the incoming person reviews the outstanding list with its remarks and acknowledges it. Managers run reports over any period and export CSVs.

**Console commands**

```bash
php artisan operations:generate-instances
```

Creates today's instances. Add `--date=YYYY-MM-DD --days=N` to backfill or pre-create a range. Safe to repeat.

```bash
php artisan operations:create-admin
```

Creates an administrator interactively. The password is never echoed.

```bash
php artisan operations:verify-audit-chain
```

Verifies the audit log's hash chain and exits non-zero if it is broken.

---

## 7. Business rules and definitions

**Operational date and time**
- Timestamps are stored in UTC. `OPERATIONS_TIMEZONE` defines the team's local time.
- An operational day runs from local 00:00 (inclusive) to the next local 00:00 (exclusive). Each instance stores that end as `closes_at` (UTC).
- Shifts (`config/operations.php`): Night 00:00–08:00, Morning 08:00–16:00, Evening 16:00–00:00. They label handovers and can narrow the handover journal.

**Activity states.** *Not updated* means no update yet. *Pending* means the latest update says Pending. *Done* means the latest update says Done. *Reopened* is a Pending update made after Done; the Done event stays in history.

**Dashboard metrics (today)**
- *Expected today*: instances whose operational date is today.
- *Completed*: of those, currently Done. *Completion %* = completed ÷ expected.
- *Still pending*: of those, not Done, split into updated vs not yet updated.
- *Updated today*: distinct instances (of any date) that received at least one update today. Repeated updates to one activity count once; historical updates are never counted as extra activities.

**Requires attention** (pending only): critical priority, flagged for follow-up, reopened, past its due time, or carried over from the previous `OPERATIONS_CARRY_OVER_DAYS` (default 7) days.

**Reports**
- *Daily activity:* instances whose operational date is in the period, with **status at close of that day** (last update before `closes_at`) and current status. The status filter applies to the current status; the personnel filter matches the assignee or anyone who updated it.
- *Activity history:* every update. Date basis is either the activity's operational date (default) or the time the update was recorded. Filters apply to the update's new status and author.
- *Pending activities:* instances in the period still pending at the **cutoff**, meaning the end of the end date, or now if that is later than now. The remark, updater and time shown are those current at the cutoff, plus whether the item has been done since.
- *Personnel:* by recorded time. Distinct activities, Done and Pending update counts, and each update with its time. Team members only see their own.
- *Completion trend:* per operational date, instances done / pending / not updated at the close of that day. Today (not yet closed) and days with nothing scheduled are excluded from the summary rates.

**CSV exports** use the same validated filters and query classes as the screen, include a header block (report, period, filters, who prepared it and when), are audited, and prefix cells starting with `= + - @`, tab or CR with an apostrophe to prevent formula injection.

---

## 8. Local setup

Requirements: PHP 8.3+ with `pdo_sqlite` (or `pdo_mysql` / `pdo_pgsql`), `mbstring`, `openssl`, `intl`, `fileinfo`; Composer 2; Node.js 20+.

Install PHP and JavaScript dependencies:

```bash
composer install
```

```bash
npm install
```

Create the environment file and application key (on Windows PowerShell use `Copy-Item .env.example .env` for the first step):

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Create the SQLite database, run migrations and seed demonstration data (local environment only):

```bash
php artisan migrate --seed
```

If `database/database.sqlite` does not exist yet, create an empty file with that name first.

Build the front-end assets, or run `npm run dev` while developing:

```bash
npm run build
```

Start the application at http://localhost:8000:

```bash
php artisan serve
```

**Demonstration accounts** (created only in the `local` and `testing` environments, password `Desk-Demo-2026`):

| Person | Email | Role |
|---|---|---|
| Kofi Boateng | kofi.boateng@example.com | Administrator |
| Ama Mensah | ama.mensah@example.com | Support Supervisor |
| Abena Kyei | abena.kyei@example.com | Support Supervisor |
| Kwesi Appiah | kwesi.appiah@example.com | Support Team Member |
| Yaw Darko | yaw.darko@example.com | Support Team Member |
| Emmanuel Osei | emmanuel.osei@example.com | Support Team Member |
| Esi Owusu | esi.owusu@example.com | Support Team Member (deactivated, to show preserved history) |

The demo seeder replays about three weeks of fictional history **through the real business actions** with the clock set to each event's time. Versions, latest-update pointers, timestamps and the audit hash chain are therefore exactly what live use would produce. Nothing is created in the future, so how much of "today" is filled in depends on the time you run the seeder. All names, remarks and figures are fictional; the application has not verified any real service, log or transaction.

To start without demo data, run `php artisan migrate`, then `php artisan operations:create-admin`.

---

## 9. Automated tests

```bash
php artisan test
```

The default configuration (`phpunit.xml`) runs against in-memory SQLite. Two more configurations run the same suite against MySQL and PostgreSQL. Each needs an empty database called `ops_desk_test`; the tests migrate it themselves.

Create the MySQL test database:

```bash
mysql -u root -e "CREATE DATABASE ops_desk_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Run the suite against MySQL:

```bash
php artisan test -c phpunit.mysql.xml
```

Create the PostgreSQL test database:

```bash
createdb -U postgres ops_desk_test
```

Run the suite against PostgreSQL:

```bash
php artisan test -c phpunit.pgsql.xml
```

Both configurations default to `127.0.0.1` on the standard port, user `root` (MySQL) or `postgres` (PostgreSQL) with no password, and database `ops_desk_test`. Setting `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` or `DB_PASSWORD` in the environment overrides them. Never point them at a database you need: the suite drops every table in it.

**Latest runs (18 October 2026): 132 tests, 530 assertions, all passing on each driver** (PHPUnit 12.5):

| Driver | Server | Duration |
|---|---|---|
| SQLite, in memory | bundled with PHP 8.3 | about 55 s |
| MySQL | 8.4.11 LTS, server time zone +03:00 | about 65 s |
| PostgreSQL | 17.11, server time zone America/New_York | about 80 s |

The servers were deliberately run outside UTC to show that nothing depends on the database server's time zone. The suite covers:

| Area | File |
|---|---|
| Sign-in, case-insensitive email, sign-out, wrong password, deactivated accounts, rate limiting and lockout auditing, guest redirects, registration disabled, mid-session deactivation | `tests/Feature/Auth/AuthenticationTest.php` |
| Reset link, no account enumeration, reset with token, password policy, change password, no secrets in audit or output | `tests/Feature/Auth/PasswordTest.php` |
| Role access for every restricted page and action (routes and Livewire actions), CSV export permission, personnel-report scoping in the query | `tests/Feature/AuthorizationTest.php` |
| Daily/weekday/weekly/monthly/ad-hoc rules, idempotency, DB unique constraint, history not reset, archived or out-of-range skipped, console backfill, timezone boundaries | `tests/Feature/InstanceGenerationTest.php` |
| Updater identity and bio snapshot, server timestamps, locked properties, validation, pending ≠ done, preserved remarks, reopening, stale/concurrent updates, double submission, **transaction rollback on failure**, future-date rule, append-only updates | `tests/Feature/RecordActivityUpdateTest.php` |
| Definition create, validation, edit audit diff, archive keeps history, case-insensitive search and filters | `tests/Feature/ActivityDefinitionTest.php` |
| Register counts and filters, not updated vs pending, case-insensitive search, malformed filters in the address, untimed activities after timed ones, navigation, lazy generation, empty states, dashboard metric rules | `tests/Feature/DailyRegisterTest.php` |
| Outstanding sorting and carry-over window, latest and earlier remarks, personnel filter, submit and acknowledge workflow, one handover per shift, printable summary | `tests/Feature/HandoverTest.php` |
| Range validation, status at close vs current, filters narrowing the query, history date bases, pending at cutoff, personnel counts, trend, empty state, CSV filtering, CSV formula escaping, audit of exports | `tests/Feature/ReportTest.php` |
| Hash chain, tamper detection (edit and delete), immutability, filtering and inspection, export permission | `tests/Feature/AuditLogTest.php` |
| Account creation with invitation link, uniqueness (email ignoring case), case-insensitive search, role-change audit, deactivation, self and last-admin protection | `tests/Feature/PersonnelTest.php` |
| Every page renders with the full demo dataset and with an empty database, 404 and 403 pages, security headers | `tests/Feature/PagesRenderTest.php` |
| CSV sanitising, recurrence rules | `tests/Unit/SupportTest.php` |

Formatting is checked with `vendor/bin/pint --test`.

---

## 10. Deployment

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for a conventional Linux + Nginx + PHP-FPM deployment: requirements, environment, database, asset build, permissions, scheduler, HTTPS, caching, logging, backup and restore, safe administrator creation and a verification checklist.

This repository has **not** been deployed to a live server as part of this work. Configuring a host, domain, TLS certificate and production database is still required.

---

## 11. Assumptions

Where the assignment did not specify a rule, these choices were made:

1. **A remark is required on every update** (minimum 10 characters), including Done, so the next person always has an explanation.
2. **"Bio details"** means the operational identity only: name, work email, staff number, designation, team and role. No personal data beyond that is collected. These are snapshotted on each update.
3. **One instance per definition per operational date.** A second run of the same check on the same day is recorded as another update on that instance, not a new instance.
4. **Administrators can also record updates.** The matrix gives administrators every capability.
5. **Support team members see all activities.** The desk is a shared queue. Query-level scoping is applied where access differs (the personnel report).
6. **CSV export is limited to supervisors and administrators.** Everyone can view and print the reports available to them.
7. **Updates are allowed on past instances** (closing carried-over work) but **not on future-dated instances.**
8. **Ad-hoc scheduling is limited to today or later.** Past dates are not backfilled from the UI, because that would fabricate history. The console command can backfill, and the resulting instances are clearly created after the fact.
9. **Shifts** default to Night 00:00–08:00, Morning 08:00–16:00, Evening 16:00–00:00 (configurable). Handovers are one per date and shift.
10. **Default timezone is Africa/Accra (GMT, no daylight saving).** Change it with `OPERATIONS_TIMEZONE`.
11. **Carry-over window is 7 days** (`OPERATIONS_CARRY_OVER_DAYS`). Older pending items remain on their own date and in the pending report.
12. **Priority** (normal, high, critical) and **due times** were added to support the "Requires attention" rules. They are optional.
13. **Email addresses are not case-sensitive.** They are stored in lower case, and sign-in and password resets accept any capitalisation.

---

## 12. Known limitations

- **No live deployment.** Instructions are provided, but the app has not been deployed to a server or verified over HTTPS here.
- **Tested on SQLite, MySQL 8.4 and PostgreSQL 17.** The full suite passes on all three (section 9). In addition, the demo data was seeded on each through `migrate:refresh --seed` (which also runs every `down()` migration). The audit chain verified on all three, and all five reports, in 15 filter combinations, gave identical summaries and rows on each. The MySQL and PostgreSQL runs found driver differences that the original suite did not cover. All are now fixed and have regression tests where a single connection can show them:
  - PostgreSQL `LIKE` is case-sensitive. Searches now use `whereLike()`, which becomes `ILIKE` on PostgreSQL.
  - Email matching was case-insensitive on MySQL only. Emails are now stored in lower case on every driver.
  - On PostgreSQL, a non-numeric category or person ID in the address bar caused a server error. Those filters are now cast to integers.
  - Activities without a scheduled time sorted first on MySQL and SQLite but last on PostgreSQL. They are now listed last everywhere, matching the handover centre.
  - On PostgreSQL, two audit events written at the same moment could both chain to the same predecessor and break the chain. The writer now re-reads the newest event after taking the lock. This was reproduced and the fix confirmed with two concurrent processes.
  - MySQL converts `TIMESTAMP` values from the session time zone. On a server not set to UTC, that stored instants offset from the UTC the app writes, and made `verifyChain()` report intact events as altered if the time zone changed. The MySQL connection now sets its session to UTC (`DB_TIMEZONE`, default `+00:00`).
- **Not tested on MariaDB or on PostgreSQL 14–16.** Nothing used is version-specific, but run the suite against your exact production engine before go-live.
- **Remaining collation differences.** With its default collation, MySQL compares text ignoring case and accents, while PostgreSQL and SQLite compare it exactly. Emails and searches are normalised for this. Other unique values (staff numbers, activity codes) still ignore case on MySQL only, and only MySQL searches ignore accents.
- **The first audit event has no row to lock.** On PostgreSQL, two events written at the same moment into an *empty* audit log could both start the chain. Once the log has one event, writers are serialised.
- **Optimistic locking is the concurrency guard.** `lockForUpdate()` adds row locks on MySQL and PostgreSQL; on SQLite it is a no-op, and the version check alone prevents lost updates.
- **The audit hash chain is tamper-evident, not tamper-proof.** Someone with database write access could rebuild the chain. Restrict database access and keep off-site backups.
- **No PDF export.** Reports and the handover summary have print stylesheets for "Print to PDF" in the browser; a server-side PDF library was not added.
- **No email, SMS or in-app notifications** for flagged items or pending acknowledgements. The sidebar shows real counts (outstanding today, handovers awaiting acknowledgement).
- **No administrative correction workflow** for wrong updates. A correction is made by recording a further update that explains it, which keeps the trail intact.
- **Browser testing was automated through the app's built-in browser** (DOM and layout checks at 375 px, 768 px and desktop widths, plus an end-to-end update). No cross-browser matrix (Safari, Firefox) was run.
- The UI mockups included decorative elements such as cluster names, certifications, PIN signatures and "real-time sync" badges. These were **intentionally not reproduced**, because the system does not implement them and they would mislead users.
