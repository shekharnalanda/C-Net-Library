<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\LibraryPortalMailService;
use App\Services\LibraryStudentSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class LibraryStudentLoginController extends Controller
{
    public function create()
    {
        return response()->view('auth.student-login')->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, LibraryStudentSessionService $sessions): RedirectResponse
    {
        $d = $request->validate(['student_code' => ['required', 'string', 'max:100'], 'password' => ['required', 'string', 'max:200']]);
        $code = strtoupper(trim($d['student_code']));
        $key = 'library-login:'.$code.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['student_code' => 'कृपया एक मिनट बाद प्रयास करें।']);
        }
        $student = Student::with('user')->where('student_code', $code)->where('status', 'active')->first();
        if (! $student || ! $student->user?->status || $student->user->role !== 'student' || ! Hash::check($d['password'], $student->user->password)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['student_code' => 'एडमिशन नंबर या पासवर्ड गलत है, अथवा खाता निष्क्रिय है।'])->onlyInput('student_code');
        }
        $token = $request->session()->get('library_device_token') ?: bin2hex(random_bytes(32));
        $sessions->claim($student, $token);
        Auth::guard('library_student')->login($student->user, false);
        $request->session()->put('library_device_token', $token);
        $request->session()->regenerate();
        RateLimiter::clear($key);

        return redirect()->route('student.dashboard');
    }

    public function logout(Request $request, LibraryStudentSessionService $sessions): RedirectResponse
    {
        $student = Student::where('user_id', Auth::guard('library_student')->id())->first();
        if ($student) {
            $sessions->release($student, $request->session()->get('library_device_token'));
        }
        Auth::guard('library_student')->logout();
        $request->session()->forget('library_device_token');
        $request->session()->regenerate();

        return redirect()->route('student.login');
    }

    public function password(Request $request): RedirectResponse
    {
        $d = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        if (! Hash::check($d['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'वर्तमान पासवर्ड गलत है।']);
        }
        $request->user()->update(['password' => $d['password']]);

        return back()->with('success', 'पासवर्ड बदल गया है।');
    }

    public function recovery(Request $request, LibraryPortalMailService $mail): RedirectResponse
    {
        $d = $request->validate(['student_code' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255']]);
        $challenge = bin2hex(random_bytes(32));
        $otp = (string) random_int(100000, 999999);
        $student = Student::with('user')->where('student_code', strtoupper(trim($d['student_code'])))->where('email', strtolower(trim($d['email'])))->where('status', 'active')->first();
        if ($student && $student->user?->status) {
            $id = DB::table('library_device_recovery')->insertGetId(['challenge_hash' => hash('sha256', $challenge), 'student_id' => $student->id, 'otp_hash' => Hash::make($otp), 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
            $mail->enqueue($student, 'device-otp:'.$id, 'C-Net Library: Close old login session', "पुराना विद्यार्थी लॉगिन बंद करने का OTP: {$otp}\nयह OTP 10 मिनट में समाप्त होगा। यदि आपने अनुरोध नहीं किया तो इसे साझा न करें।");
            $mail->deliver('device-otp:'.$id);
        }
        $request->session()->put('library_recovery_challenge', $challenge);

        return back()->with('success', 'विवरण सही होने पर रजिस्टर्ड ईमेल पर OTP भेजा जाएगा। OTP डालकर पुराना लॉगिन बंद करें।');
    }

    public function recoverConfirm(Request $request, LibraryStudentSessionService $sessions): RedirectResponse
    {
        $d = $request->validate(['otp' => ['required', 'digits:6']]);
        $hash = hash('sha256', (string) $request->session()->get('library_recovery_challenge'));
        $id = DB::table('library_device_recovery')->where('challenge_hash', $hash)->value('student_id');
        if (! $id) {
            throw ValidationException::withMessages(['otp' => 'OTP गलत है या समाप्त हो गया है।']);
        }
        $ok = DB::transaction(function () use ($hash, $d, $id) {
            Student::whereKey($id)->lockForUpdate()->firstOrFail();
            $row = DB::table('library_device_recovery')->where('challenge_hash', $hash)->lockForUpdate()->first();
            if (! $row || $row->consumed_at || $row->expires_at <= now()->format('Y-m-d H:i:s') || $row->attempts >= 5) {
                return false;
            }
            DB::table('library_device_recovery')->where('id', $row->id)->increment('attempts');
            if (! Hash::check($d['otp'], $row->otp_hash)) {
                return false;
            }
            DB::table('library_device_recovery')->where('id', $row->id)->update(['consumed_at' => now(), 'otp_hash' => '[Consumed]', 'updated_at' => now()]);
            DB::table('library_student_sessions')->where('student_id', $id)->delete();
            DB::table('mobile_api_tokens')->where('user_id', Student::findOrFail($id)->user_id)->delete();

            return true;
        }, 3);
        if (! $ok) {
            throw ValidationException::withMessages(['otp' => 'OTP गलत है या समाप्त हो गया है।']);
        }
        $request->session()->forget('library_recovery_challenge');

        return redirect()->route('student.login')->with('success', 'पुराना सत्र बंद हो गया है। अब एडमिशन नंबर और पासवर्ड से लॉगिन करें।');
    }
}
