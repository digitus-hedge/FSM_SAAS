<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    private array $perPageOptions = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, $this->perPageOptions)) $perPage = 10;

        $status = $request->input('status', 'all');  // all | active | inactive
        $search = trim((string) $request->input('q', ''));

        $query = ExpenseCategory::query()->orderBy('name');

        if ($status === 'active')   $query->active();
        if ($status === 'inactive') $query->inactive();
        if ($search !== '')         $query->search($search);

        $categories = $query->paginate($perPage)->withQueryString();

        return view('admin.expense-categories.index', [
            'categories'     => $categories,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
            'status'         => $status,
            'search'         => $search,
        ]);
    }

    public function create()
    {
        return view('admin.expense-categories.form', [
            'category' => new ExpenseCategory(['is_active' => true, 'is_billable_default' => true]),
            'mode'     => 'create',
        ]);
    }

    public function store(ExpenseCategoryRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenant_id;

        ExpenseCategory::create($data);

        return redirect()
            ->route('admin.expense-categories.index')
            ->with('success', 'Expense category created.');
    }

    public function edit(ExpenseCategory $expense_category)
    {
        return view('admin.expense-categories.form', [
            'category' => $expense_category,
            'mode'     => 'edit',
        ]);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expense_category)
    {
        $expense_category->update($request->validated());

        return redirect()
            ->route('admin.expense-categories.index')
            ->with('success', 'Expense category updated.');
    }

    public function destroy(ExpenseCategory $expense_category)
    {
        $expense_category->delete();

        return redirect()
            ->route('admin.expense-categories.index')
            ->with('success', 'Expense category deleted.');
    }

    public function suspend(ExpenseCategory $expense_category)
    {
        $expense_category->update(['is_active' => false]);

        return redirect()
            ->route('admin.expense-categories.index')
            ->with('success', 'Expense category suspended.');
    }

    public function reactivate(ExpenseCategory $expense_category)
    {
        $expense_category->update(['is_active' => true]);

        return redirect()
            ->route('admin.expense-categories.index')
            ->with('success', 'Expense category reactivated.');
    }
}