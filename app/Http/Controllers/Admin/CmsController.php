<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\Faq;
use App\Models\Testimonial;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsController extends Controller
{
    public function index(): View
    {
        return view('admin.cms.index', [
            'pages' => CmsPage::query()->orderBy('sort_order')->orderBy('title')->get(),
            'faqs' => Faq::query()->orderBy('sort_order')->get(),
            'testimonials' => Testimonial::query()->latest()->get(),
        ]);
    }

    public function updatePage(Request $request, CmsPage $page, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'status' => ['nullable', 'boolean'],
        ]);

        $old = $page->only(array_keys($data));
        $page->update($data + ['status' => $request->boolean('status')]);
        $audit->log('cms.page.updated', $page, $old, $page->only(array_keys($data)));

        return back()->with('success', 'CMS page updated.');
    }

    public function storeFaq(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
        ]);

        $faq = Faq::create($data + ['status' => true]);
        $audit->log('cms.faq.created', $faq, [], $faq->only(['question', 'answer']));

        return back()->with('success', 'FAQ added.');
    }

    public function storeTestimonial(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:3000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ]);

        $testimonial = Testimonial::create($data + ['rating' => $data['rating'] ?? 5, 'status' => true]);
        $audit->log('cms.testimonial.created', $testimonial, [], $testimonial->only(['name', 'designation', 'message', 'rating']));

        return back()->with('success', 'Testimonial added.');
    }
}
