<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequestExpense;
use Illuminate\Http\Request;

class ExpenseReviewController extends Controller
{
    /**
     * Only Company Admin can review expenses.
     * Called at the top of every method.
     */
    private function guard(): void
    {
        abort_unless(
            auth()->user()?->role?->name === 'Company Admin',
            403,
            'Only Company Admins can review expenses.'
        );
    }

    public function index(Request $request)
    {
        $this->guard();

        $tab = $request->input('tab', 'waiting');

        $base = fn() => ServiceRequestExpense::query()
            ->with([
                'serviceRequest:id,sr_number',
                'loggedBy:id,name',
                'category:id,name' . (\Schema::hasColumn('expense_categories', 'is_vendor') ? ',is_vendor' : ''),
                'receipt:id,storage_path,original_name',
            ])
            ->orderByDesc('id');

        $items = match ($tab) {
            'approved' => $base()->where('approval_status', 'approved')->get(),
            'rejected' => $base()->where('approval_status', 'rejected')->get(),
            'all'      => $base()->get(),
            default    => $base()->where('approval_status', 'pending')->get(),
        };

        $counts = [
            'waiting'  => ServiceRequestExpense::where('approval_status', 'pending')->count(),
            'approved' => ServiceRequestExpense::where('approval_status', 'approved')->count(),
            'rejected' => ServiceRequestExpense::where('approval_status', 'rejected')->count(),
            'all'      => ServiceRequestExpense::count(),
        ];

        return view('admin.expenses-review.index', compact('items', 'tab', 'counts'));
    }

    public function approve(Request $request, ServiceRequestExpense $expense)
    {
        $this->guard();

        $expense->update([
            'approval_status'     => 'approved',
            'approved_by_user_id' => auth()->id(),
            'approved_at'         => now(),
            'rejected_by_user_id' => null,
            'rejected_at'         => null,
            'rejected_reason'     => null,
        ]);

        return back()->with('success', 'Expense approved.');
    }

    public function reject(Request $request, ServiceRequestExpense $expense)
    {
        $this->guard();

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Please give a reason for rejecting this expense.',
        ]);

        $expense->update([
            'approval_status'     => 'rejected',
            'rejected_by_user_id' => auth()->id(),
            'rejected_at'         => now(),
            'rejected_reason'     => $data['reason'],
            'approved_by_user_id' => null,
            'approved_at'         => null,
        ]);

        return back()->with('success', 'Expense rejected.');
    }
}