@extends('site.layout')
@section('title', 'Waddy')
@section('description', 'Waddy: food, groceries and pet care from Maadi, in one app. Track your rider live and vote for Maadi\'s favorite spot.')
@section('content')
<main id="top">
<section class="band mint hero"><div class="wrap split">
  <div class="copy">
    <span class="eyebrow"><img src="{{ asset('public/assets/site/img/logo_teal3.webp') }}" alt=""><span lang="en">Maadi, Cairo</span><span lang="ar">المعادي، القاهرة</span></span>
    <h1><span lang="en"><span class="l1">All of Maadi<span class="dot" style="color:var(--white)">.</span></span><br><span class="l2">One app<span class="dot" style="color:var(--white)">.</span></span></span><span lang="ar"><span class="l1">المعادي كلها<span style="color:var(--white)">.</span></span><br><span class="l2">في تطبيق واحد<span style="color:var(--white)">.</span></span></span></h1>
    <p class="lead"><span lang="en">Food from Maadi's favorite spots, groceries picked the way you like them, and everything your pet needs. Then watch your rider cross Maadi, live.</span><span lang="ar">طعام من أماكن المعادي المفضلة، وبقالة كما تحبها، وكل ما يحتاجه حيوانك الأليف. ثم تابع المندوب وهو يعبر المعادي مباشرةً.</span></p>
    <div class="stores"><a class="store" href="https://apps.apple.com/app/id6757408049" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="none" d="M16.4 12.6c0-2.4 2-3.6 2.1-3.7-1.1-1.7-2.9-1.9-3.5-1.9-1.5-.2-2.9.9-3.7.9-.8 0-1.9-.9-3.2-.8-1.6 0-3.2 1-4 2.4-1.7 3-.4 7.4 1.2 9.8.8 1.2 1.8 2.5 3 2.4 1.2 0 1.7-.8 3.1-.8 1.5 0 1.9.8 3.2.8 1.3 0 2.1-1.2 2.9-2.4.9-1.4 1.3-2.7 1.3-2.8-.1 0-2.5-1-2.4-3.9zM14 5.5c.7-.8 1.1-1.9 1-3-1 0-2.2.7-2.9 1.5-.6.7-1.2 1.8-1 2.9 1.1.1 2.2-.6 2.9-1.4z"/></svg><span><small><span lang="en">Download on the</span><span lang="ar">حمّله من</span></small>App Store</span></a><span class="store soon" aria-disabled="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="none" d="M4 3.3v17.4c0 .6.6.9 1.1.6l15-8.7c.5-.3.5-1 0-1.3l-15-8.7C4.6 2.4 4 2.7 4 3.3z"/></svg><span><small><span lang="en">Coming soon to</span><span lang="ar">قريبًا على</span></small>Google Play</span></span></div>
    <div class="chips">
      <span class="chip"><img src="{{ asset('public/assets/site/img/u_combo.webp') }}" alt=""><span lang="en">Food</span><span lang="ar">طعام</span></span>
      <span class="chip"><img src="{{ asset('public/assets/site/img/u_bag.webp') }}" alt=""><span lang="en">Groceries</span><span lang="ar">بقالة</span></span>
      <span class="chip"><img src="{{ asset('public/assets/site/img/pet_packs.webp') }}" alt=""><span lang="en">Pet care</span><span lang="ar">حيوانات أليفة</span></span>
    </div>
  </div>
  <div class="stage">
    <div class="plate"></div>
    <img class="cut" src="{{ asset('public/assets/site/img/u_bag.webp') }}" alt="" style="width:190px; inset-inline-start:-4%; bottom:120px">
    <img class="cut" src="{{ asset('public/assets/site/img/u_combo.webp') }}" alt="" style="width:210px; inset-inline-end:-6%; bottom:60px; z-index:2">
    <div class="phone "><img src="{{ asset('public/assets/site/img/home_clean.jpg') }}" alt="Waddy home screen with Groceries, Food and Pets" width="620"><i class="isl"></i></div>
  </div>
</div></section>

<div class="ticker" aria-hidden="true"><div class="track"><span>Food</span><span>Groceries</span><span>Pet care</span><span>Live tracking</span><span>Waddy Spots</span><span>Maadi</span><span>الطعام</span><span>البقالة</span><span>الحيوانات الأليفة</span><span>التتبّع المباشر</span><span>أماكن وددى</span><span>المعادي</span><span>Food</span><span>Groceries</span><span>Pet care</span><span>Live tracking</span><span>Waddy Spots</span><span>Maadi</span><span>الطعام</span><span>البقالة</span><span>الحيوانات الأليفة</span><span>التتبّع المباشر</span><span>أماكن وددى</span><span>المعادي</span></div></div>
<section class="wrap" aria-labelledby="cats-h">
  <div class="sectionhead"><span class="eyebrow" style="color:var(--teal)"><span lang="en">One app, three errands</span><span lang="ar">تطبيق واحد لثلاثة مشاوير</span></span>
    <h2 id="cats-h"><span lang="en">Everything your street orders.</span><span lang="ar">كل ما يطلبه شارعك.</span></h2></div>
  <div class="cats">
    <a class="cat" href="#food"><h3><span lang="en">Food</span><span lang="ar">الطعام</span></h3><p><span lang="en">Maadi's top 10, plus every cuisine from Egyptian to sushi.</span><span lang="ar">أفضل ١٠ في المعادي، وكل المطابخ من المصري إلى السوشي.</span></p><img src="{{ asset('public/assets/site/img/u_combo.webp') }}" alt=""></a>
    <a class="cat" href="#groceries"><h3><span lang="en">Groceries</span><span lang="ar">البقالة</span></h3><p><span lang="en">Fresh produce and home essentials from stores around you.</span><span lang="ar">خضار وفاكهة ومستلزمات البيت من المتاجر القريبة منك.</span></p><img src="{{ asset('public/assets/site/img/u_crate.webp') }}" alt=""></a>
    <a class="cat" href="#pets"><h3><span lang="en">Pet care</span><span lang="ar">رعاية الحيوانات</span></h3><p><span lang="en">Food, treats and vets nearby, matched to your pet.</span><span lang="ar">طعام ومكافآت وعيادات قريبة، مناسبة لحيوانك.</span></p><img src="{{ asset('public/assets/site/img/pet_packs.webp') }}" alt=""></a>
  </div>
</section>

<section class="band teal" id="food"><div class="wrap split">
  <div class="copy">
    <span class="eyebrow" style="color:var(--mint)"><img src="{{ asset('public/assets/site/img/logo_mint3.webp') }}" alt=""><span lang="en">Food</span><span lang="ar">الطعام</span></span>
    <h2><span lang="en"><span class="l1">Maadi's Top</span><br>Bites<span class="dot">.</span></span><span lang="ar"><span class="l1">ألذّ أطباق</span><br>المعادي<span class="dot">.</span></span></h2>
    <p class="lead"><span lang="en">Browse Maadi's top 10, ranked, then every restaurant by cuisine.</span><span lang="ar">تصفّح أفضل ١٠ أماكن في المعادي، ثم كل المطاعم حسب المطبخ.</span></p>
    <ul class="ticks">
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Egyptian, burgers, pizza, grills, shawarma and more</span><span lang="ar">مصري، برجر، بيتزا، مشويات، شاورما والمزيد</span></li>
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Customize every item and reorder in seconds</span><span lang="ar">خصّص كل صنف وأعد الطلب في ثوانٍ</span></li>
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">See offers and free-delivery spots before you order</span><span lang="ar">شاهد العروض وأماكن التوصيل المجاني قبل الطلب</span></li>
    </ul>
  </div>
  <div class="stage">
    <div class="plate"></div>
    <img class="cut" src="{{ asset('public/assets/site/img/u_pizza_l.webp') }}" alt="" style="width:150px; inset-inline-end:-2%; top:10%; z-index:2">
    <div class="phone "><img src="{{ asset('public/assets/site/img/food_new.jpg') }}" alt="Top 10 Maadi spots, ranked" width="620"><i class="isl"></i></div>
    <div class="card top10"><span class="tile">10</span><span><b><span lang="en">Top 10 Maadi spots</span><span lang="ar">أفضل ١٠ أماكن في المعادي</span></b><small><span lang="en">Our top 10, ranked</span><span lang="ar">أفضل ١٠ لدينا، مرتبة</span></small></span></div>
  </div>
</div></section>

<section class="band mint" id="groceries"><div class="wrap split flip">
  <div class="copy">
    <span class="eyebrow"><img src="{{ asset('public/assets/site/img/logo_teal3.webp') }}" alt=""><span lang="en">Groceries</span><span lang="ar">البقالة</span></span>
    <h2><span lang="en"><span class="l1">Your groceries</span><br><span class="l2">Your rules<span class="dot" style="color:var(--white)">.</span></span></span><span lang="ar"><span class="l1">بقالتك</span><br><span class="l2">على طريقتك<span style="color:var(--white)">.</span></span></span></h2>
    <p class="lead"><span lang="en">Pick how ripe your fruit should be: ready to eat today, or ripe in 2–3 days.</span><span lang="ar">اختر درجة نضج الفاكهة: جاهزة للأكل اليوم، أو تنضج خلال 2–3 أيام.</span></p>
    <ul class="ticks">
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Produce, dairy, bakery, snacks and home essentials</span><span lang="ar">خضار وألبان ومخبوزات ووجبات خفيفة ومستلزمات البيت</span></li>
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Shop by aisle or search for exactly what you need</span><span lang="ar">تسوّق حسب القسم أو ابحث عمّا تحتاجه</span></li>
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Clear prices before you add to cart</span><span lang="ar">أسعار واضحة قبل الإضافة إلى السلة</span></li>
    </ul>
  </div>
  <div class="stage">
    <div class="plate"></div>
    <img class="cut" src="{{ asset('public/assets/site/img/u_crate.webp') }}" alt="" style="width:180px; inset-inline-start:-6%; top:12%">
    <div class="phone "><img src="{{ asset('public/assets/site/img/grocery.jpg') }}" alt="Bananas with a ripeness choice" width="620"><i class="isl"></i></div>
    <div class="ripe">
      <div class="card"><img src="{{ asset('public/assets/site/img/emo_yum.webp') }}" alt=""><b><span lang="en">Ready to eat</span><span lang="ar">جاهزة للأكل</span></b><small><span lang="en">Ripe and sweet, for today</span><span lang="ar">ناضجة وحلوة، للاستهلاك اليوم</span></small></div>
      <div class="card later"><img src="{{ asset('public/assets/site/img/emo_cal.webp') }}" alt=""><b><span lang="en">Ripe in 2–3 days</span><span lang="ar">تنضج خلال 2–3 أيام</span></b><small><span lang="en">A little firm, ripens at home</span><span lang="ar">متماسكة قليلًا، تنضج في المنزل</span></small></div>
    </div>
  </div>
</div></section>

<section class="band teal" id="tracking"><div class="wrap split">
  <div class="copy">
    <span class="eyebrow" style="color:var(--mint)"><img src="{{ asset('public/assets/site/img/logo_mint3.webp') }}" alt=""><span lang="en">Live tracking</span><span lang="ar">التتبّع المباشر</span></span>
    <h2><span lang="en"><span class="l1">No more</span><br>where are you?</span><span lang="ar"><span class="l1">وداعًا لسؤال</span><br>أين أنت؟</span></h2>
    <p class="lead"><span lang="en">Watch your rider cross Maadi on a live map, with an ETA that updates as they move.</span><span lang="ar">تابع المندوب وهو يعبر المعادي على خريطة مباشرة، مع وقت وصول يتحدّث مع حركته.</span></p>
    <ul class="ticks">
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Follow your order from placed to on the way</span><span lang="ar">تابع طلبك من التأكيد حتى يصبح في الطريق</span></li>
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Give your rider your delivery PIN at the door</span><span lang="ar">أعطِ المندوب رمز الاستلام عند الباب</span></li>
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Your order reaches you, and only you</span><span lang="ar">يصل طلبك إليك أنت فقط</span></li>
    </ul>
  </div>
  <div class="stage">
    <div class="plate"></div>
    <div class="phone "><img src="{{ asset('public/assets/site/img/tracking.jpg') }}" alt="Live map with estimated arrival" width="620"><i class="isl"></i></div>
    <div class="card pin"><img src="{{ asset('public/assets/site/img/helmet.webp') }}" alt=""><span><span class="lbl"><span lang="en">YOUR DELIVERY PIN</span><span lang="ar">رمز الاستلام</span></span><span class="digits"><span>1</span><span>3</span><span>0</span><span>4</span></span></span><i class="sh"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/><path d="M8.5 12l2.5 2.5L15.5 10"/></svg></i></div>
  </div>
</div></section>

<section class="band mint" id="spots"><div class="wrap split flip">
  <div class="copy">
    <span class="eyebrow"><img src="{{ asset('public/assets/site/img/logo_teal3.webp') }}" alt=""><span lang="en">Waddy Spots</span><span lang="ar">أماكن وددى</span></span>
    <h2><span lang="en"><span class="l1">Who's ruling</span><br><span class="l2">Maadi?</span></span><span lang="ar"><span class="l1">مَن يتصدّر</span><br><span class="l2">المعادي؟</span></span></h2>
    <p class="lead"><span lang="en">Every week, Maadi votes for its favorite café, restaurant or hangout. One vote each.</span><span lang="ar">كل أسبوع، تصوّت المعادي لمقهاها أو مطعمها أو مكانها المفضل. صوت واحد لكل شخص.</span></p>
    <div class="steps">
      <div class="step"><i><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></i><b><span lang="en">Vote a spot</span><span lang="ar">صوّت لمكان</span></b><span><span lang="en">One vote per person, every week.</span><span lang="ar">صوت واحد لكل شخص كل أسبوع.</span></span></div>
      <div class="step"><i><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5z"/></svg></i><b><span lang="en">It's crowned</span><span lang="ar">يتوَّج المكان</span></b><span><span lang="en">The most-voted spot wins the week.</span><span lang="ar">الأكثر تصويتًا يفوز بالأسبوع.</span></span></div>
      <div class="step"><i><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="9" width="16" height="11" rx="1.5"/><path d="M12 9v11M3.5 9h17M12 9c-1.5-3-5-4-5-1.5S10 9 12 9zM12 9c1.5-3 5-4 5-1.5S14 9 12 9z"/></svg></i><b><span lang="en">5 voters win</span><span lang="ar">٥ مصوّتين يربحون</span></b><span><span lang="en">Picked at random for a free-item voucher.</span><span lang="ar">يُختارون عشوائيًا لقسيمة صنف مجاني.</span></span></div>
    </div>
    <p class="fine"><span lang="en">Waddy runs the Spots draw. Apple and Google are not sponsors and are not involved in any way. Full rules are in the app.</span><span lang="ar">وددى هي الجهة المنظِّمة لسحب أماكن وددى، وشركتا Apple وGoogle ليستا راعيتين له ولا علاقة لهما به. الشروط الكاملة داخل التطبيق.</span></p>
  </div>
  <div class="stage">
    <div class="plate"></div>
    <div class="phone "><img src="{{ asset('public/assets/site/img/spots_new.jpg') }}" alt="This week's race leaderboard" width="620"><i class="isl"></i></div>
    <div class="card trophy"><img src="{{ asset('public/assets/site/img/trophy.webp') }}" alt=""></div>
  </div>
</div></section>

<section class="band teal" id="pets"><div class="wrap split">
  <div class="copy">
    <span class="eyebrow" style="color:var(--mint)"><img src="{{ asset('public/assets/site/img/logo_mint3.webp') }}" alt=""><span lang="en">Pet care</span><span lang="ar">رعاية الحيوانات الأليفة</span></span>
    <h2><span lang="en"><span class="l1">Sit. Stay.</span><br>We'll deliver<span class="dot">.</span></span><span lang="ar"><span class="l1">اجلس. انتظر.</span><br>والتوصيل علينا<span class="dot">.</span></span></h2>
    <p class="lead"><span lang="en">Add your cat, dog, bird or fish, and the shop fills with things they'll actually like.</span><span lang="ar">أضف قطتك أو كلبك أو طائرك أو سمكتك، ليمتلئ المتجر بما يحبه فعلًا.</span></p>
    <ul class="ticks">
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Food, treats, litter, toys and grooming</span><span lang="ar">طعام ومكافآت ورمل وألعاب ومستلزمات عناية</span></li>
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">Vet clinics nearby: call, WhatsApp or get directions</span><span lang="ar">عيادات بيطرية قريبة: اتصال أو واتساب أو اتجاهات</span></li>
      <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span lang="en">A reminder before their usual runs out</span><span lang="ar">تذكير قبل أن ينفد طعامه المعتاد</span></li>
    </ul>
  </div>
  <div class="stage">
    <div class="plate"></div>
    <img class="cut" src="{{ asset('public/assets/site/img/pet_packs.webp') }}" alt="" style="width:200px; inset-inline-start:-8%; top:14%">
    <div class="phone "><img src="{{ asset('public/assets/site/img/pets_new.jpg') }}" alt="Everything Max needs: vets and pet shops nearby" width="620"><i class="isl"></i></div>
    <div class="card petid"><div class="hd"><span lang="en">YOUR PET</span><span lang="ar">حيوانك</span></div><div class="bd">
      <div class="who"><img src="{{ asset('public/assets/site/img/dog.webp') }}" alt=""><span lang="en">Max</span><span lang="ar">ماكس</span></div>
      <div class="row"><span><span lang="en">Type</span><span lang="ar">النوع</span></span><b><span lang="en">Dog</span><span lang="ar">كلب</span></b></div>
      <div class="row"><span><span lang="en">Age</span><span lang="ar">العمر</span></span><b><span lang="en">Grown-up</span><span lang="ar">بالغ</span></b></div>
      <div class="row"><span><span lang="en">Diet</span><span lang="ar">الطعام</span></span><b><span lang="en">Dry food</span><span lang="ar">طعام جاف</span></b></div>
    </div></div>
  </div>
</div></section>
</main>
@endsection
