# MVD: Multi-trade jobs, vendors and depot service (requirements)

**Module requirements for Release B, slice B6** | Covers register items MTR-01 to MTR-05, VND-01 to VND-04 and DEP-01 to DEP-05 | Based on Blueprint v3.17 | **Draft for developer review. Decisions proposed, waiting for owner approval** | November 19, 2026

Labels in this document use the default words (service request, client, invoice, vendor). In the product every renamed word goes through the `term()` helper.

## 1. Purpose and scope

This module covers three things that Release A does not: **one job that needs several trades** (an electrician and a plumber for one fit-out), **work given to an outside company or its technicians**, and **equipment brought to the company's own workshop** instead of being serviced on site. In Release A one request has one site, one technician and one invoice, `vendors` is a plain contact list, and `service_mode` is always `on_site`.

**In scope (14 register items)**

- **Multi-trade:** MTR-01 a master request with child requests, MTR-02 the master's status derived from its children, MTR-03 children's expenses roll up to the master, MTR-04 the master is the one invoice, MTR-05 a technician sees only their own child, and tests (scenario 14).
- **Vendors:** VND-01 vendor companies, VND-02 vendor technicians, VND-03 a vendor technician sees only own jobs, no client rates, and cannot log expenses until the admin turns it on for that person, VND-04 vendor expenses reported separately, and tests (scenario 15).
- **Depot:** DEP-01 workshop site type with no client, DEP-02 depot service mode, DEP-03 intake and return checklists, DEP-04 depot screens, DEP-05 tests (scenario 16).

**Out of scope for this module**

- A separate vendor portal login with its own company screens. A vendor technician is a user of the company (D8).
- Paying vendors (accounts payable). Spend is reported only.
- Purchase orders to vendors, and rates agreed with a vendor.
- New request statuses for depot work. The ten statuses stay (assumption 15).
- Spare parts stock.
- Vendor ratings and scorecards: slice B8 may add them later.

**Needs first:** B3 (the checklist builder and versions are used for intake and return), the permission matrix (`vendor.manage`), the invoice and expense code of B7, the plan gate. **Needed by:** B8 (vendor spend, utilisation).

**Sources.** Blueprint v3.17 sections 5, 7.1, 7.6, 7.7, 11.3, 11.4, 11.5, 13.3 (scenarios 14, 15, 16), the Release B scope register (assumptions 14 to 16), the Release B overview and the code as it stood on November 19, 2026.

**Gaps and conflicts found between the documents and the code.** Each has a decision below.

1. **The schema is half there.** Present: `service_requests.parent_sr_id`, `is_master_sr`, `vendor_id`, `service_mode`; `service_categories.allow_depot_mode`; `vendors` (name, contact_name, email, phone, tax_id, status, notes). Missing: a link from a user to a vendor, a per-user "may log expenses" switch, the workshop site type, depot stage fields and the vendor reporting columns. `users.vendor_id` is in the blueprint but an old migration dropped it, so SCH-B6-01 adds it again.
2. **Release A says one request, one invoice** (blueprint 7.7). A master with children changes that to "the master is the one invoice". The Release B overview lists it as a risk, so D3 states it.
3. **`ServiceRequestController` hardcodes `service_mode` as `on_site`.** D12 lifts that for categories that allow depot mode.
4. **Technician records are narrowed by `record_level` in the controller.** A child request is the technician's own record. `Technician/JobsController` must read the child, not the master (D6).
5. **Vendors are not a user type.** The blueprint's `user_type` has `staff`, `technician` and `client`. A vendor technician is a `technician` user with `vendor_id` (D8).
6. **The event engine is not built.** Messages go through `NotificationService::notify()`.
7. **Expenses today are per request** (`service_request_expenses`, B7 conversion). Children each carry their own and the master sums them.

## 2. Decisions to review before building

The blueprint and the register did not settle these. Each has my recommendation. Write Approve or your change in the last column. Nothing here is final until you answer. D3, D9 and D13 are the owner's calls.

| # | Decision and recommendation | Your answer |
|---|---|---|
| D1 | **Plans (blueprint section 6).** Plan keys `multi_trade`, `vendors` and `depot` (the blueprint names, added by migration): Professional and Enterprise yes, Starter no. A company that loses the plan keeps its data but cannot create new masters, vendor jobs or depot jobs (downgrades keep data and pause gated automation). **No new plan limit**: no cap on masters. Plan limits stay as in Release A. Vendor technicians count as users (blueprint 6). **Recommended: approve.** | |
| D2 | **A master and its children** (MTR-01). A master request has the site, client, description and priority. Each child has its own service category (its trade), technician or vendor, scheduled date, and its own checklist. A master has 2 to 10 children. A child cannot have children. A request with one trade is a normal request. **Recommended: approve.** | |
| D3 | **One invoice** (MTR-04). The master is the only billable request. Children are never invoiced alone (`INVOICE_CHILD_REQUEST`). The invoice builder for the master lists every child's expenses, grouped by child and trade, in one currency (the quote's or the master's). This replaces the Release A rule for masters only. **Recommended: approve (owner's call).** | |
| D4 | **Master status** (MTR-02). It is derived and never set by hand: Pending until the first child exists; In Progress when any child is In Progress; Pending Review when every child is Pending Review or later (Completed or Cancelled counts as later); Completed when every child is Completed; Cancelled when every child is Cancelled. A mix of Completed and Cancelled (no one still open) is Completed. It is recomputed on every child status change in the same transaction. **Recommended: approve.** | |
| D5 | **Approval and quote.** The master is approved and quoted once, not each child. Approving the master approves the children. A child can be cancelled on its own (reason needed), which removes its part from the quote only through a quote revision. **Recommended: approve.** | |
| D6 | **A technician sees their own child** (MTR-05). Field users see and punch on their child only. The master and the other children are hidden from them (404, not 403). Office staff see all. A Team Lead sees the children of their team. **Recommended: approve.** | |
| D7 | **Expenses roll up** (MTR-03). Each child's expense lines stay on the child. The master's page and its invoice show the sum, in the master's currency using the B7 conversion at each line's stored rate. Expenses cannot be logged on the master itself. **Recommended: approve.** | |
| D8 | **Vendor companies and vendor technicians** (VND-01, VND-02). A vendor is a company with a name, contact, tax id, status, a default expense switch (D10) and notes. A vendor technician is a technician user (`user_type` technician) with `vendor_id`. They sign in on the field login page like other technicians and cannot be given any other role. The Company Admin or Operations Manager (`vendor.manage` full) creates them. A suspended vendor's technicians cannot sign in. **Recommended: approve.** | |
| D9 | **What a vendor technician sees** (VND-03). Only the jobs assigned to them. Never the client's rates, quotes, invoices, payment data, other technicians' jobs or the company's vendor list. The client name and site address are visible (needed to do the work). **Recommended: approve (owner's call).** | |
| D10 | **Expenses by vendor technicians** (VND-03). A vendor technician cannot log expenses until an admin turns "May log expenses" on for that person (`users.may_log_expenses`, default off for vendor technicians, on for others). It is changed on the user's page by `vendor.manage` full. **Recommended: approve.** | |
| D11 | **Vendor spend** (VND-04). Vendor expenses are reported apart from the company's own technicians: a "Vendor spend" table by vendor, month and client in the base currency. Vendor expense lines carry `vendor_id` (copied at logging). The B8 dashboard reads the same table. **Recommended: approve.** | |
| D12 | **Depot service mode** (DEP-02). A request of a category with `allow_depot_mode` may be created with `service_mode = depot`. The equipment is brought to a workshop: the request's site is a workshop site (D13), and the asset (or assets) stay linked to their client's site as the home site. A depot request cannot be a batch of more than 20 assets. **Recommended: approve.** | |
| D13 | **Workshop site** (DEP-01). A site with type `tenant_workshop` and no client (`client_id` null). Only Company Admin and Operations Manager create workshops (`asset.manage` full). A workshop is never offered for an on-site request. A company can have several. **Recommended: approve (owner's call).** | |
| D14 | **Depot stages without new statuses** (DEP-04, assumption 15). The ten request statuses stay. A depot request carries a `depot_stage` with `received`, `in_workshop`, `ready_for_return`, `returned` (SCH-B6-05). The stage is changed by office staff and technicians with a button. It does not change the status, except that `returned` is required before Completed. **Recommended: approve.** | |
| D15 | **Intake and return checklists** (DEP-03). The category (or the asset) points to two B3 templates, one for intake and one for return (`intake_form_template_id`, `return_form_template_id`, SCH-B6-04). Intake is filled when the stage becomes `received` (condition, accessories, photos, client signature). Return is filled at `returned` (work done, tests, signature). A missing result blocks the stage change (`DEPOT_CHECKLIST_REQUIRED`). **Recommended: approve.** | |
| D16 | **Moving the asset.** While in `received` to `ready_for_return` the asset shows "At workshop {name}". On `returned` it goes back to its home site. The asset's service date is written when the request completes. **Recommended: approve.** | |
| D17 | **Billing a depot job.** A depot request is billed like any other request of its client. Intake and return documents are attached to the invoice page, not printed on it. **Recommended: approve.** | |
| D18 | **Masters with vendors.** A child may be assigned to a vendor technician. The vendor then sees only that child (D9). A child may also be assigned to a vendor company without naming a technician ("vendor pending"), and the vendor admin cannot act because vendors have no company login. The office assigns the technician when known. **Recommended: approve.** | |
| D19 | **Assigning children.** Each child follows the normal assign rules of `ServiceRequestAccess` (assign and reschedule = sr.assign full, or critical and emergency requests for the Front Desk). Skills, time off and the dispatch settings apply as today. **Recommended: approve.** | |
| D20 | **Cancelling a master.** Cancels every child that is not Completed, with one reason. A child that is Completed stays, and the master becomes Completed (D4 rule: mix of Completed and Cancelled). **Recommended: approve.** | |
| D21 | **Audit.** Creating or cancelling a master or child, vendor create, suspend and reactivate, the expense switch, workshop create, each depot stage change and every master status recompute are audited. **Recommended: approve.** | |
| D22 | **Notifications.** D24 below lists the events. A vendor technician only gets messages about their own jobs. **Recommended: approve.** | |

D24. **Events.** `master.child_completed` (to the Operations Manager when a child is Completed), `master.ready_to_invoice` (to Finance when the master becomes Completed), `vendor.assigned` (to the vendor technician), `depot.received` and `depot.ready_for_return` (to the Front Desk and the client contact). **Recommended: approve.**

## 3. Who can do what

| Action | Key and level | Roles |
|---|---|---|
| Create a master and its children | `sr.create` with `multi_trade` | Company Admin, Operations Manager, Front Desk, Team Lead |
| Assign a child | Same as any assign | See D19 |
| Change a child's own work | `field.punch` edit own | The assigned Technician or Team Lead or vendor technician |
| Create and edit vendors and vendor technicians, switch expenses | `vendor.manage` full | Company Admin, Operations Manager |
| See vendor spend | `report.view` | Company Admin, Operations Manager, Finance (view only) |
| Create a workshop site | `asset.manage` full | Company Admin, Operations Manager |
| Change a depot stage | `sr.view` and the job is the user's own (field) or `sr.assign` full (office) | |
| Invoice a master | `invoice.manage` | Company Admin, Finance |
| Clients | See the master as one request. Never see child trades, vendors, workshop names or technicians' vendor status | Client roles |

D25. A client sees one request, with the status of the master and the depot stage text ("Received", "In workshop", "Ready for return", "Returned"). **Recommended: approve.**

## 4. Data this module reads and writes

- **Reads:** `service_requests`, `service_categories`, `assets`, `project_sites`, `users`, `roles`, `vendors`, `service_request_expenses`, `invoices`, `quotes`, `form_template_versions` (B3), `user_skills`, `user_time_off`, `dispatch_settings`.
- **Writes:** `service_requests` (master, children, depot fields), `vendors`, `users` (vendor link, expense switch), `project_sites` (type), `service_request_expenses` (vendor id), `invoices` (lines from children), `user_notifications`, `activity_logs`.
- **Used by others:** B8 (vendor spend, utilisation, callbacks).

**Changed tables** (no new tables)

- `users.vendor_id` (null, foreign key to `vendors`) and `users.may_log_expenses` (boolean, default true) (SCH-B6-01).
- `vendors.status` keeps `active` and `suspended`. New: `vendors.default_may_log_expenses` (boolean, default false) used to set the switch for a new vendor technician (SCH-B6-02).
- `project_sites.site_type` gets the value `tenant_workshop`. `project_sites.client_id` becomes nullable for that type (SCH-B6-03).
- `service_categories.intake_form_template_id`, `return_form_template_id` (null) (SCH-B6-04).
- `service_requests.depot_stage` (null or the four values), `depot_received_at`, `depot_returned_at`, `home_site_id` (SCH-B6-05).
- `service_request_expenses.vendor_id` (null) (SCH-B6-06).
- Plan keys `multi_trade`, `vendors` and `depot` in `plans.features` (SCH-B6-07).

**Error codes**

`MASTER_CHILD_COUNT`, `MASTER_CHILD_OF_CHILD`, `MASTER_NOT_BILLABLE_CHILD` (`INVOICE_CHILD_REQUEST`), `VENDOR_SUSPENDED`, `VENDOR_ROLE_LOCKED`, `EXPENSE_NOT_ALLOWED`, `DEPOT_NOT_ALLOWED`, `DEPOT_CHECKLIST_REQUIRED`, `DEPOT_RETURN_FIRST`, `WORKSHOP_HAS_NO_CLIENT`.

## 5. Screens

### 5.1 New master request (MTR-01)

The new-request form has a "This job needs several trades" switch. It shows a children table: trade (category), technician or vendor, date, notes. Add and remove rows, 2 to 10.

### 5.2 Master page (MTR-02, MTR-03)

Header with the derived status and a progress line ("2 of 3 done"). A children table with trade, assignee, status, date and expense total. A "Money" panel sums expenses in the master's currency. The invoice button is here only.

### 5.3 Child page and Field View (MTR-05)

A child opens like a normal request with a "Part of {master number}" line. The technician sees only this child.

### 5.4 Vendors (VND-01)

A page "Vendors" (Professional and Enterprise): name, contact, status, technicians (count), jobs this month. A vendor page lists its technicians and its recent jobs. Buttons: New vendor, Suspend and Reactivate.

### 5.5 Vendor technician form (VND-02)

On the user form, "Belongs to vendor" for technician users, and the "May log expenses" switch (D10).

### 5.6 Vendor spend (VND-04)

A table and an Excel export (`XlsxExporter`): vendor, month, client, expenses in the base currency.

### 5.7 Workshops and depot (DEP-01, DEP-04)

Sites page: "New workshop". A depot request page has a stage bar (D14) with the next-stage button, the intake and return checklists, and the asset location line. A Field View screen lists the depot jobs of the technician by stage.

## 6. Business rules

### 6.1 Masters and children (MTR-01, D2, D5, D20)

1. A master has 2 to 10 children (`MASTER_CHILD_COUNT`). A child has `parent_sr_id` and a master has `is_master_sr`.
2. A child cannot be a master (`MASTER_CHILD_OF_CHILD`).
3. The master and every child share the client, site and priority. The category, assignee, date and checklist belong to the child.
4. A master has no technician of its own. Its status is derived (6.2).
5. Cancelling a master follows D20.

### 6.2 The derived status (MTR-02, D4)

The rule of D4 runs after every child change (status, cancel, create) in one transaction, and writes an audit row when the master's status changes. It never changes a child.

### 6.3 Expenses and the invoice (MTR-03, MTR-04, D3, D7)

1. Child expenses keep their own request id.
2. The master's invoice builder pulls all child expense lines and the master's own labour lines, grouped by child.
3. One currency and one rate for the invoice (B7 rules). Each line keeps its `converted_at_rate`.
4. Invoicing a child alone is refused (`INVOICE_CHILD_REQUEST`).
5. A master is ready to invoice when it is Completed (D24 event).

### 6.4 Visibility (MTR-05, VND-03, D6, D9)

1. Field users (technicians, team leads, vendor technicians) see requests that are theirs, and for a master only their child.
2. A vendor technician never sees: rates, quotes, invoices, payments, the vendor list, other jobs, or other users. Their screens are the Field View only. Office routes return 403 (the `DenyClientRole` pattern, with a `DenyVendorTechnician` check), and a route that would show their data to someone else returns 404.
3. A vendor technician cannot log an expense while `may_log_expenses` is off (`EXPENSE_NOT_ALLOWED`). The Field View hides the button.

### 6.5 Vendors (VND-01, VND-02, VND-04, D8, D10, D11)

1. A vendor technician needs an active vendor (`VENDOR_SUSPENDED`). Suspending a vendor ends open sessions for its technicians, and reactivating restores access.
2. A vendor technician's role is the technician role only (`VENDOR_ROLE_LOCKED`).
3. The user's `vendor_id` is copied on the expense at logging.
4. Vendor spend uses the base currency conversion, never mixed with own technicians' expenses.

### 6.6 Depot (DEP-01 to DEP-04, D12 to D17)

1. A depot request needs a category with `allow_depot_mode` (`DEPOT_NOT_ALLOWED`), a workshop site as its site, and the home site stored in `home_site_id`.
2. A workshop has no client (`WORKSHOP_HAS_NO_CLIENT` if one is given).
3. Stages move forward only: `received`, `in_workshop`, `ready_for_return`, `returned`. Going back needs `sr.assign` full and a reason.
4. Intake is needed before `in_workshop`, return before `returned` (`DEPOT_CHECKLIST_REQUIRED`).
5. The request cannot be Completed before `returned` (`DEPOT_RETURN_FIRST`).
6. The asset's location text follows D16.

### 6.7 Permissions, plans and isolation

`level:vendor.manage` on the vendor pages and the expense switch, `multi_trade`, `vendors` and `depot` plan keys, `BelongsToTenant` on every model, `{vendor}` binding scoped by the signed-in user's company. A vendor technician belongs to a company and the tenant scope applies to them. Tests for each rule of 6.4 are in `Security/` as the rest of the access tests.

## 7. Events and wording

Sent through `NotificationService::notify()`. WhatsApp is not used.

| Event | Fired by | Recipients and channels | Fallback |
|---|---|---|---|
| master.child_completed | A child becomes Completed | Operations Manager: in-app | None |
| master.ready_to_invoice | The master becomes Completed | Finance: in-app, email | In-app |
| vendor.assigned | A child or request is assigned to a vendor technician | That technician: in-app, email | In-app |
| depot.received | Stage becomes `received` | Front Desk and the client contact: in-app, email | In-app |
| depot.ready_for_return | Stage becomes `ready_for_return` | Front Desk and the client contact: in-app, email | In-app |

## 8. API

Built with the other API endpoints of Release B; until then nothing is exposed.

- `POST /api/v1/service-requests` accepts `children` (a master) and `service_mode`.
- `GET /api/v1/service-requests/{id}/children`.
- `GET`, `POST`, `PUT /api/v1/vendors` (need `vendor.manage`).
- `POST /api/v1/service-requests/{id}/depot-stage`.
- `GET /api/v1/reports/vendor-spend`.

## 9. Exact messages

`{...}` is filled in by the system.

- Child count: "A multi-trade job needs between 2 and 10 trades."
- Child of a child: "A trade cannot have trades of its own."
- Invoice a child: "Invoice the main request {number}, not one trade."
- Vendor suspended: "This vendor is suspended."
- Vendor role: "A vendor technician can only have the technician role."
- Expense not allowed: "Your company has not allowed you to log expenses."
- Depot not allowed: "This category cannot be serviced at the workshop."
- Depot checklist: "Complete the {intake or return} checklist first."
- Depot return first: "Return the equipment before you complete this request."
- Workshop client: "A workshop does not belong to a client."
- Master progress: "{done} of {total} done."
- `master.ready_to_invoice`: "Request {number} is complete. Create the invoice."
- `vendor.assigned`: "You have a new job {number} at {site} on {date}."
- `depot.received`: "We received your {asset} at the workshop."
- `depot.ready_for_return`: "Your {asset} is ready to collect."

## 10. Acceptance tests

Each test becomes an automated test. Names in the test files start with the ID. MVD-001 to MVD-029 cover scenario 14, MVD-030 to MVD-049 scenario 15 and MVD-050 to MVD-069 scenario 16 of blueprint 13.3.

### 10.1 Master and children (MTR-01, MTR-02)

| ID | Scenario | Expected result |
|---|---|---|
| MVD-001 | Create a master with 3 children | 1 master and 3 children, master Pending |
| MVD-002 | A master with 1 child or 11 children | `MASTER_CHILD_COUNT` |
| MVD-003 | A child of a child | `MASTER_CHILD_OF_CHILD` |
| MVD-004 | One child goes In Progress | Master In Progress |
| MVD-005 | All children Pending Review or later | Master Pending Review |
| MVD-006 | All Completed | Master Completed |
| MVD-007 | Two Completed and one Cancelled | Master Completed |
| MVD-008 | All Cancelled | Master Cancelled |
| MVD-009 | Cancel the master with one child Completed | The others cancelled, the Completed one stays, master Completed |
| MVD-010 | A manual status change on a master | Refused |
| MVD-011 | The status is recomputed in the same transaction as the child change | No state with a stale master |
| MVD-012 | Plan without `multi_trade` | 403 for the switch |
| MVD-013 | A Professional company with 30 open masters creates another | Saved, no limit |

### 10.2 Invoice and expenses (MTR-03, MTR-04)

| ID | Scenario | Expected result |
|---|---|---|
| MVD-020 | Expenses on two children | The master's sum is the total of both in its currency |
| MVD-021 | Child in another currency | Converted at its stored rate |
| MVD-022 | Log an expense on the master | Refused |
| MVD-023 | Invoice a child | `INVOICE_CHILD_REQUEST` |
| MVD-024 | Invoice the master | One invoice with lines grouped by child |
| MVD-025 | Master Completed | `master.ready_to_invoice` to Finance |

### 10.3 Visibility (MTR-05)

| ID | Scenario | Expected result |
|---|---|---|
| MVD-026 | A technician opens their child | Works |
| MVD-027 | A technician opens the master or another child | 404 |
| MVD-028 | A Team Lead lists children | Only their team's |
| MVD-029 | A client opens the master | One request, no trades or vendors shown |

### 10.4 Vendors (VND-01 to VND-04)

| ID | Scenario | Expected result |
|---|---|---|
| MVD-030 | Create a vendor and a vendor technician | The technician has `vendor_id` and the technician role |
| MVD-031 | Give a vendor technician another role | `VENDOR_ROLE_LOCKED` |
| MVD-032 | Suspend the vendor | Technician cannot sign in, open session ended |
| MVD-033 | Reactivate | Technician can sign in |
| MVD-034 | A vendor technician opens a quote, an invoice, rates, the vendor list | 403 |
| MVD-035 | A vendor technician opens another technician's job | 404 |
| MVD-036 | A new vendor technician logs an expense | `EXPENSE_NOT_ALLOWED`, button hidden |
| MVD-037 | The admin turns the switch on | The expense is saved with the `vendor_id` |
| MVD-038 | Vendor spend report | Separate, by vendor and month, in the base currency |
| MVD-039 | Own technician expenses | Not in the vendor spend |
| MVD-040 | Assigning to a vendor technician | `vendor.assigned` sent to that person |
| MVD-041 | Plan without `vendors` (or without `depot` for depot jobs) | 403 |
| MVD-042 | Another company's vendor id | 404 |
| MVD-043 | Front Desk, Finance, Technician open Vendors | 403 |

### 10.5 Depot (DEP-01 to DEP-05)

| ID | Scenario | Expected result |
|---|---|---|
| MVD-050 | Create a workshop with a client | `WORKSHOP_HAS_NO_CLIENT` |
| MVD-051 | Create a workshop without a client | Saved |
| MVD-052 | Depot request, category without `allow_depot_mode` | `DEPOT_NOT_ALLOWED` |
| MVD-053 | A depot request | Site is the workshop, home site stored |
| MVD-054 | Stage `in_workshop` without the intake checklist | `DEPOT_CHECKLIST_REQUIRED` |
| MVD-055 | Stage moves back without a reason | Refused |
| MVD-056 | Complete before `returned` | `DEPOT_RETURN_FIRST` |
| MVD-057 | `returned` without the return checklist | `DEPOT_CHECKLIST_REQUIRED` |
| MVD-058 | Stage `received` | Asset shows "At workshop", `depot.received` sent |
| MVD-059 | Stage `returned` | Asset back at its home site |
| MVD-060 | The ten statuses are unchanged | No new status exists |
| MVD-061 | A depot request of 21 assets | Refused |
| MVD-062 | A client opens their depot request | Sees the stage text only |

### 10.6 Cross-cutting

| ID | Scenario | Expected result |
|---|---|---|
| MVD-070 | Every change in D21 | An audit row exists |
| MVD-071 | Every new route is on the signed matrix and behind sign-in | `ScreenPermissionsTest` and `RouteAuthTest` pass |
| MVD-072 | A vendor technician hits every office route | 403 or 404, none returns data |
| MVD-073 | The API (when built) | Same rules and errors |

## 11. What developers must confirm before building

A developer must answer each of these in writing before the slice starts. The answers are recorded in `docs/decisions.md`, or in the pull request when they are only about how the work is done.

**Questions for the owner**

- **Q1. One invoice for a master.** Approve D3, which changes the Release A one-request, one-invoice rule for masters.
- **Q2. What a vendor technician sees.** Approve D9 (client name and address visible, nothing financial).
- **Q3. Workshops.** Approve D13 (no client, Company Admin and Operations Manager create them).
- **Q4. Plans.** Decided: D1 follows blueprint section 6, no new limit.

**Questions for the developers**

1. **The invoice builder.** How child lines are pulled and grouped, and the effect on the old invoice code that still rounds with `round(x, 3)`.
2. **The record_level narrowing.** Where a field user's child is selected, in `Technician/JobsController` and in the office lists, and the `ServiceRequestAccess` entries for children.
3. **Vendor technician guard.** The middleware that keeps vendor technicians out of office routes and the list of allowed routes.
4. **Derived status.** Where the recompute lives (model event or service) and how it avoids loops.
5. **Depot and geofence.** How a workshop site's geofence works when the technician is already inside the workshop.
6. **Asset location.** Whether "At workshop" is a field or computed from the open depot request.
7. **Expense switch.** Where the check is made (the expense route and the Field View).
8. **Migration.** Making `project_sites.client_id` nullable on MySQL without breaking existing rows and indexes.
9. **Impact on other specs.** B3 (checklists), B7 (invoice, expense conversion), B8 (vendor spend), the Notifications spec (five events) and the blueprint (columns, error codes, the changed invoice rule).

## 12. Review and approval

A developer must confirm they could build this module without asking a question. Anything they would have to ask goes back into this document.

| Item | Name | Date | Answer |
|---|---|---|---|
| Checked against blueprint 7.6, 11.3 to 11.5 | | | |
| Decisions D1 to D25 (D23 removed) and the changed columns and error codes approved or changed | Project owner | | |
| Owner's calls decided: D3, D9, D13 and Q1 to Q4 | Aiywah owner | | |
| Developer confirms the module can be built without questions, with the answers to section 11 | | | |
| Operations Manager and Finance confirm the rules in sections 5 and 6 | | | |
