<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class EnquiryEmailOtpController extends Controller
{
    private const SESSION_KEY = 'enquiry_email_otp';
    private const VERIFIED_KEY = 'enquiry_email_verified';
    private const EXPIRY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function send(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:150'],
        ]);
        $email = strtolower(trim($validated['email']));
        $key = 'enquiry-email-otp:'.$request->ip().':'.$email;
        $cooldown = $key.':cooldown';

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'email' => 'बहुत अधिक OTP requests हुई हैं। कृपया कुछ देर बाद प्रयास करें।',
            ]);
        }

        if (RateLimiter::tooManyAttempts($cooldown, 1)) {
            throw ValidationException::withMessages([
                'email' => 'नया OTP भेजने से पहले एक मिनट प्रतीक्षा करें।',
            ]);
        }

        RateLimiter::hit($key, 600);
        RateLimiter::hit($cooldown, 60);
        $otp = (string) random_int(100000, 999999);

        $request->session()->put(self::SESSION_KEY, [
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES)->timestamp,
            'attempts' => 0,
        ]);
        $request->session()->forget(self::VERIFIED_KEY);

        try {
            Mail::raw(
                "Your C-Net enquiry verification code is {$otp}. It expires in 10 minutes.",
                fn ($message) => $message->to($email)->subject('Verify your enquiry email')
            );
        } catch (Throwable $exception) {
            report($exception);
            $request->session()->forget(self::SESSION_KEY);

            return back()->withInput()->withErrors([
                'email' => 'OTP email नहीं भेजा जा सका। कृपया बाद में पुनः प्रयास करें।',
            ]);
        }

        if ($request->expectsJson()) { return response()->json(['message' => 'OTP sent to your email.']); }
        return back()->withInput()->with('otp_sent', 'OTP आपके email पर भेज दिया गया है।');
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:150'],
            'otp' => ['required', 'digits:6'],
        ]);
        $email = strtolower(trim($validated['email']));
        $pending = $request->session()->get(self::SESSION_KEY);

        if (!$pending || !hash_equals((string) ($pending['email'] ?? ''), $email)) {
            throw ValidationException::withMessages([
                'otp' => 'इस email के लिए OTP उपलब्ध नहीं है। नया OTP प्राप्त करें।',
            ]);
        }

        if (now()->timestamp > (int) ($pending['expires_at'] ?? 0)) {
            $request->session()->forget(self::SESSION_KEY);
            throw ValidationException::withMessages(['otp' => 'OTP की समय-सीमा समाप्त हो गई। नया OTP प्राप्त करें।']);
        }

        if ((int) ($pending['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            $request->session()->forget(self::SESSION_KEY);
            throw ValidationException::withMessages(['otp' => 'गलत प्रयासों की सीमा पूरी हुई। नया OTP प्राप्त करें।']);
        }

        if (!Hash::check((string) $validated['otp'], (string) $pending['otp_hash'])) {
            $pending['attempts'] = (int) $pending['attempts'] + 1;
            $request->session()->put(self::SESSION_KEY, $pending);
            throw ValidationException::withMessages(['otp' => 'OTP सही नहीं है। कृपया फिर जाँचें।']);
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put(self::VERIFIED_KEY, [
            'email' => $email,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);
        $request->session()->regenerate();

        if ($request->expectsJson()) { return response()->json(['message' => 'Email verified.']); }
        return back()->withInput()->with('otp_verified', 'Email सत्यापित है। अब enquiry भेजें।');
    }
}
