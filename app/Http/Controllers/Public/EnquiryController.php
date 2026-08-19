<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

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
            'branch_id' => ['nullable', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'source' => ['nullable', 'string', 'max:100'],
            'interested_plan' => ['nullable', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        $data['enquiry_no'] = $this->generateEnquiryNo();
        $data['status'] = 'new';

        Enquiry::create($data);

        return back()->with('success', 'Your enquiry has been submitted. Reference: '.$data['enquiry_no']);
    }

    private function generateEnquiryNo(): string
    {
        do {
            $number = 'CNL-ENQ-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (Enquiry::query()->where('enquiry_no', $number)->exists());

        return $number;
    }
}
