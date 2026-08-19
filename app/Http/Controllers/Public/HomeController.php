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
use App\Services\SeatAllocationService;
use App\Services\SettingsService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SeatAllocationService $seatAllocationService,
    ) {
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

        $seats = Seat::query()->where('status', true)->get(['id']);
        $slots = StudySlot::query()->where('status', true)->get(['id', 'name', 'start_time', 'end_time']);
        $totalSeats = $seats->count();
        $totalSeatSlots = $totalSeats * $slots->count();

        $availableSeatSlots = 0;
        foreach ($slots as $slot) {
            foreach ($seats as $seat) {
                if ($this->seatAllocationService->isAvailable(
                    $seat->id,
                    today(),
                    today(),
                    $slot->start_time,
                    $slot->end_time,
                )) {
                    $availableSeatSlots++;
                }
            }
        }

        $occupiedSeatSlots = max(0, $totalSeatSlots - $availableSeatSlots);

        $jobs = Job::query()
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('last_date')->orWhereDate('last_date', '>=', today());
            })
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
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
