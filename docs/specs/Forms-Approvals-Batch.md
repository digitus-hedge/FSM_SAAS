# FAB: Forms, approvals and batch requests (requirements)

**Module requirements for Release B, slice B3** | Covers register items FRM-01 to FRM-10 and BAT-01 to BAT-04 | Based on Blueprint v3.17 | **Draft for developer review. Decisions proposed, waiting for owner approval** | November 19, 2026

Labels in this document use the default words (service request, quote, invoice, client). In the product every renamed word goes through the `term()` helper.

## 1. Purpose and scope

This module gives the company three things: **a checklist the technician fills in on the job**, **a choice of how a request gets approved**, and **one request that covers many assets of one site** (a fire-alarm round, 20 extinguishers). In Release A the request has no checklist, every request is approved by hand (`service_categories.approval_mode` is forced to `manual`) and one request means one asset.

**In scope (14 register items)**

- **Forms:** FRM-01 form templates, FRM-02 form builder, FRM-03 versions, FRM-04 binding a template to a category, an asset or a schedule, FRM-05 the checklist in the Field View with its offline event and the proof lock, FRM-06 checklist results on the request and on the asset 360, FRM-07 approval modes, FRM-08 approval rules page, FRM-09 API, FRM-10 tests.
- **Batch:** BAT-01 a batch request over many assets (`service_request_assets`) with an inspection status for each, BAT-02 creating a batch from many assets of one site, BAT-03 batch screens in the Field View, BAT-04 tests (scenario 11 of blueprint 13.3).

**Out of scope for this module**

- Scheduled (preventive) requests that use a template: slice B4 uses what this module builds.
- Multi-trade masters, vendors and depot checklists (intake and return): slice B6. It reuses the builder and the versions defined here.
- Form templates in the client portal. Clients do not fill forms.
- Conditional logic beyond one "show if" rule per field (D6).
- Free scripting, calculated fields, and field types not in D5.
- Reports on checklist answers: slice B8.

**Needs first:** B1 assets, B2 contracts (the `auto_if_in_contract` mode reads coverage), the permission matrix (`form.manage`, `workflow.manage`), the plan gate. **Needed by:** B4 (schedules carry a template), B6 (intake and return checklists, children of a master), B8 (checklist figures).

**Sources.** Blueprint v3.17 sections 7.1, 7.6, 7.8, 9.2, 11.7, 11.6 (`service_request_assets`), 13.3 (scenario 11), the Release B scope register (items above, assumptions 1 to 6), the Release B overview, the Currency-and-Payments spec for format, and the code as it stood on November 19, 2026.

**Gaps and conflicts found between the documents and the code.** Each has a decision below.

1. **The tables do not exist.** `form_templates`, `form_template_versions` and `approval_rules` are in the blueprint only. The columns that point at them already exist: `service_categories.active_form_template_id`, `approval_mode`, `require_checklist`, `require_per_asset_checklist`, `allow_multi_asset_batch`; `service_requests.form_template_version_id`, `sr_checklist_payload`, `is_master_sr`; `service_request_assets.inspection_status`, `form_template_version_id`, `asset_checklist_payload`. Only the three tables are new (SCH-B3-01 to 03). The new foreign keys are added in the same migration.
2. **`approval_mode` is forced to `manual`** by `ServiceCategoryRequest`. This module lifts that rule, by plan (D9).
3. **The proof lock lives in the technician punch-out** (`Technician/JobsController`, reading `proof_rules_snapshot`). The checklist and the batch rule are two more conditions in the same place. The office "Submit for review" button skips the proof lock today (CLAUDE.md, known issues). This module does not change that, so it skips the checklist too (D12).
4. **There is no checklist field type for a signature or photo in the Field View as a form field.** Photos and signature already exist as request proof. D5 keeps the two apart: a form field of type photo or signature stores its file like proof does, but does not replace the request's own proof rules.
5. **Offline events.** The Field View sends events (`FieldEventLog`). The checklist needs one new event type, `checklist_saved` (D10).
6. **The event engine is not built.** Staff messages go through `NotificationService::notify()`. This module fires its events (section 7) that way and does not add an outbox.
7. **Blueprint 7.6 says one invoice for a request.** A batch is one request, so one invoice. The multi-trade change of that rule belongs to B6.
8. **No technician-facing blueprint text for a batch signature.** D15 sets it.

## 2. Decisions to review before building

The blueprint and the register did not settle these. Each has my recommendation. Write Approve or your change in the last column. Nothing here is final until you answer. D9 (plan limits) and D14 (batch punch-out) are the owner's calls.

| # | Decision and recommendation | Your answer |
|---|---|---|
| D1 | **Who manages forms.** Anyone with `form.manage` at a changing level (Company Admin and Operations Manager) creates and edits templates. `workflow.manage` (Company Admin full, Operations Manager view only) governs the approval rules page. Technicians and Front Desk see a form only when filling the job. **Recommended: approve.** | |
| D2 | **Template limits (blueprint section 6).** Starter: notes only, no builder (the Field View shows no checklist, the category "Require checklist" switch is hidden). Professional: up to 10 templates. Enterprise: unlimited. No limit on fields is added (blueprint has none). The plan key is `forms` (the blueprint name; the unused `form_builder` key of `PlanSeeder` is left alone), a boolean added by migration, with the template count in the same plan record as `form_templates_limit` (10 for Professional, none for Enterprise). No other plan limit is added. **Recommended: approve.** | |
| D3 | **Template and version.** A template has a name, a description and a status (active or archived). Its content lives in versions. A version is `draft`, `published` or `retired`. A template has at most one draft and one published version. Editing a published version is not possible: "Edit" opens a new draft copy. Publishing a draft retires the previous published version. **Recommended: approve.** | |
| D4 | **What a request binds to.** At creation a request stores the id of the published version of its template (the category's `active_form_template_id`, or the asset's or schedule's template when set). Later versions never change a request that already exists. A request created while the template has no published version has no checklist. **Recommended: approve.** | |
| D5 | **Field types.** `text`, `number` (with unit, minimum and maximum), `dropdown` (2 to 20 options), `checkbox`, `yes_no`, `date`, `photo` (1 to 5 files), `signature`. Each field has a label (up to 120 characters), a key (generated, never reused), "required" and an optional help text (up to 300). Sections group fields (up to 10 sections). A `yes_no` field may be marked "a No fails the inspection" (D7). **Recommended: approve.** | |
| D6 | **Show if.** A field may have one condition on an earlier field of the same version: equals, not equals, is yes, is no, greater than, less than. A hidden field is never required and its answer is dropped on save. Cycles are impossible because a field can only look backwards. **Recommended: approve.** | |
| D7 | **Pass or fail.** A checklist has no score. It has a result: `ok` when every required field is answered and no "No fails" field is No or out of its number range, otherwise `issues`. The result is shown on the request and the asset 360. For a batch asset, `issues` suggests `failed_unsafe` or `needs_parts` but the technician picks the inspection status (D13). **Recommended: approve.** | |
| D8 | **Binding a template** (FRM-04). Priority: the schedule's template (B4), then the asset's, then the category's. The category binding is the "Checklist template" selector on the category page, and the existing switches `require_checklist` and `require_per_asset_checklist` decide if it blocks punch-out. The asset binding is a new "Checklist template" selector on the asset form (`assets.form_template_id`, SCH-B3-04). **Recommended: approve.** | |
| D9 | **Approval modes and plans** (FRM-07). `manual` for all plans. `auto_if_in_contract` and `always_auto` from Professional. `finance_and_manager` (two approvals, Finance and Operations Manager, in any order) from Enterprise. The keys are `approval_auto` and `approval_finance` (D2). A plan that does not include a mode shows it greyed out with "Not in your plan". A company that drops to a lower plan keeps the stored value but runs `manual` until the plan returns (the request page says so). **Recommended: approve.** | |
| D10 | **The checklist offline.** The Field View saves answers on the device. Each save is a `checklist_saved` event with the request id, the asset id (batch only), the version id, a client-side timestamp and the answers. The server keeps the newest event for each request and asset, rejects an event for another version than the bound one (`CHECKLIST_VERSION_MISMATCH`) and writes the result (D7). Photos upload the way proof photos do. **Recommended: approve.** | |
| D11 | **The proof lock** (FRM-05). If the bound template exists and `require_checklist` is on, punch-out is refused until the checklist result exists with every required field answered. With `require_per_asset_checklist` on, the same applies to each asset of a batch. Message in section 9. **Recommended: approve.** | |
| D12 | **Office override.** "Submit for review" by a manager (sr.qc full) stays an override: it does not check the checklist, as it does not check any proof today. The request page shows "Checklist not completed" next to the result so nobody misses it. **Recommended: approve.** | |
| D13 | **Batch inspection status.** Each asset in a batch has `pending`, `serviced_ok`, `repaired`, `needs_parts`, `failed_unsafe` or `skipped`. `skipped` needs a remark. `pending` is the only non-final status. **Recommended: approve.** | |
| D14 | **Batch punch-out and signature** (BAT-03). The technician cannot punch out while any asset is `pending`. When every asset is final, one client signature closes the whole batch (the request's normal signature proof, not one per asset). If the category requires a per-asset checklist, each asset also needs its result. **Recommended: approve (owner's call).** | |
| D15 | **Batch size.** 2 to 200 assets, all in the same site, all of the same service category, each at most once. A batch needs the category switch `allow_multi_asset_batch`. Assets with an open request of the same category are listed but not preselected, and the Front Desk sees a warning (D16). **Recommended: approve.** | |
| D16 | **Creating a batch** (BAT-02). On the site page and on the asset list the user picks assets of one site and chooses "Create batch request". The new-request form opens with the assets and the category preselected. Duplicates in the same batch are impossible. An asset already in another open batch of the same category is flagged "In open request {number}" and needs a tick to include. **Recommended: approve.** | |
| D17 | **Batch status and results.** The request keeps its own ten statuses (assumption 15). Its status follows the normal rules. The request page shows a table of assets with the status, result and remarks of each and a count ("17 serviced, 2 need parts, 1 failed"). A completed batch writes each asset's service date, so the asset 360 shows it. **Recommended: approve.** | |
| D18 | **What approval does to a batch.** One approval covers the whole batch. A batch cannot be partly approved. **Recommended: approve.** | |
| D19 | **Auto approval rules** (FRM-08). `auto_if_in_contract`: when the request is created, if contract coverage finds a covering contract (the existing `ContractCoverage` rule, status `in_contract`), the request goes to Approved at once; otherwise it stays Pending for manual approval. `always_auto`: every request of the category goes to Approved at once. An automatic approval is recorded as "Approved automatically ({mode})", with no approver, and audited. Emergency and critical priorities are always approved first, never held. **Recommended: approve.** | |
| D20 | **The rules page.** A table with one row per category: mode, an optional amount limit (an invoice or quote above the limit needs manual approval) and the second approver for `finance_and_manager`. The limit applies in the base currency. **Recommended: approve.** *Built: the mode and the second approver work. The amount limit is stored and shown but nothing applies it yet, because a request has no amount when its approval mode runs (see 6.5 item 3 and FAB-078).* | |
| D21 | **Two approvals** (`finance_and_manager`). The request stays Pending until one user with `sr.approve` full (Operations Manager or Company Admin) and one user with `expense.approve` full (Finance) have both approved. The same person cannot give both. A rejection by either cancels the approval and returns the request to the requester with the reason. **Recommended: approve.** | |
| D22 | **Template deletion.** A template that was ever published cannot be deleted, only archived. An archived template cannot be bound again, but requests already bound keep their version. A draft that was never published can be deleted. **Recommended: approve.** | |
| D23 | **Duplicating a template.** "Duplicate" copies the published (or latest) version into a new template as a draft. Counts against the plan limit. **Recommended: approve.** | |
| D24 | **Audit.** Template create, publish, retire, archive and delete, the approval-mode change, and an automatic approval are audited. A checklist save is not audited line by line (the version and result are stored on the request). **Recommended: approve.** | |

## 3. Who can do what

Keys are from the signed permission matrix. No new key is added.

| Action | Key and level | Roles |
|---|---|---|
| See the template list and a template | `form.manage` at least view | Company Admin, Operations Manager |
| Create, edit, publish, retire, archive, duplicate | `form.manage` full | Company Admin, Operations Manager |
| See the approval rules page | `workflow.manage` at least view | Company Admin, Operations Manager (view only) |
| Change an approval mode, limit or second approver | `workflow.manage` full | Company Admin |
| Fill in the checklist in the Field View | `field.punch` edit own (own job) | Technician, Team Lead |
| See the checklist result on a request | `sr.view` | Everyone who sees the request (technician: own jobs only) |
| Approve (single or first of two) | `sr.approve` full | Company Admin, Operations Manager |
| Approve as Finance (second of two) | `expense.approve` full | Company Admin, Operations Manager, Finance |
| Create a batch request | `sr.create` (Company Admin, Operations Manager, Team Lead, Front Desk) and the category allows it | |
| Clients | No access to forms or approvals. A client sees the checklist result on their own request as read-only (D25 below). | Client roles |

D25. A client sees the result (`ok` or `issues`) and the answers marked "visible to client". Every field has that tick, off by default. **Recommended: approve.**

## 4. Data this module reads and writes

- **Reads:** `service_categories`, `assets`, `project_sites`, `contracts` (coverage), `service_requests`, `service_request_assets`, `users`, `roles`, `plans`.
- **Writes:** `form_templates`, `form_template_versions`, `approval_rules`, `service_requests` (version id, checklist payload, approval fields), `service_request_assets`, `field_event_logs`, `user_notifications`, `activity_logs`.
- **Used by others:** B4 (template on a schedule, batch schedule), B6 (children, intake and return checklists), B8 (checklist and approval figures).

**New table: form_templates** (SCH-B3-01)

`id`, `tenant_id`, `name` (180), `description` (500, null), `status` (`active`, `archived`), `created_by_user_id`, timestamps. Unique `(tenant_id, name)`.

**New table: form_template_versions** (SCH-B3-02)

`id`, `tenant_id`, `form_template_id`, `version_no` (counts up for each template), `status` (`draft`, `published`, `retired`), `schema_json` (sections, fields, show_if, flags), `published_at`, `published_by_user_id`, `retired_at`, timestamps. Unique `(form_template_id, version_no)`. A published version's `schema_json` is never updated (checked in the model and in a test).

**New table: approval_rules** (SCH-B3-03)

`id`, `tenant_id`, `service_category_id`, `mode` (the four modes), `amount_limit_base` (decimal, null = no limit), `second_approver_user_id` (null = any Finance user), timestamps. Unique `(tenant_id, service_category_id)`. `service_categories.approval_mode` stays the source for the mode, and the row holds the limit and approver. They are written together.

**Changed tables**

- `assets.form_template_id` (null, SCH-B3-04).
- `service_requests`: `approval_state` (`none`, `awaiting_ops`, `awaiting_finance`, `awaiting_both`, `done`; null for manual), `approved_by_ops_user_id`, `approved_by_finance_user_id`, `auto_approved_mode` (null or a mode), `checklist_result` (`ok`, `issues`, null), `checklist_saved_at` (SCH-B3-05).
- `service_request_assets`: `checklist_result` (`ok`, `issues`, null) (SCH-B3-05).
- Plan keys in `plans.features`, added by migration (SCH-B3-06): `forms` (Professional, Enterprise), `form_templates_limit` (10 for Professional), `approval_auto` (Professional, Enterprise: the two automatic modes) and `approval_finance` (Enterprise: `finance_and_manager`). Together they are the blueprint's `forms` and `approval` rows.

**Error codes** (added to the API errors of the blueprint)

`TEMPLATE_LIMIT`, `TEMPLATE_PUBLISHED_IMMUTABLE`, `TEMPLATE_INVALID`, `CHECKLIST_REQUIRED`, `CHECKLIST_VERSION_MISMATCH`, `BATCH_SIZE`, `BATCH_SITE_MISMATCH`, `BATCH_CATEGORY_NOT_ALLOWED`, `BATCH_ASSETS_PENDING`, `APPROVAL_MODE_NOT_IN_PLAN`, `APPROVAL_SAME_PERSON`.

Added while building (November 2026): `BATCH_CATEGORY_MISMATCH` (an asset of another service category, "All assets must have the same service category."), `BATCH_ASSET_OPEN` (an asset already in an open request of the same category, needs the tick of D16), `BATCH_STATUS_INVALID`, `BATCH_REMARK_REQUIRED` (skipping without a remark), `CHECKLIST_NOT_BOUND` (a job with no checklist), `CHECKLIST_INVALID` (an answer that does not fit its field) and `APPROVAL_NOT_ASSIGNED` (a Finance approval by someone other than the approver named on the rule, D20).

## 5. Screens

### 5.1 Form templates (FRM-01)

Office page "Checklists" (the word is renamable). A table: name, published version, status, bound to (n categories, n assets), updated. Buttons: New template, Duplicate, Archive. The page needs `form.manage` and the `forms` plan key.

### 5.2 Form builder (FRM-02, FRM-03)

Two columns: the field list (add, reorder with up and down buttons, delete) and the field settings. A preview tab shows the Field View form. Buttons: Save draft, Publish (shows what changes and how many open requests keep the old version), Discard draft. A published version opens read-only with "Edit as new draft".

### 5.3 Binding (FRM-04)

Category page: a "Checklist template" selector and the two existing switches. Asset form: a "Checklist template" selector. The Schedule form (B4) has its own selector.

### 5.4 Checklist in the Field View (FRM-05)

On the job screen a "Checklist" card shows the title, a progress count (6 of 9 required) and the result. Opening it shows the sections one at a time. Saving works offline. Required fields left empty are marked when the technician taps Done. Punch-out shows the section 9 message when blocked.

### 5.5 Batch in the Field View (BAT-03)

The job lists its assets with a status chip. Tapping an asset opens its card: scan check (the existing QR verify), checklist, meter reading, before and after photos, remarks and the status picker. A footer shows "12 of 20 done". Punch-out is blocked until none is pending (D14). The signature step comes last.

### 5.6 Results on the request and the asset 360 (FRM-06)

The request page shows the checklist result, the version used and the answers in a read-only table. A batch shows the asset table of D17. The asset 360 service history lists each past checklist with its date, result and request.

### 5.7 New batch request (BAT-02)

From the site page and the asset list: tick assets, "Create batch request". The request form shows the asset table with remove buttons and the duplicate flag of D16.

### 5.8 Approval rules (FRM-08)

Table of D20 with a mode selector for each category. Modes outside the plan are disabled with the plan message. Saving is audited.

### 5.9 Approvals on the request page

An "Approval" panel shows the mode, who approved and when. For `finance_and_manager` it shows two lines (Operations Manager, Finance) with Approve and Reject buttons for the people who may use them.

## 6. Business rules

### 6.1 Templates and versions (FRM-01, FRM-03, D2, D3, D22, D23)

1. Creating a template checks the plan limit of D2 (`TEMPLATE_LIMIT`).
2. Publishing needs at least 1 field, valid show-if rules and unique keys (`TEMPLATE_INVALID` with the field named).
3. Publishing retires the earlier published version in the same transaction. The two never overlap.
4. A published or retired version is never edited (`TEMPLATE_PUBLISHED_IMMUTABLE`).
5. Archiving does not touch requests. Removing a binding on a category does not touch requests.

### 6.2 Binding (FRM-04, D4, D8)

At request creation the server picks the template (schedule, asset, category order), finds its published version and stores its id in `service_requests.form_template_version_id`. For a batch with different asset templates, the asset's template applies to its row (`service_request_assets.form_template_version_id`) and the category's to the request. With no template the request has no checklist.

### 6.3 The checklist (FRM-05, D5, D6, D7, D10, D11)

1. A checklist belongs to a request, or to one asset of a batch.
2. Saving validates each answer against its field (type, range, option, required when visible).
3. A hidden field's answer is dropped. A field that no longer exists in the bound version is rejected.
4. The result is computed by the server (D7). The device never sends a result.
5. The newest event wins. An older event arriving later is ignored and logged.
6. The proof lock is checked in the punch-out. It adds the checklist test to the signature and photo tests already there.

### 6.4 Batch (BAT-01, BAT-02, D13 to D18)

1. A batch is a request with 2 or more rows in `service_request_assets`. A request with one asset is a normal request with one row.
2. All assets share the request's site and category (`BATCH_SITE_MISMATCH`, `BATCH_CATEGORY_NOT_ALLOWED`, `BATCH_SIZE`).
3. The technician sets each asset's status. `skipped` needs a remark.
4. Punch-out needs no `pending` row (`BATCH_ASSETS_PENDING`).
5. Completing the request writes each asset's `completed_at` and its last service date. A `failed_unsafe` asset gets the asset's own "unsafe" flag if it has one, and a notification goes to the Operations Manager (D26).
6. Cancelling the request leaves the asset rows as they are, with the reason on the request.

D26. A batch with a `failed_unsafe` row notifies the Operations Manager when the technician punches out. **Recommended: approve.**

### 6.5 Approval (FRM-07, FRM-08, D9, D18 to D21)

1. The mode is read at request creation and stored (`auto_approved_mode` when it fired).
2. `auto_if_in_contract` uses `ContractCoverage`. The same coverage call the request page already shows. If coverage cannot decide, the request stays Pending.
3. An amount limit is checked when the quote or invoice is approved. Above it, the auto mode is skipped and manual approval is asked.
4. In `finance_and_manager`, the request moves to Approved only when both approvals exist. Either rejection sets `approval_state` back to `none` and notifies the requester.
5. Plan loss (D9) makes the effective mode `manual` without changing the stored value.
6. Every automatic approval and every approver action is audited with the mode.

### 6.6 Permissions, plans and isolation

Routes use `level:form.manage` and `level:workflow.manage`, plan gate `forms` on the form pages and `EnsurePlanFeature` for the modes. Models use `BelongsToTenant`. Route binding for `{template}` and `{version}` is scoped by the signed-in user's company in `AppServiceProvider`. Another company's id is a 404. `ScreenPermissionsTest` and `RouteAuthTest` must stay green with the new routes.

## 7. Events and wording

Sent through `NotificationService::notify()` (in-app and email). WhatsApp is not used.

| Event | Fired by | Recipients and channels | Fallback |
|---|---|---|---|
| sr.auto_approved | An automatic approval (6.5) | Requester and Operations Manager: in-app | None |
| sr.approval_needed | A request reaches `awaiting_*` | The users who may approve: in-app, email | In-app |
| sr.approval_rejected | A rejection in `finance_and_manager` | Requester: in-app, email | In-app |
| batch.unsafe_asset | Punch-out with a `failed_unsafe` row (D26) | Operations Manager: in-app, email | In-app |

## 8. API

Same style as the existing API of the blueprint, token and company scope, errors in the standard envelope. Built with the other API endpoints of Release B (FRM-09); until then nothing is exposed.

- `GET /api/v1/form-templates`, `GET /api/v1/form-templates/{id}` (published schema).
- `GET /api/v1/service-requests/{id}/checklist` and `PUT` (the same validation as the Field View).
- `GET` and `PUT /api/v1/service-requests/{id}/assets/{asset}` (status, checklist, remarks).
- `POST /api/v1/service-requests` accepts `asset_ids` (batch).
- `PUT /api/v1/approval-rules/{category}` (needs `workflow.manage` full).

## 9. Exact messages

`{...}` is filled in by the system.

- Template limit: "Your plan allows {n} checklists. Archive one or upgrade your plan."
- Published version: "This version is published and cannot be changed. Create a new draft to edit it."
- Invalid template: "Fix {field}: {reason}."
- Punch-out blocked, checklist: "Complete the checklist before you finish: {n} required answers are missing."
- Punch-out blocked, batch: "{n} assets are still pending. Mark each one before you finish."
- Skipped without remark: "Add a remark to skip this asset."
- Version mismatch: "This checklist changed. Reload the job to continue."
- Batch size: "A batch needs between 2 and 200 assets."
- Batch site: "All assets in a batch must be at the same site."
- Batch category: "This category does not allow batch requests."
- Approval mode not in plan: "Not in your plan."
- Same person: "A second person must give the other approval."
- Auto approved: "Approved automatically ({mode})."
- Checklist not completed (office override): "Checklist not completed."

## 10. Acceptance tests

Each test becomes an automated test. Names in the test files start with the ID. FAB-050 to FAB-058 together cover scenario 11 of blueprint 13.3.

### 10.1 Templates and versions (FRM-01, FRM-03)

| ID | Scenario | Expected result |
|---|---|---|
| FAB-001 | Professional company creates its 11th template | Refused with `TEMPLATE_LIMIT` |
| FAB-002 | Starter company opens the Checklists page | 403 "not in your plan" |
| FAB-003 | Publish a draft with no fields | `TEMPLATE_INVALID` |
| FAB-004 | Publish version 2 | Version 1 becomes retired, version 2 published, never two published |
| FAB-005 | Edit a published version through the model or a request | `TEMPLATE_PUBLISHED_IMMUTABLE`, content unchanged |
| FAB-006 | Delete a template that was published | Refused, archive offered |
| FAB-007 | Duplicate a template | A new template with a draft copy, plan limit counted |

### 10.2 Builder (FRM-02)

| ID | Scenario | Expected result |
|---|---|---|
| FAB-010 | A number field with minimum 5 and maximum 1 | Invalid, field named |
| FAB-011 | A show-if that points to a later field | Invalid |
| FAB-012 | A dropdown with 1 option or 21 options | Invalid |
| FAB-013 | A field label of 121 characters | Invalid |

### 10.3 Binding (FRM-04)

| ID | Scenario | Expected result |
|---|---|---|
| FAB-020 | Category has template A, asset has template B | A request for that asset binds to B |
| FAB-021 | Template A gets version 2 after the request was created | The request still points at version 1 |
| FAB-022 | A template with no published version | The request has no checklist |
| FAB-023 | An archived template chosen on a category | Refused |

### 10.4 Checklist and proof lock (FRM-05)

| ID | Scenario | Expected result |
|---|---|---|
| FAB-030 | Save answers with a required field empty | Saved, result not final, punch-out blocked |
| FAB-031 | A hidden field has an answer | Answer dropped |
| FAB-032 | A number outside its range on a "fails" field | Result `issues` |
| FAB-033 | All required answered, no failing field | Result `ok` |
| FAB-034 | Two events for the same request, the older arrives last | The newer answers stay |
| FAB-035 | An event for version 1 when version 2 is bound | `CHECKLIST_VERSION_MISMATCH` |
| FAB-036 | Punch-out with `require_checklist` on and no checklist | Blocked with the section 9 message |
| FAB-037 | The office "Submit for review" with no checklist | Works, "Checklist not completed" shown |
| FAB-038 | A technician saves the checklist of another technician's job | 403 or 404 |

### 10.5 Results (FRM-06)

| ID | Scenario | Expected result |
|---|---|---|
| FAB-040 | A request page with a saved checklist | Version, result and answers shown |
| FAB-041 | The asset 360 after the request completes | The checklist is listed with date and result |
| FAB-042 | A client opens their request | Only fields marked visible to the client |

### 10.6 Batch (BAT-01 to BAT-04, scenario 11)

| ID | Scenario | Expected result |
|---|---|---|
| FAB-050 | Create a batch of 20 assets at one site | One request with 20 rows, all `pending` |
| FAB-051 | A batch with 1 asset, or 201 | `BATCH_SIZE` |
| FAB-052 | An asset from another site | `BATCH_SITE_MISMATCH` |
| FAB-053 | A category that does not allow batch | `BATCH_CATEGORY_NOT_ALLOWED` |
| FAB-054 | An asset already in an open batch of the same category | Flagged, included only with the tick |
| FAB-055 | Mixed outcomes: 17 `serviced_ok`, 2 `needs_parts`, 1 `failed_unsafe` | The count line is right, the Operations Manager is notified |
| FAB-056 | Punch-out with one asset still `pending` | `BATCH_ASSETS_PENDING` |
| FAB-057 | One signature after all are final | The request goes to Pending Review |
| FAB-058 | Completing the batch | Each asset's service date is written, the asset 360 shows it |
| FAB-059 | `skipped` without a remark | Refused |
| FAB-060 | Per-asset checklist required, one asset has none | Punch-out blocked |

### 10.7 Approval modes (FRM-07, FRM-08)

| ID | Scenario | Expected result |
|---|---|---|
| FAB-070 | Starter or Professional tries `finance_and_manager` | `APPROVAL_MODE_NOT_IN_PLAN` |
| FAB-071 | `auto_if_in_contract`, a covering contract | Approved at once, "Approved automatically" |
| FAB-072 | `auto_if_in_contract`, no covering contract | Stays Pending |
| FAB-073 | `always_auto` | Approved at once, audited |
| FAB-074 | `finance_and_manager`, only the Operations Manager approves | Still waiting for Finance |
| FAB-075 | The same person tries to give both | `APPROVAL_SAME_PERSON` |
| FAB-076 | A rejection by Finance | Back to the requester with the reason, notified |
| FAB-077 | Company drops to a plan without the mode | Runs `manual`, stored value unchanged, message shown |
| FAB-078 | Amount above the limit | Manual approval asked |
| FAB-079 | A batch under an auto mode | One approval for the batch |

### 10.8 Permissions, plans, isolation and API

| ID | Scenario | Expected result |
|---|---|---|
| FAB-090 | Front Desk, Finance, Technician open the Checklists page | 403 |
| FAB-091 | Operations Manager opens the rules page and tries to save | Page opens, save 403 (view only) |
| FAB-092 | Another company's template id | 404 |
| FAB-093 | Every new route is on the signed matrix and behind sign-in | `ScreenPermissionsTest` and `RouteAuthTest` pass |
| FAB-094 | Every change in D24 writes an audit row | Row exists, no answers logged |
| FAB-095 | The API endpoints (when built) | Same validation, same errors |

## 11. What developers must confirm before building

A developer must answer each of these in writing before the slice starts. The answers are recorded in `docs/decisions.md`, or in the pull request when they are only about how the work is done.

**Questions for the owner**

- **Q1. Plan limits.** Decided: D2 follows blueprint section 6 (Starter notes only, Professional 10 templates, Enterprise unlimited). Recommended answers apply to every owner's call.
- **Q2. Batch punch-out.** Approve D14 (blocked until every asset is final, one signature).
- **Q3. Approval modes by plan.** Approve D9 and the fallback to manual when a plan is lost.
- **Q4. Client view of the checklist.** Approve D25, or hide the checklist from clients.

**Questions for the developers**

1. **Punch-out code.** Where the checklist and batch checks join the existing proof lock in `Technician/JobsController`, and whether `proof_rules_snapshot` should carry them.
2. **Offline events.** The format of `checklist_saved` in `FieldEventLog`, how the device keeps drafts, and how a conflict is shown.
3. **Photos and signature fields.** How a form photo is stored and whether it counts toward the request's own proof photos.
4. **Schema storage.** Whether `schema_json` needs a validator class and a size limit.
5. **Coverage call.** The exact `ContractCoverage` method used for `auto_if_in_contract` and its cost on request creation.
6. **Two approvals.** Where the second approval is stored for a request, and how `sr.approve` (existing approve route) changes to support it.
7. **Existing categories.** The migration for `approval_mode` values already stored and the lifted `ServiceCategoryRequest` rule.
8. **Batch queries.** The speed of the asset table with 200 rows, and the index on `service_request_assets`.
9. **Impact on other specs.** B4 (schedule template), B6 (children and checklists), the Notifications spec (four events) and the blueprint (three tables, new columns, error codes).

## 12. Review and approval

A developer must confirm they could build this module without asking a question. Anything they would have to ask goes back into this document.

| Item | Name | Date | Answer |
|---|---|---|---|
| Checked against blueprint 7.6, 7.8 and 11.7 | | | |
| Decisions D1 to D26 and the new tables, columns and error codes approved or changed | Project owner | | |
| Owner's calls decided: D9, D14 and Q1 to Q4 | Aiywah owner | | |
| Developer confirms the module can be built without questions, with the answers to section 11 | | | |
| Operations Manager confirms the rules in sections 5 and 6 | | | |
