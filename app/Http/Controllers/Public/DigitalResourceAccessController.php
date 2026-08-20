<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DigitalResource;
use App\Models\Student;
use App\Services\DigitalResourceAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        if ($resource->external_url) {
            $scheme = strtolower((string) parse_url($resource->external_url, PHP_URL_SCHEME));
            abort_unless(in_array($scheme, ['http', 'https'], true), 404);

            $access->log($resource, $action, $student, $request->ip());

            return redirect()->away($resource->external_url);
        }

        abort_unless($resource->file_path, 404);

        $path = ltrim(str_replace('\\', '/', $resource->file_path), '/');
        abort_unless(
            str_starts_with($path, 'digital-resources/')
            && ! str_contains($path, '../')
            && ! str_contains($path, '/..')
            && ! str_contains($path, "\0"),
            404
        );
        abort_unless(Storage::disk('local')->exists($path), 404);

        $access->log($resource, $action, $student, $request->ip());

        $absolutePath = Storage::disk('local')->path($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $baseName = Str::slug($resource->title) ?: 'resource';
        $downloadName = $baseName.($extension ? '.'.$extension : '');
        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

        if ($action === 'download') {
            return response()->download($absolutePath, $downloadName, [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
