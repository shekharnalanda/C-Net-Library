<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth();
        $to = $request->filled('to') ? $request->date('to') : today();
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        $baseQuery = Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        $expenses = (clone $baseQuery)
            ->with(['branch', 'creator'])
            ->latest('expense_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $categoryTotals = (clone $baseQuery)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return view('admin.expenses.index', [
            'expenses' => $expenses,
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'totalExpenses' => (float) (clone $baseQuery)->sum('amount'),
            'categoryTotals' => $categoryTotals,
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'string', 'max:120'],
            'payee' => ['nullable', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'payment_mode' => ['required', 'in:cash,upi,card,bank_transfer,other'],
            'transaction_ref' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $transactionRef = trim((string) ($data['transaction_ref'] ?? ''));
        if ($transactionRef !== '' && Expense::query()->where('transaction_ref', $transactionRef)->exists()) {
            throw ValidationException::withMessages([
                'transaction_ref' => 'This expense transaction reference has already been recorded.',
            ]);
        }

        $expense = Expense::create([
            ...$data,
            'transaction_ref' => $transactionRef !== '' ? $transactionRef : null,
            'created_by' => auth()->id(),
        ]);

        $audit->log(
            action: 'expense.created',
            auditable: $expense,
            newValues: $expense->only([
                'branch_id', 'expense_date', 'category', 'payee', 'amount',
                'payment_mode', 'transaction_ref', 'description',
            ]),
            request: $request,
        );

        return back()->with('success', 'Expense recorded in cashbook.');
    }
}
