<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function create(): View
    {
        return view('public.enquiry', [
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website' => ['nullable', 'string', 'max:0'],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('status', true)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:150'],
            'source' => ['nullable', 'string', 'max:100'],
            'interested_plan' => ['nullable', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        unset($data['website']);
        $data['name'] = trim((string) $data['name']);
        $data['mobile'] = preg_replace('/\s+/', '', trim((string) $data['mobile']));
        $data['email'] = isset($data['email']) && $data['email'] !== ''
            ? strtolower(trim((string) $data['email']))
            : null;
        $data['source'] = isset($data['source']) && $data['source'] !== '' ? trim((string) $data['source']) : null;
        $data['interested_plan'] = isset($data['interested_plan']) && $data['interested_plan'] !== ''
            ? trim((string) $data['interested_plan'])
            : null;
        $data['message'] = isset($data['message']) && $data['message'] !== '' ? trim((string) $data['message']) : null;

        $existing = Enquiry::query()
            ->where('branch_id', $data['branch_id'] ?? null)
            ->where('mobile', $data['mobile'])
            ->whereNotIn('status', ['converted', 'lost'])
            ->where('created_at', '>=', now()->subHours(24))
            ->latest('id')
            ->first();

        if ($existing) {
            return back()->with('success', 'Your enquiry has been received. Our team will follow up if needed.');
        }

        
        $verified = $request->session()->get('enquiry_email_verified');
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (!$verified || !hash_equals((string) ($verified['email'] ?? ''), $email) || now()->timestamp > (int) ($verified['expires_at'] ?? 0)) {
            return back()->withInput()->withErrors(['email' => 'पहले इस email पर भेजा गया OTP verify करें।']);
        }
        $request->session()->forget('enquiry_email_verified');

$data['enquiry_no'] = $this->generateEnquiryNo();
        $data['status'] = 'new';

        $enquiry = Enquiry::create($data);
        $branch = $enquiry->branch_id ? Branch::find($enquiry->branch_id) : null;

        return back()->with('success', 'Your enquiry has been submitted successfully.');
    }

    private function generateEnquiryNo(): string
    {
        do {
            $number = 'CNL-ENQ-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (Enquiry::query()->where('enquiry_no', $number)->exists());

        return $number;
    }
}
