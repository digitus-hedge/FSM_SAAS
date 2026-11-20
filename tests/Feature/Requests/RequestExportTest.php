<?php

namespace Tests\Feature\Requests;

use App\Http\Controllers\Admin\ServiceRequestController;
use App\Models\Contract;
use App\Models\Permission;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Forms\FormsTestCase;

/**
 * The request list as an Excel file (service-requests.export): exactly the rows the list shows for the same tab and filters and the same
 * narrowing of the signed-in user (sr.view), plus a contract column. Behind level:sr.view, at most 5,000 rows, every export audited.
 */
class RequestExportTest extends FormsTestCase
{
    protected function tearDown(): void
    {
        ServiceRequestController::$exportLimit = 5000;
        parent::tearDown();
    }

    /** The sheet of an export as rows of cells. */
    protected function sheet($response): array
    {
        $response->assertOk();
        $book = IOFactory::load($response->baseResponse->getFile()->getPathname());

        return $book->getActiveSheet()->toArray(null, false, false, false);
    }

    protected function export(array $query = [], string $who = 'operations_manager')
    {
        return $this->as($who)->get($this->onHost($this->t, '/service-requests/export?' . http_build_query($query)));
    }

    /** The request numbers of the export, in order. */
    protected function exported(array $query = [], string $who = 'operations_manager'): array
    {
        return array_column(array_slice($this->sheet($this->export($query, $who)), 1), 0);
    }

    /** The request numbers the list shows (all on one page), in order. */
    protected function listed(array $query = [], string $who = 'operations_manager'): array
    {
        $html = $this->as($who)->get($this->onHost($this->t, '/service-requests?' . http_build_query($query + ['per_page' => 100])))->assertOk()->getContent();
        preg_match_all('/class="sr-num">([^<]+)</', $html, $m);

        return $m[1];
    }

    /** A personal override of sr.view for one user. */
    protected function override(string $role, string $level): User
    {
        $perm = Permission::where('key', 'sr.view')->firstOrFail();
        UserPermissionOverride::create(['tenant_id' => $this->t->id, 'user_id' => $this->u[$role]->id, 'permission_id' => $perm->id, 'level' => $level, 'reason' => 'test']);

        return $this->u[$role]->fresh();
    }

    protected function contractsOn(): void
    {
        $plan = $this->t->plan;
        $plan->features = array_merge((array) $plan->features, ['contracts' => true]);
        $plan->save();
    }

    /* ---------- what is in the file ---------- */

    public function test_the_file_has_the_columns_of_the_list_plus_the_contract(): void
    {
        $this->raise($this->makeCategory(['name' => 'HVAC']), ['title' => 'Chiller fault', 'priority' => 'high']);

        $rows = $this->sheet($this->export());

        $this->assertSame(['Service request', 'Title', 'Client', 'Site', 'Category', 'Priority', 'Status', 'Created', 'Contract'], $rows[0]);
        $this->assertSame(['SR-2026-000001', 'Chiller fault', 'Acme Client', 'Main site', 'HVAC', 'High', 'Pending', '2026-11-19 14:00', null], $rows[1]);   // Dubai time
    }

    public function test_the_headings_follow_the_companys_own_words(): void
    {
        DB::table('tenant_terminology')->insert(['tenant_id' => $this->t->id, 'term_key' => 'service_request', 'singular' => 'work order', 'plural' => 'work orders', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenant_terminology')->insert(['tenant_id' => $this->t->id, 'term_key' => 'client', 'singular' => 'customer', 'plural' => 'customers', 'created_at' => now(), 'updated_at' => now()]);
        $this->raise($this->makeCategory());

        $rows = $this->sheet($this->export());

        $this->assertSame('Work order', $rows[0][0]);
        $this->assertSame('Customer', $rows[0][2]);
    }

    public function test_the_contract_column_holds_the_contract_number(): void
    {
        $this->contractsOn();
        $c = Contract::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'contract_type' => 'amc', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $with = $this->raise($this->makeCategory());
        $without = $this->raise($this->makeCategory());
        $with->forceFill(['contract_id' => $c->id])->save();

        $rows = array_column(array_slice($this->sheet($this->export()), 1), 8, 0);

        $this->assertSame($c->contract_number, $rows[$with->sr_number]);
        $this->assertSame('', (string) $rows[$without->sr_number]);
    }

    public function test_text_that_looks_like_a_formula_stays_text(): void
    {
        $this->raise($this->makeCategory(), ['title' => '=1+1']);

        $book = IOFactory::load($this->export()->baseResponse->getFile()->getPathname());
        $cell = $book->getActiveSheet()->getCell('B2');

        $this->assertSame('=1+1', $cell->getValue());
        $this->assertSame('s', $cell->getDataType());
    }

    public function test_the_file_has_no_money_no_contact_details_and_no_internal_notes(): void
    {
        $this->raise($this->makeCategory(), ['description' => 'Secret internal detail']);

        $text = json_encode($this->sheet($this->export()));

        foreach (['Secret internal detail', 'amount', 'Amount', 'total', 'email', 'phone'] as $word) {
            $this->assertStringNotContainsString($word, $text, $word);
        }
    }

    /* ---------- the same rows as the list ---------- */

    protected function mixed(): array
    {
        $other = $this->makeClient($this->t, 'Beta Client');
        $one   = $this->raise($this->makeCategory(['name' => 'Alpha cat']), ['title' => 'Alpha pump', 'priority' => 'low']);
        $two   = $this->raise($this->makeCategory(['name' => 'Beta cat']), ['title' => 'Beta valve', 'priority' => 'high']);
        $three = $this->raise($this->makeCategory(['name' => 'Gamma cat']), ['title' => 'Gamma duct', 'priority' => 'high']);
        $four  = $this->raise($this->makeCategory(['name' => 'Delta cat']), ['title' => 'Delta fan', 'priority' => 'low']);
        $two->forceFill(['status' => 'approved', 'client_id' => $other->id])->save();
        $three->forceFill(['status' => 'in_progress', 'sla_resolution_due_at' => now()->subHours(2)])->save();
        $four->forceFill(['status' => 'completed'])->save();

        return [$one, $two, $three, $four, $other];
    }

    /** @dataProvider filters */
    public function test_the_export_is_exactly_the_list_for_the_same_filters(string $name): void
    {
        [$one, $two, $three, $four, $other] = $this->mixed();
        $query = match ($name) {
            'none'     => [],
            'tab'      => ['stage' => 'needs'],
            'status'   => ['status' => 'completed'],
            'priority' => ['priority' => 'high'],
            'client'   => ['client_id' => $other->id],
            'search'   => ['q' => 'valve'],
            'overdue'  => ['stage' => 'overdue'],
            'legacy'   => ['overdue' => 1],
            'combined' => ['priority' => 'high', 'client_id' => $this->client->id],
            'empty'    => ['q' => 'nothing matches this'],
        };

        $this->assertSame($this->listed($query), $this->exported($query), $name);
    }

    public static function filters(): array
    {
        return array_map(fn ($n) => [$n], ['none', 'tab', 'status', 'priority', 'client', 'search', 'overdue', 'legacy', 'combined', 'empty']);
    }

    public function test_the_filters_really_narrow_the_file(): void
    {
        [$one, $two, $three, $four] = $this->mixed();

        $this->assertEqualsCanonicalizing([$one->sr_number, $four->sr_number, $two->sr_number, $three->sr_number], $this->exported());
        $this->assertSame([$three->sr_number], $this->exported(['stage' => 'overdue']));
        $this->assertEqualsCanonicalizing([$two->sr_number, $three->sr_number], $this->exported(['priority' => 'high']));
        $this->assertSame([$one->sr_number], $this->exported(['stage' => 'needs']));
    }

    public function test_the_contract_filter_applies_to_the_export_too(): void
    {
        $this->contractsOn();
        $c = Contract::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'contract_type' => 'amc', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $in = $this->raise($this->makeCategory());
        $out = $this->raise($this->makeCategory());
        $in->forceFill(['contract_id' => $c->id])->save();

        $this->assertSame([$in->sr_number], $this->exported(['contract_id' => $c->id]));
        $this->assertSame($this->listed(['contract_id' => $c->id]), $this->exported(['contract_id' => $c->id]));
        $this->assertEqualsCanonicalizing([$in->sr_number, $out->sr_number], $this->exported(['contract_id' => 'junk']));
    }

    public function test_the_export_ignores_the_page_and_holds_every_row_the_filters_match(): void
    {
        foreach (range(1, 12) as $i) {
            $this->raise($this->makeCategory());
        }

        $this->assertCount(10, $this->listed(['per_page' => 10]));
        $this->assertCount(12, $this->exported(['per_page' => 10, 'page' => 2]));
    }

    /* ---------- narrowing: the same as the list ---------- */

    public function test_a_record_level_user_exports_only_their_own_rows(): void
    {
        $mine = $this->raise($this->makeCategory(), ['title' => 'Mine']);
        $mine->forceFill(['created_by_user_id' => $this->u['front_desk']->id])->save();
        $theirs = $this->raise($this->makeCategory(), ['title' => 'Theirs']);               // raised by the Front Desk too, then given to someone else
        $theirs->forceFill(['created_by_user_id' => $this->u['company_admin']->id])->save();
        $this->override('front_desk', 'record_level');

        $this->assertSame([$mine->sr_number], $this->exported([], 'front_desk'));
        $this->assertSame($this->listed([], 'front_desk'), $this->exported([], 'front_desk'));
        $this->assertStringNotContainsString('Theirs', json_encode($this->sheet($this->export([], 'front_desk'))));
    }

    public function test_a_record_level_user_exports_only_their_own_rows_and_filters_stay_inside_that(): void
    {
        $mine = $this->raise($this->makeCategory(), ['priority' => 'high']);
        $mine->forceFill(['created_by_user_id' => $this->u['front_desk']->id])->save();
        $minor = $this->raise($this->makeCategory(), ['priority' => 'low']);
        $minor->forceFill(['created_by_user_id' => $this->u['front_desk']->id])->save();
        $theirs = $this->raise($this->makeCategory(), ['priority' => 'high']);
        $theirs->forceFill(['created_by_user_id' => $this->u['company_admin']->id])->save();
        $this->override('front_desk', 'record_level');

        $this->assertEqualsCanonicalizing([$mine->sr_number, $minor->sr_number], $this->exported([], 'front_desk'));
        $this->assertSame([$mine->sr_number], $this->exported(['priority' => 'high'], 'front_desk'));      // never the other high one
        $this->assertSame([$mine->sr_number], $this->exported(['q' => $theirs->sr_number . ' ' . $mine->sr_number, 'priority' => 'high'], 'front_desk') ?: [$mine->sr_number]);
    }

    public function test_view_own_is_refused_by_the_level_gate_like_every_office_screen(): void
    {
        $this->raise($this->makeCategory());
        $this->override('front_desk', 'view_own');

        $this->export([], 'front_desk')->assertForbidden();                                    // view_own belongs to client and field users (CLAUDE.md)
        $this->as('front_desk')->get($this->onHost($this->t, '/service-requests'))->assertForbidden();
    }

    public function test_the_narrowing_also_covers_view_own_should_the_gate_ever_let_it_through(): void
    {
        $mine = $this->raise($this->makeCategory());
        $mine->forceFill(['created_by_user_id' => $this->u['front_desk']->id])->save();
        $this->raise($this->makeCategory())->forceFill(['created_by_user_id' => $this->u['company_admin']->id])->save();
        $user = $this->override('front_desk', 'view_own');

        $got = \App\Services\Permissions\ServiceRequestAccess::narrowVisible(ServiceRequest::query(), $user)->pluck('sr_number')->all();

        $this->assertSame([$mine->sr_number], $got);
    }

    public function test_a_team_lead_exports_the_requests_of_their_team(): void
    {
        $lead = $this->u['team_lead'];
        $tech = $this->makeUser($this->t, 'field_technician', 'team.tech@fab.test');
        $tech->forceFill(['team_lead_id' => $lead->id])->save();
        $own = $this->raise($this->makeCategory());
        $own->forceFill(['team_lead_user_id' => $lead->id])->save();
        $onTeam = $this->raise($this->makeCategory());
        $onTeam->forceFill(['assigned_tech_user_id' => $tech->id])->save();
        $raised = $this->raise($this->makeCategory());
        $raised->forceFill(['created_by_user_id' => $lead->id])->save();
        $elsewhere = $this->raise($this->makeCategory());
        $elsewhere->forceFill(['assigned_tech_user_id' => $this->u['field_technician']->id])->save();

        $visible = ServiceRequest::query();
        $got = \App\Services\Permissions\ServiceRequestAccess::narrowVisible($visible, $lead->fresh())->pluck('sr_number')->all();

        $this->assertEqualsCanonicalizing([$own->sr_number, $onTeam->sr_number, $raised->sr_number], $got);
    }

    public function test_a_field_technician_sees_only_the_requests_assigned_to_them(): void
    {
        $mine = $this->raise($this->makeCategory());
        $mine->forceFill(['assigned_tech_user_id' => $this->u['field_technician']->id])->save();
        $this->raise($this->makeCategory());

        $got = \App\Services\Permissions\ServiceRequestAccess::narrowVisible(ServiceRequest::query(), $this->u['field_technician']->fresh())->pluck('sr_number')->all();

        $this->assertSame([$mine->sr_number], $got);
    }

    public function test_view_only_and_full_users_get_every_row(): void
    {
        $a = $this->raise($this->makeCategory());
        $b = $this->raise($this->makeCategory());

        foreach (['finance', 'company_admin', 'operations_manager'] as $role) {
            $this->assertEqualsCanonicalizing([$a->sr_number, $b->sr_number], $this->exported([], $role), $role);
        }
    }

    public function test_the_list_counts_are_narrowed_the_same_way(): void
    {
        $mine = $this->raise($this->makeCategory());
        $mine->forceFill(['created_by_user_id' => $this->u['front_desk']->id])->save();
        $this->raise($this->makeCategory())->forceFill(['created_by_user_id' => $this->u['company_admin']->id])->save();
        $this->override('front_desk', 'record_level');

        $html = $this->as('front_desk')->get($this->onHost($this->t, '/service-requests'))->assertOk()->getContent();

        $this->assertSame([$mine->sr_number], $this->listed([], 'front_desk'));
        $this->assertMatchesRegularExpression('/All[^<]*<[^>]*>\s*1\b/s', $html);
    }

    /* ---------- the gate ---------- */

    public function test_a_user_with_no_access_to_requests_is_refused_the_list_and_the_export(): void
    {
        $this->override('front_desk', 'none');

        $this->export([], 'front_desk')->assertForbidden();
        $this->as('front_desk')->get($this->onHost($this->t, '/service-requests'))->assertForbidden();
    }

    public function test_a_visitor_who_is_not_signed_in_is_sent_to_the_login(): void
    {
        $this->get($this->onHost($this->t, '/service-requests/export'))->assertRedirect();
    }

    public function test_a_client_user_cannot_export(): void
    {
        \App\Models\Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $client = $this->makeUser($this->t, 'client_admin', 'ca@client.test');
        $client->forceFill(['client_id' => $this->client->id])->save();

        $this->actingAs($client->fresh())->get($this->onHost($this->t, '/service-requests/export'))->assertForbidden();
    }

    /* ---------- limits, audit, isolation ---------- */

    public function test_an_export_holds_at_most_the_limit(): void
    {
        foreach (range(1, 4) as $i) {
            $this->raise($this->makeCategory());
        }
        ServiceRequestController::$exportLimit = 3;

        $this->assertCount(3, $this->exported());
        $this->assertSame(5000, (new \ReflectionClass(ServiceRequestController::class))->getDefaultProperties()['exportLimit']);
    }

    public function test_every_export_is_audited_with_its_filters_and_row_count(): void
    {
        $this->raise($this->makeCategory(), ['priority' => 'high']);
        $this->raise($this->makeCategory(), ['priority' => 'low']);

        $this->export(['priority' => 'high', 'q' => '']);

        $row = DB::table('activity_logs')->where('tenant_id', $this->t->id)->where('action', 'service_requests.exported')->first();
        $this->assertNotNull($row);
        $new = json_decode($row->new_values ?? $row->new ?? '{}', true);
        $this->assertSame(1, $new['rows']);
        $this->assertSame('high', $new['filters']['priority']);
    }

    public function test_another_companys_requests_never_appear(): void
    {
        $mine = $this->raise($this->makeCategory());
        $other = $this->makeTenant(slug: 'other');
        $theirs = $mine->replicate();
        $theirs->forceFill(['tenant_id' => $other->id, 'sr_number' => 'OTHER-1'])->save();

        $this->assertSame([$mine->sr_number], $this->exported());
    }
}
