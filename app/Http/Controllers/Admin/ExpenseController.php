<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseAdjustment;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth();
        $to = $request->filled('to') ? $request->date('to') : today();
        $branchId = $request->user()->isGlobalAdmin()
            ? ($request->filled('branch_id') ? $request->integer('branch_id') : null)
            : (int) $request->user()->branch_id;

        $baseQuery = Expense::query()
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->filled('payment_mode'), fn ($query) => $query->where('payment_mode', $request->string('payment_mode')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->where(function ($q) use ($search) {
                    $q->where('category', 'like', "%{$search}%")
                        ->orWhere('payee', 'like', "%{$search}%")
                        ->orWhere('transaction_ref', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        $expenses = (clone $baseQuery)
            ->with(['branch', 'creator', 'adjustments.creator', 'payroll.staff'])
            ->latest('expense_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $grossExpenses = (float) (clone $baseQuery)->sum('amount');
        $adjustments = (float) ExpenseAdjustment::query()
            ->whereHas('expense', function ($query) use ($from, $to, $branchId, $request) {
                $query->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
                    ->when($branchId, fn ($expense) => $expense->where('branch_id', $branchId))
                    ->when($request->filled('category'), fn ($expense) => $expense->where('category', $request->string('category')->toString()))
                    ->when($request->filled('payment_mode'), fn ($expense) => $expense->where('payment_mode', $request->string('payment_mode')->toString()))
                    ->when($request->filled('search'), function ($expense) use ($request) {
                        $search = trim($request->string('search')->toString());
                        $expense->where(function ($q) use ($search) {
                            $q->where('category', 'like', "%{$search}%")
                                ->orWhere('payee', 'like', "%{$search}%")
                                ->orWhere('transaction_ref', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                    });
            })
            ->sum('amount');

        $categoryTotals = (clone $baseQuery)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $categories = Expense::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $branches = Branch::query()->where('status', true);
        if (! $request->user()->isGlobalAdmin()) {
            $branches->whereKey($request->user()->branch_id);
        }

        return view('admin.expenses.index', [
            'expenses' => $expenses,
            'branches' => $branches->orderBy('name')->get(),
            'categories' => $categories,
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'grossExpenses' => $grossExpenses,
            'expenseAdjustments' => $adjustments,
            'totalExpenses' => max(0, $grossExpenses - $adjustments),
            'categoryTotals' => $categoryTotals,
            'entryCount' => (clone $baseQuery)->count(),
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

        if (! $request->user()->isGlobalAdmin()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $transactionRef = trim((string) ($data['transaction_ref'] ?? ''));
        if ($transactionRef !== '' && Expense::query()->where('transaction_ref', $transactionRef)->exists()) {
            throw ValidationException::withMessages([
                'transaction_ref' => 'This expense transaction reference has already been recorded.',
            ]);
        }

        try {
            $expense = Expense::create([
                ...$data,
                'transaction_ref' => $transactionRef !== '' ? $transactionRef : null,
                'created_by' => auth()->id(),
            ]);
        } catch (QueryException $exception) {
            if ($transactionRef !== '' && in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'transaction_ref' => 'This expense transaction reference has already been recorded.',
                ]);
            }

            throw $exception;
        }

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

    public function adjust(Request $request, Expense $expense, AuditService $audit)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['reversal', 'correction', 'refund'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $adjustment = DB::transaction(function () use ($expense, $data) {
            $locked = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $alreadyAdjusted = (float) $locked->adjustments()->sum('amount');
            $remaining = max(0, (float) $locked->amount - $alreadyAdjusted);
            $amount = (float) $data['amount'];

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment amount cannot exceed the remaining expense amount.',
                ]);
            }

            return $locked->adjustments()->create([
                'type' => $data['type'],
                'amount' => $amount,
                'reason' => $data['reason'],
                'created_by' => auth()->id(),
            ]);
        });

        $audit->log(
            action: 'expense.adjustment.created',
            auditable: $adjustment,
            newValues: $adjustment->only(['expense_id', 'type', 'amount', 'reason']),
            request: $request,
        );

        return back()->with('success', 'Expense adjustment recorded. Original expense remains unchanged.');
    }
}
