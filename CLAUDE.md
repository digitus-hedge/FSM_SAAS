# Aiywah FSM: working notes for Claude

Multi-tenant field service SaaS. Laravel 12, PHP 8.2+, Blade, MySQL (one database, `tenant_id` on company tables).
Product spec: `docs/specs/blueprint.md`. Module specs: `docs/specs/*.md` (current: Currency-and-Payments).
Labels such as "service request", "client" and "quote" can be renamed per company, so use `term()` (app/Helpers/terminology.php), never hardcode them.

## Working rules (always)
- Before changing any file, show the plan and the file list and wait for the owner's OK.
- Follow the spec. Never invent fields, rules, messages or permissions. If the spec is silent, ask.
- Where code column names differ from the blueprint, the code wins (e.g. `invoices.balance`, `payments.received_at`).
- At the end, list every file created or changed, migration results and test results.
- I am the only developer. Quotes, invoices and payments are older code: change them only when the plan says so.

## Status
- B7 Parts 1 and 2 built: currencies and rates, payments ledger, aging, overdue reminders, currency on the quote and invoice builders
  and the payment dialog, base-currency dashboard figures, the Reminders line on the invoice page. The API (spec section 8) is not built.
- Security clean-up built: every office route is behind sign-in and gated by the signed matrix, scoped bindings, record_level holders
  narrowed to their own records (users, service request actions, dispatch), the full suite passes.
- B3 built (docs/specs/Forms-Approvals-Batch.md): checklist templates and versions, the builder, the checklist in the Field View with the proof lock,
  approval modes and the rules page, batch requests. The API (spec section 8) is not built.
- B4 built (docs/specs/Preventive-Maintenance.md, calendar only): schedules, the 06:00 job, calendar, client list. The API is not built.
- B6 built (docs/specs/Multi-Trade-Vendors-Depot.md): multi-trade masters, vendor technicians, workshops and depot stages. The API and the depot screens of the Field View are not built.
- B8 built (docs/specs/Reports-Summaries-Preferences.md, no WhatsApp): reports with PDF and Excel, dashboard figures, daily summaries, notification preferences and digests.
  The reports API is not built.
- Follow-up from B8: real Web Push (VAPID, blueprint section 3; today push is a browser notification while the app is open).
- SLA clock and `sla:check` built (blueprint 7.1 and 9.2, no separate spec): business hours, holidays and holds in the due times, `sla.warning` and `sla.breached` fired.
- WhatsApp paused: every blueprint 9.2 WhatsApp event goes by email to the same recipients (see "Emails and event messages"). Not built yet: the signature-waiver code flow and the one-time field login (the code mailable is ready).
- B9 checkout and billing: payment gateway on hold until last.

## Tenancy
- `TenantResolver` (global web middleware) reads the host. Host equal to the APP_URL host, or not a subdomain of it, is the
  root (no tenant). `{slug}.{APP_URL host}` is a company: it loads the active Tenant (else 404) and calls `Tenant::setCurrent()`.
  Reserved subdomains www/admin/api/app are the root. A signed-in user from another company gets 403.
- `Tenant::current()` is null on the root and on the console. Production wildcard (*.fsm.aiywah.com) is DNS and APP_URL
  config, not code: company URLs are always built as `{slug}.{APP_URL host}[:port]` (CentralLoginController::tenantUrl, ProvisionCompany).
- Central login (root only): `CentralLoginController`. `attempt` checks email+password across all companies, `choose` is the
  workspace picker, `handoff` stores a 60 s one-time token in Cache and redirects to the company address, `consume`
  (`/auth/handoff/{token}`, company address) signs the user in. Sessions are host-only, one per company address.
- Platform owner console: `/admin` (`admin.dashboard`, tenants create, suspend, reactivate). Middleware `platform.host`
  (404 if a tenant is resolved), `auth:platform`, `platform.2fa`. Sign in at `/admin/login`.
  Careful: company office pages ALSO live under `/admin/...` and `admin.*` route names, on company hosts.
- A company has a plan (`plans.features` JSON). `ProvisionCompany` (queued) creates a company, so a queue worker must run.

## Auth
- Guards: `web` (users, company staff and clients) and `platform` (platform_users). Both session based.
- Staff and field users sign in with a password. Client users (`client_user`, `client_admin`, `client_viewer`) use
  emailed sign-in links (`/client/sign-in`), never a password.
- Failed sign-ins: 5 tries lock the user 15 minutes. IP rate limit 20 per 15 minutes. Audit rows `auth.*`.
- Two-factor: `TwoFactorController`, optional for company users, mandatory for platform users. After a correct password
  the pending state is kept in the session (`two_factor_pending`, with the guard), then `/two-factor/challenge`.
- Devices: `DeviceService` (register, touch) and `TouchDevice` middleware; `/profile/devices` lists and revokes.
- Field login (technicians and team leads, company host only): `/login/technician` and `/login/team-lead` show pages
  (FieldLoginController), `POST /field/login` signs in by email or phone inside the current company.
  `RouteTechnicianMobile` sends field users to `/technician/...` for every other URL. `ForcePasswordChange` forces `/change-password`.

## Permissions and plans
- Signed matrix: `database/seeders/data/permission_matrix.csv`. `PermissionCatalogSeeder` loads keys; `SeedStandardRoles` gives each
  company 7 roles. Changes need a new signed version. Tables: `permissions`, `role_permissions`, `user_permission_overrides`.
  Spatie permission is installed but not used for checks; do not use `hasRole()`.
- `User::canDo($key)` is true for ANY level above `none`, including `view_only` and `view_own`. Use it to show a page.
- `PermissionLevel::canChange($user, ...$keys)` is true for `full`, `grantable` and `record_level`. Use it for every create, edit,
  delete, send, pause, rate or settings change, and to show the buttons that do those.
- `PermissionLevel::canView($user, ...$keys)` is the same plus `view_only`. Use it to open a page read-only. `PermissionLevel::isFull()`
  is true for `full` and `grantable` only. `view_own` and `edit_own` are left out on purpose: client and field users hold them on staff
  permissions.
- `record_level` means "the user's own records" and NEVER narrows by itself: passing `canChange` is not enough. Narrow it in the
  controller, in one place: `Services/Users/UserAdminScope` (who may add, edit, turn off or reset which users: Company Admin all; Front
  Desk client users of a client with no active Client Admin; Client Admin their own client's users; nobody gives the Company Admin
  role or creates staff except the Company Admin; nobody changes their own role) and `Services/Permissions/ServiceRequestAccess`
  (approve and send-for-quote = sr.approve full, send-for-quote only while Pending; mark-review = sr.qc full; assign, reschedule and
  suggestions = full, or critical and emergency requests for the Front Desk; cancel and change priority = full, or requests the user raised). Time off and CSV user import need `full` / the Company Admin.
- Route gate: `->middleware('level:key')` (`EnsurePermissionLevel`) on a route group: GET needs canView, POST/PUT/DELETE need canChange,
  and a GET for a create, edit, build, upload or import page needs canChange. Put it after `auth`. Route binding runs before it, so an
  unknown id is a 404 before the 403 (the company is already known then, see Conventions). Screens without a `level:` or their own check are a bug: add one.
- Matrix examples: invoice approve and send-back = `invoice.approve` (Company Admin, Operations Manager); expense approve =
  `expense.approve` (Company Admin, Operations Manager, Finance); quotes: Company Admin and Finance change, Operations Manager views.
- Plan gate: `EnsurePlanFeature` middleware (`EnsurePlanFeature::class . ':key'`) shows the 403 "not in your plan" page. In code use
  `$tenant->hasFeature('key')`. Keys live in `plans.features`, seeded by `PlanSeeder` and added by migrations
  (contracts, assets_full, multi_currency, billing_full ...). A key that is missing means "no".
- Order: permission check first (404 for roles with no access), plan gate on the route. Client roles are blocked by `DenyClientRole`
  on every office group except `manage/users` (a client admin adds users for their own company).

## Forms, approvals and batch (B3)
- `Services/Forms`: `FormSchema` (pure: validate a template, evaluate answers, the result `ok`/`issues`; the device never sends a result),
  `FormTemplates` (create, draft, publish, retire, archive; plan limit), `Checklists` (bind a request to the published version at creation, save
  answers offline-safe through `field_event_log` event `checklist_saved`, `blockers()` for the punch-out), `BatchService`, `FormException`
  (carries the spec's error code; a 403 one aborts with 403). A published or retired `FormTemplateVersion` throws on any content change.
- Plan keys: `forms`, `form_templates_limit` (10 Professional, null Enterprise), `approval_auto`, `approval_finance` (blueprint "forms" and "approval" rows).
  The unused `form_builder` and `reports_pdf` keys of PlanSeeder are left alone.
- Punch-out lock (`Technician/JobsController::punchOut`): `Checklists::blockers()` first (pending batch assets, missing checklist answers), then the old proof lock.
  A lost `forms` plan pauses the checklist rule. The office "Submit for review" still skips all of it.
- Approval: `ApprovalModes` (effective mode = stored mode, or manual when the plan lacks it), `RequestApprover::complete()` writes any approval
  (a person's or automatic, `approved_by_user_id` null). `finance_and_manager` keeps `approval_state` and the two approvers on the request;
  Finance uses `service-requests.approve-finance` (expense.approve full). Automatic modes run after the request is created, on the company
  host only when contracts are on (`ContractCoverage::enabled()` needs `Tenant::current()`, so tests that need it post to `onHost()`).
- A batch has `primary_asset_id` null and one `service_request_assets` row for each asset (2 to 200, one site, one category as the request).
  A request counts for an asset as primary asset or as a batch row (Asset 360 reads both). `ServiceRequest::updated` (AppServiceProvider) gives
  finished rows their `completed_at` when the request becomes Completed.
- Route binders `{template}`, `{version}`, `{sra}` are scoped by the signed-in user's company like the others.

## Preventive maintenance (B4)
- `Services/Maintenance`: `Frequency` (every date counted from the first due date, so 31 Jan, 28 Feb, 31 Mar), `PmSchedules` (create, edit, pause, resume, end,
  delete; throws `FormException` with the spec's code), `PmGenerator` (the job), `PmScheduleAccess` (Team Lead narrowing).
- `pm:daily` (hourly, acts at 06:00 company time; `--force`, `--tenant`, `--hour`) creates the due requests and sends `pm.overdue`. Plan key `pm_time`, no cap on schedules.
  The job sets `Tenant::current()` for its steps (contract coverage needs it) and always restores it. Dates are compared as plain dates, not instants.
- No duplicates: `pm_schedule_runs` (unique schedule + due date) and the unique key `(pm_schedule_id, pm_due_date)` on `service_requests`.
- A request from a schedule has source `pm_auto`, `created_by_user_id` null, the schedule's template (wins over asset and category), the pinned contract in
  `contract_id` (`ContractCoverage::onCreated` treats it as a pin), geofence only if the site always enforces it, signature waiver allowed.

## Multi-trade, vendors, depot (B6)
- `Services/Trades`: `MasterRequests` (create a master with 2 to 10 trades, derive the master status, approve and cancel the trades, `ids()` for expense and invoice roll-up),
  `Depot` (stages `received` to `returned`, intake and return checklists as `Checklists` slots, `assertReturned`), `VendorTechnicians` (vendor link and expense switch on the user form).
- A master's status is never set by hand: a `ServiceRequest::updated` listener (AppServiceProvider) recomputes it when a trade changes. A trade QC-passes straight to Completed;
  the master is the one invoice (`ExpenseLines::eligible` takes the trades' expenses; `InvoicesController` refuses a trade).
- A vendor technician is a technician user with `users.vendor_id`. `EnsureVendorActive` (web group) signs them out while the vendor is suspended. `may_log_expenses`
  (default true, set from the vendor's default on first assignment) is checked in `JobsController`. A master or another technician's trade is a 404 in the field app.
- A depot request keeps `service_mode = depot`, the workshop as its site and the client's site in `home_site_id`; the client portal shows the home site and the stage only.
- Plan keys `multi_trade`, `vendors`, `depot` (Professional and Enterprise). `/admin/vendors` and the vendor spend report are behind `vendors`.

## Reports, summaries, preferences (B8)
- `Services/Reports`: `Figures` (dashboard figures, `null` = "No data yet"), `ReportData` (the four reports as arrays), `ReportAccess` (Team Lead and client narrowing),
  `ReportPdf` (dompdf; branded header only with `brand_pdf`, colour from `tenants.primary_color`). Plan keys `reports_full` and `brand_pdf`.
- `NotificationService::notify()` writes the in-app row always, then follows `Services/Notifications/Preferences` for email: `instant` sends, `hourly_digest` and `daily_digest`
  hold a `notification_digest_items` row (sent by `digest:send`, hourly, daily at the user's own time), `off` skips. Critical events (`sr.assigned`, `sla.breached` only) are never
  delayed and the last instant channel cannot be turned off. No WhatsApp channel exists in any table.
- `summary:daily` (hourly) acts at each role's configured local time (`daily_summary_configs`, defaults 07:00 Monday to Friday), one summary for each user and day (`daily_summary_runs`),
  nothing when every block is empty.
- A route parameter named `{role}` is bound to the Role model (AppServiceProvider): use another name for a plain string.
- Memory: the full suite needs more than the 128M of the CLI php.ini. `phpunit.xml` sets `memory_limit` to 1024M, so `php artisan test` works as it is.

## SLA clock and checks
- `Services/Sla/SlaClock` is the one place that works out due times: `start()` (every place that starts a clock calls it, never `addMinutes`), `dueTimes()`, `recalculate()`,
  `pause()` / `resume()` (JobsController hold and resume; only a hold reason with `pauses_sla` pauses), `recalculateOpen()` (after the business hours or a holiday is saved, and `sla:recalculate`).
  A policy with `uses_business_hours` counts only the open minutes of `tenant_business_hours` (company zone, `tenant_holidays` skipped, defaults when a company has no rows);
  the contract's targets replace the policy's, the flag always comes from the policy. Due times are returned in the application time zone (Eloquent writes a Carbon in its own zone).
  Resume moves a due time out by the time that was left at the pause and adds the paused clock minutes to `sla_paused_minutes`.
- `Services/Sla/SlaMonitor` (`sla:check`, every minute, `withoutOverlapping`): `sla.warning` once at 80 percent of the target (due time minus the last 20 percent, in open minutes) and
  `sla.breached` once when the due time passes, for each request and target. The flags are `sla_response_warned_at`, `sla_resolution_warned_at`, `sla_response_breached`,
  `sla_resolution_breached`, set with a guarded update. A breach also marks the warning as handled. The response target stops at `first_punch_in_at`. Not checked: quoted, completed,
  awaiting invoice, cancelled, master requests, paused, reopened (`reopened_count`), or no clock. A message goes out only when the point passed within 120 minutes; an older one sets
  the flag and writes the audit row (`notified` false) with no message. Recipients: warning = Operations Manager and the request's Team Lead; breach = Operations Manager and Company Admin.
- The clock starts at approval (blueprint 7.1). Creation paths call `startAtCreation()`, which starts it only for an Emergency request or one created already approved; `RequestApprover::complete()`
  starts it otherwise (an Emergency request keeps its creation start), `startOnApproval()` covers the quote acceptances (portal and `/q/`) and the trades of an approved master. A pending request has no clock and `sla:check` ignores it.
- `slaState()` and the response SLA read `first_punch_in_at` (not `tech_accepted_at`).
- Contracts have no coverage-hours or 24/7 field. Contract SLA targets follow the business-hours flag of the MATCHED policy (Contracts spec D10 and CC-080 say so since the owner's decision), so a 24/7 Emergency policy stays 24/7 for contract customers.

## Contracts, assets, dispatch, usage and the request list (Release B follow-up)
- Contracts: `contract.renewed` (in-app, Company Admin and Operations Manager) is sent by `ContractDailyJob` on auto-renew only. The client's own page is `portal.contracts.*` (`ClientContractsController`):
  Client Admin only, plan and `contracts` feature, no value, terms, notes or documents, drafts and cancelled hidden; its route parameter is `{clientContract}` because `{contract}` is the office binder.
  The request list filters by `contract_id` (and `?choice=1`).
- Assets: the form has the photo (attachment kind `asset_photo`, public disk), the specifications (`specifications_json`, a list of name and value) and the meter (`tracks_meter`, unit, reading; the fields show and count only while it is on).
  `{asset}` is bound by the signed-in user's company in AppServiceProvider (a deleted asset is a 404).
- Dispatch: the workload colour limits are `dispatch_settings.workload_green_below` / `workload_amber_up_to` (defaults 60 and 90; `TechnicianSuggester::band($pct, $settings)`).
  A drag and drop that really changes the time emails the client's primary active contact through `JobRescheduledMail` (else the first active contact with an email). Emergency jobs come first in each calendar day.
- Usage: `usage:refresh` also recomputes `requests_count` (created in the month, children included, not those cancelled while Pending) and `storage_bytes`, then `Services/Usage/PlanLimitWarnings` sends `plan.limit_warning`
  to the Company Admin once at 80 and once at 100 percent for each limit and month (`warnings_json` on the counter).
- Request list and export: `ServiceRequestAccess::narrowVisible()` is the one place that narrows the list, its counts and `service-requests.export` (sr.view `record_level` and `view_own`: Team Lead = their team, Field Technician = assigned
  to them, anyone else = raised by them). The list is behind `level:sr.view` (never `view_own`, as everywhere); the export is behind `level:sr.export` (blueprint 7.9a: Company Admin and Operations Manager only, matrix key `sr.export`, a personal override can grant it), holds at most 5,000 rows and writes the audit row `service_requests.exported`. The button shows only with `sr.export`.
- Time zones: every model converts a Carbon to the application zone before Eloquent formats it (`Concerns\AppTimezoneDates`, brought in by `BelongsToTenant` and added to the other models) and `App\Support\AppTimezoneMySqlConnection` does the same for query values, so a Carbon in any zone is safe to save or bind.
  `times:fix-pm-eta` (needs `--confirm`, writes a backup, `--revert=`) corrects maintenance arrival times saved before that rule.
- Gotchas found on the way: (the Carbon zone rule above replaced the old per-place conversions);
  public properties of a Mailable override the view data of the same name; a Blade `@php(...)` line pairs with the next `@endphp`, so use `@php ... @endphp` blocks.

## Feedback, message log and support sessions
- Feedback: completing a top-level request sends the client contact `FeedbackRequestMail` (event `sr.completed`) with a one-use secure link (`FeedbackLinks`, `SecureLinkService::createFor`, `secure_links` entity columns, valid `tenant_settings.feedback_link_days`, default 7).
  Public `/feedback/{token}` (throttle 20 a minute, 410 page when used or expired, 404 on another company's address) takes stars 1 to 5, a 0 to 10 score and a comment; the answer is on `service_requests.client_*` and the request page; `feedback.received` goes to the Operations Manager (Urgent for 1 or 2 stars or a score of 0 to 6).
- Message log: every email goes through `Services/Messaging/MessageLogger` (table `message_logs`, ids only, errors cleaned, never a token or code). `/manage/message-log` is behind `level:notification.manage` (Company Admin retries, Operations Manager reads).
  A failed row is retried only when it can be built again from ids (notification, reschedule, feedback with a NEW link, quote, invoice, overdue); invitations and sign-in links are not retryable. Each retry writes `message.retried`.
- Support sessions (`Services/Platform/Impersonation`, `impersonation_sessions`): owner and support, reason 10 to 255, 30 minutes (2 hours at most), one open session for each platform user, handoff through `CentralLoginController::handoff($user, false, ['impersonation_session_id' => ..])`,
  `EnforceImpersonation` (web middleware) ends it at expiry and blocks passwords, two-factor, user and role changes, invoice approve, void, send, payments, write-offs and exports. Start and end go to the platform audit (`'global' => true`, no company) and the company audit; every company audit row during the session carries `impersonation_session_id`.
  `impersonation:expire` runs every minute. Known: `AuditLogger::verifyChain()` never passes (it hashes `created_at` in another format than the write); nothing calls it.

## Conventions
- Every company table has `tenant_id`. Many models have the global scope (trait `App\Models\Concerns\BelongsToTenant`: Client,
  ClientContact, Contract, ServiceRequest, Vendor, Invoice, Quote, Payment, ServiceRequestExpense, GeofenceOverrideRequest,
  FieldEventLog ...). InvoiceEvent, ExchangeRate, User, Role, UserDevice and more do NOT,
  so always add `where('tenant_id', ...)`. The scope only works while `Tenant::current()` is set: jobs and commands have no company,
  so pass tenant ids explicitly there.
- Route binding for `{invoice}`, `{quote}`, `{payment}`, `{expense}`, `{override}`, `{log}`, `{user}` and `{role}` is scoped in
  `AppServiceProvider` by the signed-in user's company (kept for models with no global scope; the company is also known by then, see the next point).
  Another company's id is a 404. `{teamLead}` (a User) is bound the same way. User and Role are bound this way only, with no global scope, because sign-in looks users up across
  companies. UserDevice is not scoped: its revoke checks that the device is the caller's own. The public `/i/{token}` and `/q/{token}`
  links look up by token: they work on the link's own company address and on the root address, and 404 on another company's.
- Middleware order: `TenantResolver` runs BEFORE `SubstituteBindings` on every web route (`prependToPriorityList` in `bootstrap/app.php`; order: session, sign-in check, resolver, bindings),
  so every BelongsToTenant model loaded from the URL is scoped by company. With no company on the host (root address) nothing is narrowed: only platform and central routes live there.
  Unscoped models loaded from a URL: `{device}` (checked against the caller), `{tenant}` (platform console), `{user}`, `{role}`, `{teamLead}` (explicit tenant binders). Check this after adding a model to a route.
  Guests who open `/portal/*` are sent to `client.sign-in` (the intended address is kept); everyone else to `login` (`redirectGuestsTo`, `bootstrap/app.php`).
- Audit: only `AuditLogger::log($action, ['tenant_id' =>, 'item_type' =>, 'item_id' =>, 'old' =>, 'new' =>])`. Append only, hash chained.
  Never write `activity_logs` directly and never log passwords, codes or tokens.
- Staff messages (in-app and email) go through `NotificationService::notify()`, events defined in `config/notifications.php`.
  Client-facing email with a secure link uses a Mailable (`InvoiceSentMail`, `QuoteSentMail`, `InvoiceOverdueMail`). WhatsApp is paused (see "Emails and event messages").
- Excel exports go through `Services/Export/XlsxExporter` (phpspreadsheet; it writes the audit row). Other file features are CSV imports.
- Money is rounded with `Services/Currency/Money` (half up, the currency's decimals). The quote and invoice builders work totals out with
  `Services/Billing/DocumentTotals` (blueprint 7.7a), and only for DRAFTS: a sent, approved, paid or void document keeps its stored
  totals. A document's currency and rate: `Services/Currency/DocumentCurrency` (RATE_LOCKED once a quote is sent or an invoice is
  submitted). Expense lines: `ExpenseLines`. Dashboard money: `BaseCurrencyFigures`. Other older invoice code still uses `round(x, 3)`.
- Rates: `exchange_rates` (base units per 1 foreign unit), provider chosen in `config/rates.php`.

## UI rules
- Company users: layouts include `layouts/_theme.blade.php` (colour tokens, white page background, theme dock) and `layouts/_brand_font.blade.php`
  (company font). New pages extend `layouts.admin` (office), `layouts.technician*` (field) and must not hardcode colours or fonts.
- Page backgrounds are white. Cards and tables are white.
- Platform owner console: mint (default #4FC9A0; the sign-in and two-factor pages use mint #7FE0B9), no yellow anywhere. `layouts/admin.blade.php` includes `_theme` (with the dock) and `_brand_font`
  for a company, and for the platform with `brandOverride` = the platform user's colour or mint. The dock's "Theme" button opens a colour and font popup (`brandEdit`), saved on
  `platform_users` (`brand_color`, `font_key`) by `Platform\BrandingController` (`admin.platform.branding.update`, `PUT /admin/platform/branding`). `_brand_font` takes an optional `fontKey`.
- Company colour and font everywhere a client sees them: `EmailContent::brand($tenant)` (accent, ink, soft tint, fonts; no colour or an old default yellow/pink/blue = Sage #5E8A3C). Emails use it
  through the shared layout; stand-alone public and portal pages (`public/quote`, `invoice`, `feedback*`, `client/*`) include `layouts/_public_brand` (CSS variables `--brand`, `--brand-ink`, `--brand-soft`)
  and never hardcode yellow. New companies and the Branding colour picker start at Sage.

## Emails and event messages (WhatsApp paused)
- One shared layout `emails/base` and `emails/base-text` (company name and logo, heading, one intro line, details table, one button, footer with company contact and "This is an automated message"). Mailables
  pass `['m' => EmailContent::frame(...)]`. `EmailContent` (`Services/Notifications`) holds the words for every email, works from plain arrays, and gives the company block (`company()`, `labels()`, `brand()`).
- Client contact: `ClientEventMail` (`sr.created`, `sr.approved`, `sr.cancelled`, `sr.assigned`, `sr.eta_changed`, `tech.arrived`, `depot.received`, `depot.ready_for_return`), built from request, event and contact id;
  retryable from the message log. Its button opens the portal request only when the contact has an active client sign-in (same email and client; a viewer needs the site in scope); otherwise a mailto to the company.
  Already email: `sr.completed` (FeedbackRequestMail), `quote.sent`, `invoice.sent`, `invoice.overdue`, the dispatch visit-moved mail (the client's `sr.eta_changed`). Codes: `OneTimeCodeMail` (login, signature; never kept in the log).
- Staff and field go through `NotificationService::notify()` (the email is `NotificationMail`; its details table comes from `data['sr_id']`, which `notify()` fills from `sr_number`). New events: `sr.cancelled`,
  `sr.eta_changed`, `sr.reassigned`, `sr.rework`, `field.geofence_override_decided` (TeamController).
- `Services/Notifications/EventMessages` is the one place that decides. `ServiceRequest::created` and `::updated` listeners in AppServiceProvider call it, so every screen and job sends once. `sr.assigned` (technician)
  fires only on a first assignment; a change of technician is `sr.reassigned` to the old and new one. Trades of a master send no client email except `tech.arrived`. The person who did the action is not emailed about it.
  `EventMessages::$quietClientEta` is set by the dispatch calendar, which emails the client itself.
- Mute switch: `EventMessages::mute(fn)`, and the commands in `MUTED_COMMANDS` (`db:seed`, `migrate:fresh`, `migrate:refresh`, `sla:recalculate`, `times:fix-pm-eta`, `demo:remove-billing`, `usage:refresh`) are muted from start
  to finish (CommandStarting and CommandFinished listeners). Muted: the new events, the feedback link on completion, the `sr.assigned` technician message, the master trade-complete notice. Add every new bulk command to the list.
  `pm:daily` is not muted. `DemoBillingSeeder` mutes itself.
- Queue: `MessageLogger::send()` queues the classes in its `QUEUED` list through `Jobs/SendLoggedMail`: the log row is written as `queued`, the worker sends and records sent or failed (`MessageLogger::deliver`, 3 tries, retries after
  1 and 5 minutes). The job holds the message (it may hold a link or code); the log never does. Invitations, sign-in links and the digest are still sent at once. Without a queue worker nothing is delivered.
- Local preview: `/dev/emails` (`Dev\EmailPreviewController`), registered only when `APP_ENV=local` and returns 404 anywhere else. Sample data, nothing is sent. It shows the mailto version of client emails.

## Scheduled commands (routes/console.php)
- The server needs the Laravel scheduler cron (`* * * * * php artisan schedule:run`) and a queue worker (QUEUE_CONNECTION=database). The queue also carries all emails (`SendLoggedMail`).
- `usage:refresh` daily: recompute each company's usage counters.
- `contracts:daily` hourly: acts at 02:00 company time. Expire, expiring soon, alerts, auto-renew, roll call periods.
- `rates:fetch` at 00:30, 06:00, 12:00, 18:00: stores today's rate for turned-on currencies. Only missing pairs are fetched.
- `sla:check` every minute: SLA warnings and breaches. `sla:recalculate` is a one-time command (open requests on a business-hours policy).
- `invoices:overdue-daily` hourly: acts at 09:00 company time. Marks invoices overdue, sends reminders once for each number.

## Tests
- Existing migrations do not run on SQLite, so `phpunit.xml` uses the MySQL database `fsm_saas_test` (create it once). Run `php artisan test`
  (add a path to run part of it). `Tests\TestCase` and `BillingTestCase` skip any database whose name does not end in `_test`,
  because RefreshDatabase drops every table.
- Set `MAIL_MAILER=log` locally before running reminders or invoice emails, so no real email is sent.
- The full suite (212 tests, about 9 minutes) passes. `Security/RouteAuthTest` fails when a route is outside sign-in and not on its short
  public list: add to that list only on purpose. `Security/ScreenPermissionsTest` checks every gated screen against the signed matrix;
  `Security/UserAdminScopeTest` and `Security/ServiceRequestActionsTest` check the record_level narrowing.
- Images: `php artisan storage:link` must exist (on Windows it is a junction). The public disk `url` in `config/filesystems.php` is the relative `/storage`, so images load from the page's own host and port.
- Addresses: local dev port is 8000 (`APP_URL`, `AIYWAH_ROOT_HOST`, `AIYWAH_PLATFORM_HOST`). Production: `APP_URL=https://<root host>` with companies at `{slug}.{root host}`, wildcard DNS and certificate, no port.
- On Windows, composer install can fail with a file lock in phpspreadsheet. Exclude the project folder from Windows Defender and run it again.

## Known issues, not yet fixed
- Approval rules: the amount limit column is stored and shown but nothing applies it (no amount exists when a mode runs).
- Route binding of company models relies on the model scope, which does nothing on the root address (no company). Only platform and central routes live there.
- Undoing a write-off, the dispatch settings and (with UserAdminScope) staff user management are for the Company Admin by role code:
  no permission key exists for them. The client portal checks the client roles (client_admin, client_viewer) by code on purpose.
- `mark-review` (office "Submit for review", sr.qc full) moves In Progress to Pending Review WITHOUT the proof lock: it does not check the
  category's proof rules, close an open punch, compute work minutes or notify anyone. It is a manager override; the technician's punch-out is
  the normal path.
- Uploaded-PDF quotes and invoices are always in the base currency (the spec only covers the builders).
- `resources/views/admin/invoices/invoice.blade.php` is not used by anything and still formats money with the company currency.
- `/login/technician` and `/login/team-lead` are registered twice in routes/web.php (harmless: route:cache still works).
- `app/Models/ContractRequest.php` is a stray, unused copy of the contract form request (composer warns about its namespace).
- `TenantResolver` still tests `$user->is_platform_admin`, an attribute that no longer exists (always false).
