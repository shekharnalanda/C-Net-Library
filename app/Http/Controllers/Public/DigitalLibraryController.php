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
            $allowedAccessTypes[] = 'members';
            if ($isPremium) {
                $allowedAccessTypes[] = 'premium';
            }
        }

        $baseQuery = DigitalResource::query()
            ->where('status', true)
            ->whereIn('access_type', $allowedAccessTypes)
            ->when($student, function ($query) use ($student) {
                $query->where(function ($scope) use ($student) {
                    $scope->where('access_type', 'public')
                        ->orWhereNull('branch_id')
                        ->orWhere('branch_id', $student->branch_id);
                });
            }, function ($query) {
                $query->where(function ($scope) {
                    $scope->where('access_type', 'public')
                        ->orWhereNull('branch_id');
                });
            });

        $resources = (clone $baseQuery)
            ->when($request->filled('type'), fn ($q) => $q->where('resource_type', $request->string('type')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = trim($request->string('q')->toString());
                $terms = array_values(array_unique(array_filter(
                    preg_split('/[\\s,._\/-]+/u', $search) ?: [],
                    fn (string $term) => mb_strlen($term) >= 2
                )));

                $q->where(function ($sub) use ($search, $terms) {
                    $phrases = array_merge([$search], $terms);

                    foreach ($phrases as $index => $phrase) {
                        $term = '%'.$phrase.'%';
                        $method = $index === 0 ? 'where' : 'orWhere';

                        $sub->{$method}(function ($fields) use ($term) {
                            $fields->where('title', 'like', $term)
                                ->orWhere('category', 'like', $term)
                                ->orWhere('description', 'like', $term);
                        });
                    }
                });
            })
            ->orderByRaw("CASE access_type WHEN 'premium' THEN 0 WHEN 'members' THEN 1 ELSE 2 END")
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
