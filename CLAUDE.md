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
  unknown id is a 404 before the 403. Screens without a `level:` or their own check are a bug: add one.
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

## Conventions
- Every company table has `tenant_id`. Many models have the global scope (trait `App\Models\Concerns\BelongsToTenant`: Client,
  ClientContact, Contract, ServiceRequest, Vendor, Invoice, Quote, Payment, ServiceRequestExpense, GeofenceOverrideRequest,
  FieldEventLog ...). InvoiceEvent, ExchangeRate, User, Role, UserDevice and more do NOT,
  so always add `where('tenant_id', ...)`. The scope only works while `Tenant::current()` is set: jobs and commands have no company,
  so pass tenant ids explicitly there.
- Route binding for `{invoice}`, `{quote}`, `{payment}`, `{expense}`, `{override}`, `{log}`, `{user}` and `{role}` is scoped in
  `AppServiceProvider` by the signed-in user's company (binding runs before `TenantResolver`, so `Tenant::current()` is not set yet).
  Another company's id is a 404. User and Role are bound this way only, with no global scope, because sign-in looks users up across
  companies. UserDevice is not scoped: its revoke checks that the device is the caller's own. The public `/i/{token}` and `/q/{token}`
  links look up by token: they work on the link's own company address and on the root address, and 404 on another company's.
- Audit: only `AuditLogger::log($action, ['tenant_id' =>, 'item_type' =>, 'item_id' =>, 'old' =>, 'new' =>])`. Append only, hash chained.
  Never write `activity_logs` directly and never log passwords, codes or tokens.
- Staff messages (in-app and email) go through `NotificationService::notify()`, events defined in `config/notifications.php`.
  Client-facing email with a secure link uses a Mailable (`InvoiceSentMail`, `QuoteSentMail`, `InvoiceOverdueMail`). WhatsApp is not built.
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
- Platform owner console, platform sign-in page (`auth/platform-login`) and platform two-factor pages: yellow (#FFC61A), one fixed
  font (DM Sans and Figtree), no `_theme`, no `_brand_font`, no theme dock.
  `layouts/admin.blade.php` includes `_theme` and `_brand_font` only inside `@if (Tenant::current())`: keep that condition.

## Scheduled commands (routes/console.php)
- The server needs the Laravel scheduler cron (`* * * * * php artisan schedule:run`) and a queue worker (QUEUE_CONNECTION=database).
- `usage:refresh` daily: recompute each company's usage counters.
- `contracts:daily` hourly: acts at 02:00 company time. Expire, expiring soon, alerts, auto-renew, roll call periods.
- `rates:fetch` at 00:30, 06:00, 12:00, 18:00: stores today's rate for turned-on currencies. Only missing pairs are fetched.
- `invoices:overdue-daily` hourly: acts at 09:00 company time. Marks invoices overdue, sends reminders once for each number.

## Tests
- Existing migrations do not run on SQLite, so `phpunit.xml` uses the MySQL database `fsm_saas_test` (create it once). Run `php artisan test`
  (add a path to run part of it). `Tests\TestCase` and `BillingTestCase` skip any database whose name does not end in `_test`,
  because RefreshDatabase drops every table.
- Set `MAIL_MAILER=log` locally before running reminders or invoice emails, so no real email is sent.
- The full suite (212 tests, about 9 minutes) passes. `Security/RouteAuthTest` fails when a route is outside sign-in and not on its short
  public list: add to that list only on purpose. `Security/ScreenPermissionsTest` checks every gated screen against the signed matrix;
  `Security/UserAdminScopeTest` and `Security/ServiceRequestActionsTest` check the record_level narrowing.
- On Windows, composer install can fail with a file lock in phpspreadsheet. Exclude the project folder from Windows Defender and run it again.

## Known issues, not yet fixed
- Approval rules: the amount limit column is stored and shown but nothing applies it (no amount exists when a mode runs).
- `service_requests` route binding (`{service_request}`) relies on the model scope only: with no company on the host (root address) it is not narrowed.
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
