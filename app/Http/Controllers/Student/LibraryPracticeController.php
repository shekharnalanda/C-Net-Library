<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\LibraryPracticeBridge;
use Illuminate\Http\Request;

class LibraryPracticeController extends Controller
{
    public function index(Request $request, LibraryPracticeBridge $bridge)
    {
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();
        $eligible = false;
        $notice = null;
        try {
            $identity = $bridge->identity($student->id, $student->student_code, $request->user()->id);
            $eligible = $identity && $bridge->eligible($identity);
        } catch (\Throwable $e) {
            $notice = 'ऑनलाइन प्रैक्टिस की सुविधा तैयार हो रही है। कृपया लाइब्रेरी कार्यालय से संपर्क करें।';
        }

        return response()->view('student.library-practice', compact('student', 'eligible', 'notice'))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function launch(Request $request, LibraryPracticeBridge $bridge)
    {
        $request->validate(['terms' => ['accepted']]);
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();
        try {
            $identity = $bridge->identity($student->id, $student->student_code, $request->user()->id);
            abort_unless($identity, 403);

            return redirect()->away($bridge->issue($identity, hash('sha256', (string) $request->session()->get('library_device_token'))))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['practice' => 'सक्रिय लाइब्रेरी सदस्यता और फीस की स्थिति जाँचें। समस्या रहने पर कार्यालय से संपर्क करें।']);
        }
    }
}
