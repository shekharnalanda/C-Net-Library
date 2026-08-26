<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Locker;
use App\Models\LockerAllocation;
use App\Models\Student;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LockerController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $branches = Branch::query()
            ->where('status', true)
            ->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $branchIds = $branches->pluck('id');

        $lockers = Locker::query()
            ->whereIn('branch_id', $branchIds)
            ->with('branch:id,name')
            ->withCount(['allocations as active_allocations_count' => fn ($q) => $q
                ->whereIn('status', ['reserved', 'active'])
                ->whereDate('allocated_from', '<=', today())
                ->where(fn ($d) => $d->whereNull('allocated_to')->orWhereDate('allocated_to', '>=', today()))])
            ->orderBy('branch_id')
            ->orderBy('locker_no')
            ->get();

        $students = Student::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'student_code', 'name', 'mobile']);

        $allocations = LockerAllocation::query()
            ->whereHas('student', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->with(['locker:id,branch_id,locker_no,location,monthly_charge', 'student:id,branch_id,student_code,name,mobile'])
            ->latest('allocated_from')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $summary = [
            'total' => $lockers->count(),
            'active' => $lockers->where('status', true)->count(),
            'occupied' => $lockers->where('active_allocations_count', '>', 0)->count(),
            'available' => $lockers->where('status', true)->where('active_allocations_count', 0)->count(),
        ];

        return view('admin.lockers.index', compact('branches', 'lockers', 'students', 'allocations', 'summary'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'locker_no' => ['required', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:120'],
            'monthly_charge' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        AdminBranchScope::authorize($request, (int) $data['branch_id']);
        $request->validate([
            'locker_no' => [Rule::unique('lockers')->where(fn ($q) => $q->where('branch_id', $data['branch_id']))],
        ]);
        $data['status'] = $request->boolean('status', true);
        Locker::create($data);

        return back()->with('success', 'Locker created successfully.');
    }

    public function update(Request $request, Locker $locker): RedirectResponse
    {
        AdminBranchScope::authorize($request, $locker->branch_id);
        $data = $request->validate([
            'locker_no' => [
                'required', 'string', 'max:50',
                Rule::unique('lockers')->where(fn ($q) => $q->where('branch_id', $locker->branch_id))->ignore($locker->id),
            ],
            'location' => ['nullable', 'string', 'max:120'],
            'monthly_charge' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);
        $data['status'] = $request->boolean('status');
        $locker->update($data);

        return back()->with('success', 'Locker updated.');
    }

    public function allocate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locker_id' => ['required', 'exists:lockers,id'],
            'student_id' => ['required', 'exists:students,id'],
            'allocated_from' => ['required', 'date'],
            'allocated_to' => ['nullable', 'date', 'after_or_equal:allocated_from'],
            'status' => ['required', Rule::in(['reserved', 'active'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $locker = Locker::findOrFail($data['locker_id']);
        $student = Student::findOrFail($data['student_id']);
        AdminBranchScope::authorize($request, $student->branch_id);
        abort_unless((int) $locker->branch_id === (int) $student->branch_id, 422, 'Locker is in another branch.');
        abort_unless($locker->status, 422, 'Locker is disabled.');

        $to = $data['allocated_to'] ?? $data['allocated_from'];
        $conflict = LockerAllocation::query()
            ->where('locker_id', $locker->id)
            ->whereIn('status', ['reserved', 'active'])
            ->whereDate('allocated_from', '<=', $to)
            ->where(fn ($q) => $q->whereNull('allocated_to')->orWhereDate('allocated_to', '>=', $data['allocated_from']))
            ->exists();

        if ($conflict) {
            return back()->withErrors(['locker_id' => 'Selected locker is already allocated for this period.'])->withInput();
        }

        LockerAllocation::create([
            'locker_id' => $locker->id,
            'student_id' => $student->id,
            'allocated_from' => $data['allocated_from'],
            'allocated_to' => $data['allocated_to'] ?? null,
            'monthly_charge' => $locker->monthly_charge,
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Locker allocated successfully. Monthly charge has been captured on the allocation.');
    }

    public function updateAllocation(Request $request, LockerAllocation $allocation): RedirectResponse
    {
        $allocation->loadMissing('student');
        AdminBranchScope::authorize($request, $allocation->student->branch_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['reserved', 'active', 'completed', 'cancelled'])],
            'allocated_to' => ['nullable', 'date', 'after_or_equal:'.$allocation->allocated_from->toDateString()],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);
        $allocation->update($data);

        return back()->with('success', 'Locker allocation updated.');
    }
}
