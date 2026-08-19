<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DigitalResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DigitalResourceController extends Controller
{
    public function index(Request $request)
    {
        $resources = DigitalResource::query()
            ->with('branch')
            ->withCount('logs')
            ->when($request->search, fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('resource_type', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $branches = Branch::where('status', true)->orderBy('name')->get();

        return view('admin.digital-resources.index', compact('resources', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'title' => ['required', 'string', 'max:255'],
            'resource_type' => ['required', 'in:pdf,ebook,notes,question_paper,video,link'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file_path' => ['nullable', 'string', 'max:1000'],
            'external_url' => ['nullable', 'url', 'max:1000'],
            'access_type' => ['required', 'in:public,members,premium'],
            'download_allowed' => ['nullable', 'boolean'],
        ]);

        if (empty($data['file_path']) && empty($data['external_url'])) {
            return back()->withErrors(['resource' => 'Provide a private file path or external URL.'])->withInput();
        }

        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $counter = 2;
        while (DigitalResource::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        DigitalResource::create([
            ...$data,
            'slug' => $slug,
            'download_allowed' => $request->boolean('download_allowed'),
            'status' => true,
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Digital resource added successfully.');
    }
}
