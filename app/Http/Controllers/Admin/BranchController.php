<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::query()
            ->withCount(['students', 'studyHalls', 'studySlots', 'feePlans'])
            ->orderByDesc('status')
            ->orderBy('name')
            ->get();

        return view('admin.branches.index', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper(trim($data['code']));
        $data['is_24x7'] = $request->boolean('is_24x7');
        $data['status'] = $request->boolean('status', true);

        Branch::create($data);

        return back()->with('success', 'Branch created successfully. Now configure its study hall, seats, slots, fee plans and lockers.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $this->validated($request, $branch);
        $data['code'] = strtoupper(trim($data['code']));
        $data['is_24x7'] = $request->boolean('is_24x7');
        $data['status'] = $request->boolean('status');

        $branch->update($data);

        return back()->with('success', 'Branch updated successfully.');
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('branches', 'code')->ignore($branch?->id),
            ],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
            'is_24x7' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);
    }
}
