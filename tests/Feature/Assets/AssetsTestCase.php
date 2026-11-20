<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\ProjectSite;
use Tests\Feature\Forms\FormsTestCase;

/**
 * Shared set-up for the asset tests (Release B register AST-01 to AST-12). The company is on a plan with the asset features.
 */
abstract class AssetsTestCase extends FormsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setAssetPlan(true);
    }

    protected function setAssetPlan(bool $full): void
    {
        $plan = $this->t->plan;
        $plan->features = array_merge((array) $plan->features, ['assets_basic' => true, 'assets_full' => $full]);
        $plan->save();
        foreach ($this->u as $k => $user) {
            $this->u[$k] = $user->fresh();
        }
    }

    protected function second(): ProjectSite
    {
        return ProjectSite::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'name' => 'Second site']);
    }

    protected function audits(string $action): int
    {
        return \Illuminate\Support\Facades\DB::table('activity_logs')->where('tenant_id', $this->t->id)->where('action', $action)->count();
    }

    protected function host(string $path = ''): string
    {
        return $this->onHost($this->t, $path);
    }

    protected function payload(array $over = []): array
    {
        return array_merge([
            'client_id' => $this->client->id, 'project_site_id' => $this->site->id, 'service_category_id' => $this->makeCategory()->id,
            'name' => 'Chiller', 'status' => 'active',
        ], $over);
    }

    protected function create(array $over = [], string $who = 'company_admin')
    {
        return $this->as($who)->post($this->host('/admin/assets'), $this->payload($over));
    }

    protected function update(Asset $asset, array $over = [], string $who = 'company_admin')
    {
        return $this->as($who)->put($this->host('/admin/assets/' . $asset->id), $this->payload([
            'service_category_id' => $asset->service_category_id, 'name' => $asset->name,
        ] + $over));
    }

    protected function created(string $name = 'Chiller'): ?Asset
    {
        return Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->where('name', $name)->first();
    }
}
