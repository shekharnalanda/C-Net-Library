<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $templates = CommunicationTemplate::query()->latest()->get();

        $logs = CommunicationLog::query()
            ->with(['student', 'enquiry', 'template', 'creator'])
            ->when(! $request->user()->isGlobalAdmin(), fn ($query) => $query->where('branch_id', $request->user()->branchId()))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.communications.index', compact('templates', 'logs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:150', 'unique:communication_templates,slug'],
            'channel' => ['required', 'in:email,sms,whatsapp'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'status' => ['nullable', 'boolean'],
        ]);

        $data['status'] = $request->boolean('status');

        CommunicationTemplate::create($data);

        return back()->with('success', 'Communication template created.');
    }
}
