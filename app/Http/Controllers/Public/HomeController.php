<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\Faq;
use App\Models\FeePlan;
use App\Models\GalleryItem;
use App\Models\Job;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\StudySlot;
use App\Models\Testimonial;
use App\Services\SettingsService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function index(): View
    {
        $home = CmsPage::query()->where('slug', 'home')->where('status', true)->first();

        $plans = FeePlan::query()
            ->with('studySlot')
            ->where('status', true)
            ->orderBy('monthly_fee')
            ->limit(8)
            ->get();

        $totalSeats = Seat::query()->where('status', true)->count();
        $activeSlots = StudySlot::query()->where('status', true)->count();
        $totalSeatSlots = $totalSeats * $activeSlots;

        // Avoid checking every seat/slot pair individually on each public homepage request.
        // Active allocations already represent occupied seat-slot capacity for today.
        $occupiedSeatSlots = SeatAllocation::query()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->whereHas('seat', fn ($query) => $query->where('status', true))
            ->whereHas('studySlot', fn ($query) => $query->where('status', true))
            ->distinct()
            ->count('id');

        $occupiedSeatSlots = min($totalSeatSlots, $occupiedSeatSlots);
        $availableSeatSlots = max(0, $totalSeatSlots - $occupiedSeatSlots);

        $jobs = Job::query()
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('last_date')->orWhereDate('last_date', '>=', today());
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('published_date')
            ->limit(6)
            ->get();

        $faqs = Faq::query()->where('status', true)->orderBy('sort_order')->limit(8)->get();
        $testimonials = Testimonial::query()->where('status', true)->latest()->limit(6)->get();
        $gallery = GalleryItem::query()->where('status', true)->orderBy('sort_order')->limit(8)->get();

        $contact = [
            'institute_name' => $this->settings->get('institute_name', 'C-Net Library'),
            'phone' => $this->settings->get('support_phone'),
            'email' => $this->settings->get('support_email'),
            'address' => $this->settings->get('institute_address'),
            'map_embed_url' => $this->settings->get('map_embed_url'),
        ];

        return view('public.home', compact(
            'home', 'plans', 'totalSeats', 'totalSeatSlots', 'occupiedSeatSlots', 'availableSeatSlots',
            'jobs', 'faqs', 'testimonials', 'gallery', 'contact'
        ));
    }

    public function page(CmsPage $page): View
    {
        abort_unless($page->status, 404);

        return view('public.page', compact('page'));
    }
}
