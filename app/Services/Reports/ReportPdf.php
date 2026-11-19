<?php

namespace App\Services\Reports;

use App\Models\Tenant;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a report view to PDF with dompdf (Reports-Summaries-Preferences spec D3, D4). With the `brand_pdf` plan feature the header
 * carries the company's logo, name and accent colour (from the tenant settings, never a fixed colour); without it, a neutral header.
 */
class ReportPdf
{
    public function render(string $view, array $data, Tenant $tenant, string $title): string
    {
        $branded = $tenant->hasFeature('brand_pdf');
        $logo = null;
        if ($branded && $tenant->logo_path && Storage::disk('public')->exists($tenant->logo_path)) {
            $logo = 'data:image/' . pathinfo($tenant->logo_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(Storage::disk('public')->get($tenant->logo_path));
        }

        $html = view($view, $data + [
            'company' => $tenant->name, 'branded' => $branded, 'logo' => $logo, 'accent' => $branded && $tenant->primary_color ? $tenant->primary_color : '#444444',
            'title' => $title, 'generatedAt' => now($tenant->timezone ?: config('app.timezone'))->format('j M Y H:i'),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
