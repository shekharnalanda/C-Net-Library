<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedJobController extends Controller
{
    public function index(Request $request): View
    {
        $student = $this->student($request);

        $jobs = $student->savedJobs()
            ->where('jobs.status', true)
            ->where(function ($query) {
                $query->whereNull('last_date')->orWhereDate('last_date', '>=', today());
            })
            ->orderBy('last_date')
            ->orderByDesc('jobs.id')
            ->paginate(20);

        return view('student.saved-jobs.index', compact('jobs'));
    }

    public function store(Request $request, Job $job): RedirectResponse
    {
        abort_unless($job->status && (! $job->last_date || $job->last_date->gte(today())), 404);

        $student = $this->student($request);
        $student->savedJobs()->syncWithoutDetaching([$job->id]);

        return back()->with('success', 'Job saved.');
    }

    public function destroy(Request $request, Job $job): RedirectResponse
    {
        $student = $this->student($request);
        $student->savedJobs()->detach($job->id);

        return back()->with('success', 'Saved job removed.');
    }

    private function student(Request $request): Student
    {
        return Student::query()->where('user_id', $request->user()->id)->firstOrFail();
    }
}
