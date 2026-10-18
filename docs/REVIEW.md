# Pre-submission review

A final review of Operations Desk against the assignment's assessment criteria (logic, code clarity, UI innovation, requirement interpretation and non-functional requirements), carried out on 18 October 2026. It lists what was checked, what was found and fixed, and what remains open.

**Result:** all 142 automated tests pass (565 assertions); `vendor/bin/pint --test` passes; `php artisan operations:check` reports every check OK on the seeded local installation; and the main screens were exercised in a real browser with the Content-Security-Policy active, with no policy violations and no horizontal overflow at phone width.

---

## 1. Findings fixed in this review

| # | Severity | Finding | Fix | Proof |
|---|---|---|---|---|
| 1 | High (security) | "Changing your password signs out your other sessions" did not happen. `Auth::logoutOtherDevices()` only takes effect when Laravel's `AuthenticateSession` middleware is on the routes, and it was not. A password reset after a suspected compromise left existing sessions working. | Added `auth.session` to every authenticated route, and to Livewire's persistent middleware so in-page requests are covered too. | `HardeningTest::test_a_password_change_elsewhere_signs_out_this_session` fails without the middleware and passes with it. |
| 2 | Medium (security) | The "account is still active" check was not re-applied to Livewire's in-page requests. With a non-database session driver, a page left open could keep working after the account was deactivated. Write actions were already refused, because every gate checks `is_active`. | Registered `EnsureUserIsActive` as Livewire persistent middleware. | Covered by the gates and middleware; the deactivation flow is tested in `AuthenticationTest` and `PersonnelTest`. |
| 3 | Medium (robustness) | A tampered address such as `/handover/print?date[]=x` or `/audit-log/export?search[]=x` caused a server error (500), because query values were used without validation. | Both endpoints now validate their inputs (date format, known shift, numeric person, known category, `system` or numeric actor, bounded search). | `HardeningTest::test_tampered_query_strings_are_rejected_instead_of_crashing`. |
| 4 | Medium (UX bug) | Scheduling an activity that was already on the register for that date showed no message. The error was attached to a field the form does not display. | Errors from scheduling now attach to the activity field shown on the form. | `DailyRegisterTest::test_scheduling_problems_are_shown_beside_the_form`. |
| 5 | Low (integrity) | The "no scheduling in the past" rule lived only in the screen, not in the action, so another caller could bypass it. | Moved the rule into `ScheduleActivityInstance`. | Same test, which calls the action directly. |
| 6 | Low (UX) | Navigating back to today (date picker or arrows) did not create today's entries if the scheduler had not run; only the first page load or the Today button did. The handover centre had the same gap. | Both screens generate today's entries (idempotently) whenever they show today. | `DailyRegisterTest::test_navigating_back_to_today_generates_todays_instances`. |
| 7 | Low (UX) | Cancel in the register's side panel cleared the form but left the panel open. | Cancel now closes the panel. | `DailyRegisterTest::test_cancelling_the_update_form_closes_the_panel`; also checked in the browser. |
| 8 | Low (UX) | The handover journal silently stopped at 200 updates. | It now says when the limit is reached and points to the history report for the full list. | Visible in the handover centre. |
| 9 | Low | `operations:create-admin` checked email uniqueness before lower-casing it. | It now lower-cases first, matching how emails are stored. | — |

## 2. Non-functional additions made in this review

- **Configuration validation.** An invalid `OPERATIONS_TIMEZONE`, carry-over window, report range or shift definition stops the application from starting, with a message naming the setting (`App\Support\OperationsConfig`). Without this, a wrong timezone would silently put work on the wrong operational date.
- **`php artisan operations:check`.** One command verifies the installation: settings, application key, debug mode, built assets, database, pending migrations, an active administrator and audit-log integrity, plus HTTPS, secure cookies and mail in production. It exits non-zero on failure and is part of the deployment steps and checklist.
- **Structured logging context.** Every request gets an `X-Request-Id`. The same id and the user id are attached to every log line written during the request, so a reported problem can be traced.
- **Content-Security-Policy.** The app's own origin only; no plugins, framing or foreign form targets (configurable with `SECURITY_CSP`). Verified in the browser across the dashboard, register (including Alpine-driven behaviour), trend chart (lazy-loaded) and print pages.

## 3. Review against the assessment criteria

**Logic.** The core rules each live in one place and are tested at their boundaries:
- Instance generation is idempotent and uses a unique key.
- Updates are append-only and versioned (optimistic locking).
- Current state and history change in one transaction, with a rollback test that forces a failure.
- Reports read history as it was (status at close of day and at cutoff), not today's state.
- Time zones: UTC storage with a configurable operational timezone, tested with a UTC+3 zone and with database servers outside UTC.

**Code clarity.**
- Writes are actions (`app/Actions`), reads are services (`app/Services`), and each report is a class (`app/Reports`) used by both the screen and the CSV export.
- Permissions are one matrix (`App\Support\Ability`) that the personnel page also renders.
- Policies cover per-record rules.
- Non-obvious rules are documented in the class doc blocks.
- Pint passes.

**UI innovation.**
- A side-panel register that shows the update form, the activity's procedure and its history together.
- A clear "not updated" vs "pending" distinction.
- A "Requires attention" list driven by stored rules (critical, flagged, reopened, past due, carried over).
- A persisted submit/acknowledge handover with a frozen snapshot, and a printable summary.
- Reports that state their own calculation rules.
- An audit change inspector with a working integrity check.
- Responsive layouts: tables on desktop, reduced columns on tablet, cards on phones.

**Requirement interpretation.** Each of the six assignment requirements maps to a feature (README section 1). Assumptions where the brief was silent are listed in README section 11, for example a mandatory remark, the bio snapshot, one instance per day, and no updates to future-dated work.

**Non-functional requirements.**
- **Security:** CSRF; hashed passwords and policy; rate-limited sign-in with audited lockout; disabled registration; server-side authorization on routes, components and actions; session invalidation; security headers and CSP; validated input; CSV formula escaping.
- **Reliability:** transactions; a tamper-evident audit log; configuration validation; friendly error pages.
- **Performance:** pagination, indexes on filtered columns, database aggregation for reports, and lazy-loading prevention in development and tests (any N+1 query fails the test suite).
- **Accessibility:** labelled controls, text alongside colour, focus styles, reduced motion.
- **Maintainability:** conventions in `AGENTS.md`, plus deployment, backup and restore documentation.

## 4. Open items (not fixable within this repository)

1. **Live deployment.** The assignment asks for the system to be deployed. The application is ready, and `docs/DEPLOYMENT.md` gives the steps and a checklist, but it needs a server or hosting account, a domain and TLS, which only the owner can provide.
2. **The latest 10 tests were run on SQLite only.** The earlier suite (132 tests) passed on MySQL 8.4 and PostgreSQL 17. The new tests involve no driver-specific SQL, but re-running them on the production engine before go-live is recommended.
3. The limitations in README section 12 still apply: no server-side PDF export, no notifications, no correction workflow, a tamper-evident (not tamper-proof) audit chain, and the CSP allowing inline and eval scripts as Livewire/Alpine require.
