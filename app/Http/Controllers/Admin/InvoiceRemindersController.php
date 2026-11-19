<?php

namespace App\Http\Controllers\Admin;

use App\Services\Permissions\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\OverdueReminderService;
use Illuminate\Http\Request;

/**
 * Pause and resume overdue reminders on one invoice (D17). Needs invoice.manage and the billing_full plan.
 * The buttons on the invoice page come with the Part 2 work on the invoice views; these endpoints are ready for them.
 */
class InvoiceRemindersController extends Controller
{
    private function invoice(Invoice $invoice): Invoice
    {
        $user = auth()->user();
        abort_unless($user && $user->tenant_id && PermissionLevel::canChange($user, 'invoice.manage'), 403);

        // Route binding is already limited to the signed-in company, so another company's invoice is a 404 (CP-104).
        return $invoice;
    }

    public function pause(Request $request, OverdueReminderService $reminders, Invoice $invoice)
    {
        $inv = $this->invoice($invoice);
        abort_unless(in_array($inv->status, ['sent', 'partially_paid', 'overdue'], true), 422);

        $request->validate(['reason' => 'required|string|min:3|max:255'], ['reason.*' => 'Enter a reason of 3 to 255 characters.']);
        $reminders->pause($inv, $request->input('reason'), $request->user());

        return redirect()->route('admin.invoices.show', $inv)->with('success', 'Reminders paused.');
    }

    public function resume(Request $request, OverdueReminderService $reminders, Invoice $invoice)
    {
        $inv = $this->invoice($invoice);
        $reminders->resume($inv, $request->user());

        return redirect()->route('admin.invoices.show', $inv)->with('success', 'Reminders resumed.');
    }
}
