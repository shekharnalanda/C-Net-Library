<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS - C-Net Library</title>
    <style>
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1200px;margin:28px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:20px}.grid{display:grid;grid-template-columns:2fr 1fr;gap:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 13px;border-radius:9px;border:0;cursor:pointer}.btn.alt{background:#0f766e}.btn.danger{background:#b91c1c}.field{margin-bottom:12px}label{font-size:13px;font-weight:700;color:#475569}input,textarea{width:100%;margin-top:5px;padding:10px;border:1px solid #d1d5db;border-radius:9px}textarea{min-height:100px}.muted{color:#6b7280}.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.list{padding:0;margin:0;list-style:none}.list li{padding:12px 0;border-bottom:1px solid #edf0f4}.gallery-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.gallery-item img{width:100%;height:120px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb}.gallery-item form{margin-top:7px}.success{background:#f0fdf4;border:1px solid #86efac;padding:12px;border-radius:10px;margin-bottom:16px}.errors{background:#fef2f2;border:1px solid #fca5a5;padding:12px;border-radius:10px;margin-bottom:16px}@media(max-width:850px){.grid,.row{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top"><div><h1 style="margin:0">Website CMS</h1><div class="muted">Manage public pages, SEO, FAQs, testimonials and gallery.</div></div><div><a class="btn alt" href="{{ route('home') }}" target="_blank">View Website</a> <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a></div></div>

    @if(session('success'))<div class="success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="errors"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid">
        <div>
            @foreach($pages as $page)
                <div class="card">
                    <h2 style="margin-top:0">{{ $page->title }} <span class="muted" style="font-size:13px">/{{ $page->slug }}</span></h2>
                    <form method="POST" action="{{ route('admin.cms.pages.update', $page) }}">
                        @csrf @method('PATCH')
                        <div class="field"><label>Page Title<input type="text" name="title" value="{{ $page->title }}" required></label></div>
                        <div class="field"><label>Excerpt<textarea name="excerpt">{{ $page->excerpt }}</textarea></label></div>
                        <div class="field"><label>Content (HTML supported)<textarea name="content" style="min-height:180px">{{ $page->content }}</textarea></label></div>
                        <div class="row">
                            <div class="field"><label>SEO Title<input type="text" name="meta_title" value="{{ $page->meta_title }}"></label></div>
                            <div class="field"><label>Canonical URL<input type="url" name="canonical_url" value="{{ $page->canonical_url }}"></label></div>
                        </div>
                        <div class="field"><label>SEO Description<textarea name="meta_description">{{ $page->meta_description }}</textarea></label></div>
                        <div class="field"><label>SEO Keywords<input type="text" name="meta_keywords" value="{{ $page->meta_keywords }}"></label></div>
                        <div class="field"><label><input style="width:auto;margin-right:7px" type="checkbox" name="status" value="1" @checked($page->status)> Published</label></div>
                        <button class="btn alt" type="submit">Save Page</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div>
            <div class="card"><h2 style="margin-top:0">Gallery</h2><form method="POST" enctype="multipart/form-data" action="{{ route('admin.cms.gallery.store') }}">@csrf<div class="field"><label>Title<input name="title"></label></div><div class="field"><label>Alt text<input name="alt_text"></label></div><div class="field"><label>Image<input type="file" name="image" accept="image/*" required></label></div><button class="btn alt">Upload Image</button></form>@if($galleryItems->isNotEmpty())<hr style="border:0;border-top:1px solid #e5e7eb;margin:18px 0"><div class="gallery-grid">@foreach($galleryItems as $item)<div class="gallery-item"><img src="{{ asset('storage/'.$item->image_path) }}" alt="{{ $item->alt_text }}"><div style="font-size:13px;margin-top:5px">{{ $item->title ?: 'Gallery image' }}</div><form method="POST" action="{{ route('admin.cms.gallery.destroy', $item) }}">@csrf @method('DELETE')<button class="btn danger" type="submit" style="padding:6px 9px">Remove</button></form></div>@endforeach</div>@endif</div>

            <div class="card"><h2 style="margin-top:0">Add FAQ</h2><form method="POST" action="{{ route('admin.cms.faqs.store') }}">@csrf<div class="field"><label>Question<input name="question" required></label></div><div class="field"><label>Answer<textarea name="answer" required></textarea></label></div><button class="btn alt">Add FAQ</button></form><hr style="border:0;border-top:1px solid #e5e7eb;margin:18px 0"><ul class="list">@foreach($faqs as $faq)<li><strong>{{ $faq->question }}</strong><div class="muted">{{ $faq->answer }}</div></li>@endforeach</ul></div>

            <div class="card"><h2 style="margin-top:0">Add Testimonial</h2><form method="POST" action="{{ route('admin.cms.testimonials.store') }}">@csrf<div class="field"><label>Name<input name="name" required></label></div><div class="field"><label>Designation<input name="designation"></label></div><div class="field"><label>Message<textarea name="message" required></textarea></label></div><div class="field"><label>Rating<input type="number" min="1" max="5" name="rating" value="5"></label></div><button class="btn alt">Add Testimonial</button></form><hr style="border:0;border-top:1px solid #e5e7eb;margin:18px 0"><ul class="list">@foreach($testimonials as $item)<li><strong>{{ $item->name }}</strong><div class="muted">{{ $item->message }}</div></li>@endforeach</ul></div>
        </div>
    </div>
</div>
</body>
</html>
