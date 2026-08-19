<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\Testimonial;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CmsController extends Controller
{
    public function index(): View
    {
        return view('admin.cms.index', [
            'pages' => CmsPage::query()->orderBy('sort_order')->orderBy('title')->get(),
            'faqs' => Faq::query()->orderBy('sort_order')->get(),
            'testimonials' => Testimonial::query()->latest()->get(),
            'galleryItems' => GalleryItem::query()->orderBy('sort_order')->latest()->get(),
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

    public function storeGallery(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('image')->store('gallery', 'public');

        $item = GalleryItem::create([
            'title' => $data['title'] ?? null,
            'image_path' => $path,
            'alt_text' => $data['alt_text'] ?? $data['title'] ?? 'C-Net Library gallery image',
            'sort_order' => (int) GalleryItem::max('sort_order') + 1,
            'status' => true,
        ]);

        $audit->log('cms.gallery.created', $item, [], $item->only(['title', 'image_path', 'alt_text']));

        return back()->with('success', 'Gallery image uploaded.');
    }

    public function destroyGallery(GalleryItem $galleryItem, AuditService $audit): RedirectResponse
    {
        $old = $galleryItem->toArray();

        if ($galleryItem->image_path) {
            Storage::disk('public')->delete($galleryItem->image_path);
        }

        $audit->log('cms.gallery.deleted', $galleryItem, $old, []);
        $galleryItem->delete();

        return back()->with('success', 'Gallery image removed.');
    }
}
