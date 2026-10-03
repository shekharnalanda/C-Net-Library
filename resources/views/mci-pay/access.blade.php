@extends('mci-pay.layout')
@section('content')
<section class="card"><h1>फीस भुगतान</h1><p>पहले से enrolled विद्यार्थी अपने पैनल से Pay Fee खोलें। नए एडमिशन का भुगतान देखने के लिए आवेदन नंबर और उसी आवेदन का मोबाइल नंबर भरें।</p><p><a class="btn alt" href="{{ route('login') }}">विद्यार्थी लॉगिन</a></p><form method="post" action="{{ route('mci-pay.authenticate') }}">@csrf<label>Application number<input name="application_no" required maxlength="100" value="{{ old('application_no') }}"></label><label>Registered mobile<input name="phone" type="tel" required maxlength="20" autocomplete="tel"></label><button>फीस विवरण खोलें</button></form></section>
@endsection
