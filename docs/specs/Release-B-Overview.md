# Aiywah FSM: Release B overview

**Everything planned for Release B, in one document** | Draft for review | Based on Blueprint v3.17 | Prepared October 24, 2026

## 1. What Release B is

Release B is the "Depth" release. Release A gives a company the daily loop: request, approval, assignment, proof, quality check, quote, invoice and measurement. Release B adds what bigger and more organised customers need on top of it: knowing their equipment, working under contracts, planned maintenance, smarter dispatch, bigger jobs, money in several currencies, proper reports, and a way for new customers to buy and set themselves up.

Release B starts after the Release A pilot. **Nothing in it is approved yet.** Unlike Release A, it has no module specs. This document describes the scope as the blueprint states it, and the register (`Aiywah-FSM-Release-B-Scope-Register.xlsx`) breaks it into 124 items in 10 slices. Every slice will get its own spec and owner decisions before building, the same way as Release A.

**Release B ships when its exit gate passes:** acceptance scenarios 11 to 17 pass, and the pilot replay set (real pilot jobs saved during Release A) runs without unlogged gaps. I propose adding scenarios 20 to 23 for the areas that have none (section 8).

## 2. The ten slices

| Slice | Module | What it gives | Items | Needs first |
|---|---|---|---|---|
| B1 | Assets and QR | Equipment records, QR codes, asset 360 view, import, requests raised by scanning | 12 | Release A only |
| B2 | Contracts and coverage | AMCs and warranties, automatic coverage matching, call caps, renewals | 13 | B1 |
| B3 | Forms, approvals and batch inspections | Form builder, checklists on the phone, automatic approval modes, one request for many assets | 14 | B1, B2 |
| B4 | Preventive maintenance | Schedules that create jobs by themselves, with a calendar | 11 | B1, B2, B3 |
| B5 | Dispatch and scheduling | Smart technician suggestions, workload view, dispatch calendar | 10 | Release A only |
| B6 | Multi-trade, vendors and depot | Parent and child jobs, subcontractors, workshop repairs | 14 | B3 |
| B7 | Currency and payments | Quotes and invoices in other currencies, payments ledger, aging, overdue reminders | 14 | Release A only |
| B8 | Reports, summaries and preferences | PDF reports, extra dashboard figures, daily summaries, notification preferences | 16 | B2, B4, B5, B6, B7 |
| B9 | Self-service onboarding | Website checkout, subscription billing, guided WhatsApp sign-up | 15 | Release A only |
| B10 | Hardening and acceptance | Scenarios 11 to 17, the pilot replay set, regression, security and load | 5 | B1 to B9 |

## 3. What each slice does

### 3.1 Assets and QR (B1)

- An asset record holds a code, name, parent asset, category, make, model, serial number, place in the site, install date, warranty expiry, status, meter settings, photo and notes. A parent and its children must be in the same site. A decommissioned asset cannot be chosen for new requests but keeps its history.
- **Asset 360** shows identity, warranty and contract cover, the full history of requests and checklists, upcoming maintenance, lifetime service cost and photos.
- **QR codes.** Each asset has a random QR code that opens it for signed-in users of that company only. A technician can scan it to raise a request, which Release A does not allow.
- Assets can be imported in bulk with row-by-row error checks before anything is saved. Starter gets a basic asset list. Asset 360, QR and import are Professional and above.

### 3.2 Contracts and coverage (B2)

- A contract holds a number, client, type (AMC, warranty, ad-hoc or defects liability), status, dates, SLA overrides, call cap and period, value, payment terms and auto-renew, plus the sites and categories it covers.
- **Matching.** When a request is approved, the system finds the client's active contracts that cover the site and the category. If several match, the request stays Pending until a manager chooses, and the choice is recorded. An asset or maintenance schedule can pin a contract, which is then used without asking.
- **Call cap.** A call counts when the request is approved under the contract. At the cap the manager chooses: count it anyway, treat it as goodwill, or treat it as out of coverage (a quote).
- A daily job marks contracts as expiring soon and warns at 90, 60 and 30 days. Contract profitability is Release C.

### 3.3 Forms, approvals and batch inspections (B3)

- **Form builder.** Fields: text, number with unit and limits, dropdown, checkbox, yes or no, date, photo and signature. Fields can be required or shown only when another answer matches. A template version moves from draft to published to retired and a published version never changes. A request is bound to the published version when it is created.
- Checklists are filled on the phone and the proof lock can require them. Starter has notes only. Professional can have up to 10 templates and Enterprise has no limit.
- **Approval modes.** Manual (Release A), automatic if in contract, always automatic, or finance and manager, chosen by category, priority and coverage.
- **Batch requests.** One request covers many assets. Each asset has its own inspection status. Punch out stays blocked until every asset is final, and one signature closes the batch.

### 3.4 Preventive maintenance (B4)

- A schedule belongs to a site and one asset or a batch. It sets category, calendar frequency, lead days, priority, checklist template, default technician and an optional pinned contract.
- A job runs every day at 06:00 company time and creates a request for each schedule due within its lead days. It never creates duplicates, and it can skip a schedule while an earlier request from it is still open. A batch schedule creates one request with all its assets.
- A maintenance job uses the contract pinned on its schedule. With none pinned and several matching, it waits in Pending for a manager. A calendar shows what is due. Meter-based schedules are Release C.

### 3.5 Dispatch and scheduling (B5)

- **Smart suggestions** rank eligible technicians when assigning: skill match 35, availability 25, workload 20, proximity 10 and first-time-fix rate 10 by default, configurable for each company. Manual assignment always stays.
- **Proximity** is the distance from the technician's last known location or home base, shown with its age and never presented as live tracking.
- **Workload** compares scheduled minutes in the next 7 days with daily capacity: green below 60 percent, amber 60 to 90, red above 90. Time off reduces availability.
- A **dispatch calendar** lets a manager drag a job to change its arrival time. The change is recorded and the client is told. Emergency jobs sit at the top of every queue. Route optimisation is Release C.

### 3.6 Multi-trade, vendors and depot (B6)

- **Multi-trade.** A master request creates child requests, each with its own technician, punches and proof rules. The master has no technician and its status follows its children. Child expenses roll up to the master for billing.
- **Vendors.** A subcontractor technician belongs to a vendor company, sees only their own jobs and no client rates, and cannot log expenses until the admin switches it on for that person. Vendor expenses are reported separately.
- **Depot jobs.** A company can own a workshop site. A category can allow depot work. Equipment is taken in and handed back with intake and return checklists. There is no warehouse or parts stock.

### 3.7 Currency and payments (B7)

- **Multi-currency.** A quote or invoice can use another currency. The rate comes from a daily rates provider and Finance can overwrite it on that document. The rate is fixed when the quote is sent and carries to the invoice. Payments are recorded in the invoice currency. Technician expenses stay in the base currency. Dashboards and reports show the base currency.
- **Payments ledger and aging.** A page lists every payment. The aging report shows unpaid balances at 30, 60 and 90 days.
- **Overdue reminders.** Clients and Finance get WhatsApp or email reminders for overdue invoices on a schedule the company sets.

### 3.8 Reports, summaries and preferences (B8)

- **PDF reports:** Monthly SLA, Technician Scorecard, Completion Certificate and Contract Performance. **Excel:** Expense Summary (filterable by own staff or vendor). Scheduled delivery is Release C.
- **More dashboard figures:** callbacks (jobs reopened inside the window), technician utilisation, maintenance compliance and the reactive against planned ratio, plus vendor spend.
- **Daily summaries** go to the roles the admin chooses, by WhatsApp with an email fallback.
- **Notification preferences and digests.** Each person can set channel and frequency for each event: instant, hourly digest, daily digest or off. Events the platform marks critical (a new assignment and an SLA breach) cannot be turned off.

### 3.9 Self-service onboarding (B9)

- **Website checkout.** A visitor opens the product page on aiywah.com, chooses a package and billing period, fills in the company name, address, country and admin details, pays on the provider's hosted checkout, and the company is created when payment is confirmed. The country picks the provider: Tap for the Gulf, Razorpay for India, Stripe elsewhere. The buyer becomes the first Company Admin and receives an invitation. This is already designed in the Tenancy spec (decisions D24 to D31).
- **Subscription billing.** Trials end by themselves, renewals and failed payments are handled, and the Platform Admin sees subscriptions and payment events. Customers upgrading or cancelling by themselves comes after launch.
- **Guided WhatsApp sign-up** replaces pasting IDs and tokens. It needs Aiywah to be approved by Meta as a technology provider, which depends on Aiywah's own verified Meta business account.

## 4. Plans and limits for Release B features

| Feature | Starter | Professional | Enterprise |
|---|---|---|---|
| Basic asset list | Yes | Yes | Yes |
| Asset 360, QR, batch requests, bulk import | No | Yes | Yes |
| Contracts and AMCs | No | Yes | Yes |
| Time-based preventive maintenance | No | Yes | Yes |
| Form builder | Notes only | Up to 10 templates | Unlimited |
| Approval modes | Manual only | Plus automatic modes | Plus finance and manager |
| Smart assignment, calendar, workload | No | Yes | Yes |
| Multi-trade, vendors, depot jobs | No | Yes | Yes |
| Multi-currency quotes and invoices | No | Yes | Yes |
| Aging report and payments ledger | No | Yes | Yes |
| PDF reports and daily summaries | No | Yes | Yes |
| Branded PDFs | No | Yes | Yes |

The plan limits for users, requests a month and API rate stay as in Release A. Downgrading keeps all data and makes gated data read-only.

## 5. Who uses it

Release B adds permission keys to the same eight roles: `asset.view`, `asset.manage` and `asset.import`, `contract.view`, `contract.manage` and `contract.choose`, `pm.view` and `pm.manage`, and `vendor.manage`. The Company Admin and Operations Manager hold most of them. No new role is added.

## 6. The messages Release B adds

- **Events:** pm.generated, pm.overdue, contract.expiring, contract.cap_reached, sr.contract_choice_needed, invoice.overdue and the daily summary.
- **Four more mandatory WhatsApp templates:** pm_generated and pm_overdue (B4), invoice_overdue (B7) and summary_daily (B8). All four are already in Appendix C. The mandatory list grows from 18 to 22. Each needs Meta approval before its slice goes live.
- Billing notices for subscribers (trial ending, payment received, payment failed, plan changed) are new in B9 and need their own wording.

## 7. Build order and what depends on what

- **Wave 1:** B1 Assets, B7 Currency and payments and B9 Self-service onboarding. They need only Release A and can run in parallel.
- **Wave 2:** B2 Contracts (needs assets) and B5 Dispatch (needs nothing from Release B).
- **Wave 3:** B3 Forms, approvals and batch inspections. It changes the Field View, so that spec is updated first.
- **Wave 4:** B4 Preventive maintenance and B6 Multi-trade, vendors and depot.
- **Wave 5:** B8 Reports, summaries and preferences, because their figures come from five other slices.
- **Wave 6:** B10 Hardening and acceptance.

Assets are the base for contracts, forms, batch inspections and maintenance. Form templates feed maintenance, depot checklists and certificates. Currency and payments, dispatch and onboarding stand alone. The full dependency list is on the Dependencies sheet of the register.

## 8. How Release B is proved ready

| # | Scenario | Pass condition (short) |
|---|---|---|
| 11 | Batch request of 20 assets with mixed outcomes | Punch out blocked until every asset is final. One signature closes the batch |
| 12 | Several contracts match | The request waits for a manager. A pinned contract is used without asking. A reached cap forces a choice |
| 13 | Preventive maintenance generation | A rerun creates no duplicates. A batch makes one request. Lead days are honoured |
| 14 | Multi-trade parent with three children, one reworked | The parent status rolls up. Billing adds the children |
| 15 | Subcontractor technician | Sees only own jobs, no client rates, cannot log expenses until switched on. Vendor expenses reported separately |
| 16 | Depot job from intake to return | Workshop site, intake and return checklists, normal lifecycle |
| 17 | Multi-currency quote and invoice | Rate filled and overridable, fixed when sent, payment in the invoice currency, dashboards in the base currency |

**Proposed additions (not approved):** scenarios 20 to 23 for dispatch and workload, reports and summaries, preferences and digests, and checkout and billing. Release C keeps 18 and 19. The Tenancy spec already has tests AT-70 to AT-78 for checkout.

## 9. What Release B needs from Release A

- The set-up job and plan gate (for checkout), the event engine and outbox (for digests, summaries and reminders), the quotes, invoices and payments (for currency and the ledger), the assignment and Field View location (for dispatch), and the import engine (for assets).
- The schema already holds the depot fields, `daily_summary_configs`, `notification_preferences`, `user_skills`, the home base fields and `user_time_off`.
- **Three Field View changes are needed:** raising a request from a QR scan, checklists, and batch screens each need a new offline event type. The Field View spec must be updated before B1 and B3 start.
- **The pilot replay set** must be recorded during the Release A pilot. What to record has to be decided before the pilot starts.

## 10. What is not in Release B

- **Release C:** meter-based maintenance, workflow rules and custom statuses, route optimisation, webhooks and accounting integrations, published API documentation, custom domains and white label, scheduled reports and contract profitability.
- **After launch:** customers upgrading, downgrading or cancelling by themselves.
- **Never in this plan:** warehouse and parts stock, rate cards, and installation projects with milestones and Gantt (Phase 2).

## 11. Risks and open questions

- **Two approved Release A rules change:** a multi-trade master bills its children together (Release A has one invoice for each request), and multi-currency changes the single base currency rule (Quotes and invoices D20). Both need their own decisions.
- **Four areas have no scenario** in the blueprint, so they would have no end-to-end proof.
- **Subscription rules are thin:** the failed-payment grace period and what suspension does are not specified.
- **Owner actions:** Aiywah's own verified Meta business account (needed for guided sign-up), the Stripe, Tap and Razorpay accounts, package prices and trial length, the exchange rate provider, product page content and the links to the terms and privacy policy, and the CA check on invoicing subscribers.
- **Questions for you** are on the Assumptions sheet of the register: for example whether checkout should come first, whether QR labels are wanted, the overdue reminder schedule, and whether any Release C feature is essential to the pilot customer.
- **Size and dates:** the register gives only a relative size for each slice. The developers give real estimates after reading each spec.

## 12. The documents

| Document | File |
|---|---|
| Release B scope register (124 items) | `Aiywah-FSM-Release-B-Scope-Register.xlsx` |
| Planning overview with slices, waves and risks | `Release-B-Plan-Overview.md` and the Word file |
| This document | `Release-B-Overview.md` and the Word file |
| Blueprint sections that define Release B | 7.2 to 7.9, 9.4 to 9.7, 6 and 13.3 of `blueprint.md` |
| Checkout design | `TEN-ACC.md` section 11 |
