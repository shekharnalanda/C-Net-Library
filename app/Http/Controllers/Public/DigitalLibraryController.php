<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DigitalResource;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DigitalLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $resources = DigitalResource::query()
            ->where('status', true)
            ->where('access_type', 'public')
            ->when($request->filled('type'), fn ($q) => $q->where('resource_type', $request->string('type')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', $term)
                        ->orWhere('category', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('public.digital-library', compact('resources'));
    }
}
