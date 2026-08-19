<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DigitalResource;
use App\Models\Student;
use App\Services\DigitalResourceAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitalResourceAccessController extends Controller
{
    public function __invoke(
        Request $request,
        DigitalResource $resource,
        DigitalResourceAccessService $access
    ): RedirectResponse|StreamedResponse|BinaryFileResponse {
        $student = null;

        if ($request->user()?->role === 'student') {
            $student = Student::query()->where('user_id', $request->user()->id)->first();
        }

        $access->assertCanAccess($resource, $student);

        $action = $request->boolean('download') ? 'download' : 'view';
        if ($action === 'download' && ! $resource->download_allowed) {
            abort(403, 'Downloads are not allowed for this resource.');
        }

        $access->log($resource, $action, $student, $request->ip());

        if ($resource->external_url) {
            return redirect()->away($resource->external_url);
        }

        abort_unless($resource->file_path, 404);
        abort_unless(Storage::disk('local')->exists($resource->file_path), 404);

        if ($action === 'download') {
            return Storage::disk('local')->download($resource->file_path);
        }

        return response()->file(Storage::disk('local')->path($resource->file_path));
    }
}
