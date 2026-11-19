<?php

namespace Tests\Feature\Reports;

use App\Models\Contract;
use App\Models\ServiceRequest;
use App\Models\TenantSetting;
use App\Models\UserTimeOff;
use App\Services\Forms\FormException;
use App\Services\Reports\Figures;
use App\Services\Reports\ReportAccess;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Forms\FormsTestCase;

/** Reports-Summaries-Preferences spec 10.1 and 10.2: reports and figures (RSP-001 to RSP-031). Today is 19 Nov 2026. */
class ReportsTest extends FormsTestCase
{
    private function done(array $over = []): ServiceRequest
    {
        $sr = $this->makeServiceRequest($this->t, $this->client, 'completed');
        $sr->forceFill(array_merge([
            'priority' => 'high', 'created_at' => '2026-11-03 08:00:00', 'completed_at' => '2026-11-05 08:00:00',
            'sla_response_due_at' => '2026-11-03 10:00:00', 'first_punch_in_at' => '2026-11-03 09:00:00',
            'sla_resolution_due_at' => '2026-11-06 08:00:00', 'assigned_tech_user_id' => $this->u['field_technician']->id,
            'last_punch_out_at' => '2026-11-05 07:00:00', 'total_work_minutes' => 120,
        ], $over))->save();

        return $sr->fresh();
    }

    private function figures(): Figures
    {
        return app(Figures::class);
    }

    private function range(): array
    {
        return [Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')];
    }

    // ───────────── 10.1 reports ─────────────

    #[Test]
    public function rsp_001_a_starter_company_gets_the_not_in_your_plan_page(): void
    {
        $starter = $this->makeTenant($this->starterPlan(), slug: 'st8');
        $this->actingAs($this->makeUser($starter, 'company_admin'))->get(route('admin.reports.index'))->assertForbidden();
    }

    #[Test]
    public function rsp_002_the_sla_report_matches_on_screen_in_the_pdf_and_in_the_excel_file(): void
    {
        $this->done();                                                                                  // met both
        $this->done(['first_punch_in_at' => '2026-11-03 12:00:00', 'sla_response_breached' => true]);   // response missed

        $res = $this->as('operations_manager')->get(route('admin.reports.show', ['report' => 'sla', 'month' => '2026-11']));
        $res->assertOk()->assertSee('High');
        $rows = $res->viewData('rows');
        $this->assertSame(['High', 2, 2, 1, 2, 2, 2, 1], $rows[0]);

        $pdf = $this->as('operations_manager')->get(route('admin.reports.pdf', ['report' => 'sla', 'month' => '2026-11']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->as('operations_manager')->get(route('admin.reports.excel', ['report' => 'sla', 'month' => '2026-11']))->assertOk();
        $this->assertSame(2, \App\Models\ActivityLog::where('action', 'report.exported')->count(), 'every export is audited');
    }

    #[Test]
    public function rsp_003_a_future_month_is_refused(): void
    {
        $this->as('operations_manager')->get(route('admin.reports.show', ['report' => 'sla', 'month' => '2027-03']))
            ->assertSessionHas('error', 'Choose a month that has ended or the current month.');
    }

    #[Test]
    public function rsp_004_and_005_a_certificate_is_only_for_a_completed_request_and_has_no_price(): void
    {
        $open = $this->makeServiceRequest($this->t, $this->client, 'in_progress');
        $this->as('operations_manager')->get(route('service-requests.certificate', $open))->assertSessionHas('error', 'A completion certificate is only for a completed request.');

        $sr = $this->done(['title' => 'Boiler service', 'customer_signer_name' => 'Mr Khan', 'customer_signature_attachment_id' => 1]);
        $res = $this->as('operations_manager')->get(route('service-requests.certificate', $sr));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());

        $html = view('reports.pdf.certificate', app(\App\Services\Reports\ReportData::class)->certificate($sr) + [
            'company' => 'X', 'branded' => false, 'logo' => null, 'accent' => '#444444', 'title' => 'Completion certificate', 'generatedAt' => 'now',
        ])->render();
        $this->assertStringContainsString('Boiler service', $html);
        $this->assertStringContainsString('Signed by Mr Khan', $html);
        $this->assertStringNotContainsStringIgnoringCase('price', $html);
        $this->assertStringNotContainsStringIgnoringCase('total', $html);
    }

    #[Test]
    public function rsp_006_and_007_branded_pdfs_carry_the_company_header_and_plain_ones_do_not(): void
    {
        $this->t->forceFill(['primary_color' => '#AA1122'])->save();
        $pdf = app(\App\Services\Reports\ReportPdf::class);
        $base = ['headings' => ['A'], 'rows' => [[1]], 'month' => '2026-11', 'monthToDate' => false];

        $branded = view('reports.pdf.table', $base + ['company' => 'FAB Ltd', 'branded' => true, 'logo' => null, 'accent' => '#AA1122', 'title' => 'T', 'generatedAt' => 'now'])->render();
        $this->assertStringContainsString('#AA1122', $branded);
        $this->assertStringContainsString('FAB Ltd', $branded);

        $plain = view('reports.pdf.table', $base + ['company' => 'FAB Ltd', 'branded' => false, 'logo' => null, 'accent' => '#444444', 'title' => 'T', 'generatedAt' => 'now'])->render();
        $this->assertStringNotContainsString('FAB Ltd', $plain);
        $this->assertStringNotContainsString('#AA1122', $plain);

        $this->assertStringStartsWith('%PDF', $pdf->render('reports.pdf.table', $base, $this->t->fresh(), 'T'));
        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['brand_pdf' => false])])->save();
        $this->assertStringStartsWith('%PDF', $pdf->render('reports.pdf.table', $base, $this->t->fresh(), 'T'));
    }

    #[Test]
    public function rsp_008_a_team_lead_scorecard_only_has_their_teams_technicians(): void
    {
        $lead = $this->u['team_lead'];
        $mine = $this->makeUser($this->t, 'field_technician', 'mine8@fab.test');
        $mine->forceFill(['team_lead_id' => $lead->id])->save();
        $this->done(['assigned_tech_user_id' => $mine->id]);
        $this->done();   // the other technician

        $ids = ReportAccess::technicianIds($lead);
        $this->assertEqualsCanonicalizing([$lead->id, $mine->id], $ids->all());
        $this->assertNull(ReportAccess::technicianIds($this->u['operations_manager']));

        $rows = app(\App\Services\Reports\ReportData::class)->scorecard($this->t, '2026-11', $ids)['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame($mine->name, $rows[0]['technician']);
    }

    #[Test]
    public function rsp_009_a_client_cannot_get_the_certificate_of_another_client(): void
    {
        $other = $this->makeClient($this->t, 'Other');
        $sr = $this->done();
        $sr->forceFill(['client_id' => $other->id])->save();
        \App\Models\Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $c = $this->makeUser($this->t, 'client_admin', 'ca8@fab.test');
        $c->forceFill(['client_id' => $this->client->id])->save();

        $this->assertFalse(ReportAccess::mayCertificate($c->fresh(), $sr));
        $this->actingAs($c)->get($this->onHost($this->t, "/portal/requests/{$sr->id}/certificate"))->assertNotFound();

        $mine = $this->done();
        $res = $this->actingAs($c)->get($this->onHost($this->t, "/portal/requests/{$mine->id}/certificate"));
        $res->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    #[Test]
    public function rsp_012_another_companys_request_is_a_404_for_the_certificate(): void
    {
        $other = $this->makeTenant(slug: 'oth8');
        $sr = $this->makeServiceRequest($other, $this->makeClient($other), 'completed');

        $this->as('operations_manager')->get(route('service-requests.certificate', $sr))->assertNotFound();
    }

    // ───────────── 10.2 figures ─────────────

    #[Test]
    public function rsp_020_and_021_utilisation_uses_capacity_and_drops_time_off(): void
    {
        $tech = $this->u['field_technician'];
        $tech->forceFill(['daily_capacity_minutes' => 480])->save();
        $this->u['team_lead']->forceFill(['deactivated_at' => now()])->save();
        $this->done(['last_punch_out_at' => '2026-11-10 08:00:00', 'total_work_minutes' => 480]);

        // 5 to 9 Nov: one working week of 5 days only
        [$from, $to] = [Carbon::parse('2026-11-02'), Carbon::parse('2026-11-06')];
        $u = $this->figures()->utilisation($this->t, $from, $to);
        $this->assertSame(2400, $u['capacity']);
        $this->assertSame(0, $u['booked']);

        $this->done(['last_punch_out_at' => '2026-11-04 08:00:00', 'total_work_minutes' => 480]);
        $this->assertSame(20.0, $this->figures()->utilisation($this->t, $from, $to)['percent']);

        UserTimeOff::create(['tenant_id' => $this->t->id, 'user_id' => $tech->id, 'starts_at' => '2026-11-05 00:00:00', 'ends_at' => '2026-11-06 23:59:00', 'type' => 'leave']);
        $this->assertSame(1440, $this->figures()->utilisation($this->t, $from, $to)['capacity']);
    }

    #[Test]
    public function rsp_022_and_023_maintenance_on_time_and_against_reactive(): void
    {
        $s = $this->makeServiceRequest($this->t, $this->client, 'completed');
        foreach ([['2026-11-05', '2026-11-05 09:00:00'], ['2026-11-06', '2026-11-04 09:00:00'], ['2026-11-07', '2026-11-06 09:00:00'], ['2026-11-01', '2026-11-08 09:00:00']] as $i => [$due, $doneAt]) {
            $r = $this->done(['pm_schedule_id' => 900 + $i, 'pm_due_date' => $due, 'completed_at' => $doneAt, 'source' => 'pm_auto', 'created_at' => '2026-11-02 08:00:00']);
        }
        [$from, $to] = $this->range();

        $m = $this->figures()->maintenanceOnTime($this->t, $from, $to);
        $this->assertSame(3, $m['on_time']);
        $this->assertSame(1, $m['late']);
        $this->assertSame(75.0, $m['percent']);

        $this->done(['source' => 'front_desk', 'created_at' => '2026-11-09 08:00:00']);
        $mix = $this->figures()->pmVsReactive($this->t, $from, $to);
        $this->assertSame(4, $mix['maintenance']);
        $this->assertGreaterThanOrEqual(1, $mix['reactive']);
    }

    #[Test]
    public function rsp_024_contract_use_shows_calls_used_against_the_cap(): void
    {
        $c = new Contract();
        $c->forceFill(['tenant_id' => $this->t->id, 'contract_number' => 'CT-9', 'client_id' => $this->client->id, 'contract_type' => 'amc',
            'start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'status' => 'active', 'max_calls' => 10, 'calls_used_current_period' => 4])->save();

        $u = $this->figures()->contractUse($this->t);
        $this->assertSame([['contract' => 'CT-9', 'used' => 4, 'max' => 10, 'percent' => 40.0]], $u);
    }

    #[Test]
    public function rsp_025_to_027_callbacks_are_reopened_jobs_inside_the_window_over_completed_jobs(): void
    {
        TenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->t->id], ['reopen_window_days' => 14, 'feedback_link_days' => 7, 'client_sign_in_link_hours' => 24]);
        $this->done();
        $this->done();
        $this->done();
        $this->done(['reopened_count' => 1, 'reopened_at' => '2026-11-10 08:00:00']);   // completed and reopened
        [$from, $to] = $this->range();

        $c = $this->figures()->callbacks($this->t, $from, $to);
        $this->assertSame(1, $c['reopened']);
        $this->assertSame(4, $c['completed']);
        $this->assertSame(25.0, $c['percent']);
        $this->assertSame(14, $c['window_days']);

        // a window of 0 means reopening never happens, so the figure is 0
        TenantSetting::withoutGlobalScopes()->where('tenant_id', $this->t->id)->update(['reopen_window_days' => 0]);
        $this->assertSame(0, $this->figures()->callbacks($this->t, $from, $to)['reopened']);
    }

    #[Test]
    public function first_time_fix_counts_jobs_with_no_rework_and_no_reopen(): void
    {
        $this->done();
        $this->done(['rework_count' => 1]);
        $this->done(['reopened_count' => 1]);
        $this->done(['qc_rework_count' => 2]);
        [$from, $to] = $this->range();

        $f = $this->figures()->firstTimeFix($this->t, $from, $to);
        $this->assertSame(1, $f['first_time']);
        $this->assertSame(25.0, $f['percent']);
    }

    #[Test]
    public function rsp_028_vendor_spend_counts_vendor_expenses_only(): void
    {
        $sr = $this->done();
        $cat = \App\Models\ExpenseCategory::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'name' => 'Parts', 'is_billable_default' => true]);
        foreach ([[100, 5], [50, null]] as [$amount, $vendor]) {
            $e = \App\Models\ServiceRequestExpense::withoutGlobalScopes()->create([
                'tenant_id' => $this->t->id, 'service_request_id' => $sr->id, 'logged_by_user_id' => 1, 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'expense_category_id' => $cat->id, 'description' => 'x', 'quantity' => 1, 'unit_rate' => $amount, 'total_amount' => $amount, 'approval_status' => 'approved',
                'vendor_id' => $vendor,
            ]);
            // created_at is not mass assignable on this model
            \Illuminate\Support\Facades\DB::table('service_request_expenses')->where('id', $e->id)->update(['created_at' => '2026-11-10 08:00:00']);
        }
        [$from, $to] = $this->range();

        $v = $this->figures()->vendorSpend($this->t, $from, $to);
        $this->assertSame(100.0, $v['total']);
        $this->assertSame('AED', $v['currency']);
    }

    #[Test]
    public function rsp_029_a_company_with_no_data_says_no_data_yet(): void
    {
        [$from, $to] = $this->range();
        foreach (['maintenanceOnTime', 'pmVsReactive', 'callbacks', 'firstTimeFix', 'vendorSpend'] as $f) {
            $this->assertNull($this->figures()->{$f}($this->t, $from, $to), $f);
        }
        $this->assertNull($this->figures()->contractUse($this->t));

        $res = $this->actingAs($this->u['company_admin'])->get($this->onHost($this->t, '/dashboard'));
        $res->assertOk()->assertSee('No data yet')->assertSee('Operations figures');
    }

    #[Test]
    public function rsp_031_another_companys_data_never_appears_in_the_figures(): void
    {
        $other = $this->makeTenant(slug: 'oth9');
        $r = $this->makeServiceRequest($other, $this->makeClient($other), 'completed');
        $r->forceFill(['completed_at' => '2026-11-05 08:00:00', 'created_at' => '2026-11-03 08:00:00'])->save();
        [$from, $to] = $this->range();

        $this->assertNull($this->figures()->firstTimeFix($this->t, $from, $to));
        $this->assertSame(1, $this->figures()->firstTimeFix($other, $from, $to)['completed']);
    }

    // ───────────── 10.5 permissions ─────────────

    #[Test]
    public function rsp_080_and_081_who_opens_which_report(): void
    {
        foreach (['front_desk', 'field_technician'] as $who) {
            $this->as($who)->get(route('admin.reports.index'))->assertStatus($who === 'field_technician' ? 302 : 403);
        }
        // Finance: money reports only, and may export them
        $this->as('finance')->get(route('admin.reports.show', ['report' => 'contracts', 'month' => '2026-11']))->assertOk();
        $this->as('finance')->get(route('admin.reports.show', ['report' => 'scorecard', 'month' => '2026-11']))->assertForbidden();
        $this->as('finance')->get(route('admin.reports.excel', ['report' => 'contracts', 'month' => '2026-11']))->assertOk();
    }

    #[Test]
    public function rsp_082_the_new_routes_are_behind_sign_in(): void
    {
        \Illuminate\Support\Facades\Auth::logout();
        foreach ([route('admin.reports.index'), route('admin.summary-settings.index'), route('notifications.preferences')] as $url) {
            $this->get($url)->assertRedirect();
        }
    }
}
