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
use App\Models\Testimonial;
use Illuminate\View\View;

class HomeController extends Controller
{
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
        $occupiedSeatIds = SeatAllocation::query()
            ->whereIn('status', ['reserved', 'active'])
            ->whereDate('allocated_from', '<=', today())
            ->where(function ($query) {
                $query->whereNull('allocated_to')->orWhereDate('allocated_to', '>=', today());
            })
            ->distinct()
            ->pluck('seat_id');

        $occupiedSeats = $occupiedSeatIds->count();
        $availableSeats = max(0, $totalSeats - $occupiedSeats);

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

        return view('public.home', compact(
            'home', 'plans', 'totalSeats', 'occupiedSeats', 'availableSeats',
            'jobs', 'faqs', 'testimonials', 'gallery'
        ));
    }

    public function page(CmsPage $page): View
    {
        abort_unless($page->status, 404);

        return view('public.page', compact('page'));
    }
}
