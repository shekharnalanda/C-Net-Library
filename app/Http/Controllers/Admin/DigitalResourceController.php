<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DigitalResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'resource_file' => [
                'nullable',
                'file',
                'max:51200',
                'mimes:pdf,epub,txt,doc,docx,ppt,pptx,xls,xlsx,mp4,webm',
                'mimetypes:application/pdf,application/epub+zip,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,video/mp4,video/webm',
            ],
            'external_url' => ['nullable', 'url', 'max:1000'],
            'access_type' => ['required', 'in:public,members,premium'],
            'download_allowed' => ['nullable', 'boolean'],
        ]);

        if (! $request->hasFile('resource_file') && empty($data['external_url'])) {
            return back()->withErrors(['resource' => 'Upload a file or provide an external URL.'])->withInput();
        }

        if ($request->hasFile('resource_file') && ! empty($data['external_url'])) {
            return back()->withErrors(['resource' => 'Choose either a file upload or an external URL, not both.'])->withInput();
        }

        if (! empty($data['external_url'])) {
            $scheme = strtolower((string) parse_url($data['external_url'], PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                throw ValidationException::withMessages([
                    'external_url' => 'External resource URLs must use HTTP or HTTPS.',
                ]);
            }
        }

        if ($data['resource_type'] === 'link' && $request->hasFile('resource_file')) {
            throw ValidationException::withMessages([
                'resource_file' => 'Link resources must use an external URL.',
            ]);
        }

        if ($data['resource_type'] !== 'link' && ! $request->hasFile('resource_file') && ! empty($data['external_url'])) {
            // External videos/documents are allowed, but are always served through the secure access endpoint.
        }

        $filePath = null;
        if ($request->hasFile('resource_file')) {
            $file = $request->file('resource_file');
            $extension = strtolower($file->getClientOriginalExtension());
            $fileName = Str::uuid().($extension ? '.'.$extension : '');
            $filePath = $file->storeAs('digital-resources', $fileName, 'local');
        }

        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $counter = 2;
        while (DigitalResource::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        DigitalResource::create([
            'branch_id' => $data['branch_id'] ?? null,
            'title' => $data['title'],
            'slug' => $slug,
            'resource_type' => $data['resource_type'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'file_path' => $filePath,
            'external_url' => $data['external_url'] ?? null,
            'access_type' => $data['access_type'],
            'download_allowed' => $request->boolean('download_allowed'),
            'status' => true,
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Digital resource added successfully.');
    }
}
