# Aiywah FSM: Complete Product Blueprint

**Version 3.17, Phase 1 Complete Edition** | Prepared by Digit-Us Operations | October 23, 2026 | Supersedes v3.16

This version keeps the full Phase 1 scope and records the approved Dashboards, limits and platform admin decisions (DSH D1 to D22). It completes the set of module specs: every Release A slice now has an approved spec. The main changes are the dashboard and usage rules in section 7.9a, one new event and four schema additions. Section 1 lists what changed. Section 14 lists the confirmed decisions and the actions still open.

## 1. What Changed from v3.16

| Area | Change | Section |
|---|---|---|
| Dashboards | The Dashboards, limits and platform admin spec (`docs/specs/Dashboards-and-Platform-Admin.md`) is written and its 22 decisions are approved: one home page for each role, the figures Release A shows and how each is worked out, and the request Excel export | 7.9, 7.9a |
| Limits | Usage counters for users, requests, storage and messages. Warnings at 80 and 100 percent, once for each limit and month. Nothing is ever blocked and proof photos are always accepted | 6, 7.9a |
| Platform admin | Company detail with usage, impersonation with a reason, a time limit and blocked actions, the history page for the Company Admin, and the WhatsApp health board | 7.9a, 9.4 |
| Events | One event is added: `impersonation.started` | 9.2 |
| New columns and table | `warnings_json` and `email_message_count` on `tenant_usage_counters`, `end_reason` and `actions_count` on `impersonation_sessions`, `impersonation_session_id` on `activity_logs`, and the table `whatsapp_health_snapshots` | 11 |
| Error codes | `IMPERSONATION_BLOCKED` and `EXPORT_TOO_LARGE` | 12.1 |

## 2. Vision and Principles

Aiywah FSM exists for one reason: every company that sends people to do work at a location should have a single platform that tracks the entire journey from "we need help" to "the job is done, here's proof, how did we do?" It serves facility management, HVAC, MEP, equipment services, property maintenance, IT infrastructure, cleaning, biomedical, solar, elevators and any business built on field visits. It is the same product for a 10-person AC repair company in Sharjah and a 500-technician facility management firm in Mumbai: different in scale and configuration, identical in architecture.

**1. The work, not the tool, is the centre.** Every screen exists to move a job forward: create it, assign it, do it, prove it, bill it, learn from it.

**2. The Field View is the workplace. WhatsApp is the doorbell.** Technicians do their jobs in a fast, installable mobile-browser Field View. Notifications reach them by whichever channel their plan provides (WhatsApp, web push, email, in-app). No core step ever depends on one channel.

**3. Proof of work is enforced by category.** GPS-stamped punch, photos, checklists and customer signatures are required or not according to each Service Category's rules. A job cannot close until its category's proof exists.

**4. Configuration and aliasing replace customisation.** No code changes per industry. Service categories, forms, SLA rules, approval rules, branding and vocabulary (Technician becomes Doctor or Inspector, Service Request becomes Work Order) are tenant settings.

**5. API-first, cloud-native, native-app ready.** Every feature is a versioned REST endpoint first. The web portal and Field View consume it in Phase 1. The native app in Phase 2 consumes the same endpoints with no backend rewrite.

## 3. Platform Architecture

- **Web application and Field View:** Laravel 12, PHP 8.2+, Blade, Vite. The Field View is a Progressive Web App (manifest, service worker, IndexedDB) served from the same codebase.
- **REST API:** Laravel Sanctum, prefix `/api/v1/`, tenant resolved from subdomain or token.
- **Database:** MySQL, single database, `tenant_id` on every tenant table, enforced by a global scope (`BelongsToTenant`).
- **Files:** S3 or Cloudflare R2, tenant-prefixed paths, short-lived signed URLs.
- **Queue and scheduler:** Redis-backed queues. Scheduled jobs run per tenant timezone, never server timezone.
- **Channels:** each company's own Meta WhatsApp Business account (Cloud API), transactional email provider, Web Push (VAPID), in-app notifications.
- **Maps:** from Release A, a free OpenStreetMap-based map library for picking site coordinates. The tile provider is chosen by developers and may have a usage cost at scale. The geofence distance is a plain calculation and needs no maps provider. From Release C, a paid geocoding and distance matrix provider for route optimisation.
- **Exchange rates:** a rates provider read once a day, from Release B, for multi-currency quotes and invoices.
- **Native app compatibility rule:** every Phase 1 screen, including the web portal and Field View, uses only the `/api/v1/` endpoints that the iOS and Android apps will use. No business logic lives in web-only code. Contract tests enforce this.
- **Subscription billing:** Laravel Cashier for Stripe, plus a gateway table for Tap (GCC) and Razorpay (India). The product page on aiywah.com is a separate website for all Aiywah products. It reads the packages from a public list in the product and starts the provider's hosted checkout (Release B).

### Browser capabilities and their constraints

| Capability | Used for | Constraint to design around |
|---|---|---|
| Geolocation | Punch in and out, signature location | Needs HTTPS and user permission. Foreground only. Indoor accuracy varies, so accuracy is stored with every fix. |
| Camera capture | Before, after and receipt photos | Uses the file input capture mechanism. Large photos must be compressed on device before upload. |
| Canvas | Customer signature | None significant. |
| Camera stream and JS decoder | QR scanning of assets | Use a JavaScript QR decoding library. Do not rely on the BarcodeDetector API, which is not available in all browsers. |
| Service worker and IndexedDB | Offline queue and cached jobs | Storage quotas apply. iOS may clear site data for sites not installed to the Home Screen. No background sync on iOS, so syncing happens when the app is open. |
| Web Push | Job alerts without WhatsApp | On iPhone it works only for the installed Home Screen app. Delivery is best effort, so email and in-app remain as backups. |

## 4. Roles, RBAC and Terminology Aliasing

Eight roles cover every service company. Platform Admin works above all tenants (separate `platform_users` table). The other seven are tenant roles.

| Role | What they do | Industry examples |
|---|---|---|
| Platform Admin | Manages all tenants, plans, subscription billing, WhatsApp health, support impersonation. | Aiywah FSM internal team |
| Company Admin | Full control inside the tenant: users, roles, vocabulary, branding, master data, workflows. | Owner, GM, IT Manager |
| Operations Manager | Approves work, assigns, performs QC, sees all operational reports. | Head of Projects, Branch Manager |
| Team Lead | Optional layer. Manages a team, picks technicians, monitors workload. | Service Engineer, Shift Supervisor |
| Field Technician | Does the work: accepts jobs, punches, completes proof, logs expenses. Subcontractor technicians are Field Technicians linked to a vendor and see only their own jobs. | Maintenance Lead, Doctor, Cleaner |
| Front Desk / Dispatcher | Logs requests, registers clients, creates SRs, dispatches urgent jobs. | Receptionist, Helpdesk Operator |
| Finance / Accounts | Quotes, invoices, payments, expense reconciliation. | Accountant, Billing Clerk |
| Client User | External customer: views and raises requests, answers quotes, rates service. Client Admin sees the whole client, Client Viewer sees assigned sites only. Client sign-in is on Professional and above only. On Starter, clients have no sign-in and use secure links. | Building Manager, Hospital Admin |

### Permission keys (43)

| Group | Keys |
|---|---|
| Service requests | sr.view, sr.create, sr.approve, sr.assign, sr.qc, sr.cancel, sr.reopen, sr.override_geofence, sr.export |
| Field work | field.punch, expense.log, expense.approve |
| Commercial | quote.manage, quote.respond, invoice.manage, invoice.approve, payment.record |
| Assets and contracts | asset.view, asset.manage, asset.import, contract.view, contract.manage, contract.choose, pm.view, pm.manage |
| Configuration | form.manage, workflow.manage, settings.manage, vocabulary.manage, category.manage |
| People and master data | user.manage, role.manage, client.manage, site.manage, vendor.manage |
| Insight | dashboard.company, dashboard.team, report.view, report.export, audit.view |
| Integration | notification.manage, webhook.manage, api.manage |

### Access levels (7)

| Level | Meaning |
|---|---|
| full | All records in the tenant. |
| none | No access. |
| record_level | Records matching the user's relationship: own, assigned, team, or site list. |
| view_only | Read without change. |
| view_own | Read only own records. |
| edit_own | Read and change own records. |
| grantable | Full access plus the right to extend this permission to other users. |

Rules: a user's effective access is the role's level, widened or narrowed by `user_permission_overrides` (optionally expiring). Permission checks happen in policy classes and middleware, never inline in views. Adding a module means adding keys to a seeder, not editing existing checks. A team lead's "own team" means requests in the service categories they cover, or assigned to their team's technicians. The default permission matrix is in Appendix B and in the workbook `Aiywah-FSM-Permission-Matrix-Draft-2.xlsx`. Draft 2 was signed off on October 17, 2026 by Kiran E (BA - PM) with no changes, so it is approved for seeding. Later changes need a new signed version. When an admin creates a user, five optional permissions appear as tick boxes (approve requests, pass QC, approve expenses, assign jobs, log expenses). They set a personal extra on top of the role. Only the Company Admin adds staff and technicians. A Client Admin adds users for their own company, and front desk adds client users when a client has no Client Admin. Operations managers do not add users.

### Terminology aliasing

Each tenant sets display labels for: service_request, technician, team_lead, client, site, asset, contract, quote, invoice, qc_review. Code must call a single `term('technician')` helper and never hardcode these labels. Aliases apply to the web UI, PDFs and emails. For WhatsApp, aliases are passed as template variables (see Section 9.5).

## 5. Phase 1 Release Structure

Phase 1 is the complete product described here. It is built and shipped in three releases. A release ships only when its exit gate passes. Nothing moves between releases without something else of equal size moving out.

### Release A: Core Loop

- Tenancy, subdomain routing, plans and feature flags, manual tenant provisioning by Platform Admin. The set-up job takes a source and a checkout reference, so Release B can start it from a payment.
- Authentication for staff, technicians and clients. RBAC with seeded roles. Terminology aliasing.
- Master data: clients, contacts, sites with a map picker for coordinates, service categories with proof rules, SLA policies, expense categories, hold reasons, tax rates, business hours.
- Service request lifecycle: all 10 statuses, hold and resume, QC, rework, cancel, reopen, internal notes, status history, audit log.
- Field View: prepare-my-day, GPS punch, before and after photos, notes, signature, expenses with receipts, offline-tolerant queue.
- Notifications: in-app, email and browser push on every plan. WhatsApp through the company's own Meta account (manual connection and a setup wizard) on Professional and above, with automatic fallback.
- Client portal (Professional and above): sign-in, view requests, raise request, quote accept or reject. Quote answers and feedback also work by secure link on every plan.
- Quotes and invoices: line-item builder or PDF upload, payment recording, and the invoice-mandatory setting per category, in the company currency.
- Role dashboards (core counts and queues), SR Excel export, Platform Admin panel with audited impersonation.
- Plan limits: usage counters, warnings at 80 percent and upgrade prompts at 100 percent, without stopping work in progress.
- Also in Release A: CSV import for clients, sites and users, the audit trail viewer, optional two-factor sign-in, and the message log with retry. Technicians cannot raise requests until QR scanning arrives with assets in Release B.

**Exit gate:** acceptance scenarios 1 to 10 pass; tenant isolation test suite passes with zero leaks; Operations has signed off the default permission matrix.

### Release B: Depth

- Assets with 360 view, QR, bulk import, parent and child assets, batch SRs.
- Contracts and AMCs, automatic matching, call caps, renewal alerts.
- Time-based preventive maintenance with automatic SR creation and PM calendar.
- Versioned dynamic form builder and per-asset checklists. Approval modes beyond manual.
- Smart assignment, dispatch calendar, workload view, time off.
- Multi-trade parent and child SRs. Subcontractor vendors. Depot and workshop jobs.
- Payments ledger, aging and overdue reminders, PDF reports, daily WhatsApp summaries, notification preferences and digests.
- Multi-currency quotes and invoices with automatic daily rates. Guided Meta sign-up for WhatsApp. Website checkout on the aiywah.com product page with automatic company set-up and subscription billing, tested by the Tenancy spec tests AT-70 to AT-78. Customer self-service for upgrade and cancel comes after launch.

**Exit gate:** acceptance scenarios 11 to 17 pass; the pilot replay set (Section 13) runs without unlogged gaps.

### Release C: Differentiation

- Meter-based preventive maintenance.
- If-then workflow rules and custom statuses.
- Route optimisation.
- Webhooks and accounting integrations, published API documentation.
- Custom domains, white-label branding, regional data residency, scheduled reports, contract profitability analytics.

**Exit gate:** acceptance scenarios 18 and 19 pass; external security review completed.

## 6. Plans and Feature Gating

Four rules govern every plan decision:

- The core loop (request, approval, assignment, punch, proof, QC, completion, feedback) is in every plan.
- Plans gate depth, volume and advanced automation. They never gate delivery of a core step.
- This matrix is the only source of plan rules. Modules do not restate them.
- Downgrades keep all data, make gated data read-only and pause gated automation. The API returns 403 with code `FEATURE_NOT_IN_PLAN` for gated actions.

Starter alerts use email, in-app and browser push. Browser push works only where the user has allowed browser notifications, and on iPhone only for the installed Home Screen app.

Starter clients have no sign-in. They receive email updates and use secure links to answer quotes and give feedback.

Enforcement is central: `tenant.can('feature_key')` in middleware and policies, `@feature('key')` in Blade.

| Feature | Key | Starter | Professional | Enterprise |
|---|---|---|---|---|
| Core SR lifecycle, hold, QC, rework | core | Yes | Yes | Yes |
| Field View with offline queue | field_view | Yes | Yes | Yes |
| In-app, email, web push notifications | notify_basic | Yes | Yes | Yes |
| WhatsApp on the company's own verified Meta account | whatsapp | No | Yes | Yes |
| Client portal: sign-in, view and raise requests | portal_basic | No | Yes | Yes |
| Client answers by secure link: quote answer and feedback | client_links | Yes | Yes | Yes |
| Client portal: quotes, invoices, SLA, documents | portal_full | No | Yes | Yes |
| Quotes and invoices by PDF upload | billing_upload | Yes | Yes | Yes |
| Line-item builder, payments, aging | billing_full | No | Yes | Yes |
| Multi-currency quotes and invoices | multi_currency | No | Yes | Yes |
| Invoice-mandatory setting per category | invoice_gate | Yes | Yes | Yes |
| Basic asset registry | assets_basic | Yes | Yes | Yes |
| Asset 360, QR, batch SRs, bulk import | assets_full | No | Yes | Yes |
| Contracts and AMCs | contracts | No | Yes | Yes |
| Time-based preventive maintenance | pm_time | No | Yes | Yes |
| Meter-based preventive maintenance | pm_meter | No | No | Yes |
| Dynamic form builder | forms | Notes only | Up to 10 templates | Unlimited |
| Approval modes | approval | Manual only | Plus automatic modes | Plus finance and manager |
| Custom statuses | custom_status | No | No | Yes |
| If-then workflow rules | workflow_rules | No | No | Yes |
| Smart assignment, calendar, workload | dispatch_smart | No | Yes | Yes |
| Route optimisation | dispatch_route | No | No | Yes |
| Multi-trade parent and child SRs | multi_trade | No | Yes | Yes |
| Subcontractor vendors | vendors | No | Yes | Yes |
| Depot and workshop jobs | depot | No | Yes | Yes |
| Team Lead role | team_lead | No | Yes | Yes |
| SR Excel export | reports_basic | Yes | Yes | Yes |
| PDF reports and daily summaries | reports_full | No | Yes | Yes |
| Scheduled reports | reports_sched | No | No | Yes |
| REST API | api | Yes | Yes | Yes |
| Webhooks and accounting sync | webhooks | No | No | Yes |
| Terminology aliasing | aliases | Yes | Yes | Yes |
| Logo and colours | brand_basic | Yes | Yes | Yes |
| Branded PDFs | brand_pdf | No | Yes | Yes |
| Custom domain and white label | white_label | No | No | Yes |

| Limit | Starter | Professional | Enterprise |
|---|---|---|---|
| Users | 10 | 50 | Unlimited |
| Service requests per month | 100 | 500 | Unlimited |
| API requests per minute | 100 | 500 | 2,000 |
| WhatsApp message cost | Not applicable | Billed by Meta to the company | Billed by Meta to the company |
| File storage | Allowance set by the Aiywah owner | Allowance set by the Aiywah owner | Allowance set by the Aiywah owner |

### At a plan limit

- Usage is counted per company and per calendar month: users, requests created and storage used.
- Staff, technicians and vendor technicians count as users. Client users do not.
- Every request created in the month counts, including maintenance jobs and child requests. A request cancelled while still Pending does not count.
- At 80 percent of a limit the company admin is warned. At 100 percent an upgrade prompt appears.
- Nothing is blocked in Phase 1, so a company can go over its limit. Usage over a limit is reported to the Aiywah owner for follow-up.
- Work already under way never stops, and proof photos from technicians in the field are always accepted.


## 7. Functional Modules

### 7.1 Service Request Lifecycle

**Statuses (10):** Pending, Quoted, Approved, Assigned, In Progress, Pending Review, Awaiting Invoice, Rework, Completed, Cancelled. Hold is a flag on In Progress (`is_on_hold`), not a separate status. Awaiting Invoice is used only for billable (out-of-coverage) jobs in service categories marked invoice mandatory. Payment tracking runs on a separate track (`billing_status`) and never blocks completion.

The screen-level detail for this module is in the SRL module spec (`docs/specs/SRL.md`). Where the spec and this summary differ, the spec prevails.

#### Allowed transitions

| From | To | Who | Conditions and effects |
|---|---|---|---|
| (new) | Pending | Front desk, client (portal, Professional and above), QR scan, PM job, API | Client, site and category required. SLA policy resolved. Matching contracts found. `proof_rules_snapshot` and `invoice_mandatory` frozen. The SLA clock has not started. |
| Pending | Approved | Ops Manager (sr.approve) or system | In-coverage: per approval mode. Out-of-coverage: only after an accepted quote, or manager override as goodwill with a reason. If several contracts match and none is pinned, the request stays Pending with a choose-contract flag until a manager picks one. The SLA clock starts (Emergency requests start it at creation). |
| Pending | Quoted | Finance (quote.manage) | Coverage is out_of_coverage. Quote sent. The SLA clock has not started and does not run while the client decides. |
| Quoted | Approved | Client accepts, or manager override with reason | Quote marked accepted. The SLA clock starts. |
| Quoted | Cancelled | Client rejects, quote expires, or manager | Reason recorded. |
| Approved | Assigned | Ops Manager, Team Lead, Dispatcher (sr.assign) | Technician (own or vendor) chosen, ETA set. A skill mismatch warns but does not block. The assigner may tick Enforce geofence and Allow signature waiver. Both are off by default. |
| Assigned | Assigned (reassign) | Same as above | New technician notified. Previous technician's queued events for this SR are marked conflict. |
| Assigned | In Progress | Technician punch in | Geofence enforced only if ticked for this job. GPS is always recorded. First punch time stored. Response SLA stops. |
| In Progress | On Hold and back | Technician | Hold reason required. Hold minutes tracked separately. SLA pauses if the reason has `pauses_sla`. |
| In Progress | Pending Review | Technician punch out | Every proof rule of the category satisfied. Work minutes computed. |
| Pending Review | Completed | Ops Manager (sr.qc) | QC pass on a job that needs no invoice: the category is not invoice mandatory, or the job is in contract, in warranty or goodwill. Resolution SLA stops. Feedback link sent. |
| Pending Review | Awaiting Invoice | Ops Manager (sr.qc) | QC pass on an out-of-coverage job in an invoice-mandatory category. Resolution SLA stops at QC pass. Finance is told to invoice. |
| Awaiting Invoice | Completed | Ops Manager (invoice.approve) | Invoice approved. Feedback link sent. Only invoice approval holds the job, never payment. If the manager rejects the invoice, it returns to Finance as a draft and the job stays here. |
| Pending Review | Rework | Ops Manager | Reason mandatory. `rework_count` increases. Technician notified. |
| Rework | In Progress | Technician punch in | New punch session. Original timestamps preserved. Work minutes accumulate. |
| Completed | Rework | Ops Manager (sr.reopen) | Within the company's reopen window (default 14 days, 0 means never), reason mandatory. |
| Any open status | Cancelled | Ops Manager (sr.cancel). Client only while Pending. | Reason mandatory. An open punch is force-closed with a note. |

Any other transition returns `INVALID_TRANSITION`. Every transition writes `sr_status_history` and `activity_logs` and fires its notification event.

#### Rules

- **Numbering:** SR, quote, invoice and contract numbers come from `tenant_sequences` with row locking. Default pattern `PREFIX-YYYY-000001`.
- **Coverage order:** (1) manager override, (2) matched contract, (3) asset under warranty, (4) out_of_coverage.
- **SLA source order:** contract SLA, then category and priority policy, then tenant default policy.
- **SLA clock:** starts at approval. For paid work that is when the client accepts the quote. Emergency requests start the clock at creation. Response SLA is met at first punch in. Resolution SLA is met at QC pass, so a slow invoice never counts against the technician. The clock pauses only during holds whose reason pauses SLA. Due times are recalculated on every pause and resume and use business hours when the policy says so. A scheduler marks breaches each minute and raises a warning event at 80 percent of the target.
- **Review time:** the time from creation to approval is reported separately as review time, so managers can see how long requests wait.
- **Reopen:** each company sets its reopen window (default 14 days, range 0 to 365, where 0 means no reopening). The SLA does not restart. The extra visit is goodwill unless the manager bills it. A reopen counts against first-time fix. After the window closes, a Create follow-up job button copies the details into a new linked request. An invoice already approved stays unchanged. The window is the completion time plus the company's days times 24 hours.
- **Invoice mandatory:** a setting on each service category, frozen onto the request at creation. It applies only when the job is out of coverage and therefore billable.
- **Send for quote:** a manager can forward a request for quoting. It stays Pending with `quote_requested_at` set, and Finance sees it in a Waiting for quote queue. Finance can send a quote only for such a request.
- **SLA timing:** the SLA policy is picked when the clock starts. A request raised to Emergency after creation starts its clock at that moment, or, if the clock is running, has its due times recalculated from the clock start with the Emergency policy. The SLA result is fixed at the first QC pass, and reopening never changes it.
- **Billable job that will not be billed:** the manager changes the coverage to Goodwill with a reason. Any draft invoice is voided and the request completes.
- **Assignment:** a technician can be changed while the request is Assigned or in Rework. Front desk assigns critical and emergency requests only.
- **Title and lists:** a request has a short title. Staff type it, and the client portal builds it from the category and site. Lists show 25 rows per page.
- **Multi-trade:** a master SR spawns child SRs, each with its own technician, punches and proof rules. The master has no technician. Its status is derived: Pending until children exist, In Progress when any child is, Pending Review when all children are Pending Review or later, Completed when all are Completed, Cancelled when all are Cancelled. Child expenses roll up to the master for billing.
- **Batch SRs:** an SR links to many assets through `service_request_assets`. Punch out is blocked until every asset row has a final inspection status. One master signature closes the batch.
- **Feedback:** one secure link per completed SR, valid for 7 days by default. Star rating (1 to 5) and NPS (0 to 10) are required, comment is optional.

### 7.2 Assets and Equipment (Release B)

- Fields: unique asset code, name, parent asset, service category, manufacturer, model, serial number, location within site, installation date, warranty expiry, status, meter settings, photo, specifications, notes.
- **QR:** each asset has a random `qr_hash`. The QR encodes `https://{slug}.aiywah.com/q/{qr_hash}` and resolves only for signed-in users of that tenant.
- Selecting a site filters the asset list. Selecting an asset fills the category, shows warranty status and recent history.
- **Asset 360:** identity, warranty and contract coverage, full SR and checklist history, upcoming PM, lifetime service cost, photos.
- A parent and its children must belong to the same site. Decommissioned assets cannot be chosen for new SRs but keep their history.
- **Bulk import:** upload, validate, preview with row-level errors, then commit valid rows (`import_jobs`).

### 7.3 Preventive Maintenance (Release B calendar, Release C meter)

- A schedule belongs to a site and one asset or a batch of assets. It sets category, trigger type, priority, checklist template and optional default technician.
- **Calendar trigger:** a daily job at 06:00 tenant time creates an SR for every schedule due within its `lead_days`, then advances `next_due_date`. If `skip_if_open_sr` is on and an SR from this schedule is still open, no new SR is created. Re-running the job must never create duplicates.
- **Meter trigger (Release C):** when a checklist submission raises an asset's meter reading past `next_due_meter`, an SR is created. A reading lower than the previous one is rejected unless the user has `asset.manage`, which records a correction in the audit log.
- A batch schedule creates one SR with all its assets. A maintenance job uses the contract pinned on its schedule. With no pin and several matching contracts, the job waits in Pending for a manager's choice.
- Dashboards: on-time versus overdue PMs, PM versus reactive ratio. PM overdue events feed workflow rules and escalations.

### 7.4 Contracts and AMCs (Release B)

- Fields: number, client, type (AMC, warranty, ad-hoc, DLP), status, dates, SLA overrides, call cap and period, calls used, value, payment terms, auto-renew.
- **Matching:** candidates are contracts of the SR's client that are active today, cover the SR's site (no site rows means all client sites) and cover its category (no category rows means all categories).
- **Overlaps:** if several contracts match, a manager always chooses. The request stays Pending with a choose-contract flag until someone with `contract.choose` picks one, and the choice is audited. A maintenance schedule or an asset may pin a default contract. A pinned contract that is still active and still covers the site and category is used without asking, and the manager may change it.
- **Call cap:** a call is counted when the SR is approved under the contract and released if the SR is cancelled before work starts. When the cap is reached the manager must choose: count it anyway, treat as goodwill (no billing), or treat as out-of-coverage (quote).
- **Renewals:** a daily job moves contracts to expiring_soon and raises alerts at 90, 60 and 30 days.
- **Profitability (Release C):** SLA compliance, calls used versus included, service cost versus contract value, satisfaction.

### 7.5 Dispatch and Scheduling

- **Assignment options:** when assigning, two boxes can be ticked. **Enforce geofence** blocks the punch outside the site's radius. **Allow signature waiver** lets a code replace the customer's signature. Both are off by default. Enforce geofence can be ticked only for a site with saved coordinates. Jobs created automatically, such as maintenance jobs, use the defaults unless the site is marked always enforce.
- **Manual assignment** is always available. **Smart suggestions** (Release B) score each eligible technician: skill match, availability, workload, proximity, first-time-fix rate. Default weights are 35, 25, 20, 10 and 10, configurable per tenant.
- **Proximity** means distance from the technician's last known location (last punch, or last Field View check-in) or home base. It is shown with its age and is never presented as live tracking.
- **Availability** uses time off and existing ETAs. **Workload** compares scheduled minutes in the next 7 days with daily capacity: green below 60 percent, amber 60 to 90, red above 90 (defaults, configurable).
- **Calendar:** drag and drop changes the ETA, writes `sr_reschedules` and notifies the client. An emergency flag puts an SR at the top of every queue and sends immediate notifications.
- **Route optimisation (Release C):** for one technician and one day, suggests a visiting order using the mapping provider. It is a suggestion the manager accepts, written to `daily_route_sequence`.

### 7.6 Field Execution

- **Punch in:** device time, GPS fix and accuracy are always stored. If Enforce geofence is ticked for the job and the distance from the site is beyond the site's radius (default 250 m), punch in is blocked. The technician may request an override with a reason and photo, which a manager with `sr.override_geofence` approves and which is flagged at QC.
- **GPS fallbacks:** accuracy worse than 150 m prompts a retry. If GPS is denied or unavailable, a job with Enforce geofence ticked blocks punching. Otherwise the punch is allowed and flagged `gps_missing`.
- **Proof lock:** punch out stays disabled until every flag in the SR's `proof_rules_snapshot` is met: at least one before photo and one after photo, checklist complete (Release B forms), signature or a verified waiver code where the job allows it, and for batch SRs every asset finished. Punch-out distance is recorded and flagged if outside the radius.
- **Signature:** signer name required. If Allow signature waiver is ticked for the job, the technician may instead request a code. The code goes to a contact chosen from the project's contact list, by WhatsApp when the company's account is live and otherwise by email. It is valid for 10 minutes with 3 attempts. The system stores who verified, when, and through which contact, and QC sees the flag. Contacts need a phone or email saved. Without the tick, the signature is mandatory.
- **Expenses:** chosen from admin-defined categories with quantity, rate and receipt photo (mandatory if the category says so). Billable expenses flow to the invoice editor. Managers approve or reject. Vendor technicians cannot log expenses unless the admin switches that on for the person when adding them. Expense reports show vendor expenses separately and can filter them. The total is quantity times rate, rounded half up to the currency's decimals. When the category has a default rate, the technician cannot change it.
- **Hold and resume:** reason from the tenant's hold reasons list. Work and hold minutes are tracked separately.

### 7.7 Billing and Finance

- **Quotes:** line items with tax rates, discount, validity date, terms. Revising a sent quote creates a new version and marks the old one superseded. The client accepts or rejects through a secure link on every plan, or through the portal on Professional and above. Name, time and IP of the response are stored.
- **Invoices:** built from SR expenses, parts and manually entered labour lines, or uploaded as PDF. Flow: draft, pending approval, approved, sent. Paid states follow payments. Invoices are voided with a reason, never deleted. In Release A an invoice is a pro-forma or customer copy. Where the law requires a government-cleared tax invoice, the customer issues it from its own accounting suite, and the legal invoice number issued elsewhere can be recorded on the invoice.
- **Payments:** recorded per invoice with method and reference, reversible with audit. Aging report at 30, 60 and 90 days. WhatsApp or email reminders for overdue invoices.
- **Currency:** each company has one base currency, set once and locked after its first quote or invoice. Dashboards and reports always show the base currency.
- **Multi-currency (Release B):** a quote or invoice may use another currency. The rate comes automatically from a daily rates provider and can be overwritten by Finance on that document. The rate is fixed when the quote is sent and carries to the invoice unless Finance changes it. Payments are recorded in the invoice currency. Technician expenses stay in the base currency. Reports convert at the stored rate.
- **Invoice before completion:** for billable (out-of-coverage) jobs in categories marked invoice mandatory, the job waits in Awaiting Invoice until the invoice is approved. Jobs in contract, in warranty or goodwill complete at QC pass. If the manager rejects an invoice, it returns to Finance as a draft and the job stays in Awaiting Invoice. Finance builds invoices and a manager approves them.
- No rate cards, so labour lines are entered by Finance. Accounting sync is by webhook (Release C).

### 7.7a Billing rules approved on October 23, 2026

- **Roles.** Finance builds and sends quotes and invoices and records payments. The Operations Manager approves invoices and otherwise only views. The Company Admin can do everything. A client sees only their own documents.
- **Quotes.** Only for a request that was sent for a quote and is out of coverage, one open quote at a time. A line has a description, a quantity, an optional unit, a rate and a tax rate, up to 50 lines. One discount for the whole document, as a percentage or an amount. Valid 14 days by default and default terms, both company settings. A revision is a new version with the number plus a suffix. Uploaded PDF quotes need a total, and on Starter this is the only way.
- **Arithmetic.** Quantity times rate, one discount shared over the lines by line total, tax for each line, all rounded half up to the currency's decimals at each step, stored with three decimals.
- **Sending.** A secure link for each ticked contact, valid until the end of the valid-until day, by WhatsApp or email. The client accepts with a typed name and a tick, or rejects with a reason, and the name, time and network address are saved. Acceptance approves the job and starts the SLA clock. Rejection cancels it. The client gets no message for their own answer.
- **Invoices.** One invoice that is not void for each billable request that is Awaiting Invoice or Completed. Built from approved expenses, the accepted quote or empty. Flow: draft, pending approval, approved, sent. A manager can send it back with a reason. Approval completes a job that is Awaiting Invoice, and the person who submitted may approve their own invoice. The invoice number is taken at draft and kept on void. The due date comes from the client's payment terms.
- **Pro-forma copy.** An optional legal invoice number issued elsewhere. The PDF carries the line "Customer copy. The legal tax invoice is issued separately where the law requires it." Branded PDFs and Excel download on Professional and above. Starter uploads PDFs.
- **Payments and status.** Payments are recorded by hand on sent invoices, with part payments, and can be reversed with a reason. Billing status is kept up to date by the system. Overdue starts the day after the due date. Finance can write off the balance with a reason and the Company Admin can undo it. There is no payment gateway, aging report or overdue reminder in Release A.
- **Void.** Invoices are voided with a reason and never deleted, drafts included. Payments are reversed first.
- **Expense review.** Operations Manager, Company Admin and Finance approve (with a Billable tick) or reject with a reason. An expense on an invoice that is not void is locked. The technician sees the result.

### 7.7b Client portal rules approved on October 23, 2026

- **Who.** Client users of companies on Professional and above. A Client Admin sees the whole client company. A Client Viewer sees only their sites. Starter has no portal.
- **Scope.** The system limits every list, count, detail, file and download. A record outside the scope behaves as not found.
- **What a client sees.** An allowed list of fields only. Never priority, coverage, internal notes, expenses, GPS, hold or quality-check reasons, or technician contact details. The technician's name, the work photos and the SLA targets can each be switched off by the company. Status words are plain, and hold and rework are hidden.
- **What a client does.** A Client Admin raises requests (site, category, description, up to 10 photos, priority Medium, at most 30 a day for each client company), cancels or edits a request they raised while it is Pending, and answers quotes. A Viewer reads only. Clients cannot write notes in Release A.
- **Invoices.** The client sees sent invoices only, with the bank details and the PDF, and no payment button.
- **Feedback.** By secure link on every plan. Stars (1 to 5) and a 0 to 10 score are required and a comment is optional. The link is valid for `feedback_link_days` days (7 by default) and works once. One answer for each completion. A Client Admin can also rate from the portal. A reopened job gets a new link and the new answer replaces the old one. The Operations Manager is told, and a low rating is Urgent.
- **A client cancels their own request.** The Operations Manager gets an in-app item and the client gets no message.

### 7.8 Forms, Approvals and Workflow Rules

- **Forms (Release B):** field types text, number (with unit and min and max), dropdown, checkbox, yes or no, date, photo, signature. Fields can be required and can show only when another answer matches. A template version moves draft, published, retired. A published version is immutable. An SR binds to the published version at creation, so later edits never change past answers.
- **Approval rules:** per category, priority and coverage type, choosing manual, auto if in contract, always auto, or finance and manager. In Release A only manual approval is active. The other modes arrive later.
- **If-then rules (Release C):** triggers (SR created, status changed, SLA warning, SLA breached, PM overdue, not accepted within N hours), AND or OR conditions, and actions (assign, set priority, notify, escalate to role, add note). Safeguards: a rule runs at most `max_runs_per_sr` times per SR, and every run is logged in `workflow_rule_runs`.
- **Custom statuses (Release C):** extra stages placed between core statuses, each mapped to a core status group so SLA and dashboards keep working. Core proof and SLA transitions cannot be removed.

### 7.9 Dashboards and Reports

| KPI | Definition |
|---|---|
| First-time fix rate | Completed SRs with zero rework divided by completed SRs. |
| Response time | First punch in minus the SLA clock start, net of paused time. |
| Resolution time | QC pass minus the SLA clock start, net of paused time. |
| Review time | Approval minus creation. |
| Callbacks | Jobs reopened inside the window divided by completed jobs. |
| SLA compliance | SRs meeting resolution target divided by completed SRs plus open SRs already breached. |
| Technician utilisation | Work minutes divided by scheduled available minutes. |
| NPS | Percent promoters (9 to 10) minus percent detractors (0 to 6). |
| PM compliance | PM SRs completed by their due date divided by PM SRs due. |
| Reactive versus PM ratio | Reactive SRs divided by PM SRs over a period. |

Dashboards are role-specific: company KPIs for Admin, queues for Ops Manager, team workload for Team Lead, personal stats for Technician, outstanding money for Finance. Reports: Monthly SLA (PDF), Technician Scorecard (PDF), SR Completion Certificate (PDF), Contract Performance (PDF), Expense Summary (Excel, filterable by own staff or vendor), SR Data Export (Excel). Scheduled delivery is Release C.

### 7.9a Dashboard, limit and platform admin rules approved on October 23, 2026

- **One home page for each role.** Company Admin and Operations Manager: the Company dashboard and the Operations queue. Team Lead: the Team dashboard. Technician: their own stats. Front Desk: the Front desk queue. Finance: the Finance dashboard. Clients: the portal dashboard.
- **Periods.** Today, Last 7 days, Last 30 days (the default), This month, Last month and a custom range up to 366 days, in the company's time zone.
- **Figures in Release A.** Counts by status, SLA compliance, response time, resolution time and review time, plus first-time fix, average stars and NPS, as defined in the table above and in the DSH spec. Times show the median with the average beside it. Utilisation, PM compliance, callbacks and contract figures arrive later.
- **Queues.** The Operations queue lists pending approvals, QC items, overdue jobs and jobs awaiting invoice. The Front desk queue shows the requests the person raised and today's calls, meaning every request any front desk user raised today. The Finance dashboard shows quotes waiting, unpaid and overdue invoices, expenses and payments.
- **Export.** The Company Admin and Operations Manager export the filtered request list, up to 5,000 rows, with no money, no contact details and no internal notes. Every export is recorded.
- **Usage counters.** For each company and month: active users (staff, technicians and vendor technicians, not client users), requests created, total file storage and WhatsApp and email messages sent.
- **Limits.** The Company Admin is told at 80 and at 100 percent of a plan limit, once for each limit and month, with banners. Nothing is ever blocked, and proof photos from the field are always accepted. A plan with no number shows "No limit". The Usage page is view only for the Company Admin and Operations Manager. Upgrades are by contacting Aiywah in Release A.
- **Impersonation.** Owner and support roles only. A reason of 10 to 255 characters is required, with 30 minutes by default and 2 hours at most, and one session at a time. A banner shows on every screen. The impersonated session cannot change passwords, two-factor, roles or permissions, approve or void an invoice, send a quote or an invoice, record or reverse a payment, write off a balance, or export data. Every change is recorded with the session. Every Company Admin is told when a session starts, and sees the history on a Support access page, kept 3 years.
- **WhatsApp health board.** One row for each company with WhatsApp set up: state, quality rating, messaging limit, messages in 24 hours and 7 days, failure rate (red over 10 percent, amber over 5) and rejected templates, from an hourly snapshot. Read only.
- **Freshness.** Dashboards are worked out when opened and kept 60 seconds. The export and the usage meters are always current. Counters are checked every night.
- **Permissions.** No new permission keys. The Usage page uses `settings.manage` (view) and the support history uses `audit.view`.

## 8. Field View (Progressive Web App) Specification

**Access.** Technicians sign in on the tenant subdomain with phone or email plus password, or a one-time code (sent by email on Starter, by WhatsApp or email elsewhere). A device stays trusted for 30 days. Notification links open the exact job after the session check. A link alone never grants access. Admins can revoke any device (`user_devices.revoked_at`).

**Screens.** Today's jobs, job detail, punch, photos, checklist or notes, expenses, signature, punch-out summary, asset scan, sync status, notifications, profile. It also has a menu, All my jobs (Active, Review, Done) and, for team leads, a Team tab to approve, reject, assign and raise requests, online only.

### Prepare my day

When online, the app caches the technician's assigned jobs for the coming days: job details, site coordinates, contact, asset lists, the published checklist versions, and hold reasons. A technician who opened the job before entering a basement can still work.

### Offline event queue

- Every action (punch in, hold, resume, punch out, checklist save, expense, photo, signature) is first written to a local queue with a `client_event_uuid` and the device timestamp. The screen shows pending count and last sync.
- Events replay in order when connectivity returns and the app is open or foregrounded. Background sync is not available on iPhone, and the app says so plainly.
- **Idempotency:** the server stores `client_event_uuid` with a unique index and treats a repeated event as success. Retries can never duplicate a punch, photo or expense.
- **Conflicts:** if the SR was cancelled, reassigned or completed while the technician was offline, the affected events are stored with `sync_status = conflict_rejected` and the evidence is kept and flagged for the manager. The technician sees a clear message. Nothing is silently discarded.
- **Time and GPS:** server stores device time, sync time and clock drift. Geofence results use the coordinates captured at the moment of the action.
- **Photos:** resized on device to a maximum long edge of 1600 px, JPEG quality 0.8, stored as blobs in IndexedDB, uploaded with retry. The app warns when device storage is low.
- **Security:** at sign-out and on device revocation the saved jobs and personal data are cleared at once. Work not yet sent stays on the phone, locked, until the same technician signs in again and it is sent. Sign-out while offline with items waiting is blocked. The Field View is a mobile web app in Phase 1 and the native app is Phase 2.

### What the Field View does not do

- It does not work fully offline for days. The native app in Phase 2 covers that.
- It does not track location in the background. Location is captured at punches and when the app is open.
- It cannot guarantee notification delivery on iPhone unless installed to the Home Screen.

## 9. Communications and WhatsApp Operations

### 9.1 Channels and fallback

Channels are WhatsApp, email, web push and in-app. Each event has default channels and a fallback. If a WhatsApp message fails, is not allowed (no consent, account not live) or is not delivered within the event's fallback window, the system sends email and logs the original message as `fallback_sent`. Starter tenants use email, browser push and in-app only. Browser push works only where the user has allowed browser notifications. Client contacts receive email, and WhatsApp where the company's account is live. Starter clients have no sign-in, so they use the secure links in those emails.

### 9.2 Event catalogue

| Event | Recipients | Default channels | Fallback |
|---|---|---|---|
| sr.created | Client contact, Ops Manager | Client: WhatsApp. Ops Manager: in-app | Email |
| sr.approved | Client contact | WhatsApp | Email |
| sr.cancelled | Client contact, assigned technician | Client: WhatsApp. Technician: WhatsApp, push | Email |
| sr.quote_requested | Finance | In-app, email | None |
| quote.sent | Client contact (secure link) | WhatsApp, email | Email |
| quote.accepted, quote.rejected | Finance, Ops Manager | In-app, email | None |
| sr.assigned | Technician, Client contact (name and ETA) | Technician: WhatsApp, push. Client: WhatsApp | Email |
| sr.eta_changed | Client contact, Technician | Client: WhatsApp. Technician: WhatsApp, push | Email |
| sr.reassigned | Previous and new technician | WhatsApp, push | Email |
| tech.arrived | Client contact | WhatsApp | Email |
| sr.on_hold | Ops Manager | In-app, push | Email |
| sr.pending_review | Ops Manager | In-app, push | Email |
| sr.awaiting_invoice | Finance, Ops Manager | In-app, email | None |
| sr.contract_choice_needed | Ops Manager | In-app, push | Email |
| signature.code | Chosen project contact | WhatsApp, email | Email |
| sr.rework | Technician | WhatsApp, push | Email |
| sr.completed | Client contact (feedback link), Ops Manager | Client: WhatsApp. Ops Manager: in-app | Email |
| invoice.sent | Client contact | WhatsApp, email | Email |
| invoice.overdue | Client contact, Finance | Client: WhatsApp. Finance: email | Email |
| payment.received | Finance | In-app | None |
| sla.warning | Ops Manager, Team Lead | Push, in-app | Email |
| sla.breached | Ops Manager, Company Admin | WhatsApp, push | Email |
| plan.limit_warning | Company Admin | In-app, email | None |
| pm.generated | Default technician, Ops Manager | WhatsApp, in-app | Email |
| pm.overdue | Ops Manager | WhatsApp, in-app | Email |
| contract.expiring | Company Admin, Ops Manager | Email, in-app | None |
| contract.cap_reached | Ops Manager | In-app, push | Email |
| summary.daily | Roles configured by the admin | WhatsApp | Email |
| whatsapp.template_rejected | Company Admin, Platform Admin | In-app, email | None |
| import.completed | Importing user | In-app | None |
| field.geofence_override_requested | Ops Manager, the job's Team Lead | In-app, push | Email if unread after 5 minutes |
| field.geofence_override_decided | Technician | In-app, push, WhatsApp | Email |
| field.conflict | Ops Manager, the job's Team Lead | In-app, email | None |
| sr.client_reply | Ops Manager, the request's Team Lead | In-app, push | Email if unread |
| whatsapp.connection_problem | Company Admin | In-app, email | None |
| invoice.submitted | Operations Manager | In-app, email | None |
| invoice.sent_back | The person who submitted the invoice | In-app | None |
| feedback.received | Operations Manager | In-app. Urgent for 1 or 2 stars, or a score of 0 to 6 | None |
| impersonation.started | Company Admin | In-app, email. Urgent | None |

**Client contacts** receive WhatsApp where the company's account is live, and email otherwise. On Starter, and before a company's WhatsApp account is live, they receive email. They never receive in-app or push alerts. Portal users on Professional and above see request status on screen.

Users may set per-event channel and frequency (instant, hourly digest, daily digest, off) in `notification_preferences`, except events marked critical by the platform (sr.assigned, sla.breached).

### 9.3 Consent

WhatsApp is sent only to people with `whatsapp_opt_in_at` set: client contacts at creation or import, staff when their account is created. Opt-out replies set `whatsapp_opt_out_at` and stop WhatsApp to that number. Messages skipped for consent are logged as `skipped_no_consent`. A staff member's opt-out is stored in `users.whatsapp_opt_out_at`. A reply of STOP, STOP ALL, UNSUBSCRIBE, END or CANCEL opts the number out for every contact and user with it in that company. A reply of START records consent again. Staff and admins cannot opt anyone back in.

### 9.4 Account types and onboarding

Every company connects its own Meta WhatsApp Business account and number. There is no shared Aiywah number. WhatsApp is for Professional and above and turns on only after Meta verifies the account and approves the templates. Replies arrive on the company's own number and are matched to the open request by phone number, then stored as `sr_notes` with source `whatsapp_inbound`.

**How a company connects:** in Release A the admin pastes the Business account ID, the Phone number ID, the access token and the app secret from Meta into a setup wizard. The app secret lets the system check that a webhook call really came from Meta. In Release B a guided sign-up through Meta replaces the manual paste. That needs Aiywah to be approved by Meta as a technology provider, which depends on Aiywah's own verified Meta business account. This is an owner action that should start now.

Onboarding is tracked as a state machine:

| Step | Status value | Owner | Done when |
|---|---|---|---|
| 1 | not_started | Company Admin | Wizard opened. |
| 2 | business_verification_pending | Company Admin with Meta | Business verification submitted and later verified. |
| 3 | number_registered | Company Admin | Phone number registered (not in use on a personal WhatsApp app). |
| 4 | templates_submitted | System | Master template library submitted automatically. |
| 5 | templates_partially_approved | Meta | Some templates approved. |
| 6 | live | System | All mandatory templates approved, admin toggles go-live. |

Until the account is live, the company uses email, push and in-app. Template rejections raise `whatsapp.template_rejected` with the reason. Platform Admin has a WhatsApp health board: onboarding status, quality rating, messaging tier, failure rates.

### 9.5 Template library rules

- Every automated message is a pre-approved utility template keyed by event. Tenants cannot free-type automated messages.
- Alias terms (for example "Work Order" instead of "Service Request") and company names are passed as template variables, never baked into approved text. Templates must respect Meta's rules on variable placement.
- English only in Phase 1.
- Appendix C is the first draft of the message wording. Each template is submitted to Meta as soon as the company's account is verified, and the wording may change after Meta's review.
- Release A has 18 mandatory templates: the 13 that Release A uses in Appendix C, `sr_cancelled_tech`, `sr_eta_changed_tech` and `field_override_decided`, and `quote_sent` and `invoice_sent`. The master library is kept in `platform_whatsapp_templates` and owned by the Platform Admin. A template is submitted as `{key}_v{n}`. An approved template is never edited. A change is submitted as a new version and the old one stays in use until the new one is approved. A company that is already live stays live when a template is added, and its messages go by email until Meta approves it.

### 9.6 Operations

- Outbound messages are queued, throttled to the account's messaging limits, retried with backoff, and updated from Meta delivery webhooks (sent, delivered, read, failed).
- Meta bills each company directly for its messages. The platform keeps message counts per company for support, with no cap.
- Message payloads are kept 12 months, metadata longer per the audit retention rule.

### 9.7 Event engine rules (Release A)

- **One sender.** A module records an event in `event_outbox` in the same transaction as the change. The event engine reads it, finds the recipients, picks the channels and sends. Nothing else sends event messages. Sign-in emails and one-time codes are system messages: they use the same sender and log, skip the event rules, and are logged without the code or link.
- **Channels by plan.** Every staff recipient gets an in-app item for every event. Starter, and a Professional company whose WhatsApp is not yet live, use email where the default is WhatsApp. That is the first choice, not a fallback. Client contacts never get in-app or push.
- **Fallback.** WhatsApp with no consent, not live, or failed after retries goes to email at once. WhatsApp accepted but not delivered after 15 minutes (5 minutes for urgent events, 60 seconds for codes) also goes to email. Push and in-app events send email only if the in-app item is still unread. Events marked with no fallback never fall back.
- **Retries.** A failed WhatsApp send is retried after 1, 5 and 15 minutes, then falls back to email.
- **Duplicates.** The same event for the same person and record within 60 seconds is sent once. An assignment and an arrival time change within 10 seconds are one message.
- **Replies.** An inbound WhatsApp reply is matched by phone number to the request that person was last messaged about in the previous 7 days and saved as an internal note. A new event `sr.client_reply` tells the Operations Manager and the team lead.
- **Bell.** One list for each person with an unread count, kept 90 days. Web push is turned on with a button and never by a prompt on first load.
- **Keeping records.** Message payloads 12 months, metadata per the audit rule, outbox rows 30 days after they are done.

## 10. Industry Coverage and Boundaries

| Business pattern | Phase 1 support | How |
|---|---|---|
| Reactive repair visits | Native | Release A. |
| Scheduled preventive visits | Native | Release B calendar triggers. |
| Usage-based maintenance | Native | Release C meter triggers. |
| Batch inspections (50 extinguishers) | Native | Release B batch SRs. |
| Multi-trade jobs | Native | Release B parent and child SRs. |
| Subcontracted work | Supported | Release B: vendor companies with restricted technicians. |
| Depot or workshop repair | Supported | Release B: workshop site type, depot service mode, intake and return checklists. No warehouse or parts inventory. |
| Fixed recurring visit routes | Partial | Daily or weekly PM schedules create one SR per site per visit. No route-level planning until Phase 2. |
| Installation projects with milestones | Not in Phase 1 | Workaround: parent and child SRs as milestones, without schedules, progress billing or Gantt. Phase 2. |
| Spare parts inventory | Phase 2 | Phase 1 records parts as expenses only. |
| Long offline field work | Phase 2 | Native app with local database. |
| Payroll, attendance, fleet telematics, full accounting | Not a fit | Integrate, do not replace. |

**Fit test for a prospect:** (1) Does work happen at a place away from the office? (2) Does a single job have a requester, a doer and a checker? (3) Do they need proof the work was done? (4) Do they report on response time or SLA? (5) Can the work be done mostly with a phone? Four or five yes answers is a fit. Fewer means ask more questions before selling.

## 11. Database Schema (MySQL, Laravel 12)

### 11.1 Conventions

- Every table has `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `created_at` and `updated_at TIMESTAMP NULL`. Append-only tables carry `created_at` only.
- Tables marked [T] also have `tenant_id BIGINT UNSIGNED NOT NULL` with a foreign key to `tenants`. It is the leading column of every composite index. Tables marked [G] are global.
- The listings below omit `id`, `tenant_id`, timestamps and foreign key declarations to stay readable. Every `*_id` column must have a foreign key to its table with ON DELETE RESTRICT. `deleted_at` appears only where soft deletes apply.
- Money is `DECIMAL(14,3)` in the tenant's single currency, so the Kuwaiti dinar, Bahraini dinar, Omani rial and Jordanian dinar fit. Each currency is shown with its own number of decimals (2 for most, 3 for those four, 0 for a few). All timestamps are stored in UTC. Display and scheduled jobs use `tenants.timezone`.
- ENUM values are validated by PHP enums in the application. JSON columns are validated against schemas in the application.
- Laravel standard tables are used as is: `personal_access_tokens`, `jobs`, `failed_jobs`, `cache`, `sessions`, and Cashier tables for Stripe.
- Cross-tenant integrity: any foreign key between two [T] tables must be checked to belong to the same tenant in a model rule and covered by the isolation test suite.

| Group | Tables |
|---|---|
| Platform and tenancy | tenants, plans, gateway_subscriptions, platform_users, impersonation_sessions, tenant_settings, tenant_sequences, tenant_vocabulary, tenant_usage_counters |
| Users and access | users, roles, permissions, role_permissions, user_permission_overrides, user_skills, user_time_off, user_devices, user_locations, secure_links, auth_codes |
| Master data | clients, client_contacts, project_sites, vendors, service_categories, sla_policies, tenant_business_hours, tenant_holidays, expense_categories, hold_reasons, tax_rates |
| Assets, contracts, PM | assets, contracts, contract_sites, contract_categories, pm_schedules, pm_schedule_assets |
| Forms and workflow | form_templates, form_template_versions, approval_rules, workflow_rules, workflow_rule_runs |
| Service requests | service_requests, service_request_assets, service_request_punches, service_request_expenses, geofence_override_requests, field_event_log, sr_status_history, sr_notes, sr_reschedules, attachments, signature_waiver_otps |
| Billing | quotes, quote_items, invoices, invoice_items, payments, exchange_rates |
| Communication | notification_preferences, notifications, message_logs, whatsapp_accounts, whatsapp_templates, whatsapp_health_snapshots, event_outbox, platform_whatsapp_templates, daily_summary_configs |
| Integration and system | webhook_endpoints, webhook_deliveries, import_jobs, report_schedules, activity_logs |

### 11.2 Changes to v3.1 tables, and since v3.2

- `service_requests`: status is now the ten core values with `custom_stage_code` for Release C. Added hold flag, billing status, service mode, vendor, form template version, coverage override, SLA pause fields, signature waiver and reopen count.
- `service_request_punches`: added `client_event_uuid` (idempotency), GPS accuracy and status, nullable coordinates, override reason, sync status, clock drift. `hold_reason` text replaced by `hold_reason_id`.
- `service_request_expenses`: `expense_category` text replaced by `expense_category_id`. Added `client_event_uuid`.
- `service_categories`: added `allow_signature_waiver` and `allow_depot_mode`.
- Photo and signature file paths are replaced by `attachments`. JSON path columns now hold attachment IDs.
- `assets.photo_path` becomes `photo_attachment_id`.
- Since v3.2: geofence enforcement and signature waiver are per job (`service_requests.enforce_geofence`, `allow_signature_waiver`). The category columns `require_gps_geofence`, `geofence_radius_meters` and `allow_signature_waiver` are removed. The radius moves to `project_sites`.
- Since v3.2: `service_categories.invoice_mandatory`, status `awaiting_invoice` and `contract_choice_pending` are added. `contracts.match_precedence` is removed.
- Since v3.2: quotes, invoices and payments carry currency and rate. Tables `exchange_rates` and `signature_waiver_otps` are added. `whatsapp_accounts` is per company only, with no shared mode.
- Since v3.3: `plans.max_storage_gb` and the table `tenant_usage_counters` are added for plan limits. `invoices.approval_rejection_reason` is added.
- Since v3.4: `service_request_expenses.vendor_id` is added so vendor expenses can be reported separately.
- Since v3.7: `service_requests` gains `quote_requested_at`, `follow_up_of_sr_id`, `sla_response_warned_at` and `sla_resolution_warned_at`.
- Since v3.10: `tenants` gains `source` and `checkout_reference`. `users` gains the reserved `aiywah_account_id`.
- Since v3.11: new table `auth_codes`. `users` gains `client_access`, `site_scope_json`, `must_change_password`, `failed_login_count`, `locked_until` and `two_factor_confirmed_at`. `platform_users` gains `failed_login_count` and `locked_until`. `secure_links.purpose` gains `user_invite`, `password_reset`, `client_sign_in` and `email_change`.
- Since v3.13: every money column changes from `DECIMAL(14,2)` to `DECIMAL(14,3)` and money unit rates from `DECIMAL(12,2)` to `DECIMAL(12,3)` (SCH-14). Meter readings, quantities, tax percentages and exchange rates are unchanged.
- Since v3.13: `client_contacts` gains `is_active` (SCH-13), so a contact used for a signature code or a message log can be made inactive instead of deleted.
- Since v3.14: new tables `geofence_override_requests` (SCH-15) and `field_event_log` (SCH-16). The Field View sync endpoint records every event in `field_event_log`.
- Since v3.15: new tables `event_outbox` and `platform_whatsapp_templates`. New columns on `users` and `client_contacts` (`email_bounced_at`, and `whatsapp_opt_out_at` on users), `notifications` (`priority`), `message_logs` (`dedupe_key`, `fallback_due_at`, `notification_id`, `is_system`, three new statuses), `whatsapp_accounts` (`app_secret_encrypted`, `webhook_path_token`, `paused_at`, `paused_reason`, `last_error`) and `whatsapp_templates` (`meta_template_id`, `version`, `last_checked_at`).
- Since v3.16: `quotes` and `invoices` gain `discount_type` and `discount_value`. `quotes` gains `expired_at`. `invoices` gains `legal_invoice_number`, `notes`, `submitted_at`, `submitted_by_user_id`, `voided_at`, `write_off_amount`, `written_off_at`, `write_off_reason` and the status `written_off`. `payments` gains `payment_number` (unique), `reversal_reason` and `reversed_by_user_id`. `service_request_expenses` gains `approved_at` and `rejection_reason`. `secure_links.purpose` gains `client_invoice`. `service_categories` gains `client_selectable`. `service_requests` gains `client_feedback_at`, `client_feedback_source` and `client_feedback_by`. The master template library gains the rows `quote_sent` and `invoice_sent`.
- Since v3.17: `tenant_usage_counters` gains `warnings_json` and `email_message_count` (the columns `warned_80_at` and `prompted_100_at` are no longer used). `impersonation_sessions` gains `end_reason` and `actions_count`. `activity_logs` gains `impersonation_session_id` with an index. New table `whatsapp_health_snapshots`.

### 11.3 Platform and tenancy

```
/* [G] */ CREATE TABLE tenants (
  name VARCHAR(160) NOT NULL, slug VARCHAR(60) NOT NULL,
  status ENUM('trial','active','suspended','terminated') NOT NULL DEFAULT 'trial',
  plan_id BIGINT UNSIGNED NOT NULL, country_code CHAR(2) NOT NULL,
  timezone VARCHAR(60) NOT NULL, currency_code CHAR(3) NOT NULL, /* base currency */
  currency_locked_at TIMESTAMP NULL,
  data_region ENUM('mumbai','bahrain','frankfurt') NOT NULL DEFAULT 'mumbai',
  logo_attachment_id BIGINT UNSIGNED NULL,
  brand_primary_color CHAR(7) NULL, brand_secondary_color CHAR(7) NULL,
  custom_domain VARCHAR(190) NULL, trial_ends_at TIMESTAMP NULL,
  suspended_reason VARCHAR(255) NULL, feature_overrides_json JSON NULL,
  source ENUM('platform_admin','website') NOT NULL DEFAULT 'platform_admin', /* v3.11 */
  checkout_reference VARCHAR(120) NULL, /* v3.11, payment provider reference */
  UNIQUE KEY uq_slug (slug), UNIQUE KEY uq_custom_domain (custom_domain)
);

/* [G] */ CREATE TABLE plans (
  code VARCHAR(30) NOT NULL, name VARCHAR(80) NOT NULL,
  max_users INT UNSIGNED NULL, max_srs_per_month INT UNSIGNED NULL,
  max_storage_gb INT UNSIGNED NULL, api_rate_limit_per_min INT UNSIGNED NOT NULL,
  features_json JSON NOT NULL, /* feature_key => true, false or numeric limit */
  gateway_price_ids_json JSON NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE KEY uq_code (code)
);

/* [T] */ CREATE TABLE gateway_subscriptions (
  gateway ENUM('stripe','tap','razorpay') NOT NULL,
  gateway_customer_id VARCHAR(120) NOT NULL, gateway_subscription_id VARCHAR(120) NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('trialing','active','past_due','cancelled') NOT NULL,
  billing_cycle ENUM('monthly','annual') NOT NULL,
  current_period_end TIMESTAMP NULL, cancel_at_period_end BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE KEY uq_gateway_sub (gateway, gateway_subscription_id)
);

/* [G] */ CREATE TABLE platform_users (
  name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, password VARCHAR(255) NOT NULL,
  role ENUM('owner','support','finance') NOT NULL, two_factor_secret TEXT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE, last_login_at TIMESTAMP NULL,
  failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0, locked_until TIMESTAMP NULL, /* v3.12 */
  UNIQUE KEY uq_email (email)
);

/* [G] */ CREATE TABLE impersonation_sessions (
  platform_user_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(255) NOT NULL,
  started_at TIMESTAMP NOT NULL, expires_at TIMESTAMP NOT NULL, ended_at TIMESTAMP NULL,
  ip_address VARCHAR(45) NULL,
  end_reason ENUM('ended','expired','signed_out') NULL, /* v3.17 */
  actions_count INT UNSIGNED NOT NULL DEFAULT 0
);

/* [T] */ CREATE TABLE tenant_settings (
  setting_key VARCHAR(80) NOT NULL, value_json JSON NOT NULL,
  UNIQUE KEY uq_tenant_setting (tenant_id, setting_key)
); /* keys: reopen_window_days, feedback_link_days, magic_link_hours, smart_assign_weights ... */

/* [T] */ CREATE TABLE tenant_sequences (
  sequence_key ENUM('sr','quote','invoice','contract','payment') NOT NULL,
  prefix VARCHAR(20) NOT NULL, pattern VARCHAR(60) NOT NULL DEFAULT '{PREFIX}-{YYYY}-{SEQ:6}',
  next_value BIGINT UNSIGNED NOT NULL DEFAULT 1,
  reset_policy ENUM('never','yearly') NOT NULL DEFAULT 'yearly', last_reset_year SMALLINT NULL,
  UNIQUE KEY uq_tenant_seq (tenant_id, sequence_key)
);

/* [T] */ CREATE TABLE tenant_vocabulary (
  term_key VARCHAR(40) NOT NULL, singular_label VARCHAR(60) NOT NULL,
  plural_label VARCHAR(60) NOT NULL,
  UNIQUE KEY uq_tenant_term (tenant_id, term_key)
);

/* [T] one row per company per month, feeds the 80 and 100 percent warnings */
CREATE TABLE tenant_usage_counters (
  period_month CHAR(7) NOT NULL, /* YYYY-MM */
  user_count INT UNSIGNED NOT NULL DEFAULT 0, sr_count INT UNSIGNED NOT NULL DEFAULT 0,
  storage_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  whatsapp_message_count INT UNSIGNED NOT NULL DEFAULT 0,
  email_message_count INT UNSIGNED NOT NULL DEFAULT 0, warnings_json JSON NULL, /* v3.17 */
  warned_80_at TIMESTAMP NULL, prompted_100_at TIMESTAMP NULL, /* unused since v3.17 */
  UNIQUE KEY uq_tenant_period (tenant_id, period_month)
);
```

### 11.4 Users and access

```
/* [T] */ CREATE TABLE users (
  user_type ENUM('staff','technician','client') NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL, phone VARCHAR(40) NULL, password VARCHAR(255) NULL,
  employee_code VARCHAR(40) NULL, client_id BIGINT UNSIGNED NULL,
  vendor_id BIGINT UNSIGNED NULL, team_lead_user_id BIGINT UNSIGNED NULL,
  home_base_lat DECIMAL(10,7) NULL, home_base_lng DECIMAL(10,7) NULL,
  daily_capacity_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 480,
  whatsapp_opt_in_at TIMESTAMP NULL, whatsapp_opt_out_at TIMESTAMP NULL, email_bounced_at TIMESTAMP NULL, email_verified_at TIMESTAMP NULL,
  phone_verified_at TIMESTAMP NULL, two_factor_secret TEXT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE, last_login_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  aiywah_account_id BIGINT UNSIGNED NULL, /* v3.11, reserved for one login across products, unused in Release A */
  client_access ENUM('admin','viewer') NULL, site_scope_json JSON NULL, /* v3.12, client users */
  must_change_password BOOLEAN NOT NULL DEFAULT FALSE, /* v3.12 */
  failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0, locked_until TIMESTAMP NULL, /* v3.12 */
  two_factor_confirmed_at TIMESTAMP NULL, /* v3.12 */
  UNIQUE KEY uq_tenant_email (tenant_id, email), UNIQUE KEY uq_tenant_phone (tenant_id, phone),
  INDEX idx_tenant_role_active (tenant_id, role_id, is_active)
);

/* [T] */ CREATE TABLE roles (
  code VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL,
  base_role ENUM('company_admin','ops_manager','team_lead','field_technician',
                 'front_desk','finance','client_user') NOT NULL,
  is_system BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE KEY uq_tenant_role (tenant_id, code)
);

/* [G] */ CREATE TABLE permissions (
  permission_key VARCHAR(60) NOT NULL, group_name VARCHAR(40) NOT NULL,
  description VARCHAR(255) NULL, UNIQUE KEY uq_key (permission_key)
);

/* [T] */ CREATE TABLE role_permissions (
  role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL,
  access_level ENUM('full','none','record_level','grantable','view_only',
                    'view_own','edit_own') NOT NULL,
  UNIQUE KEY uq_role_perm (role_id, permission_id)
);

/* [T] */ CREATE TABLE user_permission_overrides (
  user_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL,
  access_level ENUM('full','none','record_level','grantable','view_only',
                    'view_own','edit_own') NOT NULL,
  granted_by_user_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(255) NULL,
  expires_at TIMESTAMP NULL, UNIQUE KEY uq_user_perm (user_id, permission_id)
);

/* [T] */ CREATE TABLE user_skills (
  user_id BIGINT UNSIGNED NOT NULL, service_category_id BIGINT UNSIGNED NOT NULL,
  proficiency TINYINT UNSIGNED NOT NULL DEFAULT 2, /* 1 basic, 2 skilled, 3 expert */
  UNIQUE KEY uq_user_skill (user_id, service_category_id)
);

/* [T] */ CREATE TABLE user_time_off (
  user_id BIGINT UNSIGNED NOT NULL, starts_at TIMESTAMP NOT NULL, ends_at TIMESTAMP NOT NULL,
  type ENUM('leave','training','other') NOT NULL DEFAULT 'leave', note VARCHAR(255) NULL,
  INDEX idx_tenant_user_time (tenant_id, user_id, starts_at)
);

/* [T] */ CREATE TABLE user_devices (
  user_id BIGINT UNSIGNED NOT NULL, device_label VARCHAR(120) NULL,
  user_agent VARCHAR(255) NULL, session_token_hash CHAR(64) NOT NULL,
  push_subscription_json JSON NULL, trusted_until TIMESTAMP NOT NULL,
  last_seen_at TIMESTAMP NULL, revoked_at TIMESTAMP NULL,
  INDEX idx_tenant_user (tenant_id, user_id)
);

/* [T] append-only, 30 day retention */ CREATE TABLE user_locations (
  user_id BIGINT UNSIGNED NOT NULL, lat DECIMAL(10,7) NOT NULL, lng DECIMAL(10,7) NOT NULL,
  accuracy_meters INT UNSIGNED NULL,
  source ENUM('punch','field_view_open','manual_checkin') NOT NULL,
  recorded_at TIMESTAMP NOT NULL,
  INDEX idx_tenant_user_time (tenant_id, user_id, recorded_at)
);

/* [T] */ CREATE TABLE secure_links (
  purpose ENUM('technician_job','client_quote','client_invoice','client_feedback','client_tracking',
               'daily_summary','user_invite','password_reset','client_sign_in','email_change') NOT NULL,
  token_hash CHAR(64) NOT NULL, user_id BIGINT UNSIGNED NULL,
  client_contact_id BIGINT UNSIGNED NULL, entity_type VARCHAR(60) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL, expires_at TIMESTAMP NOT NULL,
  max_uses INT UNSIGNED NULL, used_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_used_at TIMESTAMP NULL, revoked_at TIMESTAMP NULL,
  UNIQUE KEY uq_token (token_hash)
);

/* [T] one row per sign-in code, stored hashed, valid for 10 minutes (v3.12) */
CREATE TABLE auth_codes (
  user_id BIGINT UNSIGNED NOT NULL, channel ENUM('whatsapp','email') NOT NULL,
  code_hash CHAR(64) NOT NULL, expires_at TIMESTAMP NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, verified_at TIMESTAMP NULL,
  INDEX idx_tenant_user (tenant_id, user_id, created_at)
);
```

### 11.5 Master data

```
/* [T] */ CREATE TABLE clients (
  client_code VARCHAR(40) NOT NULL, name VARCHAR(180) NOT NULL,
  contact_person VARCHAR(150) NULL, email VARCHAR(190) NULL, phone VARCHAR(40) NULL,
  billing_address TEXT NULL, tax_id VARCHAR(60) NULL,
  payment_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  asset_visibility_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE, notes TEXT NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_tenant_client_code (tenant_id, client_code)
);

/* [T] */ CREATE TABLE client_contacts (
  client_id BIGINT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL, phone VARCHAR(40) NULL, whatsapp_number VARCHAR(40) NULL,
  whatsapp_opt_in_at TIMESTAMP NULL, whatsapp_opt_out_at TIMESTAMP NULL, email_bounced_at TIMESTAMP NULL,
  receives_events_json JSON NULL, site_scope_json JSON NULL, /* null = all sites */
  is_primary BOOLEAN NOT NULL DEFAULT FALSE, is_active BOOLEAN NOT NULL DEFAULT TRUE, /* v3.13 */
  INDEX idx_tenant_client (tenant_id, client_id)
);

/* [T] client_id is NULL for tenant-owned workshops */
CREATE TABLE project_sites (
  client_id BIGINT UNSIGNED NULL, site_code VARCHAR(40) NOT NULL, name VARCHAR(180) NOT NULL,
  site_type ENUM('client_site','tenant_workshop') NOT NULL DEFAULT 'client_site',
  address TEXT NULL, city VARCHAR(100) NULL,
  lat DECIMAL(10,7) NULL, lng DECIMAL(10,7) NULL,
  geofence_radius_meters INT UNSIGNED NOT NULL DEFAULT 250,
  always_enforce_geofence BOOLEAN NOT NULL DEFAULT FALSE,
  contact_name VARCHAR(150) NULL, contact_phone VARCHAR(40) NULL,
  access_instructions TEXT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE,
  deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_tenant_site_code (tenant_id, site_code),
  INDEX idx_tenant_client (tenant_id, client_id)
);

/* [T] */ CREATE TABLE vendors (
  name VARCHAR(180) NOT NULL, contact_name VARCHAR(150) NULL, email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL, tax_id VARCHAR(60) NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active', notes TEXT NULL
);

/* [T] amended v3.1 */ CREATE TABLE service_categories (
  name VARCHAR(120) NOT NULL, code VARCHAR(40) NOT NULL, service_domain VARCHAR(80) NULL,
  active_form_template_id BIGINT UNSIGNED NULL, custom_workflow_json JSON NULL,
  approval_mode ENUM('manual','auto_if_in_contract','always_auto','finance_and_manager')
    NOT NULL DEFAULT 'manual',
  require_before_photo BOOLEAN NOT NULL DEFAULT TRUE,
  require_after_photo BOOLEAN NOT NULL DEFAULT TRUE,
  require_signature BOOLEAN NOT NULL DEFAULT TRUE,
  client_selectable BOOLEAN NOT NULL DEFAULT TRUE,
  require_checklist BOOLEAN NOT NULL DEFAULT FALSE,
  invoice_mandatory BOOLEAN NOT NULL DEFAULT FALSE, /* v3.3 */
  allow_multi_asset_batch BOOLEAN NOT NULL DEFAULT TRUE,
  require_per_asset_checklist BOOLEAN NOT NULL DEFAULT FALSE,
  allow_depot_mode BOOLEAN NOT NULL DEFAULT FALSE, /* v3.2 */
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE KEY uq_tenant_category_code (tenant_id, code),
  INDEX idx_tenant_active (tenant_id, is_active)
);

/* [T] category NULL means tenant default. Uniqueness is enforced in the application */
CREATE TABLE sla_policies (
  service_category_id BIGINT UNSIGNED NULL,
  priority ENUM('low','medium','high','critical','emergency') NOT NULL,
  response_minutes INT UNSIGNED NOT NULL, resolution_minutes INT UNSIGNED NOT NULL,
  uses_business_hours BOOLEAN NOT NULL DEFAULT FALSE,
  INDEX idx_tenant_cat_priority (tenant_id, service_category_id, priority)
);

/* [T] */ CREATE TABLE tenant_business_hours (
  weekday TINYINT UNSIGNED NOT NULL, opens_at TIME NULL, closes_at TIME NULL,
  is_open BOOLEAN NOT NULL DEFAULT TRUE, UNIQUE KEY uq_tenant_weekday (tenant_id, weekday)
);

/* [T] */ CREATE TABLE tenant_holidays (
  holiday_date DATE NOT NULL, name VARCHAR(120) NOT NULL,
  UNIQUE KEY uq_tenant_date (tenant_id, holiday_date)
);

/* [T] */ CREATE TABLE expense_categories (
  name VARCHAR(100) NOT NULL, default_unit_rate DECIMAL(12,3) NULL,
  is_billable_default BOOLEAN NOT NULL DEFAULT TRUE,
  requires_receipt BOOLEAN NOT NULL DEFAULT FALSE, is_active BOOLEAN NOT NULL DEFAULT TRUE
);

/* [T] */ CREATE TABLE hold_reasons (
  label VARCHAR(120) NOT NULL, pauses_sla BOOLEAN NOT NULL DEFAULT FALSE,
  requires_note BOOLEAN NOT NULL DEFAULT FALSE, is_active BOOLEAN NOT NULL DEFAULT TRUE
);

/* [T] */ CREATE TABLE tax_rates (
  name VARCHAR(80) NOT NULL, rate_percent DECIMAL(5,2) NOT NULL,
  is_default BOOLEAN NOT NULL DEFAULT FALSE, is_active BOOLEAN NOT NULL DEFAULT TRUE
);
```

### 11.6 Assets, contracts and preventive maintenance

```
/* [T] amended v3.1 */ CREATE TABLE assets (
  client_id BIGINT UNSIGNED NOT NULL, project_site_id BIGINT UNSIGNED NOT NULL,
  parent_asset_id BIGINT UNSIGNED NULL, service_category_id BIGINT UNSIGNED NOT NULL,
  asset_code VARCHAR(80) NOT NULL, qr_hash VARCHAR(64) NOT NULL, /* random, not guessable */
  name VARCHAR(180) NOT NULL, manufacturer VARCHAR(120) NULL, model VARCHAR(120) NULL,
  serial_number VARCHAR(120) NULL, location_within_site VARCHAR(255) NULL,
  installation_date DATE NULL, warranty_expiry_date DATE NULL,
  default_contract_id BIGINT UNSIGNED NULL,
  status ENUM('active','under_repair','inactive','decommissioned') NOT NULL DEFAULT 'active',
  tracks_meter BOOLEAN NOT NULL DEFAULT FALSE, meter_unit VARCHAR(30) NULL,
  current_meter_reading DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0.00,
  last_meter_recorded_at TIMESTAMP NULL, photo_attachment_id BIGINT UNSIGNED NULL,
  specifications_json JSON NULL, notes TEXT NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_tenant_asset_code (tenant_id, asset_code),
  UNIQUE KEY uq_tenant_qr_hash (tenant_id, qr_hash),
  INDEX idx_tenant_site_category (tenant_id, project_site_id, service_category_id),
  INDEX idx_tenant_serial (tenant_id, serial_number)
);

/* [T] */ CREATE TABLE contracts (
  contract_number VARCHAR(60) NOT NULL, client_id BIGINT UNSIGNED NOT NULL,
  contract_type ENUM('amc','warranty','adhoc','dlp') NOT NULL,
  status ENUM('draft','active','expiring_soon','expired','renewed','cancelled')
    NOT NULL DEFAULT 'draft',
  start_date DATE NOT NULL, end_date DATE NOT NULL,
  response_sla_minutes INT UNSIGNED NULL, resolution_sla_minutes INT UNSIGNED NULL,
  max_calls INT UNSIGNED NULL,
  call_period ENUM('contract_term','monthly','quarterly','annual')
    NOT NULL DEFAULT 'contract_term',
  calls_used_current_period INT UNSIGNED NOT NULL DEFAULT 0, period_started_on DATE NULL,
  annual_value DECIMAL(14,3) NULL, payment_terms VARCHAR(120) NULL,
  auto_renew BOOLEAN NOT NULL DEFAULT FALSE, renewed_from_contract_id BIGINT UNSIGNED NULL,
  notes TEXT NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_tenant_contract_number (tenant_id, contract_number),
  INDEX idx_tenant_client_status (tenant_id, client_id, status, end_date)
);

/* [T] */ CREATE TABLE contract_sites (
  contract_id BIGINT UNSIGNED NOT NULL, project_site_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_contract_site (contract_id, project_site_id)
);

/* [T] */ CREATE TABLE contract_categories (
  contract_id BIGINT UNSIGNED NOT NULL, service_category_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_contract_category (contract_id, service_category_id)
);

/* [T] */ CREATE TABLE pm_schedules (
  name VARCHAR(150) NOT NULL, project_site_id BIGINT UNSIGNED NOT NULL,
  service_category_id BIGINT UNSIGNED NOT NULL, contract_id BIGINT UNSIGNED NULL,
  trigger_type ENUM('calendar','meter') NOT NULL DEFAULT 'calendar',
  frequency ENUM('daily','weekly','biweekly','monthly','quarterly','biannual','annual',
                 'custom_days') NULL,
  interval_days SMALLINT UNSIGNED NULL, meter_interval DECIMAL(12,2) NULL,
  next_due_date DATE NULL, next_due_meter DECIMAL(12,2) NULL,
  lead_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  default_priority ENUM('low','medium','high','critical','emergency') NOT NULL DEFAULT 'medium',
  form_template_id BIGINT UNSIGNED NULL, default_tech_user_id BIGINT UNSIGNED NULL,
  default_description TEXT NULL, skip_if_open_sr BOOLEAN NOT NULL DEFAULT TRUE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  last_generated_at TIMESTAMP NULL, last_sr_id BIGINT UNSIGNED NULL,
  INDEX idx_tenant_due (tenant_id, is_active, next_due_date)
);

/* [T] */ CREATE TABLE pm_schedule_assets (
  pm_schedule_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_pm_asset (pm_schedule_id, asset_id)
);
```

### 11.7 Forms and workflow

```
/* [T] */ CREATE TABLE form_templates (
  name VARCHAR(150) NOT NULL, service_category_id BIGINT UNSIGNED NULL,
  current_version_id BIGINT UNSIGNED NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE
);

/* [T] a published version is immutable */ CREATE TABLE form_template_versions (
  form_template_id BIGINT UNSIGNED NOT NULL, version_no INT UNSIGNED NOT NULL,
  status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
  schema_json JSON NOT NULL, /* fields: key, label, type, required, options, min, max, show_if */
  published_at TIMESTAMP NULL, published_by_user_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_template_version (form_template_id, version_no)
);

/* [T] */ CREATE TABLE approval_rules (
  service_category_id BIGINT UNSIGNED NULL,
  priority ENUM('low','medium','high','critical','emergency') NULL,
  coverage_type ENUM('in_contract','in_warranty','out_of_coverage','goodwill') NULL,
  approval_mode ENUM('manual','auto_if_in_contract','always_auto','finance_and_manager') NOT NULL,
  required_roles_json JSON NULL, sort_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE
);

/* [T] */ CREATE TABLE workflow_rules (
  name VARCHAR(150) NOT NULL, trigger_event VARCHAR(60) NOT NULL,
  conditions_json JSON NOT NULL, actions_json JSON NOT NULL,
  run_order INT NOT NULL DEFAULT 0, max_runs_per_sr TINYINT UNSIGNED NOT NULL DEFAULT 3,
  is_active BOOLEAN NOT NULL DEFAULT TRUE, last_run_at TIMESTAMP NULL
);

/* [T] append-only */ CREATE TABLE workflow_rule_runs (
  workflow_rule_id BIGINT UNSIGNED NOT NULL, service_request_id BIGINT UNSIGNED NULL,
  outcome ENUM('applied','skipped','failed') NOT NULL, detail_json JSON NULL,
  INDEX idx_tenant_rule_sr (tenant_id, workflow_rule_id, service_request_id)
);
```

### 11.8 Service requests and field records

```
/* [T] amended v3.1 */ CREATE TABLE service_requests (
  sr_number VARCHAR(50) NOT NULL, parent_sr_id BIGINT UNSIGNED NULL,
  is_master_sr BOOLEAN NOT NULL DEFAULT FALSE,
  client_id BIGINT UNSIGNED NOT NULL, project_site_id BIGINT UNSIGNED NOT NULL,
  service_category_id BIGINT UNSIGNED NOT NULL, primary_asset_id BIGINT UNSIGNED NULL,
  contract_id BIGINT UNSIGNED NULL, pm_schedule_id BIGINT UNSIGNED NULL,
  vendor_id BIGINT UNSIGNED NULL, /* v3.2 */
  service_mode ENUM('on_site','depot') NOT NULL DEFAULT 'on_site', /* v3.2 */
  source ENUM('front_desk','client_portal','pm_auto','qr_scan','api') NOT NULL DEFAULT 'front_desk',
  coverage_type ENUM('in_contract','in_warranty','out_of_coverage','goodwill')
    NOT NULL DEFAULT 'out_of_coverage',
  coverage_override_by_user_id BIGINT UNSIGNED NULL, coverage_override_reason VARCHAR(255) NULL,
  contract_choice_pending BOOLEAN NOT NULL DEFAULT FALSE, /* v3.3 */
  quote_requested_at TIMESTAMP NULL, /* v3.8, set by Send for quote */
  follow_up_of_sr_id BIGINT UNSIGNED NULL, /* v3.8, the request this one follows */
  invoice_mandatory BOOLEAN NOT NULL DEFAULT FALSE, /* v3.3, frozen from the category, applies only when out of coverage */
  enforce_geofence BOOLEAN NOT NULL DEFAULT FALSE, /* v3.3, set when assigning */
  allow_signature_waiver BOOLEAN NOT NULL DEFAULT FALSE, /* v3.3, set when assigning */
  priority ENUM('low','medium','high','critical','emergency') NOT NULL DEFAULT 'medium',
  status ENUM('pending','quoted','approved','assigned','in_progress','pending_review',
              'awaiting_invoice','rework','completed','cancelled')
    NOT NULL DEFAULT 'pending', /* v3.3 */
  custom_stage_code VARCHAR(40) NULL, /* Release C */
  is_on_hold BOOLEAN NOT NULL DEFAULT FALSE, current_hold_reason_id BIGINT UNSIGNED NULL,
  billing_status ENUM('not_applicable','pending_invoice','invoiced','partially_paid',
                      'paid','written_off') NOT NULL DEFAULT 'not_applicable',
  title VARCHAR(255) NOT NULL, description TEXT NULL, client_po_reference VARCHAR(100) NULL,
  created_by_user_id BIGINT UNSIGNED NULL, approved_by_user_id BIGINT UNSIGNED NULL,
  team_lead_user_id BIGINT UNSIGNED NULL, assigned_tech_user_id BIGINT UNSIGNED NULL,
  daily_route_sequence SMALLINT UNSIGNED NULL,
  form_template_version_id BIGINT UNSIGNED NULL, /* v3.2 */
  sla_clock_started_at TIMESTAMP NULL, sla_paused_at TIMESTAMP NULL,
  sla_paused_minutes INT UNSIGNED NOT NULL DEFAULT 0, /* v3.2 */
  sla_response_due_at TIMESTAMP NULL, sla_resolution_due_at TIMESTAMP NULL,
  sla_response_warned_at TIMESTAMP NULL, sla_resolution_warned_at TIMESTAMP NULL, /* v3.8 */
  sla_response_breached BOOLEAN NOT NULL DEFAULT FALSE,
  sla_resolution_breached BOOLEAN NOT NULL DEFAULT FALSE,
  scheduled_eta_at TIMESTAMP NULL, tech_accepted_at TIMESTAMP NULL,
  first_punch_in_at TIMESTAMP NULL, last_punch_out_at TIMESTAMP NULL,
  total_work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  total_hold_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  rework_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  reopened_count TINYINT UNSIGNED NOT NULL DEFAULT 0, /* v3.2 */
  proof_rules_snapshot JSON NOT NULL, sr_checklist_payload JSON NULL,
  customer_signer_name VARCHAR(150) NULL, customer_signer_phone VARCHAR(40) NULL,
  customer_signature_attachment_id BIGINT UNSIGNED NULL,
  signature_waiver_otp_id BIGINT UNSIGNED NULL, /* v3.3, verified code that replaced the signature */
  signature_gps_lat DECIMAL(10,7) NULL, signature_gps_lng DECIMAL(10,7) NULL,
  signed_at TIMESTAMP NULL,
  qc_reviewed_by_user_id BIGINT UNSIGNED NULL, qc_reviewed_at TIMESTAMP NULL,
  qc_rejection_reason TEXT NULL, cancellation_reason TEXT NULL, completed_at TIMESTAMP NULL,
  client_star_rating TINYINT UNSIGNED NULL, client_nps_score TINYINT UNSIGNED NULL,
  client_feedback_comment TEXT NULL, client_feedback_at TIMESTAMP NULL,
  client_feedback_source ENUM('link','portal') NULL, client_feedback_by VARCHAR(150) NULL,
  deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_tenant_sr_number (tenant_id, sr_number),
  INDEX idx_tenant_status_priority (tenant_id, status, priority),
  INDEX idx_tenant_tech_schedule (tenant_id, assigned_tech_user_id, status, scheduled_eta_at),
  INDEX idx_tenant_client_site (tenant_id, client_id, project_site_id),
  INDEX idx_tenant_parent_sr (tenant_id, parent_sr_id),
  INDEX idx_tenant_sla (tenant_id, status, sla_resolution_due_at)
);

/* [T] before and after photo columns hold attachment IDs */
CREATE TABLE service_request_assets (
  service_request_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL,
  inspection_status ENUM('pending','serviced_ok','repaired','needs_parts','failed_unsafe',
                         'skipped') NOT NULL DEFAULT 'pending',
  qr_scanned_verify BOOLEAN NOT NULL DEFAULT FALSE, scanned_at TIMESTAMP NULL,
  form_template_version_id BIGINT UNSIGNED NULL, asset_checklist_payload JSON NULL,
  recorded_meter_reading DECIMAL(12,2) UNSIGNED NULL,
  before_photo_paths JSON NULL, after_photo_paths JSON NULL,
  technician_remarks TEXT NULL, completed_at TIMESTAMP NULL,
  UNIQUE KEY uq_sr_asset (tenant_id, service_request_id, asset_id),
  INDEX idx_tenant_asset_history (tenant_id, asset_id, completed_at)
);

/* [T] amended v3.1 */ CREATE TABLE service_request_punches (
  service_request_id BIGINT UNSIGNED NOT NULL, technician_user_id BIGINT UNSIGNED NOT NULL,
  client_event_uuid CHAR(36) NOT NULL, /* idempotency key */
  punch_type ENUM('punch_in','hold_start','hold_resume','punch_out') NOT NULL,
  hold_reason_id BIGINT UNSIGNED NULL,
  recorded_at_device TIMESTAMP NOT NULL,
  synced_to_server_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_offline_sync BOOLEAN NOT NULL DEFAULT FALSE, clock_drift_seconds INT NULL,
  gps_lat DECIMAL(10,7) NULL, gps_lng DECIMAL(10,7) NULL,
  gps_accuracy_meters INT UNSIGNED NULL,
  gps_status ENUM('ok','low_accuracy','denied','unavailable') NOT NULL DEFAULT 'ok',
  site_distance_meters INT UNSIGNED NULL, is_within_geofence BOOLEAN NULL,
  geofence_override_reason VARCHAR(255) NULL, geofence_override_by_user_id BIGINT UNSIGNED NULL,
  sync_status ENUM('synced','conflict_rejected') NOT NULL DEFAULT 'synced',
  conflict_note VARCHAR(255) NULL, device_info VARCHAR(255) NULL,
  UNIQUE KEY uq_tenant_event (tenant_id, client_event_uuid),
  INDEX idx_tenant_sr_punches (tenant_id, service_request_id, recorded_at_device)
);

/* [T] amended v3.1 */ CREATE TABLE service_request_expenses (
  service_request_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NULL,
  logged_by_user_id BIGINT UNSIGNED NOT NULL, client_event_uuid CHAR(36) NOT NULL,
  vendor_id BIGINT UNSIGNED NULL, /* v3.5, set when a vendor technician logged it */
  expense_category_id BIGINT UNSIGNED NOT NULL, description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00, unit_rate DECIMAL(12,3) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,3) NOT NULL DEFAULT 0.00,
  is_billable_to_client BOOLEAN NOT NULL DEFAULT TRUE, receipt_attachment_id BIGINT UNSIGNED NULL,
  approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  approved_by_user_id BIGINT UNSIGNED NULL, approved_at TIMESTAMP NULL,
  rejection_reason VARCHAR(255) NULL,
  UNIQUE KEY uq_tenant_event (tenant_id, client_event_uuid),
  INDEX idx_tenant_sr_expenses (tenant_id, service_request_id, approval_status)
);

/* [T] append-only */ CREATE TABLE sr_status_history (
  service_request_id BIGINT UNSIGNED NOT NULL, from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NOT NULL, custom_stage_code VARCHAR(40) NULL,
  actor_type ENUM('user','client','system') NOT NULL, actor_id BIGINT UNSIGNED NULL,
  reason TEXT NULL, meta_json JSON NULL,
  INDEX idx_tenant_sr_time (tenant_id, service_request_id, created_at)
);

/* [T] */ CREATE TABLE sr_notes (
  service_request_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL,
  visibility ENUM('internal','client_visible') NOT NULL DEFAULT 'internal',
  source ENUM('user','whatsapp_inbound','system') NOT NULL DEFAULT 'user', body TEXT NOT NULL,
  INDEX idx_tenant_sr (tenant_id, service_request_id)
);

/* [T] */ CREATE TABLE sr_reschedules (
  service_request_id BIGINT UNSIGNED NOT NULL, previous_eta_at TIMESTAMP NULL,
  new_eta_at TIMESTAMP NOT NULL, reason VARCHAR(255) NULL,
  source ENUM('drag_drop','technician_request','system') NOT NULL,
  rescheduled_by_user_id BIGINT UNSIGNED NULL
);

/* [T] canonical store for every uploaded file */ CREATE TABLE attachments (
  attachable_type VARCHAR(60) NOT NULL, attachable_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('sr_attachment','before_photo','after_photo','signature','receipt','document',
            'logo','asset_photo','report','quote_pdf','invoice_pdf') NOT NULL,
  storage_path VARCHAR(500) NOT NULL, original_name VARCHAR(255) NULL,
  mime_type VARCHAR(100) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, sha256 CHAR(64) NULL,
  captured_at TIMESTAMP NULL, gps_lat DECIMAL(10,7) NULL, gps_lng DECIMAL(10,7) NULL,
  uploaded_by_user_id BIGINT UNSIGNED NULL, client_event_uuid CHAR(36) NULL,
  UNIQUE KEY uq_tenant_event (tenant_id, client_event_uuid),
  INDEX idx_tenant_owner (tenant_id, attachable_type, attachable_id, kind)
);

/* [T] one row per code sent to replace a customer signature */
CREATE TABLE signature_waiver_otps (
  service_request_id BIGINT UNSIGNED NOT NULL, client_contact_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL, channel ENUM('whatsapp','email') NOT NULL,
  code_hash CHAR(64) NOT NULL, expires_at TIMESTAMP NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, verified_at TIMESTAMP NULL,
  INDEX idx_tenant_sr (tenant_id, service_request_id)
);

/* [T] v3.14, a geofence override request waiting for a manager */
CREATE TABLE geofence_override_requests (
  service_request_id BIGINT UNSIGNED NOT NULL, technician_user_id BIGINT UNSIGNED NOT NULL,
  client_event_uuid CHAR(36) NOT NULL, reason VARCHAR(255) NOT NULL,
  photo_attachment_id BIGINT UNSIGNED NULL, requested_at_device TIMESTAMP NOT NULL,
  gps_lat DECIMAL(10,7) NULL, gps_lng DECIMAL(10,7) NULL, gps_accuracy_meters INT UNSIGNED NULL,
  site_distance_meters INT UNSIGNED NULL,
  status ENUM('pending','approved','rejected','expired','used','cancelled') NOT NULL DEFAULT 'pending',
  decided_by_user_id BIGINT UNSIGNED NULL, decided_at TIMESTAMP NULL, decision_note VARCHAR(255) NULL,
  valid_until TIMESTAMP NULL, used_punch_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_tenant_event (tenant_id, client_event_uuid),
  INDEX idx_tenant_sr (tenant_id, service_request_id, status)
);

/* [T] v3.14, every field event and its result, kept as evidence */
CREATE TABLE field_event_log (
  user_id BIGINT UNSIGNED NOT NULL, device_id BIGINT UNSIGNED NULL,
  client_event_uuid CHAR(36) NOT NULL, event_type VARCHAR(40) NOT NULL,
  service_request_id BIGINT UNSIGNED NULL,
  recorded_at_device TIMESTAMP NOT NULL, synced_to_server_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  clock_drift_seconds INT NULL,
  result ENUM('applied','duplicate','conflict_rejected','invalid') NOT NULL,
  conflict_code VARCHAR(40) NULL, conflict_note VARCHAR(255) NULL,
  payload_hash CHAR(64) NOT NULL, payload_json JSON NOT NULL,
  acknowledged_at TIMESTAMP NULL, reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL, review_note VARCHAR(255) NULL,
  UNIQUE KEY uq_tenant_event (tenant_id, client_event_uuid),
  INDEX idx_tenant_sr_result (tenant_id, service_request_id, result)
);
```

### 11.9 Billing

```
/* [T] */ CREATE TABLE quotes (
  quote_number VARCHAR(50) NOT NULL, service_request_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL, source ENUM('builder','uploaded_pdf') NOT NULL,
  status ENUM('draft','sent','accepted','rejected','expired','superseded') NOT NULL DEFAULT 'draft',
  version_no INT UNSIGNED NOT NULL DEFAULT 1, supersedes_quote_id BIGINT UNSIGNED NULL,
  currency_code CHAR(3) NOT NULL, exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 1, /* to base */
  rate_source ENUM('provider','manual') NOT NULL DEFAULT 'provider',
  subtotal DECIMAL(14,3) NOT NULL DEFAULT 0, tax_total DECIMAL(14,3) NOT NULL DEFAULT 0,
  discount_total DECIMAL(14,3) NOT NULL DEFAULT 0, total DECIMAL(14,3) NOT NULL DEFAULT 0,
  base_total DECIMAL(14,3) NOT NULL DEFAULT 0,
  valid_until DATE NULL, terms TEXT NULL, pdf_attachment_id BIGINT UNSIGNED NULL,
  discount_type ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
  discount_value DECIMAL(12,3) NOT NULL DEFAULT 0, expired_at TIMESTAMP NULL,
  sent_at TIMESTAMP NULL, responded_at TIMESTAMP NULL,
  responded_by_name VARCHAR(150) NULL, responded_ip VARCHAR(45) NULL,
  rejection_reason TEXT NULL, created_by_user_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_tenant_quote_number (tenant_id, quote_number),
  INDEX idx_tenant_sr (tenant_id, service_request_id)
);

/* [T] */ CREATE TABLE quote_items (
  quote_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL, quantity DECIMAL(10,2) NOT NULL, unit VARCHAR(20) NULL,
  unit_rate DECIMAL(12,3) NOT NULL, tax_rate_id BIGINT UNSIGNED NULL,
  line_total DECIMAL(14,3) NOT NULL, source_expense_id BIGINT UNSIGNED NULL
);

/* [T] */ CREATE TABLE invoices (
  invoice_number VARCHAR(50) NOT NULL, service_request_id BIGINT UNSIGNED NULL,
  client_id BIGINT UNSIGNED NOT NULL, quote_id BIGINT UNSIGNED NULL,
  source ENUM('builder','uploaded_pdf') NOT NULL,
  status ENUM('draft','pending_approval','approved','sent','partially_paid','paid',
              'overdue','written_off','void') NOT NULL DEFAULT 'draft',
  currency_code CHAR(3) NOT NULL, exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 1,
  rate_source ENUM('provider','manual') NOT NULL DEFAULT 'provider',
  issue_date DATE NULL, due_date DATE NULL,
  subtotal DECIMAL(14,3) NOT NULL DEFAULT 0, tax_total DECIMAL(14,3) NOT NULL DEFAULT 0,
  discount_total DECIMAL(14,3) NOT NULL DEFAULT 0, total DECIMAL(14,3) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(14,3) NOT NULL DEFAULT 0, balance_due DECIMAL(14,3) NOT NULL DEFAULT 0,
  base_total DECIMAL(14,3) NOT NULL DEFAULT 0,
  bank_details_snapshot_json JSON NULL, pdf_attachment_id BIGINT UNSIGNED NULL,
  approved_by_user_id BIGINT UNSIGNED NULL, approved_at TIMESTAMP NULL,
  sent_at TIMESTAMP NULL, void_reason VARCHAR(255) NULL,
  discount_type ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
  discount_value DECIMAL(12,3) NOT NULL DEFAULT 0, legal_invoice_number VARCHAR(60) NULL,
  notes VARCHAR(500) NULL, submitted_at TIMESTAMP NULL, submitted_by_user_id BIGINT UNSIGNED NULL,
  voided_at TIMESTAMP NULL, write_off_amount DECIMAL(14,3) NOT NULL DEFAULT 0,
  written_off_at TIMESTAMP NULL, write_off_reason VARCHAR(255) NULL,
  approval_rejection_reason VARCHAR(255) NULL, /* v3.4, set when a manager sends the invoice back */
  UNIQUE KEY uq_tenant_invoice_number (tenant_id, invoice_number),
  INDEX idx_tenant_status_due (tenant_id, status, due_date)
);

/* [T] */ CREATE TABLE invoice_items (
  invoice_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL, quantity DECIMAL(10,2) NOT NULL, unit VARCHAR(20) NULL,
  unit_rate DECIMAL(12,3) NOT NULL, tax_rate_id BIGINT UNSIGNED NULL,
  line_total DECIMAL(14,3) NOT NULL, source_expense_id BIGINT UNSIGNED NULL
);

/* [T] */ CREATE TABLE payments (
  invoice_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(14,3) NOT NULL, /* invoice currency */
  base_amount DECIMAL(14,3) NOT NULL, paid_on DATE NOT NULL,
  method ENUM('cash','bank_transfer','card','cheque','online','other') NOT NULL,
  reference VARCHAR(120) NULL, recorded_by_user_id BIGINT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL, reversed_at TIMESTAMP NULL,
  payment_number VARCHAR(50) NOT NULL, reversal_reason VARCHAR(255) NULL,
  reversed_by_user_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_tenant_payment_number (tenant_id, payment_number),
  INDEX idx_tenant_invoice (tenant_id, invoice_id)
);

/* [T] daily rates from the provider, with manual overrides */
CREATE TABLE exchange_rates (
  from_currency CHAR(3) NOT NULL, to_currency CHAR(3) NOT NULL,
  rate DECIMAL(18,8) NOT NULL, rate_date DATE NOT NULL,
  source ENUM('provider','manual') NOT NULL DEFAULT 'provider',
  UNIQUE KEY uq_tenant_pair_date (tenant_id, from_currency, to_currency, rate_date)
);
```

### 11.10 Communication

```
/* [T] */ CREATE TABLE notification_preferences (
  user_id BIGINT UNSIGNED NOT NULL, event_key VARCHAR(60) NOT NULL,
  channel ENUM('whatsapp','email','push','in_app') NOT NULL,
  frequency ENUM('instant','hourly_digest','daily_digest','off') NOT NULL DEFAULT 'instant',
  UNIQUE KEY uq_user_event_channel (user_id, event_key, channel)
);

/* [T] */ CREATE TABLE notifications (
  user_id BIGINT UNSIGNED NOT NULL, event_key VARCHAR(60) NOT NULL,
  title VARCHAR(190) NOT NULL, body VARCHAR(500) NULL,
  priority ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
  entity_type VARCHAR(60) NULL, entity_id BIGINT UNSIGNED NULL,
  action_url VARCHAR(500) NULL, read_at TIMESTAMP NULL,
  INDEX idx_tenant_user_read (tenant_id, user_id, read_at)
);

/* [T] */ CREATE TABLE message_logs (
  direction ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
  channel ENUM('whatsapp','email','push') NOT NULL,
  recipient_user_id BIGINT UNSIGNED NULL, recipient_contact_id BIGINT UNSIGNED NULL,
  recipient_address VARCHAR(190) NOT NULL, event_key VARCHAR(60) NULL,
  template_key VARCHAR(80) NULL, entity_type VARCHAR(60) NULL, entity_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL, provider_message_id VARCHAR(120) NULL,
  whatsapp_account_id BIGINT UNSIGNED NULL,
  status ENUM('queued','sent','delivered','read','failed','fallback_sent',
              'skipped_no_consent','skipped_pref','skipped_read','suppressed_duplicate','cancelled')
    NOT NULL DEFAULT 'queued',
  failure_code VARCHAR(40) NULL, failure_reason VARCHAR(255) NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, next_retry_at TIMESTAMP NULL,
  fallback_of_message_id BIGINT UNSIGNED NULL, cost_minor INT UNSIGNED NULL,
  dedupe_key VARCHAR(120) NULL, fallback_due_at TIMESTAMP NULL, notification_id BIGINT UNSIGNED NULL,
  is_system BOOLEAN NOT NULL DEFAULT FALSE,
  sent_at TIMESTAMP NULL, delivered_at TIMESTAMP NULL, read_at TIMESTAMP NULL,
  INDEX idx_tenant_entity (tenant_id, entity_type, entity_id),
  INDEX idx_provider_msg (provider_message_id),
  INDEX idx_retry (status, next_retry_at),
  INDEX idx_fallback (status, fallback_due_at)
);

/* [T] one account per company */
CREATE TABLE whatsapp_accounts (
  connection_method ENUM('manual','guided_signup') NOT NULL DEFAULT 'manual',
  waba_id VARCHAR(60) NULL, phone_number_id VARCHAR(60) NULL,
  display_phone_number VARCHAR(40) NULL, access_token_encrypted TEXT NULL,
  webhook_verify_token_encrypted TEXT NULL,
  business_verification_status ENUM('not_started','pending','verified','rejected')
    NOT NULL DEFAULT 'not_started',
  onboarding_status ENUM('not_started','business_verification_pending','number_registered',
    'templates_submitted','templates_partially_approved','live','suspended')
    NOT NULL DEFAULT 'not_started',
  quality_rating ENUM('green','yellow','red','unknown') NOT NULL DEFAULT 'unknown',
  messaging_tier VARCHAR(20) NULL, go_live_at TIMESTAMP NULL, last_health_check_at TIMESTAMP NULL,
  app_secret_encrypted TEXT NULL, webhook_path_token CHAR(32) NOT NULL, paused_at TIMESTAMP NULL,
  paused_reason ENUM('admin','plan') NULL, last_error VARCHAR(255) NULL
);

CREATE TABLE whatsapp_templates (
  whatsapp_account_id BIGINT UNSIGNED NOT NULL, template_key VARCHAR(80) NOT NULL,
  meta_template_name VARCHAR(120) NOT NULL, language VARCHAR(10) NOT NULL DEFAULT 'en',
  category ENUM('utility','authentication','marketing') NOT NULL DEFAULT 'utility',
  body_text TEXT NOT NULL, variables_json JSON NULL, buttons_json JSON NULL,
  approval_status ENUM('draft','submitted','approved','rejected','paused','disabled')
    NOT NULL DEFAULT 'draft',
  rejection_reason VARCHAR(255) NULL, is_mandatory BOOLEAN NOT NULL DEFAULT TRUE,
  submitted_at TIMESTAMP NULL, approved_at TIMESTAMP NULL,
  meta_template_id VARCHAR(60) NULL, version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  last_checked_at TIMESTAMP NULL,
  UNIQUE KEY uq_account_template (whatsapp_account_id, template_key, language)
);

/* [T] v3.17, one row an hour for each company with WhatsApp set up, read by the platform health board */
CREATE TABLE whatsapp_health_snapshots (
  taken_at TIMESTAMP NOT NULL, onboarding_status VARCHAR(40) NOT NULL,
  quality_rating ENUM('green','yellow','red','unknown') NOT NULL DEFAULT 'unknown',
  messaging_tier VARCHAR(20) NULL,
  sent_count INT UNSIGNED NOT NULL DEFAULT 0, delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0, fallback_count INT UNSIGNED NOT NULL DEFAULT 0,
  templates_rejected SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_tenant_time (tenant_id, taken_at)
);

/* [T] v3.15, the engine's inbox: one row per event until it is processed */
CREATE TABLE event_outbox (
  event_key VARCHAR(60) NOT NULL, entity_type VARCHAR(60) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL, payload_json JSON NOT NULL, dedupe_key VARCHAR(120) NOT NULL,
  status ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, processed_at TIMESTAMP NULL,
  UNIQUE KEY uq_tenant_dedupe (tenant_id, dedupe_key),
  INDEX idx_status (status, created_at)
);

/* platform table, not per company. v3.15 */
CREATE TABLE platform_whatsapp_templates (
  template_key VARCHAR(80) NOT NULL, version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  category ENUM('utility','authentication') NOT NULL DEFAULT 'utility',
  body_text TEXT NOT NULL, variables_json JSON NULL, buttons_json JSON NULL,
  is_mandatory BOOLEAN NOT NULL DEFAULT TRUE, is_active BOOLEAN NOT NULL DEFAULT TRUE,
  published_at TIMESTAMP NULL, published_by_platform_user_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_key_version (template_key, version)
);

/* [T] */ CREATE TABLE daily_summary_configs (
  base_role VARCHAR(40) NOT NULL, send_time TIME NOT NULL DEFAULT '08:00:00',
  days_of_week_json JSON NOT NULL, content_blocks_json JSON NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE
);
```

### 11.11 Integration and system

```
/* [T] */ CREATE TABLE webhook_endpoints (
  url VARCHAR(500) NOT NULL, secret_encrypted TEXT NOT NULL, events_json JSON NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0, disabled_at TIMESTAMP NULL
);

/* [T] */ CREATE TABLE webhook_deliveries (
  webhook_endpoint_id BIGINT UNSIGNED NOT NULL, event_key VARCHAR(60) NOT NULL,
  event_uuid CHAR(36) NOT NULL, payload_json JSON NOT NULL,
  status ENUM('pending','delivered','failed','abandoned') NOT NULL DEFAULT 'pending',
  response_code SMALLINT UNSIGNED NULL, attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_retry_at TIMESTAMP NULL, delivered_at TIMESTAMP NULL,
  INDEX idx_status_retry (status, next_retry_at)
);

/* [T] */ CREATE TABLE import_jobs (
  entity ENUM('clients','sites','assets','users') NOT NULL,
  file_attachment_id BIGINT UNSIGNED NOT NULL,
  status ENUM('uploaded','validated','committed','failed') NOT NULL DEFAULT 'uploaded',
  total_rows INT UNSIGNED NOT NULL DEFAULT 0, valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
  error_rows INT UNSIGNED NOT NULL DEFAULT 0, error_report_attachment_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL, committed_at TIMESTAMP NULL
);

/* [T] */ CREATE TABLE report_schedules (
  report_key VARCHAR(60) NOT NULL, params_json JSON NULL,
  frequency ENUM('daily','weekly','monthly') NOT NULL,
  day_of_week TINYINT UNSIGNED NULL, day_of_month TINYINT UNSIGNED NULL,
  send_time TIME NOT NULL DEFAULT '07:00:00', format ENUM('pdf','xlsx') NOT NULL,
  recipients_json JSON NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE,
  last_run_at TIMESTAMP NULL
);

/* [T] append-only, tamper evident via hash chain, 3 year retention */
CREATE TABLE activity_logs (
  actor_type ENUM('user','client','system','platform') NOT NULL,
  actor_id BIGINT UNSIGNED NULL, impersonated_by_platform_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL, entity_type VARCHAR(60) NOT NULL, entity_id BIGINT UNSIGNED NULL,
  old_values JSON NULL, new_values JSON NULL, ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL, prev_hash CHAR(64) NULL, row_hash CHAR(64) NOT NULL,
  impersonation_session_id BIGINT UNSIGNED NULL, /* v3.17 */
  INDEX idx_tenant_entity_time (tenant_id, entity_type, entity_id, created_at),
  INDEX idx_impersonation (impersonation_session_id)
);
```

## 12. API Design and Security

### 12.1 API conventions

- Prefix `/api/v1/`. Breaking changes go to `/api/v2/`. Bearer tokens by Sanctum with expiry, refresh for the native app later.
- Tenant comes from the token and the subdomain. A mismatch is rejected.
- Pagination with cursor or page parameters, filtering and sorting on documented fields only.
- `Idempotency-Key` header on every write from the Field View, equal to `client_event_uuid`.
- Field View batch sync: `POST /api/v1/field/sync` accepts an ordered list of events and returns a per-event result (applied, duplicate, conflict_rejected, invalid).
- Error format: JSON with `code`, `message`, `details`. Codes include `INVALID_TRANSITION`, `PROOF_INCOMPLETE`, `GEOFENCE_VIOLATION`, `CONFLICT_STALE_STATE`, `FEATURE_NOT_IN_PLAN`, `RATE_LIMITED`, `VALIDATION_FAILED`. Sign-in and access add `UNAUTHENTICATED`, `AUTH_INVALID`, `TWO_FACTOR_REQUIRED`, `AUTH_LOCKED`, `FORBIDDEN`, `PASSWORD_CHANGE_REQUIRED`, `COMPANY_SUSPENDED`, `TENANT_MISMATCH`, `CODE_INVALID`, `CODE_EXPIRED` and `LINK_INVALID`.
- The Field View adds `IDEMPOTENCY_MISMATCH` (the same event ID sent with different content) and `JOB_ALREADY_OPEN` (the technician already has a job running).
- Notifications add `WHATSAPP_CREDENTIALS_INVALID` (the Business account ID, Phone number ID, token or app secret does not work) and `WHATSAPP_NOT_READY` (go-live before every mandatory template is approved).
- Quotes and invoices add `QUOTE_NOT_REQUESTED`, `QUOTE_CLOSED`, `INVOICE_EXISTS`, `INVOICE_LOCKED`, `PAYMENT_EXCEEDS_BALANCE` and `EXPENSE_LOCKED`. The Client portal adds `FEEDBACK_CLOSED`.
- Dashboards add `IMPERSONATION_BLOCKED` (an action that is not allowed while support is in a company's workspace) and `EXPORT_TOO_LARGE` (a request export with more than 5,000 rows).
- Rate limits per tenant by plan (Section 6).
- Native app rule: no Phase 1 screen calls anything outside `/api/v1/`. Contract tests fail the build if a screen needs a web-only endpoint.

### 12.2 Endpoint groups

| Group | Main operations |
|---|---|
| /auth | login, logout, OTP request and verify, sign-in link, password reset and change, invitation, two-factor, device list and revoke |
| /service-requests | CRUD, `/transition`, assets, notes, status history, reschedule, contract candidates |
| /field | today's jobs, sync, punch, expenses, photo and signature upload, waiver code request and verify, location check-in, device registration for push |
| /assets, /contracts, /preventive-schedules | CRUD, imports, coverage check, calendar data, compliance |
| /forms | templates, versions, publish, retire |
| /quotes, /invoices, /payments | create, send, respond, approve, void, record and reverse |
| /clients, /sites, /vendors, /users, /roles | CRUD, assignments, permission overrides |
| /master-data | categories, SLA policies, expense categories, hold reasons, tax rates, vocabulary, business hours, currencies and rates, reopen window |
| /notifications, /preferences | list, mark read, preferences, push subscriptions, WhatsApp account, template status, message log and retry, and the public WhatsApp webhook |
| /dashboard, /reports | role dashboards, report generation and download, schedules |
| /webhooks | endpoints, test delivery, delivery log |
| /public | packages for the product page, company address check, checkout session (Release B) |
| /webhooks/payments/{provider} | payment events from Stripe, Tap and Razorpay (Release B) |

### 12.3 Webhooks (Release C)

Events: sr.created, sr.status_changed, sr.completed, punch.in, punch.out, invoice.created, payment.received, contract.expiring. Each payload carries an `event_uuid` and is signed with HMAC-SHA256 over timestamp plus body. Failed deliveries retry with backoff and are abandoned after repeated failures. An endpoint with continuous failures is disabled and the admin is notified.

### 12.4 Security

- **Tenant isolation:** global scope on every tenant model, composite indexes led by `tenant_id`, tenant-prefixed storage paths, tenant-scoped sessions and caches. An automated isolation test suite is a gate for every release: with tenant A credentials, every endpoint, QR URL, secure link, file URL and report is requested using tenant B identifiers and must return not found.
- **Audit trail:** `activity_logs` is append-only. The application database user has no UPDATE or DELETE privilege on it. Each row stores a hash of the previous row so tampering is detectable. Retained 3 years.
- **Impersonation:** Platform Admin only (owner and support), with a reason, time limit, full logging, and visible to the tenant admin. The Company Admin is told when a session starts and sees the history. Security changes, invoice approval, sending, payments, write-offs and exports are blocked inside a session (section 7.9a).
- **Secure links and QR:** random tokens stored hashed, expiring, revocable, limited uses. QR codes grant nothing without a signed-in session.
- **Authentication:** bcrypt, passwords of at least 10 characters checked against common passwords, lockout after 5 wrong passwords in 15 minutes, staff sessions ending after 8 hours idle, technician devices trusted for 30 days, invitation links for new users, optional TOTP for admin and manager roles (mandatory for Platform Admin), and one-time codes for technicians. The full rules are in the TEN-ACC spec.
- **Encryption:** TLS 1.3 in transit. WhatsApp tokens, webhook secrets and gateway keys encrypted at rest with per-tenant keys. Server-side encryption on file storage.
- **Files:** private storage, signed URLs with short expiry, size and type limits, image re-encoding to strip scripts.
- **Privacy:** location pings kept 30 days, message payloads 12 months, audit 3 years. Tenant data export and deletion on request. GDPR and SOC 2 are design targets, not claimed certifications.
- **Residency (Release C):** deployment in Mumbai, Bahrain or Frankfurt per tenant `data_region`.

## 13. Validation Protocol and Acceptance Scenarios

A specification cannot prove itself. It is only validated when real jobs, real technicians and real clients go through it. This protocol makes that the first thing that happens, not the last.

### 13.1 Validation sources

- **Replay set:** take 30 real completed jobs from the existing client's history, including at least 5 problem jobs (rework, cancelled, disputed, out of coverage). Re-enter each in the new platform and record every place where the platform needed a workaround.
- **Pilot tenants:** two real businesses from different industries run live jobs on the platform before general launch.
- **Workflow mapping session:** before each pilot, walk through their paper or spreadsheet process with the questionnaire below.

### 13.2 Workflow mapping questionnaire

1. Who can raise a request, and how does it reach you today (call, WhatsApp, email, portal)?
2. What information do you need on a request before you can act on it?
3. Who approves work, and what makes you auto-approve or refuse?
4. Do you quote before working? When, and who accepts on the client side?
5. How do you choose which technician gets a job?
6. What counts as proof that work was done? Is a signature always possible?
7. What happens when work fails inspection?
8. Do your technicians have reliable signal on site?
9. What are your response and resolution promises, and are they in contracts?
10. How do you track equipment and warranties today?
11. Do you do scheduled preventive visits? How do you plan them?
12. Do you use subcontractors or take equipment into a workshop?
13. How and when do you invoice, and what do you attach?
14. What words do you use for job, technician, site and client?
15. What report does your client ask for every month?
16. Which services must be invoiced before the job can be closed?
17. Do you quote or invoice in more than one currency?
18. Do your contracts measure response time from when you receive the request or from when you approve it?

### 13.3 Acceptance scenarios

| # | Scenario | Release | Pass condition |
|---|---|---|---|
| 1 | In-coverage job end to end on a Starter tenant and a Professional tenant | A | Every status reached. Starter users notified by email, browser push and in-app. Professional users also by WhatsApp once their account is verified. A Starter client has no sign-in and receives email with secure links. A Professional client can sign in. |
| 2 | Out-of-coverage job with quote accepted by secure link | A | The SLA clock starts only when the client accepts. Name, time and address of the response stored. |
| 3 | QC failure, rework and reopen | A | Reason mandatory. Original timestamps preserved. Reopen blocked after the company's window, and a follow-up job can be created. |
| 4 | Technician loses signal mid-job | A | Photos, notes, expense and punch out made offline all sync once on reconnect. No duplicates after forced retries. |
| 5 | SR cancelled or reassigned while technician is offline | A | Events stored as conflict_rejected, evidence kept and flagged, technician informed. |
| 6 | Geofence and signature waiver per job | A | Box unticked: GPS recorded and never blocks. Ticked: outside the radius blocks and a manager can approve an override. Waiver works only with a valid code sent to a project contact and fails after 3 wrong attempts or 10 minutes. |
| 7 | Tenant isolation and role permissions | A | Tenant B cannot reach tenant A data by UI, API, QR URL, secure link, file URL or report. Each role can do only what its column in Appendix B allows. A tick box at user creation changes only that one user. |
| 8 | WhatsApp connection and fallback | A | Setup states advance to live. Nothing is sent on WhatsApp before live. A rejected template alerts the admin. No consent or failed delivery produces an email fallback and a log entry. |
| 9 | Invoice-mandatory category | A | After QC pass an out-of-coverage job moves to Awaiting Invoice and completes only when the invoice is approved. An in-contract job in the same category completes at QC pass. A rejected invoice returns to Finance and the job stays. The resolution SLA stops at QC pass. |
| 10 | Plan limits | A | A warning appears at 80 percent and a prompt at 100 percent. Nothing is blocked, work under way continues, and proof photos from the field are still accepted. |
| 11 | Batch SR of 20 assets with mixed outcomes | B | Punch out blocked until all assets final. One signature closes the batch. |
| 12 | Several contracts match | B | Request waits in Pending with the choose-contract flag until a manager picks. A pinned default contract is used without asking. A maintenance job uses its pinned contract or waits when none is pinned. A reached cap forces a decision. |
| 13 | PM calendar generation | B | Job rerun creates no duplicates. Batch PM creates one SR. Lead days honoured. |
| 14 | Multi-trade parent with three children, one reworked | B | Parent status rolls up correctly. Billing aggregates children. |
| 15 | Subcontractor technician | B | Sees only own jobs, no client rates. Cannot log expenses until the admin switches it on for that person. Vendor expenses appear separately in reports. |
| 16 | Depot job from intake to return | B | Workshop site, intake and return checklists, normal lifecycle. |
| 17 | Multi-currency quote and invoice | B | Rate filled automatically and overridable. Rate fixed when the quote is sent and carried to the invoice. Payment in invoice currency. Dashboards show the base currency. |
| 18 | Meter-based PM and if-then rule with loop protection | C | Lower reading rejected, correction audited, rule stops at its run limit. |
| 19 | Webhook delivery and route suggestion | C | Signature verifies, retries back off, route order accepted by manager. |

### 13.4 Pilot success criteria

- Every job in the replay set can be represented without a workaround, or the gap is logged and classified.
- Pilot technicians complete a full job unassisted after a single briefing.
- A manager can see where any job stands and whether it is on SLA without calling anyone.
- A client can see status and download documents without calling the front desk.
- No cross-tenant exposure is found at any point.

### 13.5 Change control

Every finding from a replay or pilot is logged and classified as Configuration (no change needed), Code change, Phase 2, or Rejected. Accepted code changes bump the blueprint version and the affected release gate. A change may enter a release only by moving something of equal size out of it.

## 14. Confirmed Decisions and Open Actions

All 15 decisions from v3.2 were answered by the project owner. Seven more were confirmed in the v3.3 review and are applied in this version.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 1 | WhatsApp on Starter | None on Starter. It uses email, browser push (when browser notifications are on, since Phase 1 has no mobile app) and in-app. Other plans use WhatsApp only after the company's business account is verified. | 6, 9 |
| 2 | When a job closes | For billable (out-of-coverage) jobs in categories marked invoice mandatory, the job waits for invoice approval before Completed (new Awaiting Invoice status). Other jobs complete at QC pass. | 7.1, 7.7 |
| 3 | SLA clock | Starts at approval (Emergency at creation). Pauses only for holds flagged to pause. Review time is reported separately. | 7.1 |
| 4 | Currency | One base currency per company, set once. Quotes and invoices may use other currencies with an automatic rate (Release B). Dashboards use the base currency. | 7.7 |
| 5 | Geofence | Chosen per job when assigning, off by default. Radius on the site. GPS always recorded. Ticked and outside the radius: blocked, and a manager can approve an override. Punch-out distance is flagged only. | 7.5, 7.6 |
| 6 | Signature waiver | Allowed per job when assigning, off by default. Skipped only with a code sent to a project contact (10 minutes, 3 attempts). | 7.6 |
| 7 | Contract call count | Counted on approval, released on early cancellation. | 7.4 |
| 8 | Overlapping contracts | A manager always chooses. Schedules and assets may pin a default contract. | 7.4 |
| 9 | WhatsApp account | Each company's own Meta account. Manual connection in Release A, guided sign-up in Release B. | 9.4 |
| 10 | Native app | Phase 2 add-on. Phase 1 uses only the API the app will use, so the backend and database need no rework. The native screens are new work. | 3, 12 |
| 11 | Advanced features | Release C and Enterprise only. Before Release A ends, ask the first pilot customer whether any of the five is essential. If so, move it to Release B and move something of equal size out. | 5, 6 |
| 12 | Vendors and depot jobs | Release B, Professional and above. | 5, 6 |
| 13 | WhatsApp cap | Removed. There is no shared number, so there is no cap. | 9.6 |
| 14 | Reopen window | Set by each company. Default 14 days, range 0 to 365, where 0 means no reopening. | 7.1 |
| 15 | Permission matrix | Operations signs it off before seeding. Draft 2 was signed off on October 17, 2026 by Kiran E (BA - PM) with no changes. | 4, App. B |

### Added in v3.4

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 16 | Plan limits | Warn at 80 percent, upgrade prompt at 100 percent, nothing blocked in Phase 1. Staff, technicians and vendor technicians count as users, client users do not. | 6 |
| 17 | File storage | An allowance per plan with the same warnings. Proof photos from the field are never refused. The Aiywah owner sets the numbers. | 6 |
| 18 | Existing data | No history migration in Phase 1. CSV import for clients, sites, assets and users. | 15 |
| 19 | WhatsApp wording | First draft in Appendix C, submitted to Meta once a company's account is verified. | 9.5, App. C |
| 20 | Invoice-mandatory scope | Applies only to billable jobs. A rejected invoice returns to Finance as a draft. | 7.1, 7.7 |
| 21 | Maintenance and contracts | A maintenance job uses the contract pinned on its schedule, otherwise it waits for a manager's choice. | 7.3 |
| 22 | Maps | Free map picker for site coordinates in Release A. Paid maps only from Release C. | 3 |

### Added in v3.5

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 23 | Team leads and requests | Team leads can approve requests for their own team. | App. B |
| 24 | Front desk and sites | Front desk can create and edit sites. | App. B |
| 25 | Who manages users | Only the Company Admin adds staff and technicians. A Client Admin adds users for their own company. Front desk adds client users when a client has none. Operations managers do not add users. | 4, App. B |
| 26 | Operations manager settings | Operations managers can edit company settings, including SLA policies and the reopen window. | App. B |
| 27 | Tick boxes at user creation | Five optional permissions appear when an admin creates a user. Team leads start with QC and expense approval off. | 4, App. B |
| 28 | Vendor expenses | Off by default. The admin can switch it on per vendor technician. Reports show vendor expenses separately. | 7.6, 7.9 |

### Added in v3.7

The project owner accepted all 14 assumptions from the Release A scope register.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 29 | Technician requests | Technicians cannot raise requests in Release A. The QR scan path needs assets, which arrive in Release B. | 5 |
| 30 | CSV import | Clients, sites and users in Release A. Assets import in Release B. | 5 |
| 31 | Audit trail viewer | Release A. | 5 |
| 32 | Two-factor sign-in | Optional for admin and operations manager, Release A. | 5 |
| 33 | Overdue invoices | Reminders, the aging report and the invoice.overdue notification are Release B. | 5 |
| 34 | Message log | The log page and retry are Release A, because the email fallback depends on it. | 5, 9.6 |
| 35 | Coverage type | The manager chooses it on approval in Release A. Contract and warranty matching arrive in Release B. | 7.1 |
| 36 | Starter content | Default categories, SLA numbers, hold reasons, expense categories and tax rates must be supplied before provisioning can load them. | 6 |
| 37 | Notification preferences | Release B. In Release A every user gets each event's default channels. | 5 |
| 38 | PDF reports | Release B. Release A has the request Excel export and quote and invoice PDFs. | 5 |
| 39 | Starter PDFs | Starter companies upload PDF quotes and invoices. The builder and branded PDFs need Professional. | 6 |
| 40 | Client portal on Starter | Superseded in v3.9 by decision 57. Starter has no client sign-in. | 6 |
| 41 | Dashboards | Release A shows core counts and SLA figures. Utilisation, PM compliance and contract profitability arrive later. | 7.9 |
| 42 | Plan-limit warning | New event plan.limit_warning warns the admin at 80 and 100 percent. | 9.2 |

### Added in v3.8

The project owner approved all 14 decisions of the SRL module spec on October 17, 2026.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 43 | D1 Request title | Staff type a short title of 3 to 255 characters. The client portal builds it from the category and site. | 7.1 |
| 44 | D2 Front desk assigning | Front desk assigns critical and emergency requests only. | 7.1 |
| 45 | D3 Approval modes | Only manual approval is active in Release A. | 7.8 |
| 46 | D4 SLA policy timing | The policy is picked when the clock starts. | 7.1 |
| 47 | D5 Emergency raised later | The clock starts then, or is recalculated from its start if already running. | 7.1 |
| 48 | D6 Changing the technician | Allowed while Assigned or in Rework. | 7.1 |
| 49 | D7 Cancel notice | The assigned technician is told too. | 9.2 |
| 50 | D8 Client-visible notes | Approvers, assigners and front desk. Clients cannot add notes in Release A. | SRL spec |
| 51 | D9 Billable job not billed | Coverage changes to Goodwill with a reason, any draft invoice is voided, the request completes. | 7.1 |
| 52 | D10 Attachments | Up to 10 files, 10 MB each, JPG, PNG or PDF. | SRL spec |
| 53 | D11 List paging and order | 25 per page. Emergency first, then soonest SLA due, then newest. | SRL spec |
| 54 | D12 Reopen window counting | Completion time plus the company's days times 24 hours. | 7.1 |
| 55 | D13 SLA result | Fixed at the first QC pass. Reopening never changes it. | 7.1 |
| 56 | D14 Quote requested event | New event sr.quote_requested to Finance. | 9.2 |

### Added in v3.9

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 57 | Client sign-in on Starter | Starter has no client sign-in and no client portal. Starter clients receive email updates and use secure links to answer quotes and give feedback. The client portal (sign-in, view, raise, quote answer) is Professional and above. | 4, 6, 9 |

### Added in v3.10

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 58 | Client alerts | Client contacts receive WhatsApp (where the company's account is live) or email only. Never in-app or push. | 9.2 |
| 59 | Plan split approved | The Aiywah owner approved the plan split on October 18, 2026: WhatsApp for Professional and above, Release C features in Enterprise only, vendors and depot jobs in Professional and above, multi-currency in Professional and above, and the client portal in Professional and above. | 6 |

### Added in v3.11

The project owner described the subscription model on October 18, 2026 and approved the recommended details on October 19, 2026.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 60 | Two ways to create a company | A Platform Admin in Release A. Website checkout on the aiywah.com product page in Release B. Both start the same set-up job. | 5, 6 |
| 61 | Product page | A separate website for all Aiywah products. The FSM page lists the packages, reads them from a public list in the product and starts checkout. | 3 |
| 62 | Checkout | The payment provider's hosted checkout. The company address is chosen on our form and held for 30 minutes. The company is created when payment is confirmed. | 12 |
| 63 | Payment providers | Stripe worldwide, Tap for the Gulf and Razorpay for India, chosen by the buyer's country. | 3, 12 |
| 64 | Billing | Monthly or annual. A free trial before payment is set per package by the Aiywah owner. | 15 |
| 65 | Self-service | Customers cannot upgrade or cancel themselves at first launch. Aiywah staff do it. Self-service comes later. | 5 |
| 66 | First admin | The buyer becomes the first Company Admin and gets an invitation email. No password is ever sent. | 6 |
| 67 | One login across products | Planned and not built. Release A reserves an empty aiywah_account_id on users. | 11 |

### Added in v3.12

The project owner approved decisions D1 to D23 of the TEN-ACC spec on October 19, 2026.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 68 | D1 Where people sign in | Staff and clients on the company address, technicians in the Field View, Platform Admin on admin.aiywah.com. | TEN-ACC |
| 69 | D2 Company address rules | 3 to 40 lowercase letters, digits and hyphens, starting with a letter, not reserved. Only a Platform Admin can change it. | TEN-ACC |
| 70 | D3 Passwords | At least 10 characters, common-password check, bcrypt. Reset links 60 minutes, invitation links 7 days. | TEN-ACC |
| 71 | D4 Sessions | Staff and client sessions end after 8 hours idle. Technician devices stay trusted 30 days. Password change or turning off a user ends sessions at once. | TEN-ACC |
| 72 | D5 Lockout | 5 wrong passwords in 15 minutes lock the account for 15 minutes. 20 failures from one address pause it. Errors never say which part was wrong. | TEN-ACC |
| 73 | D6 One-time codes | Technicians only. 6 digits, 10 minutes, 3 tries. Email on Starter, WhatsApp or email elsewhere. | TEN-ACC |
| 74 | D7 Client sign-in link | 24 hours, works once, Professional and above. Clients can also set a password. | TEN-ACC |
| 75 | D8 Two-factor | Optional for Company Admin and Operations Manager, can be required company-wide, mandatory for Platform Admin. No recovery codes in Release A. | TEN-ACC |
| 76 | D9 New users | Invitation email and own password. A user with no email gets a temporary password shown once. | TEN-ACC |
| 77 | D10 Suspended companies | Cannot sign in and see a contact-support page. Trials do not end automatically in Release A. | TEN-ACC |
| 78 | D11 User limits | Warn only, never block. Client users are not counted. | TEN-ACC |
| 79 | D12 Role choices follow the plan | Team Lead needs Professional and above. Client User needs the client portal. | TEN-ACC |
| 80 | D13 Standard roles | Cannot be deleted or renamed. Permissions can be changed per company and reset to the signed defaults. Custom roles are based on a standard role. | TEN-ACC |
| 81 | D14 Team lead scope | A team lead covers service categories chosen on their user record. A request in those categories, or assigned to their team, is own team. | TEN-ACC |
| 82 | D15 Client Admin and Viewer | Admin sees the whole company. Viewer sees chosen sites and cannot raise requests or answer quotes. | TEN-ACC |
| 83 | D16 Last admin | The last active Company Admin cannot be turned off, demoted or deleted. | TEN-ACC |
| 84 | D17 Turning off a user | Ends sessions at once and warns about open assigned requests. Does not reassign them. | TEN-ACC |
| 85 | D18 Sign-in emails | Seven system emails with fixed wording, outside the event engine, logged without the secret. | TEN-ACC |
| 86 | D19 Platform Admin roles | Owner, support and finance. Finance is read only until billing arrives. | TEN-ACC |
| 87 | D20 Company set-up job | Queued, shows progress, usually ready within seconds. | TEN-ACC |
| 88 | D21 Email change | Needs the current password and confirmation from the new address. | TEN-ACC |
| 89 | D22 Audit trail page | Read only with filters, no export in Release A, kept 3 years. | TEN-ACC |
| 90 | D23 Number prefixes | SR, QT, INV, CT and PAY with the pattern PREFIX-YYYY-000001. | TEN-ACC |

### Added in v3.13

The project owner approved these on October 22, 2026.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 91 | Money decimals | Money is stored with 3 decimals and shown with each currency's own decimals. Any currency with 0, 2 or 3 decimals can be the base currency. | 11, Master data spec |
| 92 | Invoices in Release A | Aiywah invoices are pro-forma or customer copies. The customer issues the legal tax invoice from its own accounting suite where the law requires it. Invoices download easily as PDF and Excel. The Quotes and invoices spec adds a field for the legal invoice number issued elsewhere. | 7.7, Quotes and invoices spec |
| 93 | Government e-invoicing and accounting sync | The customer manages government e-invoicing (for example Saudi ZATCA, the UAE and Oman) itself. No accounting sync or e-invoicing provider is planned for now. The Release C webhooks stay as planned. | 7.7 |
| 94 | Master data decisions | The 25 decisions D1 to D25 of the Master data spec are approved, including the starter content and the global tax set-up. | Master data spec |

### Added in v3.14

The project owner approved these on October 22, 2026.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 95 | Field View decisions | The 25 decisions D1 to D25 of the Field View spec are approved. The Field View is a mobile web app in Phase 1, installable to the Home Screen. The native app is Phase 2 | Field View spec |
| 96 | Unsent work at sign-out | Saved jobs and personal data are cleared at sign-out and on revocation. Work not yet sent stays locked on the phone until the same technician signs in again. This replaces the blueprint's earlier wording | 8 |
| 97 | Expense total rounding | Quantity times rate, rounded half up to the currency's decimals | 7.6 |
| 98 | Team lead on the phone | A team lead can approve, reject, assign and raise requests from the Field View, online only. Field work still works offline | 8, Field View spec |


### Added in v3.15

The project owner approved these on October 23, 2026.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 99 | Notifications decisions | The 24 decisions D1 to D24 of the Notifications spec are approved. One event engine with an outbox sends every event message. Sign-in emails and codes are system messages through the same sender | 9.7, Notifications spec |
| 100 | Channels and fallback | Email is the first choice on Starter and before WhatsApp is live. Fallback to email on no consent, failure, or no delivery after 15 minutes (5 for urgent events, 60 seconds for codes). Push and in-app events fall back only if unread. WhatsApp retries after 1, 5 and 15 minutes | 9.7 |
| 101 | WhatsApp templates | 16 mandatory templates, owned by the Platform Admin in a master library and submitted as key_v1. Three new technician templates are approved | 9.5, App. C |
| 102 | Replies and consent | STOP and START words. Replies are matched to the request last messaged within 7 days and saved as internal notes. New event sr.client_reply | 9.3, 9.7 |
| 103 | New events | Five events are added: the three Field View events, sr.client_reply and whatsapp.connection_problem. Register items NTF-44 to NTF-48 | 9.2 |

### Added in v3.16

The project owner approved these on October 23, 2026.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 104 | Quotes and invoices decisions | The 25 decisions D1 to D25 of the Quotes and invoices spec are approved. Finance builds and sends, the Operations Manager approves invoices | 7.7a, QIN spec |
| 105 | Money arithmetic | Quantity times rate, one document discount shared over the lines, tax for each line, all rounded half up to the currency's decimals at each step | 7.7a |
| 106 | Invoice rules | One invoice that is not void for each billable request. Draft, pending approval, approved, sent. Approval completes an Awaiting Invoice job. Void with a reason, never delete | 7.7a |
| 107 | Pro-forma copy | Optional legal invoice number issued elsewhere and the line "Customer copy. The legal tax invoice is issued separately where the law requires it." Branded PDFs and Excel on Professional and above, uploads on every plan | 7.7a |
| 108 | Payments and status | Payments by hand on sent invoices, reversible. Billing status kept by the system. Write-off with a reason, undone by the Company Admin. No gateway, aging report or reminders | 7.7a |
| 109 | Billing events and templates | Events invoice.submitted and invoice.sent_back added. A client_invoice link type. 18 mandatory WhatsApp templates. A live company stays live and uses email until the new templates are approved | 9.2, 9.5 |
| 110 | Client portal decisions | The 22 decisions D1 to D22 of the Client portal spec are approved. Client Admin sees the whole company, Viewer only the chosen sites | 7.7b, CLP spec |
| 111 | What a client sees and does | An allowed list of fields. Three company switches. A Client Admin raises requests, cancels or edits an own Pending request and answers quotes. No notes from clients | 7.7b |
| 112 | Feedback | By secure link on every plan, stars and a 0 to 10 score required, valid 7 days by default, one answer for each completion. New event feedback.received, urgent for low scores. A category tick Clients can choose this category | 7.7b, 9.2 |

### Added in v3.17

The project owner approved these on October 23, 2026.

| # | Decision | Confirmed answer | Where applied |
|---|---|---|---|
| 113 | Dashboards decisions | The 22 decisions D1 to D22 of the Dashboards, limits and platform admin spec are approved. One home page for each role, one period picker, the figures of Release A and how each is worked out | 7.9a, DSH spec |
| 114 | Queues and export | Operations queue, team dashboard, technician stats, front desk queue (today's calls means every request any front desk user raised today) and finance dashboard. Request export up to 5,000 rows with no money or contact details | 7.9a |
| 115 | Limits and usage | Usage counters for users, requests, storage and messages. Warnings at 80 and 100 percent once for each limit and month. Nothing is ever blocked and proof photos are always accepted | 7.9a |
| 116 | Impersonation | Owner and support only, a reason, 30 minutes by default and 2 hours at most. Security changes, invoice approval, sending, payments, write-offs and exports are blocked. The Company Admin is told and sees a history kept 3 years. New event impersonation.started | 7.9a, 9.2, 12.4 |
| 117 | Platform screens | Company detail with usage and the WhatsApp health board from an hourly snapshot, read only | 7.9a, 9.4 |
| 118 | Freshness and records | Dashboards kept 60 seconds, export and meters always current. Counters 24 months, snapshots 90 days, sessions 3 years. No new permission keys | 7.9a |

### Open actions

| Action | Owner |
|---|---|
| Register or verify Aiywah's own Meta business account and decide who holds it. Needed for the guided WhatsApp sign-up | Aiywah owner |
| Name the first pilot customer and their industry. Ask them about the five Release C features, subcontractors and workshop jobs | Project owner |
| Supply a sample client contract showing SLA wording, to check when the clock should start | Project owner |
| Set the file storage allowance for each plan | Aiywah owner |
| Review and decide the management items in Section 15 | Aiywah owner |
| Review the WhatsApp message wording in Appendix C | Project owner |
| Choose the exchange rate provider | Developers propose, project owner approves |
| Set the package prices for each billing period and currency, and the trial length of each package | Aiywah owner |
| Provide the FSM product page content and the links to the terms of service and privacy policy | Aiywah owner |
| Open the payment provider accounts (Stripe, Tap and Razorpay) | Aiywah owner |
| Confirm the SRL module spec can be built without questions | Developers |
| Confirm the TEN-ACC module spec can be built without questions | Developers |
| Supply the starter content for new companies: default categories, SLA numbers, hold reasons, expense categories and tax rates | Project owner |
| Confirm with the CA how DIGIT-US issues its own tax invoices to Aiywah subscribers, including whether India's e-invoicing threshold applies | Project owner |

## 15. Management Items Outside the Code

These are proposals so management has something concrete to approve or change. The developers do not decide them alone.

| Topic | Proposed starting position | Decided by |
|---|---|---|
| Availability | 99.5 percent a month for the web app and API, not counting announced maintenance. A public status page. | Aiywah owner |
| Speed | Normal lists and screens open in about 2 seconds. Field View actions confirm in about 1 second on a normal mobile connection. Photo uploads take longer and run in the background. Developers confirm this with a load test before launch. | Developers propose, owner approves |
| Backups and recovery | Database backups every day, kept for 30 days, plus point-in-time recovery so at most 15 minutes of data is lost. Files are stored with versioning. A restore is tested every quarter. Without point-in-time recovery, up to a day of data could be lost, which costs less. | Aiywah owner |
| Monitoring | Error tracking, uptime checks, a heartbeat on the SLA and maintenance schedulers, alerts on message failures and full queues, and a named person on call. | Aiywah owner |
| Support | Help inside the product and by email. Opening hours and reply times by severity to be set. Support staff enter a company's workspace only through audited impersonation. | Aiywah owner |
| Security testing | A penetration test and dependency review before general launch. The tenant isolation test suite runs on every release. | Aiywah owner |
| Browsers and devices | Latest two versions of Chrome, Edge, Safari and Firefox. Field View on Android Chrome and iPhone Safari. iPhone push needs iOS 16.4 or later and the Home Screen install. | Project owner |
| Legal documents | Terms of service, privacy policy, data processing agreement, acceptable use policy, and the wording that collects WhatsApp consent. Reviewed by legal counsel in each country where Aiywah is sold. Needed before the first paying customer. | Aiywah owner with legal counsel |
| Pricing | Plan prices, trial length, annual discount, and tax on Aiywah's own subscriptions. | Aiywah owner |
| Failed payments | Tell the admin and retry through the gateway. Warn after 7 days. Read-only after 14 days (no new requests, data kept). Suspended after 60 days. Terminated after 90 days, with a data export offered first. | Aiywah owner |
| Existing data | No history is migrated in Phase 1. Clients, sites, assets and users come in by CSV import. The old system stays read-only as an archive. | Project owner |
| Data after cancellation | Company data can be exported for 30 days after termination, then is deleted, except what the law requires to be kept. | Aiywah owner with legal counsel |

## Appendix A. Phase 2 Backlog

- Native iOS and Android app with local database and full offline operation.
- Spare parts inventory: catalogue, warehouse and van stock, reorder alerts, consumption reports, OEM warranty claims.
- Recurring route plans with route-level scheduling.
- Installation projects with milestones, progress billing and schedules.
- Rate cards and additional languages.
- IoT and telemetry triggers, AI scheduling.

## Appendix B. Default Permission Matrix (draft 2, signed off; version 3 of 2026-11-20 adds sr.export)

The same matrix is in the workbook `Aiywah-FSM-Permission-Matrix-Draft-2.xlsx`, signed off on October 17, 2026 by Kiran E (BA - PM) with no changes. If the two ever differ, the signed workbook wins. Platform Admin works above all companies and is not listed. Draft 2 applies Operations' answers to draft 1.

Roles: CA is Company Admin, OM is Operations Manager, TL is Team Lead, FT is Field Technician, FD is Front Desk, FN is Finance, CU is Client User.

| Code | Level | Meaning |
|---|---|---|
| F | Full | All records in the company. |
| G | Grantable | Full, plus the right to extend this permission to others. |
| R | Record level | Records matching the person's relationship: own team, assigned jobs, own company's sites. |
| V | View only | Read all, change nothing. |
| VO | View own | Read only their own records. |
| EO | Edit own | Read and change only their own records. |
| - | None | No access. |

#### Service requests

| Permission | What it allows | CA | OM | TL | FT | FD | FN | CU |
|---|---|---|---|---|---|---|---|---|
| sr.view | See requests and their history | F | F | R | R | F | V | R |
| sr.create | Raise a new request | F | F | F | R | F | - | R |
| sr.approve | Approve or reject pending requests | F | F | R | - | - | - | - |
| sr.assign | Assign or reassign technicians, set the ETA, tick the geofence and waiver boxes | F | F | R | - | R | - | - |
| sr.qc | Pass or fail the quality check | F | F | - | - | - | - | - |
| sr.cancel | Cancel a request with a reason | F | F | - | - | R | - | R |
| sr.reopen | Reopen a completed job inside the company's window | F | F | - | - | - | - | - |
| sr.override_geofence | Approve a technician's geofence override | F | F | R | - | - | - | - |
| sr.export | Download the request list as an Excel file | F | F | - | - | - | - | - |

#### Field work

| Permission | What it allows | CA | OM | TL | FT | FD | FN | CU |
|---|---|---|---|---|---|---|---|---|
| field.punch | Punch in and out, hold, resume, capture proof | - | - | EO | EO | - | - | - |
| expense.log | Log expenses on a job | - | - | EO | EO | - | - | - |
| expense.approve | Approve or reject logged expenses | F | F | - | - | - | F | - |

#### Commercial

| Permission | What it allows | CA | OM | TL | FT | FD | FN | CU |
|---|---|---|---|---|---|---|---|---|
| quote.manage | Create, revise and send quotes | F | V | - | - | - | F | VO |
| quote.respond | Accept or reject a quote | - | - | - | - | - | - | R |
| invoice.manage | Create, edit, send and void invoices | F | V | - | - | - | F | VO |
| invoice.approve | Approve an invoice | F | F | - | - | - | - | - |
| payment.record | Record and reverse payments | F | - | - | - | - | F | - |

#### Assets and contracts

| Permission | What it allows | CA | OM | TL | FT | FD | FN | CU |
|---|---|---|---|---|---|---|---|---|
| asset.view | See assets and their history | F | F | R | R | F | V | R |
| asset.manage | Create, edit and decommission assets | F | F | - | - | - | - | - |
| asset.import | Bulk import assets | F | F | - | - | - | - | - |
| contract.view | See contracts | F | F | - | - | V | V | VO |
| contract.manage | Create, edit and renew contracts | F | F | - | - | - | - | - |
| contract.choose | Choose the contract when several match | F | F | - | - | - | - | - |
| pm.view | See maintenance schedules and the calendar | F | F | R | - | - | - | VO |
| pm.manage | Create and edit maintenance schedules | F | F | - | - | - | - | - |

#### Configuration

| Permission | What it allows | CA | OM | TL | FT | FD | FN | CU |
|---|---|---|---|---|---|---|---|---|
| form.manage | Build and publish form templates | F | F | - | - | - | - | - |
| workflow.manage | Manage approval rules, if-then rules and custom statuses | F | V | - | - | - | - | - |
| settings.manage | Company settings: SLA policies, business hours, currency, reopen window | F | F | - | - | - | - | - |
| vocabulary.manage | Rename terms such as Technician or Work Order | F | - | - | - | - | - | - |
| category.manage | Service categories, proof rules, invoice-mandatory setting | F | F | - | - | - | - | - |

#### People and master data

| Permission | What it allows | CA | OM | TL | FT | FD | FN | CU |
|---|---|---|---|---|---|---|---|---|
| user.manage | Create and deactivate users, reset access | G | - | - | - | R | - | R |
| role.manage | Edit roles and give one-off permissions | G | - | - | - | - | - | - |
| client.manage | Create and edit clients, contacts and WhatsApp consent | F | F | - | - | F | V | - |
| site.manage | Create and edit sites, coordinates and radius | F | F | - | - | F | - | - |
| vendor.manage | Create and edit vendors | F | F | - | - | - | - | - |

#### Insight

| Permission | What it allows | CA | OM | TL | FT | FD | FN | CU |
|---|---|---|---|---|---|---|---|---|
| dashboard.company | Company-wide dashboard | F | F | - | - | - | V | VO |
| dashboard.team | Team and personal dashboards | F | F | R | VO | VO | - | - |
| report.view | View reports and certificates | F | F | R | - | - | V | VO |
| report.export | Download and schedule reports | F | F | - | - | - | F | VO |
| audit.view | Read the audit trail | F | - | - | - | - | - | - |

#### Integration

| Permission | What it allows | CA | OM | TL | FT | FD | FN | CU |
|---|---|---|---|---|---|---|---|---|
| notification.manage | Notification settings, WhatsApp connection, message log and retries | F | V | - | - | - | - | - |
| webhook.manage | Webhook endpoints | F | - | - | - | - | - | - |
| api.manage | API tokens | F | - | - | - | - | - | - |

#### Scope notes

- **sr.view:** Team lead: own team. Technician: jobs assigned to them. Client: own company's sites.
- **sr.create:** Technician: from a QR scan, for the site of the current job. Client: own sites.
- **sr.approve:** Team lead: own team. The admin can switch it off for a person.
- **sr.assign:** Team lead: own team. Front desk: urgent and emergency requests only.
- **sr.qc:** Team lead: off by default. The admin can switch it on for a person, for their own team.
- **sr.cancel:** Front desk and client: only requests they raised, and only while Pending.
- **sr.override_geofence:** Team lead: own team.
- **sr.export:** Added in version 3 (2026-11-20, approved by the owner). Only the Company Admin and the Operations Manager export the request list (section 7.9a): up to 5,000 rows, narrowed like the list.
- **field.punch:** Only on jobs assigned to the person.
- **expense.log:** Vendor technicians: off by default. The admin can switch it on for a person. Reports show vendor expenses separately.
- **expense.approve:** Team lead: off by default. The admin can switch it on for a person, for their own team.
- **quote.manage:** Client: only quotes sent to them.
- **quote.respond:** Client Admin only, for their own company.
- **invoice.manage:** Client: only their own invoices.
- **asset.view:** Technician: assets on their jobs and scans. Client: only if asset visibility is on for them.
- **contract.view:** Client: own contracts.
- **pm.view:** Client: only if asset visibility is on.
- **user.manage:** Only the Company Admin adds staff and technicians. Client Admin: users of their own company. Front desk: client users only, when a client has no Client Admin.
- **site.manage:** Wrong coordinates can block technicians when a job enforces the geofence. The map picker helps.
- **dashboard.company:** Client: own company's figures only. Finance: money figures.
- **dashboard.team:** Team lead: own team. Technician and front desk: their own figures.
- **report.view:** Client: own reports and certificates.
- **report.export:** Finance: financial reports. Client: download their own.

#### Tick boxes when an admin creates a user

| Tick box | Permission | Shown for | Starting value | Scope when ticked |
|---|---|---|---|---|
| Approve requests | sr.approve | Team Lead | On | Own team |
| Pass quality check | sr.qc | Team Lead | Off | Own team |
| Approve expenses | expense.approve | Team Lead | Off | Own team |
| Assign jobs | sr.assign | Team Lead, Front Desk | On for both | Team lead: own team. Front desk: urgent and emergency requests only |
| Log expenses | expense.log | Vendor technician | Off | Their own expenses |

#### Operations' answers and how they were applied (14)

| # | Topic | Applied in draft 2 |
|---|---|---|
| 1 | sr.create | Kept. Technicians can raise requests from a QR scan, for the site of their current job. |
| 2 | sr.approve | Changed. Team leads can approve requests for their own team. |
| 3 | sr.assign | Kept. Front desk assigns urgent and emergency requests only (confirmed). |
| 4 | sr.qc | Team lead starts as off. The admin can switch it on per person with a tick box when creating the user. (Your answer ended mid-sentence, so this follows my recommendation.) |
| 5 | expense.approve | Team lead starts as off. The admin can tick it per person when creating the user. |
| 6 | contract.view | Kept. Front desk can view contracts. |
| 7 | site.manage | Changed. Front desk can create and edit sites. |
| 8 | user.manage | Changed. Operations managers cannot manage users. Only the Company Admin can. Keep at least two admins per company. |
| 9 | settings.manage | Changed. Operations managers can edit company settings, including SLA policies and the reopen window. |
| 10 | report.export | Kept. Finance exports financial reports only. |
| 11 | Money visibility | Kept. Amounts are visible only to roles with access to quotes, invoices, contracts or expenses. |
| 12 | Vendor technicians | Expense logging starts as off. A tick box when adding a vendor technician switches it on. Expense reports show vendor expenses separately and can filter them. |
| 13 | Client users | Applied as: a Client Admin adds users for their own company. Front desk adds client users when a client has no Client Admin. Neither can create staff. The Client Admin and Client Viewer split is kept as drafted. |
| 14 | invoice.manage and payment.record | Kept. The Company Admin can edit invoices and record payments. |

#### Rules

- **Extra access for one person:** An admin can give one user an extra permission beyond their role, with an optional end date. Every grant is logged.
- **Scope of Record level:** Records that match the person's relationship: own team for a team lead, assigned jobs for a technician, own company's sites for a client.
- **Platform Admin:** Aiywah's own staff work in a separate area above all companies and are not in this matrix.
- **Starting point only:** Each new company starts with these defaults. A company admin can change them for that company without affecting others.
- **Tick boxes when creating a user:** When an admin creates a user, five optional permissions appear as tick boxes (see the Tick boxes sheet). They set a personal extra on top of the role. Every change is logged.
- **Who adds users:** Only the Company Admin adds staff and technicians. A Client Admin adds users for their own company. Front desk adds client users when a client has no Client Admin. Operations managers do not add users.
- **Vendor technicians:** They use the Field Technician column but see only their own assigned jobs and no amounts. Expense logging is off unless the admin switches it on for that person. Reports show vendor expenses separately and can filter them.

## Appendix C. Draft WhatsApp Message Library

First draft of the wording for review. Each message is a Meta utility template, except the two verification codes. A {{n}} marks a value the system fills in. Terms such as Work Order or Service Request go in as variables, so one approved template serves every industry. Messages start and end with fixed text, because Meta tends to reject templates that begin or end with a variable. Buttons carry the secure links. Meta's review may require edits.

| Template key | Message | Variables |
|---|---|---|
| sr_created | We have received your {{1}} {{2}} for {{3}}. We will update you once it has been reviewed. | 1 request term, 2 reference, 3 site |
| sr_approved | Your {{1}} {{2}} has been approved and will be scheduled shortly. | 1 request term, 2 reference |
| sr_cancelled | Your {{1}} {{2}} has been cancelled. Reason given: {{3}}. | 1 request term, 2 reference, 3 reason |
| quote_sent | A quote for your {{1}} {{2}} is ready. Please review it and let us know your decision. Button: View quote. | 1 request term, 2 reference |
| sr_assigned_tech | You have a new {{1}}, {{2}}, at {{3}}. Please open it, confirm your arrival time and start when you reach the site. Button: Open job. | 1 request term, 2 reference, 3 site |
| sr_assigned_client | Your {{1}} {{2}} has been assigned to {{3}}, expected on {{4}}. | 1 request term, 2 reference, 3 technician name, 4 date and time |
| sr_eta_changed | The expected arrival for your {{1}} {{2}} has changed to {{3}}. We apologise for the inconvenience. | 1 request term, 2 reference, 3 new date and time |
| sr_reassigned | Your {{1}} {{2}} has been reassigned to someone else. Please stop work on it and check your list. | 1 request term, 2 reference |
| tech_arrived | Work has started on your {{1}} {{2}}. {{3}} has arrived at {{4}}. | 1 request term, 2 reference, 3 technician name, 4 site |
| sr_rework | The quality check found issues on {{1}} {{2}}. Please open it, read the reason and correct the work. | 1 request term, 2 reference |
| sr_completed | Your {{1}} {{2}} is complete. We would value your rating of the service. Button: Rate service. | 1 request term, 2 reference |
| invoice_sent | Invoice {{1}} for your {{2}} {{3}} is ready. The amount is {{4}} and payment is due by {{5}}. Button: View invoice. | 1 invoice number, 2 request term, 3 reference, 4 amount, 5 due date |
| invoice_overdue | Invoice {{1}} was due on {{2}} and is still unpaid. Please arrange payment or contact us. | 1 invoice number, 2 due date |
| sla_breached | Attention: {{1}} {{2}} at {{3}} has passed its {{4}} target. Please review it now. | 1 request term, 2 reference, 3 site, 4 response or resolution |
| pm_generated | A scheduled maintenance {{1}} {{2}} has been created for {{3}}, due on {{4}}. | 1 request term, 2 reference, 3 site, 4 due date |
| pm_overdue | Scheduled maintenance {{1}} {{2}} for {{3}} is overdue by {{4}} days. Please review it. | 1 request term, 2 reference, 3 site, 4 number of days |
| summary_daily | Your daily summary for {{1}} is ready. Open it to see yesterday's figures. Button: Open summary. | 1 date |
| signature_code | Meta's fixed authentication wording, with the 10 minute expiry line. | 1 code |
| login_code | Meta's fixed authentication wording, with the 10 minute expiry line. | 1 code |
| sr_cancelled_tech | Update: {{1}} {{2}} has been cancelled. Please stop work on it and check your list. | 1 request term, 2 reference (added in v3.15) |
| sr_eta_changed_tech | Update: the arrival time for {{1}} {{2}} has changed to {{3}}. Please check your list. | 1 request term, 2 reference, 3 new date and time (added in v3.15) |
| field_override_decided | Update: your request to punch in at {{1}} {{2}} was {{3}}. Please open the job. Button: Open job. | 1 request term, 2 reference, 3 approved or not approved (added in v3.15) |

