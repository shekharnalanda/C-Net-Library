<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DigitalResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DigitalLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $student = null;
        $membership = null;
        $isPremium = false;

        if ($request->user()?->role === 'student') {
            $student = Student::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->first();

            if ($student) {
                $membership = $student->memberships()
                    ->with('feePlan')
                    ->where('status', 'active')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('expiry_date', '>=', today())
                    ->latest('id')
                    ->first();

                $isPremium = $membership
                    && str_contains(strtolower($membership->feePlan?->name ?? ''), 'premium');
            }
        }

        $allowedAccessTypes = ['public'];
        if ($membership) {
            $allowedAccessTypes[] = 'member';
            if ($isPremium) {
                $allowedAccessTypes[] = 'premium';
            }
        }

        $baseQuery = DigitalResource::query()
            ->where('status', true)
            ->whereIn('access_type', $allowedAccessTypes);

        $resources = (clone $baseQuery)
            ->when($request->filled('type'), fn ($q) => $q->where('resource_type', $request->string('type')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim($request->string('q')->toString()).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', $term)
                        ->orWhere('category', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderByRaw("CASE access_type WHEN 'premium' THEN 0 WHEN 'member' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $categories = (clone $baseQuery)
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('public.digital-library', compact(
            'resources', 'categories', 'student', 'membership', 'isPremium'
        ));
    }
}
