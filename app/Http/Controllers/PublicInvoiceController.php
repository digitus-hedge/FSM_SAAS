<?php
namespace App\Http\Controllers;

use App\Models\InvoiceToken;
use Illuminate\Http\Request;

class PublicInvoiceController extends Controller
{
    public function show(string $token)
    {
        $t = InvoiceToken::where('token', $token)->firstOrFail();
        $invoice = $t->invoice()->with(['items.taxRate', 'client', 'serviceRequest'])->firstOrFail();

        $t->update([
            'viewed_first_at' => $t->viewed_first_at ?? now(),
            'viewed_count'    => $t->viewed_count + 1,
        ]);

        return view('public.invoice', ['invoice' => $invoice, 'token' => $t]);
    }

    public function pdf(string $token)
    {
        $t = InvoiceToken::where('token', $token)->firstOrFail();
        $invoice = $t->invoice()->with(['items.taxRate', 'client', 'serviceRequest.site'])->firstOrFail();
        return view('admin.invoices.pdf', compact('invoice'));
    }
}