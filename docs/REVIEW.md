# Pre-submission review

A final review of Operations Desk against the assignment's assessment criteria (logic, code clarity, UI innovation, requirement interpretation and non-functional requirements), carried out on 18 October 2026. It lists what was checked, what was found and fixed, and what remains open.

**Result:** after two review passes, all 147 automated tests pass (580 assertions) and `vendor/bin/pint --test` passes. `php artisan operations:check` reports every check OK on the seeded local installation. The main screens were exercised in a real browser with the Content-Security-Policy active: no policy violations, no console errors and no horizontal overflow at phone width.

---

## 0. Second pass: flaws found and fixed

Each flaw was first reproduced with a throwaway test, then fixed and covered by a permanent regression test.

| # | Severity | Flaw | Fix | Regression test |
|---|---|---|---|---|
| A | Medium (data quality) | Livewire bypasses Laravel's whitespace trimming. A remark of nine spaces plus "x" passed the 10-character minimum and was saved as "x". Titles, names and handover notes had the same gap. | Remarks, supporting details, handover notes, definition titles and personnel fields are trimmed before validation. | `RecordActivityUpdateTest::test_padding_cannot_satisfy_the_minimum_remark_length` |
| B | Medium (report accuracy) | The pending report counted an ad-hoc activity scheduled for a later date as "pending at cutoff", with negative days outstanding, whenever the range extended past today. | Activities dated after the cutoff are excluded (and the rule is stated in the report's notes). | `ReportTest::test_activities_scheduled_after_the_cutoff_are_not_counted_as_pending` |
| C | Medium (security) | The dashboard's once-a-minute auto-refresh counted as activity, so an unattended screen's session never expired. | Refreshes only while the page is visible and someone has used it within the session lifetime; after that it stops, so the idle session times out. | Checked in the browser: refresh requests while active, none once idle. |
| D | Low (integrity) | A handover could be back-dated to any past date, attaching a snapshot of today's state to it. | Handovers can be submitted for today or yesterday only (a shift may end after midnight); the form explains this for older dates. | `HandoverTest::test_handovers_can_only_be_submitted_for_today_or_yesterday` |
| E | Low | Ad-hoc scheduling assigned the definition's default owner even if that account was deactivated (daily generation already skipped them). | Uses the same active-owner rule as daily generation. | `DailyRegisterTest::test_ad_hoc_scheduling_does_not_assign_a_deactivated_default_owner` |
| F | Low (robustness) | Two people scheduling the same activity, or submitting the same shift's handover, at the same moment hit the database's unique constraint and got a server error. | The constraint violation is turned into the normal "already scheduled / already submitted" message. | — (race; covered by the unique constraints) |
| G | Low (UX) | New staff received Laravel's default email, "You are receiving this email because we received a password reset request", which reads wrongly for an invitation. | A neutral "Set your Operations Desk password" email that suits both invitations and resets. | `PasswordTest::test_the_password_email_reads_correctly_for_new_accounts_and_resets` |
| H | Low (accessibility) | On phones, keyboard focus could move behind the open navigation drawer and the register's slide-over panel. | Focus is trapped inside both while they cover the page, and returns to the menu button on close. | Checked in the browser at 375 px. |
| I | Low (UX) | On phones, the register's card list showed only the time of a last update, even when it was on an earlier day. | Shows the date when the update was not today, as the desktop table already did. | — |

Probed and found sound: tampered address parameters on every Livewire page (all handled without errors), SQL ordering inputs (whitelisted), output escaping (no unescaped Blade output anywhere), authorization on every component action, and unique-key handling for daily generation.

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
2. **The 15 tests added in the review were run on SQLite only.** The earlier suite (132 tests) passed on MySQL 8.4 and PostgreSQL 17. The new tests involve no driver-specific SQL, but re-running the suite on the production engine before go-live is recommended.
3. **Password links last 60 minutes, for invitations as well as resets.** A new person who misses the window must use "Forgot password?" or ask an administrator to resend from their personnel page. A separate, longer-lived invitation link was not added.
4. **Demo data depends on when it is seeded.** Nothing is created in the future, so a seed run early in the morning leaves today mostly "not updated". Re-run `php artisan migrate:fresh --seed` shortly before a demonstration.
5. The limitations in README section 12 still apply: no server-side PDF export, no notifications, no correction workflow, a tamper-evident (not tamper-proof) audit chain, and the CSP allowing inline and eval scripts as Livewire/Alpine require.
