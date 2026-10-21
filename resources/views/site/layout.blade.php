@php($home = request()->routeIs('home') ? '' : route('home'))
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="@yield('description')">
<link rel="icon" href="{{ asset('public/assets/site/img/app_icon.webp') }}">
<title>@yield('title')</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Alexandria:wght@600;800;900&display=swap">
<link rel="stylesheet" href="{{ asset('public/assets/site/site.css') }}?v={{ @filemtime(public_path('assets/site/site.css')) }}">
</head>
<body>
<header><div class="wrap bar">
  <a class="brand" href="{{ $home ?: '#top' }}"><img src="{{ asset('public/assets/site/img/app_icon.webp') }}" alt="" width="40"><span lang="en">Waddy</span><span lang="ar">وددى</span></a>
  <nav aria-label="Sections">
    <a href="{{ $home }}#food"><span lang="en">Food</span><span lang="ar">الطعام</span></a><a href="{{ $home }}#groceries"><span lang="en">Groceries</span><span lang="ar">البقالة</span></a>
    <a href="{{ $home }}#tracking"><span lang="en">Tracking</span><span lang="ar">التتبّع</span></a><a href="{{ $home }}#spots"><span lang="en">Spots</span><span lang="ar">أماكن وددى</span></a><a href="{{ $home }}#pets"><span lang="en">Pets</span><span lang="ar">الحيوانات الأليفة</span></a>
  </nav>
  <button class="lang" id="lang" type="button" aria-label="Switch language">عربي</button>
  <a class="cta-sm" href="{{ $home }}#get"><span lang="en">Get the app</span><span lang="ar">حمّل التطبيق</span></a>
</div></header>

@yield('content')

<footer class="foot">
@php($social = array_filter([
    'Instagram' => ['url' => '', 'icon' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>'],
    'Facebook' => ['url' => '', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z"/></svg>'],
    'TikTok' => ['url' => '', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.5 2.6 2.2 4.2 5 4.5"/></svg>'],
    'X' => ['url' => '', 'icon' => '<svg viewBox="0 0 24 24"><path d="M4 4l16 16M20 4L4 20"/></svg>'],
    'WhatsApp' => ['url' => '', 'icon' => '<svg viewBox="0 0 24 24"><path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9.5c.3 2 2.5 4.2 4.5 4.5l1-1.2 2 .9c-.2 1.2-1.2 1.8-2.3 1.6C11 14.8 9 12.7 8.6 10.4c-.2-1.1.4-2.1 1.6-2.3l.9 2z"/></svg>'],
], fn ($s) => $s['url'] !== ''))
  <div class="wrap cols">
    <div class="brandcol">
      <a class="brand" href="{{ $home ?: '#top' }}"><img src="{{ asset('public/assets/site/img/app_icon.webp') }}" alt="" width="40"><span lang="en">Waddy</span><span lang="ar">وددى</span></a>
      <p class="note"><span lang="en">Food, groceries and pet care from across Maadi, delivered and tracked live.</span><span lang="ar">طعام وبقالة ومستلزمات الحيوانات الأليفة من كل المعادي، مع تتبّع مباشر.</span></p>
      @if($social)<div class="social">@foreach($social as $label => $s)<a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $label }}">{!! $s['icon'] !!}</a>@endforeach</div>@endif
    </div>
    <div><h4><span lang="en">Explore</span><span lang="ar">استكشف</span></h4><ul>
      <li><a href="{{ $home }}#food"><span lang="en">Food</span><span lang="ar">الطعام</span></a></li><li><a href="{{ $home }}#groceries"><span lang="en">Groceries</span><span lang="ar">البقالة</span></a></li>
      <li><a href="{{ $home }}#tracking"><span lang="en">Live tracking</span><span lang="ar">التتبّع المباشر</span></a></li><li><a href="{{ $home }}#spots"><span lang="en">Waddy Spots</span><span lang="ar">أماكن وددى</span></a></li>
      <li><a href="{{ $home }}#pets"><span lang="en">Pet care</span><span lang="ar">رعاية الحيوانات الأليفة</span></a></li></ul></div>
    <div><h4><span lang="en">Company</span><span lang="ar">الشركة</span></h4><ul>
      <li><a href="{{ route('about-us') }}"><span lang="en">About us</span><span lang="ar">من نحن</span></a></li>
      <li><a href="{{ route('contact-us') }}"><span lang="en">Contact us</span><span lang="ar">تواصل معنا</span></a></li></ul></div>
    <div><h4><span lang="en">Legal</span><span lang="ar">قانوني</span></h4><ul>
      <li><a href="{{ route('privacy-policy') }}"><span lang="en">Privacy policy</span><span lang="ar">سياسة الخصوصية</span></a></li>
      <li><a href="{{ route('terms-and-conditions') }}"><span lang="en">Terms & conditions</span><span lang="ar">الشروط والأحكام</span></a></li>
      <li><a href="{{ route('refund') }}"><span lang="en">Refund policy</span><span lang="ar">سياسة الاسترداد</span></a></li></ul></div>
    <div><h4><span lang="en">Support</span><span lang="ar">الدعم</span></h4><ul>
      <li class="note"><span lang="en">Chat with us from inside the app, any time.</span><span lang="ar">تحدّث معنا من داخل التطبيق في أي وقت.</span></li>
      
      
      <li><a href="{{ route('contact-us') }}"><span lang="en">Support page</span><span lang="ar">صفحة الدعم</span></a></li>
      <li class="note"><span lang="en">Maadi, Cairo, Egypt</span><span lang="ar">المعادي، القاهرة، مصر</span></li></ul></div>
  </div>
  <div class="wrap mark" aria-label="Waddy"><img src="{{ asset('public/assets/site/img/logo_teal3.webp') }}" alt=""><span>WADDY</span></div>
  <div class="base"><div class="wrap">
    <span>© 2026 Waddy. <span lang="en">All rights reserved.</span><span lang="ar">جميع الحقوق محفوظة.</span></span>
    <span><span lang="en">Available in English and Arabic</span><span lang="ar">متوفر بالعربية والإنجليزية</span></span>
    <a class="totop sp" href="#top"><span lang="en">Back to top</span><span lang="ar">إلى الأعلى</span> ↑</a>
  </div></div>
</footer>
<script src="{{ asset('public/assets/site/site.js') }}?v={{ @filemtime(public_path('assets/site/site.js')) }}"></script>
@stack('scripts')
</body>
</html>
