<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = Job::query()->with('branch')->latest('id');

        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('organization', 'like', "%{$term}%")
                    ->orWhere('qualification', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('job_type', $request->type);
        }

        return view('admin.jobs.index', [
            'jobs' => $query->paginate(25)->withQueryString(),
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'title' => ['required', 'string', 'max:255'],
            'organization' => ['required', 'string', 'max:255'],
            'job_type' => ['required', Rule::in(['government', 'private', 'internship', 'apprenticeship'])],
            'category' => ['nullable', 'string', 'max:150'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'published_date' => ['nullable', 'date'],
            'last_date' => ['nullable', 'date', 'after_or_equal:published_date'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'official_url' => ['required', 'url', 'max:2048'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['status'] = $request->has('status') ? $request->boolean('status') : true;

        Job::create($data);

        return back()->with('success', 'Job listing created successfully.');
    }
}
