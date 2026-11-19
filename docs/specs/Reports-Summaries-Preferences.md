# RSP: Reports, daily summaries and notification preferences (requirements)

**Module requirements for Release B, slice B8** | Covers register items RPT-01 to RPT-09, SUM-01 to SUM-03 and PRF-01 to PRF-04 | Based on Blueprint v3.17 | **Draft for developer review. Decisions proposed, waiting for owner approval** | November 19, 2026

Labels in this document use the default words (service request, client, contract, invoice). In the product every renamed word goes through the `term()` helper. **Channels in this module are email, in-app and push only. WhatsApp is not used anywhere in it.**

## 1. Purpose and scope

This module gives managers **printable reports**, **new dashboard figures built from the work of B3 to B7**, a **daily summary message** and **personal notification settings**. In Release A there are dashboards and Excel exports only, every staff message is sent at once, and a user cannot choose what they receive.

**In scope (16 register items)**

- **Reports:** RPT-01 the Reports page with PDF for Monthly SLA, Technician scorecard, Completion certificate and Contract performance, RPT-02 branded PDFs (`brand_pdf`), RPT-03 to RPT-07 dashboard figures (technician utilisation, maintenance on time or overdue, maintenance against reactive, contract use, callbacks and vendor spend), RPT-08 reports API, RPT-09 tests.
- **Daily summary:** SUM-01 settings, SUM-02 the `summary.daily` job, SUM-03 tests.
- **Preferences:** PRF-01 notification preferences page, PRF-02 digests (hourly and daily), PRF-03 critical events cannot be turned off, PRF-04 tests.

**Out of scope for this module**

- **WhatsApp**: no template, no opt-in, no WhatsApp channel in the preferences table, the summary or the digests.
- Event outbox and the full event engine for every event of the blueprint. This module adds only what preferences and digests need (D14).
- Report builders where a user chooses columns. The four reports are fixed.
- Scheduled report email to clients. Not in the register.
- Accounting reports (profit and loss). Money reports are in B7.
- SMS.

**Needs first:** B2 contracts, B4 maintenance, B5 dispatch, B6 vendors and B7 money (the figures read their data), a PDF library (D3), a scheduler cron and a queue worker on the server. **Needed by:** nothing in Release B.

**Sources.** Blueprint v3.17 sections 6, 7.9, 9.2, 9.6, 9.7, 11.10, 12, 13.3, the Release B scope register (RPT, SUM, PRF items, assumptions 10 to 13), the Release B overview and the code as it stood on November 19, 2026.

**Gaps and conflicts found between the documents and the code.** Each has a decision below.

1. **There is no PDF library.** Documents are HTML print views today. A decision on the library is needed (D3).
2. **There is no web push.** The register says "push", the code has none. D12 sets what push means in this release.
3. **The event engine and outbox are not built** (`event_outbox` does not exist). Messages go through `NotificationService::notify()`. Digests need a small queue of held messages, defined in D14.
4. **`notification_preferences` and `daily_summary_configs` do not exist.** SCH-B8-01 and 02 add them from blueprint 11.10, with WhatsApp left out.
5. **The register item for the daily summary mentions WhatsApp.** Here it is email and in-app only, and `summary_daily` is an email template (D9).
6. **Brand PDFs** need the company's logo, colours and font. `_theme` and `_brand_font` serve web pages only. D4 sets what the PDF reads.
7. **Figures need data from other slices.** Each figure says which slice it reads (D6), and a figure shows "No data yet" when its source has none.
8. **Critical events are `sr.assigned` and `sla.breached` (blueprint 9.2).** Neither is in `config/notifications.php` today, and nothing in the code detects an SLA breach. D13 adds both definitions and fires only `sr.assigned`.

## 2. Decisions to review before building

The blueprint and the register did not settle these. Each has my recommendation. Write Approve or your change in the last column. Nothing here is final until you answer. D3, D12 and D13 are the owner's calls.

| # | Decision and recommendation | Your answer |
|---|---|---|
| D1 | **Plans (blueprint section 6).** PDF reports, the dashboard figures of this module and daily summaries use the plan key `reports_full` (Professional and Enterprise, added by migration). Branded PDFs use `brand_pdf` (Professional and Enterprise; the key is added if missing). The Excel export stays on `reports_basic` (all plans, already built). Notification preferences and digests are for every plan (starter alerts are email, in-app and browser push). Scheduled reports (`reports_sched`, Enterprise) are not in this module. No new plan limit. **Recommended: approve.** | |
| D2 | **Four reports.** Monthly SLA (requests by priority, response and resolution against target, breaches), Technician scorecard (jobs, on-time rate, first-time fix, average job time, callbacks), Completion certificate (one completed request: work, assets, checklist result, signature), Contract performance (coverage used, visits, SLA, amount billed, expiry). Each takes a month or a record and offers PDF and Excel (Excel through `XlsxExporter`). **Recommended: approve.** | |
| D3 | **PDF library.** Add `dompdf/dompdf` (pure PHP, works on the Windows development machine and on the server). Reports are Blade views rendered to PDF, so one view serves the page and the PDF. No browser engine on the server. A later switch to another renderer changes one class. **Recommended: approve (owner's call, adds a package).** | |
| D4 | **Branded PDFs** (RPT-02). With `brand_pdf` the header shows the company's logo and name, and the accent colour comes from the company's primary theme colour. The font is the company font when it is a web-safe or bundled one, otherwise the default PDF font. Without `brand_pdf`, a plain neutral header. Hardcoded colours are not used (CLAUDE.md): colours come from the tenant settings. **Recommended: approve.** | |
| D5 | **Who sees what.** `report.view` opens the page and the figures. `report.export` makes PDF and Excel. A Team Lead (`report.view` record level) sees only their team's technicians and their requests. Finance sees money reports only (view only). A client (view own) sees Completion certificate and Contract performance of their own records. **Recommended: approve.** | |
| D6 | **Dashboard figures** (RPT-03 to RPT-07). All in the company's base currency where money. (a) Technician utilisation: booked minutes against capacity for a period (`daily_capacity_minutes`), from B5. (b) Maintenance on time or overdue: requests from schedules (B4) completed by their due date or not. (c) Maintenance against reactive: counts of `pm_auto` requests against others. (d) Contract use: visits and hours used against the contract's limits (B2). (e) Callbacks: the figure of D7. (f) Vendor spend: from B6. Each figure has a date range (7, 30, 90 days, this month, last month) and a "No data yet" state. **Recommended: approve.** | |
| D7 | **Callbacks (blueprint 7.9).** Callbacks = jobs reopened inside the company's reopen window divided by completed jobs. A reopen is a Completed request moved back to work with a reason (`sr.reopen`), which the code already counts in `service_requests.reopened_count`. The window is the company's `reopen_window_days` (default 14, 0 means no reopening, so the figure is 0). The figure is the number of completed requests with `reopened_count` above 0 whose reopen happened within the window, over all completed requests in the period. No other callback rule is added. **Recommended: approve.** | |
| D8 | **First-time fix (blueprint 7.9).** Completed requests with zero rework divided by completed requests. Worked out when the report runs, not stored. A reopen counts against it (blueprint 7.1). Rework means the QC failure path (the existing rework count) or a reopen. **Recommended: approve.** | |
| D9 | **Daily summary** (SUM-01, SUM-02). Each user with a role that has a summary can switch it on. A company default role set (`base_role`) decides the content blocks: Operations Manager (today's requests, unassigned, overdue, SLA at risk, technicians off), Finance (invoices due, overdue, payments yesterday, quotes waiting), Technician and Team Lead (today's jobs in order, with addresses). Sent by email and in-app (D12), only for companies with `reports_full` (D1). The template `summary_daily` is an email view. Nothing is sent when every block is empty ("Quiet day" is not sent). **Recommended: approve (WhatsApp left out).** | |
| D10 | **Summary settings** (SUM-01). `daily_summary_configs` per company and role: send time (default 07:00 company time), days of the week (default Monday to Friday), content blocks (a list of ticks) and active. The Company Admin (`notification.manage` full) edits it. The Operations Manager sees it (view only). Each user can only turn their own summary on or off. **Recommended: approve.** | |
| D11 | **The summary job.** `summary:daily` runs hourly and acts at the configured local time, like `contracts:daily`. It sends at most one summary for each user and day (a unique key on user and date). A rerun sends nothing twice. **Recommended: approve.** | |
| D12 | **Channels** (owner's call). **Email** and **in-app** are built. **Push** in this release means a browser notification for a signed-in office user while the app is open (the in-app bell plus the browser Notification API, with the user's permission), not a mobile or background push service. No new third-party service, no WhatsApp. A real web push service is left for a later release and would be a new decision. **Recommended: approve (owner's call).** | |
| D13 | **Critical events cannot be turned off** (PRF-03, blueprint 9.2). Only two: `sr.assigned` (to the technician) and `sla.breached`. `invoice.overdue` is not critical. The code has neither event in `config/notifications.php` today (checked November 19, 2026: its events are `sr.contract_choice_needed`, `contract.*` and `invoice.overdue`). This module adds both definitions with the blueprint names and marks them critical, and sends `sr.assigned` when a request is assigned to a technician. **`sla.breached` is defined but nothing fires it**, because no SLA clock or breach job exists in the code. Firing it belongs to the slice that builds the SLA clock, and is listed as a follow-up. A user can choose the channel (email or in-app, at least one stays on) for a critical event but not turn it off or delay it. **Recommended: approve.** | |
| D14 | **Preferences and digests** (PRF-01, PRF-02). For each user, event and channel (`email`, `in_app`, `push`) the frequency is `instant`, `hourly_digest`, `daily_digest` or `off`. The default is `instant` for everything, so nothing changes until a user chooses. `NotificationService::notify()` reads the preference for each recipient, channel and event: `instant` sends at once, `off` skips (unless critical, D13), a digest stores the message in `notification_digest_items` (SCH-B8-03). **Recommended: approve.** | |
| D15 | **Digest jobs.** `digest:send` runs every hour. Hourly digests go at the top of the hour with everything held since the last one. Daily digests go at the user's daily time (default 08:00 company time). One email lists the held messages grouped by event, newest first, up to 50 lines with "and {n} more". Held items older than 7 days are dropped and counted in the log. In-app messages are always written at once (the bell stays current), so only email and push are held. **Recommended: approve.** | |
| D16 | **Who has preferences.** Staff and field users. Client users do not: their client emails are fixed (invoice, quote, overdue) and sent through Mailables. **Recommended: approve.** | |
| D17 | **Preferences page.** A table of events the user can receive (only those their role gets), with a frequency choice for each channel. A "Quiet hours" setting is not included (not in the register). **Recommended: approve.** | |
| D18 | **Report data rules.** A report reads only the signed-in user's company. A month runs from the first to the last day in the company's time zone. Money is in the base currency. A report on a month in progress says "Month to date". **Recommended: approve.** | |
| D19 | **Report size.** A report is limited to one month, or one record. A PDF is created while the user waits for up to 100 pages and above that it is queued and emailed as a download link (secure, 7 days). Excel exports follow the existing exporter limits. **Recommended: approve.** | |
| D20 | **Audit.** Each report export (PDF or Excel, with its type and period), each summary settings change and each change of someone else's preference (none allowed) is audited. A user's own preference change is audited too. **Recommended: approve.** | |
| D21 | **Completion certificate.** Only for a Completed request. It shows the client, site, asset or asset list, work done, technician, date, checklist result and the signature image. It carries a unique number (the request number) and has no price. **Recommended: approve.** | |
| D22 | **Reports API** (RPT-08). Read-only JSON of the same figures and report data for a month, with the standard token and company scope. No PDF over the API in this release. **Recommended: approve.** | |

## 3. Who can do what

| Action | Key and level | Roles |
|---|---|---|
| See the Reports page and the new figures | `report.view` | Company Admin, Operations Manager (full), Finance (view only, money reports), Team Lead (record level, own team) |
| Export PDF or Excel | `report.export` | Company Admin, Operations Manager, Finance (full) |
| See dashboard company figures | `dashboard.company` | Company Admin, Operations Manager (full), Finance (view only) |
| See team figures | `dashboard.team` | Team Lead (own team) |
| Edit the summary settings | `notification.manage` full | Company Admin |
| See the summary settings | `notification.manage` view | Operations Manager (view only) |
| Turn your own summary on or off | Any signed-in staff or field user | |
| Set your own preferences | Any signed-in staff or field user | |
| Client reports | `report.view` view own, `report.export` view own | Client roles: Completion certificate and Contract performance of their own records |

No new key is added. Narrowing for the Team Lead (own team) and the client (own records) is in one class, `ReportAccess`, with a test, as CLAUDE.md requires for record_level.

## 4. Data this module reads and writes

- **Reads:** `service_requests`, `service_request_assets`, `assets`, `contracts`, `invoices`, `payments`, `quotes`, `service_request_expenses`, `pm_schedules`, `vendors`, `users`, `user_time_off`, `dispatch_settings`, `tenants`, `tenant_settings` (brand), `user_notifications`.
- **Writes:** `daily_summary_configs`, `daily_summary_runs`, `notification_preferences`, `notification_digest_items`, `user_notifications`, `activity_logs`.
- **Used by others:** nothing.

**New table: daily_summary_configs** (SCH-B8-01)

`id`, `tenant_id`, `base_role` (the role code: `operations_manager`, `finance`, `technician`, `team_lead`), `send_time` (time, default 07:00), `days_of_week_json` (list of 1 to 7), `content_blocks_json` (list of block keys), `is_active` (boolean), timestamps. Unique `(tenant_id, base_role)`.

**New table: notification_preferences** (SCH-B8-02)

`id`, `tenant_id`, `user_id`, `event_key` (180), `channel` (`email`, `in_app`, `push`), `frequency` (`instant`, `hourly_digest`, `daily_digest`, `off`), timestamps. Unique `(user_id, event_key, channel)`. There is no WhatsApp channel value.

**New table: notification_digest_items** (SCH-B8-03)

`id`, `tenant_id`, `user_id`, `event_key`, `channel`, `frequency`, `title`, `body`, `link`, `created_at`, `sent_at` (null until sent). Index `(user_id, channel, frequency, sent_at)`.

**New table: daily_summary_runs** (SCH-B8-04)

`id`, `tenant_id`, `user_id`, `run_date`, `sent_at`, `blocks_count`. Unique `(user_id, run_date)`.

**Changed tables**

- `users.daily_summary_on` (boolean, default true) and `users.digest_daily_time` (time, default 08:00) (SCH-B8-05).
- Plan keys `reports_full` and `brand_pdf` in `plans.features` (SCH-B8-06). `reports_pdf`, an unused key of `PlanSeeder`, is left alone.

**Added while building (November 2026)**

- `sr.assigned` is sent by a listener on `ServiceRequest` (any screen or job that assigns a technician). `sla.breached` is defined in `config/notifications.php` and is critical, but nothing fires it: no SLA breach job exists. That is a follow-up.
- In-app messages are always written. A preference decides email (`instant`, digests, `off`) and push. A digest is only for email. Push means a browser notification while the app is open: the page polls `/notifications/latest` once a minute and only for users who allowed notifications. Real Web Push (VAPID, blueprint section 3) is a follow-up.
- The reports page lists Monthly SLA, Technician scorecard and Contract performance. The Completion certificate is opened from a completed request (office) or from the client's own request page (portal).
- Callbacks (D7) are completed requests reopened inside the reopen window over completed requests (blueprint 7.9). First-time fix (D8) is completed requests with no rework, no QC rework and no reopen over completed requests.
- Dompdf is added (D3). A PDF is made while the user waits; the queued path for reports over 100 pages (D19) and `report.ready` are not built.
- Left out of this build: the reports API (section 8), the "recent downloads" list reads the audit rows, the client's contract report page link in the portal, per-event role filtering on the preferences page (every event of the catalogue is listed), and push digests.
- Route parameter for the summary settings is `{baseRole}` because `{role}` is bound to the Role model.

**Error codes**

`REPORT_PERIOD_INVALID`, `REPORT_NOT_COMPLETED`, `REPORT_NOT_ALLOWED`, `PREFERENCE_CRITICAL`, `SUMMARY_BLOCK_INVALID`.

## 5. Screens

### 5.1 Reports (RPT-01)

A page "Reports" with four cards, each with a month picker (or a request search for the certificate) and PDF and Excel buttons. A recent exports list shows the last 10 with date and user.

### 5.2 Dashboard figures (RPT-03 to RPT-07)

New cards on the company dashboard and the team dashboard (by permission): utilisation bar, maintenance on time or overdue, maintenance against reactive, contract use, callbacks, vendor spend. Each has the range picker of D6 and links to the list behind it.

### 5.3 Daily summary settings (SUM-01)

A page under Settings: a row for each role with send time, days, content ticks and an active switch. The Company Admin edits. A "Preview" button shows what the summary would be today for the viewer's own role without sending it.

### 5.4 Preferences (PRF-01)

Under the user's profile: "Notifications". A table: event, then a choice for each channel. Critical rows show a lock and a short reason, and their frequency is fixed. A "Daily digest time" field. The own daily summary switch.

### 5.5 Browser notifications (D12)

A button "Allow browser notifications" on the Notifications page. When allowed and the user is signed in with the app open, a new in-app message also shows as a browser notification.

## 6. Business rules

### 6.1 Reports (RPT-01, RPT-02, D2, D4, D5, D18, D19, D21)

1. The period is a calendar month or earlier (`REPORT_PERIOD_INVALID` for a future month).
2. The Completion certificate needs a Completed request (`REPORT_NOT_COMPLETED`).
3. A user may export only what `ReportAccess` allows (`REPORT_NOT_ALLOWED`).
4. PDFs are made from Blade views with the brand settings of D4.
5. Every export writes an audit row and a row in the recent exports list.

### 6.2 Figures (RPT-03 to RPT-07, D6 to D8)

The queries are made for one company with explicit `tenant_id` filters (some tables have no scope). A figure over a source that has no data returns "No data yet". Money figures use `BaseCurrencyFigures` and `Money`. Queries have the indexes listed in section 11.

### 6.3 Daily summary (SUM-01 to SUM-02, D9 to D11)

1. The job `summary:daily` runs hourly. For each company, at the configured local time on an allowed weekday, it builds a summary for each user with the role and `daily_summary_on`.
2. A user receives one summary a day. An empty summary is not sent.
3. The summary goes by email and in-app according to the user's preferences (D14).
4. A technician's or Team Lead's summary lists only their own jobs.
5. Mail is sent only through the configured mailer. In development `MAIL_MAILER=log` and no email leaves the machine.

### 6.4 Preferences (PRF-01 to PRF-03, D13 to D17)

1. A user can change only their own preferences.
2. A preference for a critical event cannot be `off`, `hourly_digest` or `daily_digest` (`PREFERENCE_CRITICAL`). At least one channel stays `instant`.
3. No preference row means `instant` on every channel.
4. Client users have no preferences.
5. `NotificationService::notify()` checks the preference for each recipient and channel and acts as D14 says.

### 6.5 Digests (PRF-02, D15)

`digest:send` runs every hour. It sends the held items that are due, marks them sent, and drops items older than 7 days. A digest for a user with no due items is not sent. A digest that fails is retried the next hour and never sent twice.

### 6.6 Permissions, plans and isolation

`level:report.view`, `level:report.export` and `level:notification.manage` on the routes, plan gate `reports_full` on the Reports page and the figures, `BelongsToTenant` on every new model, scoped route bindings for any new `{report}` or `{export}` id. `ReportAccess` narrows the Team Lead and the client. Preferences are scoped by `user_id` and `tenant_id`.

## 7. Events and wording

Sent through `NotificationService::notify()` and `config/notifications.php`. Channels: email, in-app and push (D12). No WhatsApp.

| Event | Fired by | Recipients and channels | Fallback |
|---|---|---|---|
| summary.daily (SUM-02) | The `summary:daily` job | Each user with the summary on, by role: email, in-app | In-app |
| report.ready | A queued report finished (D19) | The user who asked: email, in-app | In-app |
| digest | The `digest:send` job | The user who holds items: email, push | None |

## 8. API

Read-only, built with the other API endpoints of Release B (RPT-08); until then nothing is exposed.

- `GET /api/v1/reports/sla?month=`, `/technician-scorecard?month=`, `/contract-performance?month=`.
- `GET /api/v1/reports/completion/{request}`.
- `GET /api/v1/dashboard/figures?range=`.
- `GET` and `PUT /api/v1/me/notification-preferences`.

## 9. Exact messages

`{...}` is filled in by the system.

- Plan: "Not in your plan."
- Period: "Choose a month that has ended or the current month."
- Not completed: "A completion certificate is only for a completed request."
- Not allowed: "You cannot export this report."
- Critical: "This message cannot be turned off. You can choose email or in-app."
- No data: "No data yet."
- Month to date: "Month to date, {date}."
- Summary subject: "Your daily summary for {date}"
- Digest subject: "{n} new messages from {company}"
- More lines: "and {n} more"
- Report ready: "Your {report} for {period} is ready to download."
- Browser prompt: "Allow browser notifications"
- Callback legend: "A callback is a completed job that was reopened within {n} days."

## 10. Acceptance tests

Each test becomes an automated test. Names in the test files start with the ID.

### 10.1 Reports (RPT-01, RPT-02)

| ID | Scenario | Expected result |
|---|---|---|
| RSP-001 | Starter company opens Reports | 403 "not in your plan" |
| RSP-002 | Monthly SLA for a month with data | PDF and Excel match the on-screen numbers |
| RSP-003 | A future month | `REPORT_PERIOD_INVALID` |
| RSP-004 | A certificate for a request that is not Completed | `REPORT_NOT_COMPLETED` |
| RSP-005 | A certificate for a Completed request | Shows work, checklist result and signature, no price |
| RSP-006 | With `brand_pdf` | Logo and company name in the header |
| RSP-007 | Without `brand_pdf` | A neutral header, no logo |
| RSP-008 | A Team Lead exports the scorecard | Only their team's technicians |
| RSP-009 | A client exports a certificate of another client | `REPORT_NOT_ALLOWED` |
| RSP-010 | Every export | An audit row and a recent exports entry |
| RSP-011 | A report over 100 pages | Queued, `report.ready` sent with a download link |
| RSP-012 | Another company's request id | 404 |

### 10.2 Figures (RPT-03 to RPT-07)

| ID | Scenario | Expected result |
|---|---|---|
| RSP-020 | Utilisation with known booked minutes | The right percentage of capacity |
| RSP-021 | A technician on approved time off | Capacity is reduced for those days |
| RSP-022 | Maintenance, 3 on time and 1 overdue | 75 percent on time |
| RSP-023 | Maintenance against reactive | The counts match the sources |
| RSP-024 | Contract use against its visit limit | The right used and left |
| RSP-025 | A completed request reopened 10 days after completion, window 14 | Counted as a callback |
| RSP-026 | Reopened after the window | Not possible (reopen refused), not counted |
| RSP-027 | Window set to 0 | Callbacks figure is 0 |
| RSP-028 | Vendor spend | By vendor in the base currency, own technicians excluded |
| RSP-029 | A company with no maintenance | "No data yet" |
| RSP-030 | Money figures with a foreign currency invoice | Uses the base amounts |
| RSP-031 | Another company's data | Never in the figures |

### 10.3 Daily summary (SUM-01 to SUM-03)

| ID | Scenario | Expected result |
|---|---|---|
| RSP-040 | The job at the configured time | One summary for each user with it on |
| RSP-041 | The job twice the same day | Nothing is sent twice |
| RSP-042 | A weekday that is not selected | Nothing sent |
| RSP-043 | All blocks empty | Nothing sent |
| RSP-044 | A technician's summary | Only their own jobs |
| RSP-045 | A Finance summary | Money blocks, no jobs list |
| RSP-046 | Two companies in different time zones | Each at its own local time |
| RSP-047 | Mail with `MAIL_MAILER=log` | Nothing leaves the machine |
| RSP-048 | The Operations Manager edits the settings | 403 |
| RSP-049 | The Company Admin edits the settings | Saved and audited |
| RSP-050 | A user turns their own summary off | Not sent |

### 10.4 Preferences and digests (PRF-01 to PRF-04)

| ID | Scenario | Expected result |
|---|---|---|
| RSP-060 | A user with no preference rows | Everything `instant` |
| RSP-061 | A user sets an event to `off` | No email, no push, in-app is still written |
| RSP-062 | A user sets `daily_digest` for email | The message is held, not emailed |
| RSP-063 | The digest job at the user's time | One email with the held items grouped by event |
| RSP-064 | More than 50 held items | 50 lines and "and {n} more" |
| RSP-065 | Items older than 7 days | Dropped |
| RSP-066 | `sr.assigned` set to `off` or `daily_digest` | `PREFERENCE_CRITICAL` |
| RSP-067 | A critical event with email `instant` and in-app `off` | Allowed, email stays on |
| RSP-068 | All channels of a critical event off | Refused |
| RSP-069 | A user edits someone else's preferences | 403 |
| RSP-070 | A client user | No preferences page, fixed emails unchanged |
| RSP-071 | A failed digest | Retried the next hour, never sent twice |
| RSP-072 | No WhatsApp channel value in the table or the page | Confirmed by a test |

### 10.5 Permissions, plans, isolation and API

| ID | Scenario | Expected result |
|---|---|---|
| RSP-080 | Front Desk and Technician open Reports | 403 |
| RSP-081 | Finance exports a money report, opens the scorecard | Money report works, scorecard 403 |
| RSP-082 | Every new route is on the signed matrix and behind sign-in | `ScreenPermissionsTest` and `RouteAuthTest` pass |
| RSP-083 | `ReportAccess` narrowing | A test for the Team Lead and for a client |
| RSP-084 | The API (when built) | Same rules and errors, read-only |

## 11. What developers must confirm before building

A developer must answer each of these in writing before the slice starts. The answers are recorded in `docs/decisions.md`, or in the pull request when they are only about how the work is done.

**Questions for the owner**

- **Q1. PDF library.** Decided: dompdf (D3).
- **Q2. What push means.** Decided: a browser notification while the app is open (D12). Real Web Push (VAPID, blueprint section 3) is a follow-up, listed in CLAUDE.md.
- **Q3. Critical events.** Decided: `sr.assigned` and `sla.breached` only (D13). `sla.breached` firing is a follow-up.
- **Q4. Callback window.** Decided: the company's reopen window (D7).
- **Q5. Plans.** Decided: D1 follows blueprint section 6.

**Questions for the developers**

1. **PDF.** Installing dompdf on Windows (the composer file lock noted in CLAUDE.md), fonts, logo images, memory for 100 pages and the queue for large reports.
2. **Preferences in the notification code.** How `NotificationService::notify()` reads preferences without a query for each recipient, and the cache.
3. **Critical events.** The two definitions added to `config/notifications.php` (`sr.assigned`, `sla.breached`), the critical flag, and the notify call on assignment.
4. **Digest queue.** The table, the jobs, the retry and the dedupe.
5. **The jobs.** `summary:daily` and `digest:send` in `routes/console.php`, copied from `contracts:daily`, and their time zone tests.
6. **Figures.** The queries, indexes (`service_requests` by asset, completed date and category), and speed with 100,000 requests.
7. **Utilisation.** How booked minutes are worked out from B5 and time off.
8. **`ReportAccess`.** Where "own team" is defined for a Team Lead.
9. **Brand.** Which tenant settings hold the logo and colour, and how the PDF reads them.
10. **Browser notifications.** The permission flow, and that nothing is sent to a third party.
11. **Impact on other specs.** B7 (base figures), B6 (vendor spend), B4 (maintenance figures), the Notifications spec (preferences, digests, the summary template, the missing WhatsApp rows) and the blueprint (four tables, columns, error codes).

## 12. Review and approval

A developer must confirm they could build this module without asking a question. Anything they would have to ask goes back into this document.

| Item | Name | Date | Answer |
|---|---|---|---|
| Checked against blueprint 9.6, 9.7 and 11.10 | | | |
| Decisions D1 to D22 and the new tables, columns and error codes approved or changed | Project owner | | |
| Owner's calls decided: D3, D12, D13 and Q1 to Q5 | Aiywah owner | | |
| Developer confirms the module can be built without questions, with the answers to section 11 | | | |
| Operations Manager and Finance confirm the rules in sections 5 and 6 | | | |
