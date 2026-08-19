<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = Job::query()
            ->where('status', true)
            ->where(function ($q) {
                $q->whereNull('last_date')
                    ->orWhereDate('last_date', '>=', today());
            })
            ->orderByDesc('is_featured')
            ->orderBy('last_date')
            ->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('job_type', $request->type);
        }

        if ($request->filled('qualification')) {
            $query->where('qualification', 'like', '%' . trim((string) $request->qualification) . '%');
        }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . trim((string) $request->location) . '%');
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('organization', 'like', "%{$term}%")
                    ->orWhere('category', 'like', "%{$term}%");
            });
        }

        return view('public.jobs.index', [
            'jobs' => $query->paginate(20)->withQueryString(),
        ]);
    }
}
