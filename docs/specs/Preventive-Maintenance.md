# PMC: Preventive maintenance, calendar based (requirements)

**Module requirements for Release B, slice B4** | Covers register items PMC-01 to PMC-11 | Based on Blueprint v3.17 | **Draft for developer review. Decisions proposed, waiting for owner approval** | November 19, 2026

Labels in this document use the default words (service request, client, contract). In the product every renamed word goes through the `term()` helper.

## 1. Purpose and scope

This module makes the system **create the routine jobs by itself**: "service the generator at Site A every 3 months", "inspect these 40 extinguishers every year". A schedule says what, where, how often and who; every morning the system makes the requests that are due. In Release A a request is always raised by a person (`service_requests.source` has `pm_auto` but nothing sets it).

**In scope (11 register items)**

- PMC-01 schedule list and form, PMC-02 daily generation at 06:00 company time with no duplicates, PMC-03 a batch schedule makes one request, PMC-04 pinned contract, PMC-05 geofence and waiver defaults, PMC-06 calendar (month and week), PMC-07 the `pm.generated` event, PMC-08 the `pm.overdue` event, PMC-09 skip if open, PMC-10 API, PMC-11 tests (scenario 13 of blueprint 13.3).

**Out of scope for this module**

- **Meter-based and condition-based schedules** (every 500 running hours). Calendar frequencies only. The meter column is not created.
- Spare parts and stock.
- Moving a schedule to another site: the user ends it and creates a new one.
- Client-side schedule editing. A client with `pm.view` view-own sees upcoming dates for their own sites, read-only.
- Reports on maintenance figures: slice B8.

**Needs first:** B1 assets, B2 contracts (pinned contract, coverage), B3 (templates and the batch request), the scheduler cron on the server. **Needed by:** B8 (maintenance on time or overdue, maintenance against reactive).

**Sources.** Blueprint v3.17 sections 7.3, 7.6, 9.2, 11.6, 13.3 (scenario 13), the Release B scope register (PMC items, assumptions 3 and 4), the Release B overview and the code as it stood on November 19, 2026.

**Gaps and conflicts found between the documents and the code.** Each has a decision below.

1. **The schedule tables do not exist.** `pm_schedules` and `pm_schedule_assets` are in blueprint 11.6 only. `service_requests.pm_schedule_id` and the source `pm_auto` already exist. The foreign key and the two tables are new (SCH-B4-01, 02).
2. **The blueprint also lists meter-based schedules** (7.3). The owner chose calendar only. The blueprint columns for meters are left out and a later release adds them.
3. **Hourly job at a company-local hour** is an existing pattern (`contracts:daily`, `invoices:overdue-daily`). This module follows it. The blueprint says 06:00 company time.
4. **Contract choice when several contracts match** is already built for requests (B2: the status `Pending` with the event `sr.contract_choice_needed` and the choice panel). D9 reuses it.
5. **The event engine is not built.** `pm.generated` and `pm.overdue` go through `NotificationService::notify()` and `config/notifications.php`.
6. **Permission keys exist** (`pm.view`, `pm.manage`). Team Lead holds `pm.view` at record level, which means "their own team's records". D3 sets what that is.
7. **The request form hardcodes some defaults** (geofence, waiver). D10 reads them from the site and category.

## 2. Decisions to review before building

The blueprint and the register did not settle these. Each has my recommendation. Write Approve or your change in the last column. Nothing here is final until you answer. D1 and D9 are the owner's calls.

| # | Decision and recommendation | Your answer |
|---|---|---|
| D1 | **Calendar only.** A schedule repeats by a calendar frequency: `weekly`, `monthly`, `quarterly`, `half_yearly`, `yearly`, or `every_n_days` (7 to 730) or `every_n_months` (1 to 36). No meter or condition rules. **Recommended: approve (owner's call).** | |
| D2 | **Plan (blueprint section 6).** The plan key is `pm_time` (the blueprint name, added by migration): Professional and Enterprise yes, Starter no. **No new plan limit**: there is no cap on schedules. Plan limits stay as in Release A (blueprint section 6: users, requests per month, API rate, storage). Requests the job creates count in the monthly request usage like any other (blueprint 6, "At a plan limit"), and nothing is blocked. **Recommended: approve.** | |
| D3 | **Who does what.** `pm.manage` full (Company Admin, Operations Manager) creates, edits, pauses and ends schedules. `pm.view` full sees all. A Team Lead (`pm.view` record level) sees only schedules whose default technician is on their team. The client sees upcoming visits at their own sites, read-only, and nothing else. **Recommended: approve.** | |
| D4 | **What a schedule holds.** Name, site, one asset or a batch of assets (2 to 200, same site and category as in the batch rules of B3), service category, frequency, first due date, lead days (0 to 60), priority (default Normal), checklist template (optional, B3), default technician (optional), pinned contract (optional), "skip if open" (default on), notes, and an end date (optional). **Recommended: approve.** | |
| D5 | **Due date and lead days.** `next_due_date` is the date the work should be done. The request is created `lead_days` before it (so a due date of 1 March and lead 7 creates the request on 22 Feb). The request's scheduled date is the due date. **Recommended: approve.** | |
| D6 | **The daily job.** The command `pm:daily` runs hourly and acts at 06:00 company time, the way `contracts:daily` acts at 02:00. For each company it creates the requests that are due (D5) and then sends overdue messages (D13). Missed days are caught up on the next run, but at most 1 request is created for each schedule and cycle (D7). **Recommended: approve.** | |
| D7 | **No duplicates.** A request records the schedule id and the due date it was made for (`service_requests.pm_schedule_id` and `pm_due_date`, SCH-B4-03). A unique key on `(pm_schedule_id, pm_due_date)` prevents a second one, so a rerun, a retry or two workers create nothing twice. **Recommended: approve.** | |
| D8 | **After generation.** `next_due_date` moves forward by the frequency from the **due date**, not from the day the work was done, so a late job does not shift the whole calendar. If several due dates were missed, the schedule creates one request for the earliest missed date and jumps to the first date after today (a "catch-up" note is stored on the request). **Recommended: approve.** | |
| D9 | **Pinned contract.** A schedule may pin one contract. A request from it uses that contract for coverage (status `in_contract`). With none pinned and exactly one active contract covering the asset, that one is used. With none pinned and more than one match, the request is created in **Pending** with a note "Choose a contract" and is not assigned or approved automatically. With no match the request is created as out of coverage and follows the category's approval mode. **Recommended: approve (owner's call, assumption 4).** | |
| D10 | **Request defaults.** Source `pm_auto`. Geofence is enforced as the site says unless the site is "always enforce"; the geofence waiver is allowed by default for these requests (nobody is at the door). The request carries the schedule's priority, checklist template and default technician (assigned at once if the technician is free and not on time off, otherwise left unassigned with the Front Desk told). Approval follows the category mode (B3). **Recommended: approve.** | |
| D11 | **Skip if open.** With "skip if open" on, a due date creates no request while an earlier request of the same schedule is still open (not Completed or Cancelled). The date is marked `skipped_open` in the schedule history, `next_due_date` still moves forward, and an overdue message follows the open one (D13). With it off, a new request is created anyway. **Recommended: approve.** | |
| D12 | **A batch schedule.** A schedule with many assets makes one request with one row for each asset (`service_request_assets`, B3). Adding or removing an asset takes effect from the next request. An asset that was deleted or retired is skipped with a note. **Recommended: approve.** | |
| D13 | **Overdue.** A schedule is overdue when its request is still open on the due date plus a grace of 1 day. `pm.overdue` is sent once for each request at the 09:00 run, to the Operations Manager. A second message after 7 days, then none. **Recommended: approve.** | |
| D14 | **Pausing and ending.** Pause (stops generation, `next_due_date` stays) and Resume (moves `next_due_date` to the next date on or after today, no catch-up). End (set an end date or end now) stops it for good, and already created requests stay. A schedule cannot be deleted once it has created a request, only ended. **Recommended: approve.** | |
| D15 | **Editing.** Changes apply to requests created after the change. A change to frequency or first due date asks for a new `next_due_date`. Changes are audited with old and new values. **Recommended: approve.** | |
| D16 | **Calendar.** A page with a month and a week view. It shows requests already made (colored by status) and upcoming due dates of active schedules (outlined). It reads one query with a date range (up to 6 months). Filters: site, category, technician. It does not move anything by drag. **Recommended: approve.** | |
| D17 | **Time zone.** Every date is a company-local date. A due date has no time of day. **Recommended: approve.** | |
| D18 | **Technician on time off.** A request is assigned to the default technician only if they are not on approved time off on the due date. Otherwise it is created unassigned, flagged "Default technician is off", and the Front Desk gets `pm.generated` without a technician. **Recommended: approve.** | |
| D19 | **Template and checklist.** The schedule's template wins over the asset's and the category's (B3 D8). A request binds to the published version at creation. **Recommended: approve.** | |
| D20 | **Limits per job run.** A run creates at most 500 requests for each company. The rest are done on the next hourly run and reported in the log. **Recommended: approve.** | |
| D21 | **Audit.** Schedule create, edit, pause, resume, end, and every automatic request creation are audited with the schedule id and the due date. **Recommended: approve.** | |

## 3. Who can do what

| Action | Key and level | Roles |
|---|---|---|
| See schedules and the calendar | `pm.view` | Company Admin, Operations Manager (all), Team Lead (their team's, record level), Client (view own, read-only) |
| Create, edit, pause, resume, end a schedule | `pm.manage` full | Company Admin, Operations Manager |
| Receive `pm.generated` | The default technician and the Operations Manager | |
| Receive `pm.overdue` | The Operations Manager | |
| Choose a contract on a waiting request | `sr.approve` full | Company Admin, Operations Manager |

The Team Lead's record level is narrowed in the controller in one place (a `PmScheduleAccess` class) and tested, as CLAUDE.md requires.

## 4. Data this module reads and writes

- **Reads:** `assets`, `project_sites`, `service_categories`, `contracts`, `form_template_versions`, `users`, `user_time_off`, `tenants` (time zone), `service_requests`.
- **Writes:** `pm_schedules`, `pm_schedule_assets`, `pm_schedule_runs`, `service_requests`, `service_request_assets`, `user_notifications`, `activity_logs`.
- **Used by others:** B8 (maintenance figures), B6 (a schedule may point at a vendor later, not in this module).

**New table: pm_schedules** (SCH-B4-01)

`id`, `tenant_id`, `name` (180), `project_site_id`, `service_category_id`, `frequency` (the six types of D1), `interval_value` (null or the number for the `every_n_*` types), `first_due_date`, `next_due_date`, `lead_days`, `priority`, `form_template_id` (null), `default_technician_user_id` (null), `contract_id` (null, the pinned one), `skip_if_open` (boolean, default true), `status` (`active`, `paused`, `ended`), `end_date` (null), `notes`, `created_by_user_id`, timestamps. Index `(tenant_id, status, next_due_date)`.

**New table: pm_schedule_assets** (SCH-B4-02)

`pm_schedule_id`, `asset_id`, `tenant_id`. Unique `(pm_schedule_id, asset_id)`. A schedule with one asset has one row.

**New table: pm_schedule_runs** (SCH-B4-04)

`id`, `tenant_id`, `pm_schedule_id`, `due_date`, `result` (`created`, `skipped_open`, `skipped_paused`, `needs_contract`, `error`), `service_request_id` (null), `note`, `created_at`. Unique `(pm_schedule_id, due_date)`. This is the schedule history shown on its page.

**Changed tables**

- `service_requests.pm_due_date` (date, null) with a unique key `(pm_schedule_id, pm_due_date)` (SCH-B4-03). `pm_schedule_id` gets its foreign key.
- Plan key `pm_time` in `plans.features` (SCH-B4-05).

**Error codes**

`PM_ASSET_SITE_MISMATCH`, `PM_ASSET_CATEGORY_MISMATCH`, `PM_FREQUENCY_INVALID`, `PM_SCHEDULE_ENDED`, `PM_HAS_REQUESTS` (a delete attempt).

## 5. Screens

### 5.1 Schedules (PMC-01)

A table: name, site, assets (count), category, frequency, next due, default technician, status. Filters: site, category, status, technician. Buttons: New schedule, and for each row Pause, Resume, End. A schedule page shows its details, its history (the runs table) and the requests it made.

### 5.2 Schedule form (PMC-01)

Fields of D4. Picking a site loads its assets. One asset or many (a check list). The frequency picker shows the next 3 due dates as a preview. Contract choice lists the contracts that cover the site.

### 5.3 Calendar (PMC-06)

Month and week views (D16) with the filters. Clicking an item opens the request or the schedule.

### 5.4 Request page

A request from a schedule shows "From schedule {name}, due {date}" with a link. A request waiting for a contract shows the "Choose a contract" panel.

### 5.5 Client view

Read-only list "Upcoming maintenance" for the client's own sites: date, asset or "n assets", category.

## 6. Business rules

### 6.1 Schedules (PMC-01, D1 to D5, D14, D15)

1. All assets share the site and the category (`PM_ASSET_SITE_MISMATCH`, `PM_ASSET_CATEGORY_MISMATCH`). A batch has 2 to 200 assets.
2. `next_due_date` starts at `first_due_date` (not in the past more than 1 day).
3. The frequency is added by calendar rules (month ends are kept: 31 Jan plus 1 month is 28 or 29 Feb, then 31 Mar from the original day).
4. A paused schedule creates nothing. An ended schedule is read-only (`PM_SCHEDULE_ENDED`).
5. No limit on the number of schedules (D2).

### 6.2 The daily job (PMC-02, D6 to D8, D20)

For each company, when it is 06:00 local time and the run has not been done today:

1. Select active schedules with `next_due_date - lead_days <= today`.
2. For each, in order of due date: apply "skip if open" (D11), choose the contract (D9), create the request with its rows (D10, D12), write a `pm_schedule_runs` row, then move `next_due_date` (D8). Each schedule is in its own transaction.
3. Send `pm.generated` for each created request.
4. Send `pm.overdue` (D13).

Running the job again the same day, or twice at once, creates nothing more (D7). The job has no company set, so it passes tenant ids explicitly and sets the company for each step, as the contract job does.

### 6.3 Contract and approval (PMC-04, D9)

With one pinned contract the coverage call uses it. With several matches and none pinned, the request is Pending and not assigned. The coverage result is stored on the request as for any other request. The approval mode of the category then applies (B3). If the mode is automatic and the contract is unknown, the request stays Pending.

### 6.4 Defaults (PMC-05, D10, D18, D19)

Geofence: follows the site, with the waiver allowed. Priority, template and technician from the schedule. Source `pm_auto`. The request has no client-facing quote step unless out of coverage.

### 6.5 Overdue (PMC-08, D13)

At the 09:00 job, each open request made by a schedule whose due date is more than 1 day past gets a `pm.overdue` message once, a second after 7 days. Completed requests are never reported.

### 6.6 Permissions, plans and isolation

`level:pm.view` and `level:pm.manage` on the routes, plan gate `pm_time`. Models use `BelongsToTenant`. A `{schedule}` binding is scoped by the signed-in user's company in `AppServiceProvider`. The Team Lead narrowing (D3) is in `PmScheduleAccess`. A client sees only their own client's sites.

## 7. Events and wording

Sent through `NotificationService::notify()`. WhatsApp is not used.

| Event | Fired by | Recipients and channels | Fallback |
|---|---|---|---|
| pm.generated (PMC-07) | A request is created by the job | Default technician (if any) and Operations Manager: in-app, email | In-app |
| pm.overdue (PMC-08) | The 09:00 job (6.5) | Operations Manager: in-app, email | In-app |

## 8. API

Built with the other API endpoints of Release B (PMC-10); until then nothing is exposed.

- `GET /api/v1/pm-schedules`, `GET /api/v1/pm-schedules/{id}`.
- `POST`, `PUT`, `POST /api/v1/pm-schedules/{id}/pause`, `/resume`, `/end` (need `pm.manage` full).
- `GET /api/v1/pm-calendar?from=&to=` (up to 6 months).

## 9. Exact messages

`{...}` is filled in by the system.

- Site mismatch: "All assets in a schedule must be at the same site."
- Category mismatch: "All assets must have the same service category."
- Ended: "This schedule has ended and cannot be changed."
- Has requests: "A schedule that created requests cannot be deleted. End it instead."
- Choose a contract: "More than one contract covers this asset. Choose the contract for this request."
- Default technician off: "Default technician {name} is off on {date}. Assign someone."
- `pm.generated`: "Maintenance request {number} was created for {asset or n assets} at {site}. Due {date}."
- `pm.overdue`: "Maintenance request {number} at {site} was due on {date} and is not done."
- Catch-up note: "Created late. {n} earlier dates were missed."

## 10. Acceptance tests

Each test becomes an automated test. Names in the test files start with the ID. PMC-040 to PMC-049 together cover scenario 13 of blueprint 13.3.

### 10.1 Schedules (PMC-01)

| ID | Scenario | Expected result |
|---|---|---|
| PMC-001 | Create a schedule with one asset, monthly | Saved, next due is the first due date |
| PMC-002 | An asset from another site | `PM_ASSET_SITE_MISMATCH` |
| PMC-003 | Assets of two categories | `PM_ASSET_CATEGORY_MISMATCH` |
| PMC-004 | A Professional company with 40 active schedules creates another | Saved, no limit |
| PMC-005 | Starter company opens the page | 403 "not in your plan" |
| PMC-006 | Monthly from 31 January | 28 February, then 31 March |
| PMC-007 | `every_n_days` of 5 | Invalid |
| PMC-008 | Delete a schedule that created a request | `PM_HAS_REQUESTS` |
| PMC-009 | Edit a schedule | Audit row with old and new values |

### 10.2 Daily generation (PMC-02, PMC-09)

| ID | Scenario | Expected result |
|---|---|---|
| PMC-010 | Due in 7 days with lead 7, job at 06:00 | One request, source `pm_auto`, scheduled for the due date |
| PMC-011 | The job runs twice the same day | One request |
| PMC-012 | Two workers run at once | One request (unique key) |
| PMC-013 | The job runs at 05:00 company time | Nothing created |
| PMC-014 | A paused schedule | Nothing created |
| PMC-015 | Skip if open on, earlier request still open | No request, run `skipped_open`, next due moves on |
| PMC-016 | Skip if open off, earlier request still open | A new request |
| PMC-017 | Three due dates missed | One request for the earliest, next due is the first date after today, catch-up note |
| PMC-018 | More than 500 due | 500 created, the rest on the next run |
| PMC-019 | An end date in the past | Nothing created, schedule ended |

### 10.3 Batch (PMC-03)

| ID | Scenario | Expected result |
|---|---|---|
| PMC-020 | A schedule with 12 assets | One request with 12 rows |
| PMC-021 | An asset retired after the schedule was made | Skipped with a note, the others stay |

### 10.4 Contract (PMC-04)

| ID | Scenario | Expected result |
|---|---|---|
| PMC-030 | A pinned contract | The request uses it, `in_contract` |
| PMC-031 | No pin, one covering contract | That one is used |
| PMC-032 | No pin, two covering contracts | Request Pending, "Choose a contract", not assigned |
| PMC-033 | No covering contract | Out of coverage, category approval mode applies |

### 10.5 Defaults and technician (PMC-05)

| ID | Scenario | Expected result |
|---|---|---|
| PMC-040 | A site that is "always enforce" | Geofence enforced, no waiver |
| PMC-041 | A normal site | Waiver allowed |
| PMC-042 | Default technician free | Assigned |
| PMC-043 | Default technician on time off | Unassigned, flagged, Front Desk told |
| PMC-044 | A schedule with a template | The request binds to its published version |
| PMC-045 | Category mode `always_auto` | The request is Approved at once |
| PMC-046 | Category mode `manual` | The request is Pending |

### 10.6 Calendar (PMC-06)

| ID | Scenario | Expected result |
|---|---|---|
| PMC-050 | Month view | Made requests and upcoming dates, correct days |
| PMC-051 | Range over 6 months | Refused |
| PMC-052 | Filter by technician | Only that technician's items |

### 10.7 Events (PMC-07, PMC-08)

| ID | Scenario | Expected result |
|---|---|---|
| PMC-060 | A request is created | `pm.generated` to the technician and the Operations Manager |
| PMC-061 | A request with no technician | Sent to the Operations Manager only |
| PMC-062 | A request 2 days past due and open | `pm.overdue` once, not again the next day |
| PMC-063 | 7 days past due | A second message, then no more |
| PMC-064 | A completed request | No overdue message |

### 10.8 Permissions, plans, isolation and API

| ID | Scenario | Expected result |
|---|---|---|
| PMC-070 | Front Desk, Technician, Finance open schedules | 403 |
| PMC-071 | Team Lead lists schedules | Only those whose default technician is on their team |
| PMC-072 | Team Lead tries to create | 403 |
| PMC-073 | A client | Upcoming list for their own sites, nothing else |
| PMC-074 | Another company's schedule id | 404 |
| PMC-075 | The job for two companies | Each uses its own time zone and its own data |
| PMC-076 | Every new route is on the signed matrix and behind sign-in | `ScreenPermissionsTest` and `RouteAuthTest` pass |
| PMC-077 | The API (when built) | Same rules and errors |

## 11. What developers must confirm before building

A developer must answer each of these in writing before the slice starts. The answers are recorded in `docs/decisions.md`, or in the pull request when they are only about how the work is done.

**Questions for the owner**

- **Q1. Calendar only.** Confirm no meter-based schedules in Release B (D1).
- **Q2. Several contracts.** Approve D9 (Pending, choose a contract).
- **Q3. Plan limit.** Decided: none added (D2).
- **Q4. Catch-up.** Approve D8 (one request for the earliest missed date).

**Questions for the developers**

1. **The job.** The command shape, copied from `contracts:daily`, the local-hour check and the catch-up after an outage.
2. **Duplicates.** The unique key and how the job handles the constraint error without failing the run.
3. **Date arithmetic.** The library for month-end rules and daylight saving.
4. **Request creation.** Which existing code builds a request (`ServiceRequestController::store` or a service) and how it is reused with no signed-in user.
5. **Notifications.** `config/notifications.php` entries for the two events and their wording.
6. **Team Lead scope.** How "their team" is read (the `team_lead_user_id` column) and the test.
7. **Calendar query.** Its indexes and speed with 5,000 schedules.
8. **Contract choice.** The panel on the request page and what the approval rules do while it is open.
9. **Impact on other specs.** B3 (batch requests, templates), B8 (figures), the Notifications spec (two events), the Contracts spec (coverage for a pinned contract) and the blueprint (the tables, columns and error codes).

## 12. Review and approval

A developer must confirm they could build this module without asking a question. Anything they would have to ask goes back into this document.

| Item | Name | Date | Answer |
|---|---|---|---|
| Checked against blueprint 7.3 and 11.6 | | | |
| Decisions D1 to D21 and the new tables, columns and error codes approved or changed | Project owner | | |
| Owner's calls decided: D1, D9 and Q1 to Q4 | Aiywah owner | | |
| Developer confirms the module can be built without questions, with the answers to section 11 | | | |
| Operations Manager confirms the rules in sections 5 and 6 | | | |
