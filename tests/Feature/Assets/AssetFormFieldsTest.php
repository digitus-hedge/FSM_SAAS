<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** AST-02: the photo, the specifications and the meter fields of the asset form. The meter fields show only while "Track a meter reading" is on. */
class AssetFormFieldsTest extends AssetsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /* ---------- the form ---------- */

    public function test_the_form_can_upload_a_file_and_has_the_new_fields(): void
    {
        $html = $this->as('company_admin')->get($this->host('/admin/assets/create'))->assertOk()->getContent();

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        foreach (['name="photo"', 'name="tracks_meter"', 'name="meter_unit"', 'name="current_meter_reading"', 'name="specs[0][name]"', 'name="specs[0][value]"'] as $field) {
            $this->assertStringContainsString($field, $html, $field);
        }
    }

    public function test_the_meter_fields_are_hidden_and_disabled_until_tracking_is_on(): void
    {
        $off = $this->makeAsset(null, ['tracks_meter' => false]);
        $on  = $this->makeAsset(null, ['tracks_meter' => true, 'meter_unit' => 'hours', 'current_meter_reading' => 120.5]);

        $html = $this->as('company_admin')->get($this->host('/admin/assets/' . $off->id . '/edit'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="meter-fields"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/name="meter_unit"[^>]*disabled/', $html);

        $html = $this->as('company_admin')->get($this->host('/admin/assets/' . $on->id . '/edit'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="meter-fields"\s+hidden/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="meter_unit"[^>]*disabled/', $html);
        $this->assertStringContainsString('value="hours"', $html);
        $this->assertStringContainsString('value="120.50"', $html);
    }

    public function test_the_form_shows_the_saved_specifications_and_the_current_photo(): void
    {
        $asset = $this->makeAsset(null, ['specifications_json' => [['name' => 'Capacity', 'value' => '500 kW'], ['name' => 'Fuel', 'value' => 'Diesel']]]);
        $att = Attachment::create(['tenant_id' => $this->t->id, 'attachable_type' => $asset::class, 'attachable_id' => $asset->id, 'kind' => 'asset_photo', 'storage_path' => 'assets/x/p.jpg', 'original_name' => 'p.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 10, 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid()]);
        $asset->update(['photo_attachment_id' => $att->id]);

        $html = $this->as('company_admin')->get($this->host('/admin/assets/' . $asset->id . '/edit'))->assertOk()->getContent();

        $this->assertStringContainsString('value="Capacity"', $html);
        $this->assertStringContainsString('value="500 kW"', $html);
        $this->assertStringContainsString('value="Fuel"', $html);
        $this->assertStringContainsString('name="specs[1][name]"', $html);
        $this->assertStringContainsString('name="remove_photo"', $html);
        $this->assertStringContainsString('assets/x/p.jpg', $html);
    }

    /* ---------- saving ---------- */

    public function test_a_new_asset_can_be_saved_with_a_photo_specifications_and_a_meter(): void
    {
        $this->create([
            'photo' => UploadedFile::fake()->image('chiller.jpg', 400, 300),
            'tracks_meter' => 1, 'meter_unit' => 'hours', 'current_meter_reading' => '1250.5',
            'specs' => [['name' => 'Capacity', 'value' => '500 kW'], ['name' => '', 'value' => ''], ['name' => ' Fuel ', 'value' => ' Diesel ']],
        ])->assertSessionHasNoErrors();

        $asset = $this->created();
        $this->assertNotNull($asset);
        $this->assertTrue($asset->tracks_meter);
        $this->assertSame('hours', $asset->meter_unit);
        $this->assertEquals(1250.5, (float) $asset->current_meter_reading);
        $this->assertNotNull($asset->last_meter_recorded_at);
        $this->assertSame([['name' => 'Capacity', 'value' => '500 kW'], ['name' => 'Fuel', 'value' => 'Diesel']], $asset->specifications_json);   // empty rows dropped, text trimmed

        $att = Attachment::find($asset->photo_attachment_id);
        $this->assertNotNull($att);
        $this->assertSame('asset_photo', $att->kind);
        $this->assertSame(Asset::class, $att->attachable_type);
        $this->assertSame($asset->id, (int) $att->attachable_id);
        Storage::disk('public')->assertExists($att->storage_path);
    }

    public function test_the_meter_fields_are_ignored_while_tracking_is_off(): void
    {
        $this->create(['meter_unit' => 'km', 'current_meter_reading' => '99'])->assertSessionHasNoErrors();

        $asset = $this->created();
        $this->assertFalse($asset->tracks_meter);
        $this->assertNull($asset->meter_unit);
        $this->assertEquals(0, (float) $asset->current_meter_reading);
        $this->assertNull($asset->last_meter_recorded_at);
    }

    public function test_switching_tracking_off_keeps_what_was_stored(): void
    {
        $asset = $this->makeAsset(null, ['tracks_meter' => true, 'meter_unit' => 'hours', 'current_meter_reading' => 300]);

        $this->update($asset, ['name' => $asset->name])->assertSessionHasNoErrors();           // tracks_meter is not sent: it is off

        $fresh = $asset->fresh();
        $this->assertFalse($fresh->tracks_meter);
        $this->assertSame('hours', $fresh->meter_unit);
        $this->assertEquals(300, (float) $fresh->current_meter_reading);
    }

    public function test_a_changed_reading_stamps_the_time_and_an_unchanged_one_does_not(): void
    {
        $asset = $this->makeAsset(null, ['tracks_meter' => true, 'meter_unit' => 'hours', 'current_meter_reading' => 300]);

        $this->update($asset, ['tracks_meter' => 1, 'meter_unit' => 'hours', 'current_meter_reading' => '300'])->assertSessionHasNoErrors();
        $this->assertNull($asset->fresh()->last_meter_recorded_at);

        $this->update($asset, ['tracks_meter' => 1, 'meter_unit' => 'hours', 'current_meter_reading' => '310'])->assertSessionHasNoErrors();
        $this->assertNotNull($asset->fresh()->last_meter_recorded_at);
        $this->assertEquals(310, (float) $asset->fresh()->current_meter_reading);
    }

    public function test_the_specifications_can_be_cleared(): void
    {
        $asset = $this->makeAsset(null, ['specifications_json' => [['name' => 'Capacity', 'value' => '500 kW']]]);

        $this->update($asset, ['specs' => [['name' => '', 'value' => '']]])->assertSessionHasNoErrors();

        $this->assertNull($asset->fresh()->specifications_json);
    }

    /* ---------- validation ---------- */

    public function test_tracking_a_meter_needs_a_unit(): void
    {
        $this->create(['tracks_meter' => 1, 'meter_unit' => ''])->assertSessionHasErrors('meter_unit');
        $this->assertNull($this->created());
    }

    /** @dataProvider badReadings */
    public function test_a_bad_reading_is_refused($reading): void
    {
        $this->create(['tracks_meter' => 1, 'meter_unit' => 'km', 'current_meter_reading' => $reading])->assertSessionHasErrors('current_meter_reading');
    }

    public static function badReadings(): array
    {
        return [['-5'], ['abc'], ['99999999999']];
    }

    public function test_a_specification_value_needs_a_name(): void
    {
        $this->create(['specs' => [['name' => '', 'value' => '500 kW']]])->assertSessionHasErrors('specs.0.name');
    }

    public function test_at_most_30_specifications(): void
    {
        $rows = array_map(fn ($i) => ['name' => 'N' . $i, 'value' => 'V'], range(1, 31));

        $this->create(['specs' => $rows])->assertSessionHasErrors('specs');
    }

    public function test_the_photo_must_be_a_small_image(): void
    {
        $this->create(['photo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->create(['photo' => UploadedFile::fake()->image('big.jpg')->size(6000)])->assertSessionHasErrors('photo');
        $this->assertNull($this->created());
    }

    /* ---------- the photo over time ---------- */

    public function test_a_new_photo_replaces_the_old_file_and_row(): void
    {
        $this->create(['photo' => UploadedFile::fake()->image('one.jpg')])->assertSessionHasNoErrors();
        $asset = $this->created();
        $old = Attachment::find($asset->photo_attachment_id);

        $this->update($asset, ['photo' => UploadedFile::fake()->image('two.png')])->assertSessionHasNoErrors();

        $fresh = $asset->fresh();
        $new = Attachment::find($fresh->photo_attachment_id);
        $this->assertNotSame($old->id, $new->id);
        Storage::disk('public')->assertExists($new->storage_path);
        Storage::disk('public')->assertMissing($old->storage_path);
        $this->assertNull(Attachment::find($old->id));                      // soft deleted
    }

    public function test_remove_photo_clears_it(): void
    {
        $this->create(['photo' => UploadedFile::fake()->image('one.jpg')])->assertSessionHasNoErrors();
        $asset = $this->created();
        $path = Attachment::find($asset->photo_attachment_id)->storage_path;

        $this->update($asset, ['remove_photo' => 1])->assertSessionHasNoErrors();

        $this->assertNull($asset->fresh()->photo_attachment_id);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_saving_without_a_file_keeps_the_photo(): void
    {
        $this->create(['photo' => UploadedFile::fake()->image('one.jpg')])->assertSessionHasNoErrors();
        $asset = $this->created();
        $id = $asset->photo_attachment_id;

        $this->update($asset, ['name' => 'Chiller'])->assertSessionHasNoErrors();

        $this->assertSame($id, $asset->fresh()->photo_attachment_id);
    }

    public function test_the_asset_360_page_lists_the_photo(): void
    {
        $this->create(['photo' => UploadedFile::fake()->image('one.jpg')])->assertSessionHasNoErrors();
        $asset = $this->created();

        $html = $this->as('company_admin')->get($this->host('/admin/assets/' . $asset->id))->assertOk()->getContent();

        $this->assertStringContainsString(Attachment::find($asset->photo_attachment_id)->storage_path, $html);
    }

    public function test_a_decommissioned_asset_cannot_change_its_photo_or_meter(): void
    {
        $asset = $this->makeAsset(null, ['status' => 'decommissioned']);

        $this->update($asset, ['status' => 'active', 'photo' => UploadedFile::fake()->image('one.jpg'), 'tracks_meter' => 1, 'meter_unit' => 'km'])->assertSessionHas('error');

        $fresh = $asset->fresh();
        $this->assertSame('decommissioned', $fresh->status);
        $this->assertNull($fresh->photo_attachment_id);
        $this->assertFalse($fresh->tracks_meter);
    }

    public function test_only_people_who_manage_assets_can_save_them(): void
    {
        $this->create(['name' => 'Nope'], 'finance')->assertForbidden();

        $this->assertNull($this->created('Nope'));
    }
}
