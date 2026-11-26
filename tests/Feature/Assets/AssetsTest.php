<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\ImportJob;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * AST-12: parent and child in one site, decommission, import errors, QR isolation and the plan gate.
 * (The API, AST-11, is not built.)
 */
class AssetsTest extends AssetsTestCase
{
    /* ---------- parent and child (blueprint 7.2) ---------- */

    public function test_a_child_can_sit_under_a_parent_at_the_same_site(): void
    {
        $parent = $this->makeAsset();

        $this->create(['name' => 'Pump', 'parent_asset_id' => $parent->id])->assertSessionHasNoErrors();

        $this->assertSame($parent->id, $this->created('Pump')->parent_asset_id);
        $this->assertSame(1, $parent->children()->count());
    }

    public function test_a_child_at_another_site_is_refused_by_the_form(): void
    {
        $parent = $this->makeAsset();
        $second = $this->second();

        $this->create(['name' => 'Pump', 'project_site_id' => $second->id, 'parent_asset_id' => $parent->id])->assertSessionHasErrors('parent_asset_id');

        $this->assertNull($this->created('Pump'));
    }

    public function test_the_model_refuses_a_child_at_another_site_and_an_asset_as_its_own_parent(): void
    {
        $parent = $this->makeAsset();
        $second = $this->second();

        try {
            $this->makeAsset(null, ['project_site_id' => $second->id, 'parent_asset_id' => $parent->id]);
            $this->fail('A child at another site was saved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_asset_id', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $parent->update(['parent_asset_id' => $parent->id]);
    }

    public function test_a_parent_with_children_cannot_move_to_another_site(): void
    {
        $parent = $this->makeAsset();
        $this->makeAsset(null, ['parent_asset_id' => $parent->id]);
        $second = $this->second();

        $this->update($parent, ['project_site_id' => $second->id])->assertSessionHasErrors('project_site_id');

        $this->assertSame($this->site->id, $parent->fresh()->project_site_id);
    }

    /* ---------- decommission (AST-03) ---------- */

    public function test_decommissioning_keeps_the_asset_and_its_history_but_it_can_no_longer_be_chosen(): void
    {
        $asset = $this->makeAsset();
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(['primary_asset_id' => $asset->id])->save();

        $this->as('company_admin')->post($this->host('/admin/assets/' . $asset->id . '/decommission'))->assertSessionHasNoErrors();

        $this->assertSame('decommissioned', $asset->fresh()->status);
        $this->assertSame($asset->id, $sr->fresh()->primary_asset_id);                          // the history stays
        $this->assertSame(1, $this->audits('asset.decommissioned'));
        $this->assertNotContains($asset->id, Asset::selectable()->pluck('id')->all());

        $json = $this->as('operations_manager')->getJson($this->host('/service-requests/assets/for-site?site_id=' . $this->site->id))->assertOk()->json();
        $this->assertNotContains($asset->id, array_column($json, 'id'));
    }

    public function test_a_decommissioned_asset_is_read_only(): void
    {
        $asset = $this->makeAsset(null, ['status' => 'decommissioned']);

        $this->update($asset, ['name' => 'Renamed'])->assertSessionHas('error');

        $this->assertNotSame('Renamed', $asset->fresh()->name);
    }

    public function test_only_people_who_manage_assets_can_decommission(): void
    {
        $asset = $this->makeAsset();

        $this->as('finance')->post($this->host('/admin/assets/' . $asset->id . '/decommission'))->assertForbidden();

        $this->assertSame('active', $asset->fresh()->status);
    }

    /* ---------- import (AST-10) ---------- */

    protected function header(): string
    {
        return "asset_code,name,client_code,site_code,category,parent_asset_code,manufacturer,model,serial_number,location_within_site,installation_date,warranty_expiry_date,status,notes\n";
    }

    protected function row(array $o = []): string
    {
        $cat = $o['category'] ?? $this->importCategory()->code;
        $f = $o + ['code' => '', 'name' => 'Pump', 'client' => $this->client->client_code, 'site' => $this->site->site_code, 'parent' => '', 'status' => ''];

        return implode(',', [$f['code'], $f['name'], $f['client'], $f['site'], $cat, $f['parent'], '', '', '', '', $f['install'] ?? '', $f['warranty'] ?? '', $f['status'], '']) . "\n";
    }

    protected function importCategory()
    {
        static $cat = null;
        if ($cat === null || $cat->tenant_id !== $this->t->id) {
            $cat = $this->makeCategory(['code' => 'HVAC', 'name' => 'HVAC']);
        }

        return $cat;
    }

    protected function upload(string $csv, string $who = 'company_admin')
    {
        return $this->as($who)->post($this->host('/admin/assets/import'), ['csv' => UploadedFile::fake()->createWithContent('assets.csv', $csv)]);
    }

    protected function job(): ImportJob
    {
        return ImportJob::where('tenant_id', $this->t->id)->where('entity', 'assets')->latest('id')->firstOrFail();
    }

    public function test_the_upload_checks_every_row_and_creates_nothing(): void
    {
        $csv = $this->header()
            . $this->row(['name' => 'Good pump'])
            . $this->row(['name' => '', 'code' => ''])                                    // no name
            . $this->row(['name' => 'Bad client', 'client' => 'NOPE'])
            . $this->row(['name' => 'Bad status', 'status' => 'broken'])
            . $this->row(['name' => 'Bad dates', 'install' => '2026-05-01', 'warranty' => '2026-01-01'])
            . $this->row(['name' => 'Bad category', 'category' => 'Nonexistent']);

        $this->upload($csv)->assertRedirect();

        $job = $this->job();
        $this->assertSame(6, $job->total_rows);
        $this->assertSame(1, $job->valid_rows);
        $this->assertSame(5, $job->error_rows);
        $this->assertSame('validated', $job->status);
        $this->assertSame(0, Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->count());
    }

    public function test_commit_creates_the_valid_rows_and_skips_the_rest(): void
    {
        $csv = $this->header()
            . $this->row(['code' => 'AS-9001', 'name' => 'Chiller'])
            . $this->row(['name' => 'Pump of the chiller', 'parent' => 'AS-9001'])           // a parent earlier in the same file
            . $this->row(['name' => 'Broken', 'client' => 'NOPE']);
        $this->upload($csv);
        $job = $this->job();

        $this->as('company_admin')->post($this->host('/admin/assets/import/' . $job->id . '/commit'))->assertRedirect(route('admin.assets.index'));

        $chiller = Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->where('asset_code', 'AS-9001')->first();
        $pump = Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->where('name', 'Pump of the chiller')->first();
        $this->assertNotNull($chiller);
        $this->assertSame($chiller->id, $pump->parent_asset_id);
        $this->assertNull(Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->where('name', 'Broken')->first());
        $this->assertSame('committed', $job->fresh()->status);
        $this->assertSame(2, $job->fresh()->valid_rows);
    }

    public function test_an_existing_code_is_skipped_and_a_second_commit_changes_nothing(): void
    {
        $this->makeAsset(null, ['asset_code' => 'AS-5000']);
        $this->upload($this->header() . $this->row(['code' => 'AS-5000', 'name' => 'Same code']) . $this->row(['name' => 'New one']));
        $job = $this->job();
        $this->assertSame(1, $job->skipped_rows);

        $this->as('company_admin')->post($this->host('/admin/assets/import/' . $job->id . '/commit'));
        $count = Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->count();
        $this->as('company_admin')->post($this->host('/admin/assets/import/' . $job->id . '/commit'));

        $this->assertSame(2, $count);                                                          // the old one and the new one
        $this->assertSame($count, Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->count());
    }

    public function test_a_parent_at_another_site_or_unknown_is_an_error(): void
    {
        $second = $this->second();
        $this->makeAsset(null, ['asset_code' => 'AS-7000']);
        $this->upload($this->header()
            . $this->row(['name' => 'Elsewhere', 'site' => $second->site_code, 'parent' => 'AS-7000'])
            . $this->row(['name' => 'Orphan', 'parent' => 'AS-0000'])
            . $this->row(['name' => 'Self', 'code' => 'AS-8000', 'parent' => 'AS-8000']));

        $this->assertSame(3, $this->job()->error_rows);
    }

    public function test_the_errors_file_lists_the_rows_with_their_messages(): void
    {
        $this->upload($this->header() . $this->row(['name' => 'Fine']) . $this->row(['name' => 'Bad client', 'client' => 'NOPE']));
        $job = $this->job();

        $csv = $this->as('company_admin')->get($this->host('/admin/assets/import/' . $job->id . '/errors'))->assertOk()->streamedContent();

        $this->assertStringContainsString('row,result,message', $csv);
        $this->assertStringContainsString('client NOPE not found', $csv);
        $this->assertStringNotContainsString('Fine', $csv);
    }

    public function test_a_file_without_the_required_columns_is_refused(): void
    {
        $this->upload("name,client_code\nPump,C1\n")->assertSessionHas('error');

        $this->assertSame(0, ImportJob::where('tenant_id', $this->t->id)->count());
    }

    public function test_a_file_over_2000_rows_is_refused(): void
    {
        $csv = $this->header() . str_repeat($this->row(), 2001);

        $this->upload($csv)->assertSessionHas('error');
    }

    public function test_only_people_who_may_import_can_upload(): void
    {
        $this->upload($this->header() . $this->row(), 'finance')->assertForbidden();
        $this->upload($this->header() . $this->row(), 'front_desk')->assertForbidden();
    }

    public function test_another_companys_import_job_is_a_404(): void
    {
        $other = $this->makeTenant(slug: 'other');
        $job = ImportJob::create(['tenant_id' => $other->id, 'entity' => 'assets', 'file_path' => 'imports/x.csv', 'original_name' => 'x.csv', 'status' => 'validated', 'created_by_user_id' => 1, 'total_rows' => 1, 'valid_rows' => 1, 'error_rows' => 0, 'skipped_rows' => 0]);

        $this->as('company_admin')->get($this->host('/admin/assets/import/' . $job->id))->assertNotFound();
        $this->as('company_admin')->post($this->host('/admin/assets/import/' . $job->id . '/commit'))->assertNotFound();
        $this->as('company_admin')->get($this->host('/admin/assets/import/' . $job->id . '/errors'))->assertNotFound();
    }

    /* ---------- QR codes (AST-05, AST-07) ---------- */

    public function test_a_signed_in_user_of_the_company_opens_the_asset_from_its_qr(): void
    {
        $asset = $this->makeAsset(null, ['name' => 'Scanned chiller']);

        $html = $this->as('operations_manager')->get($this->host('/q/' . $asset->qr_hash))->assertOk()->getContent();

        $this->assertStringContainsString('Scanned chiller', $html);
    }

    public function test_the_hash_is_long_and_random(): void
    {
        $a = $this->makeAsset();
        $b = $this->makeAsset();

        $this->assertSame(40, strlen($a->qr_hash));
        $this->assertNotSame($a->qr_hash, $b->qr_hash);
    }

    public function test_a_visitor_who_is_not_signed_in_is_sent_to_the_login(): void
    {
        $asset = $this->makeAsset();

        $this->get($this->host('/q/' . $asset->qr_hash))->assertRedirect();
        $this->assertGuest();
    }

    public function test_a_client_user_cannot_open_an_asset_qr(): void
    {
        $asset = $this->makeAsset();
        Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $client = $this->makeUser($this->t, 'client_admin', 'qr@client.test');

        $this->actingAs($client)->get($this->host('/q/' . $asset->qr_hash))->assertForbidden();
    }

    public function test_another_companys_user_gets_a_404_for_this_companys_qr(): void
    {
        $asset = $this->makeAsset();
        $other = $this->makeTenant(slug: 'other');
        $theirs = $this->makeUser($other, 'company_admin');
        $plan = $other->plan;
        $plan->features = array_merge((array) $plan->features, ['assets_full' => true]);
        $plan->save();

        // on their own address and on the root address: always looked up in the signed-in user's own company
        $this->actingAs($theirs->fresh())->get($this->onHost($other, '/q/' . $asset->qr_hash))->assertNotFound();
        if (\App\Support\Tenancy::isPath()) {
            // path mode: an old root QR link goes on to the asset's own company, which signs a user of another company out
            $owner = \App\Models\Tenant::find($asset->tenant_id);
            $this->actingAs($theirs->fresh())->get('http://localhost/q/' . $asset->qr_hash)->assertStatus(301)->assertRedirect($this->onHost($owner, '/q/' . $asset->qr_hash));
            $this->actingAs($theirs->fresh())->get($this->onHost($owner, '/q/' . $asset->qr_hash))->assertRedirect($this->onHost($owner, '/login'));
        } else {
            $this->actingAs($theirs->fresh())->get('http://localhost/q/' . $asset->qr_hash)->assertNotFound();
        }
    }

    public function test_an_unknown_hash_is_a_404(): void
    {
        $this->as('operations_manager')->get($this->host('/q/' . str_repeat('a', 40)))->assertNotFound();
    }

    public function test_a_decommissioned_asset_does_not_offer_a_new_request(): void
    {
        $asset = $this->makeAsset(null, ['status' => 'decommissioned']);

        $html = $this->as('operations_manager')->get($this->host('/q/' . $asset->qr_hash))->assertOk()->getContent();

        $this->assertStringNotContainsString('service-requests/create?asset=', $html);
    }

    /* ---------- plan gate ---------- */

    public function test_the_basic_pages_work_on_every_plan_but_labels_import_and_360_need_assets_full(): void
    {
        $this->setAssetPlan(false);
        $asset = $this->makeAsset();

        $this->as('company_admin')->get($this->host('/admin/assets'))->assertOk();
        $this->as('company_admin')->get($this->host('/admin/assets/create'))->assertOk();
        foreach (['/admin/assets/' . $asset->id, '/admin/assets/' . $asset->id . '/label', '/admin/assets/labels', '/admin/assets/import'] as $path) {
            $this->as('company_admin')->get($this->host($path))->assertForbidden();
        }
        $this->as('operations_manager')->get($this->host('/q/' . $asset->qr_hash))->assertForbidden();
    }

    public function test_the_full_pages_open_on_a_plan_with_assets_full(): void
    {
        $asset = $this->makeAsset();

        foreach (['/admin/assets/' . $asset->id, '/admin/assets/' . $asset->id . '/label', '/admin/assets/labels?ids[]=' . $asset->id, '/admin/assets/import'] as $path) {
            $this->as('company_admin')->get($this->host($path))->assertOk();
        }
    }

    /* ---------- isolation ---------- */

    public function test_another_companys_asset_is_a_404_everywhere(): void
    {
        $other = $this->makeTenant(slug: 'other');
        $theirs = Asset::withoutGlobalScopes()->create([
            'tenant_id' => $other->id, 'client_id' => $this->makeClient($other, 'Theirs')->id, 'project_site_id' => 999, 'service_category_id' => 999,
            'name' => 'Their chiller', 'status' => 'active',
        ]);

        foreach (['/admin/assets/' . $theirs->id, '/admin/assets/' . $theirs->id . '/edit', '/admin/assets/' . $theirs->id . '/label'] as $path) {
            $this->as('company_admin')->get($this->host($path))->assertNotFound();
        }
        $this->as('company_admin')->put($this->host('/admin/assets/' . $theirs->id), $this->payload(['name' => 'Hijacked']))->assertNotFound();
        $this->as('company_admin')->post($this->host('/admin/assets/' . $theirs->id . '/decommission'))->assertNotFound();

        $this->assertSame('Their chiller', $theirs->fresh()->name);
        $this->assertSame('active', $theirs->fresh()->status);
    }

    public function test_the_list_shows_only_this_companys_assets(): void
    {
        $mine = $this->makeAsset(null, ['name' => 'My chiller']);
        $other = $this->makeTenant(slug: 'other');
        Asset::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'client_id' => $this->makeClient($other, 'Theirs')->id, 'project_site_id' => 999, 'service_category_id' => 999, 'name' => 'Their chiller', 'status' => 'active']);

        $html = $this->as('company_admin')->get($this->host('/admin/assets'))->assertOk()->getContent();

        $this->assertStringContainsString('My chiller', $html);
        $this->assertStringNotContainsString('Their chiller', $html);
    }
}
