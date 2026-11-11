<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Tenant;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

class AssetLabelsController extends Controller
{
    private const MAX = 200;

    private function allowed(): void
    {
        abort_unless(auth()->user()?->canDo('asset.view'), 403);
    }

    /** One label, from the asset form. */
    public function one(Asset $asset)
    {
        $this->allowed();
        return $this->render(collect([$asset]), false);
    }

    /** Labels for everything matching the list's current filters (never decommissioned). */
    public function many(Request $request)
    {
        $this->allowed();

        $q = Asset::query()->selectable()->with(['site:id,name'])->orderBy('asset_code');

        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('asset_code', 'like', "%{$s}%")
                ->orWhere('name', 'like', "%{$s}%")
                ->orWhere('serial_number', 'like', "%{$s}%")
                ->orWhere('manufacturer', 'like', "%{$s}%"));
        }
        if ($request->filled('site_id'))     $q->where('project_site_id', $request->input('site_id'));
        if ($request->filled('category_id')) $q->where('service_category_id', $request->input('category_id'));
        if ($request->filled('status'))      $q->where('status', $request->input('status'));
        if ($request->filled('warranty')) {
            match ($request->input('warranty')) {
                'in_warranty' => $q->whereNotNull('warranty_expiry_date')->whereDate('warranty_expiry_date', '>=', now()->toDateString()),
                'expired'     => $q->whereNotNull('warranty_expiry_date')->whereDate('warranty_expiry_date', '<', now()->toDateString()),
                'none'        => $q->whereNull('warranty_expiry_date'),
                default       => null,
            };
        }

        $total  = (clone $q)->count();
        $assets = $q->limit(self::MAX)->get();

        return $this->render($assets, $total > self::MAX ? $total : false);
    }

    private function render($assets, $overLimitTotal)
    {
        $base = request()->getSchemeAndHttpHost();
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 0), new SvgImageBackEnd()));

        $labels = $assets->map(function (Asset $a) use ($writer, $base) {
            $svg = $writer->writeString($base . '/q/' . $a->qr_hash);
            return [
                'code' => $a->asset_code,
                'name' => $a->name,
                'site' => $a->site?->name,
                'svg'  => preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg),
            ];
        });

        return view('admin.assets.labels', [
            'labels'    => $labels,
            'company'   => Tenant::current()?->name,
            'overLimit' => $overLimitTotal,
            'max'       => self::MAX,
        ]);
    }
}