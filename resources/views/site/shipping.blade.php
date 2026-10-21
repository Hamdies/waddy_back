@extends('site.layout')
@section('title', 'Shipping & delivery policy · Waddy')
@section('description', 'Waddy shipping and delivery policy')
@section('content')
<main id="top"><section class="phero"><div class="wrap"><div class="crumb"><a href="{{ route('home') }}"><span lang="en">Home</span><span lang="ar">الرئيسية</span></a> / <span lang="en">Shipping & delivery policy</span><span lang="ar">سياسة الشحن والتوصيل</span></div><h1><span lang="en">Shipping & delivery policy</span><span lang="ar">سياسة الشحن والتوصيل</span></h1><p><span lang="en">Where Waddy delivers, how delivery works, what you pay, and what to do if something goes wrong. This policy is part of the Waddy <a href="{{ route('terms-and-conditions') }}">Terms & conditions</a>.</span><span lang="ar">أين يوصّل وددى، وكيف يعمل التوصيل، وما الذي تدفعه، وماذا تفعل إذا حدث خطأ. هذه السياسة جزء من <a href="{{ route('terms-and-conditions') }}">الشروط والأحكام</a> الخاصة بوددى.</span></p><span class="updated"><span lang="en">Last updated: October 2026</span><span lang="ar">آخر تحديث: أكتوبر 2026</span></span></div></section>
<div class="wrap doc"><nav class="toc" aria-label="On this page"><b><span lang="en">On this page</span><span lang="ar">في هذه الصفحة</span></b>
<a href="#where-we-deliver"><span lang="en">Where we deliver</span><span lang="ar">أين نوصّل</span></a>
<a href="#what-you-pay"><span lang="en">What you pay</span><span lang="ar">ما الذي تدفعه</span></a>
<a href="#payment"><span lang="en">Payment</span><span lang="ar">الدفع</span></a>
<a href="#delivery-times"><span lang="en">Delivery times</span><span lang="ar">مواعيد التوصيل</span></a>
<a href="#opening-hours"><span lang="en">Opening hours</span><span lang="ar">ساعات العمل</span></a>
<a href="#receiving-your-order"><span lang="en">Receiving your order</span><span lang="ar">استلام طلبك</span></a>
<a href="#changing-your-order"><span lang="en">Changing your address or order</span><span lang="ar">تغيير العنوان أو الطلب</span></a>
<a href="#problems-with-delivery"><span lang="en">Problems with delivery</span><span lang="ar">مشكلات التوصيل</span></a>
<a href="#contact-us"><span lang="en">Contact us</span><span lang="ar">تواصل معنا</span></a>
</nav><article>

<section id="where-we-deliver"><h2><span class="n">01</span><span lang="en">Where we deliver</span><span lang="ar">أين نوصّل</span></h2>
<div lang="en"><p>Waddy delivers within our active <b>delivery zones</b>, which currently cover <b>Maadi, Cairo</b>. We are growing one neighborhood at a time.</p><ul>
<li>When you enter or choose your address, the app checks whether it is inside a delivery zone.</li>
<li>If you are outside a zone, the app will tell you. You can ask us to launch in your area, and we will notify you when we do.</li>
<li>Browsing the app is open to everyone, but we can only deliver to addresses inside our zones.</li></ul></div>
<div lang="ar"><p>يوصّل وددى داخل <b>مناطق التوصيل</b> المفعّلة لدينا، وهي حاليًا <b>المعادي، القاهرة</b>. ونتوسّع حيًّا بعد حي.</p><ul>
<li>عند إدخال عنوانك أو اختياره، يتحقق التطبيق مما إذا كان داخل منطقة توصيل.</li>
<li>إذا كنت خارج المنطقة، سيُعلمك التطبيق بذلك. يمكنك أن تطلب منا الوصول إلى منطقتك، وسنُخطرك عندما نصل إليها.</li>
<li>تصفّح التطبيق متاح للجميع، لكن لا يمكننا التوصيل إلا إلى العناوين داخل مناطقنا.</li></ul></div></section>

<section id="what-you-pay"><h2><span class="n">02</span><span lang="en">What you pay</span><span lang="ar">ما الذي تدفعه</span></h2>
<div lang="en"><p>The total you pay is made up of:</p>
<table class="fees"><thead><tr><th>Item</th><th>What it is</th><th>Amount</th></tr></thead><tbody>
<tr><td>Items</td><td>The food, groceries or products you order, at the price the restaurant or store sets</td><td>Shown on each item</td></tr>
<tr><td>Delivery fee</td><td>The cost of getting your order to you by rider</td><td>25 EGP per order</td></tr>
<tr><td>Service fee</td><td>Helps us run the Waddy platform, payments and customer support</td><td>7 EGP per order</td></tr>
<tr><td>Discounts</td><td>Promo codes, vouchers and offers</td><td>Deducted from your total</td></tr></tbody></table>
<p>Example: an order with 250 EGP of items, plus a 25 EGP delivery fee and a 7 EGP service fee, comes to <b>282 EGP</b>.</p><ul>
<li>The delivery fee and service fee are charged <b>once per order</b>, not per item.</li>
<li>Restaurants and stores set their own item prices. Prices in Waddy can be different from prices in their own shop.</li>
<li>You will always see the <b>full total, including all fees, before you confirm your order</b>. There are no hidden charges after checkout.</li>
<li>Fees can change from time to time. The fees shown at checkout when you place your order are the ones that apply to that order.</li>
<li>All prices are in Egyptian pounds (EGP).</li>
<li>Some offers, such as free-delivery codes, remove the delivery fee. The offer's conditions are shown with the offer.</li></ul></div>
<div lang="ar"><p>يتكوّن إجمالي ما تدفعه من:</p>
<table class="fees"><thead><tr><th>البند</th><th>ما هو</th><th>المبلغ</th></tr></thead><tbody>
<tr><td>الأصناف</td><td>الطعام أو البقالة أو المنتجات التي تطلبها، بالسعر الذي يحدده المطعم أو المتجر</td><td>معروض على كل صنف</td></tr>
<tr><td>رسوم التوصيل</td><td>تكلفة إيصال طلبك إليك عبر المندوب</td><td>25 جنيهًا لكل طلب</td></tr>
<tr><td>رسوم الخدمة</td><td>تساعدنا على تشغيل منصة وددى والمدفوعات وخدمة العملاء</td><td>7 جنيهات لكل طلب</td></tr>
<tr><td>الخصومات</td><td>أكواد الخصم والقسائم والعروض</td><td>تُخصم من الإجمالي</td></tr></tbody></table>
<p>مثال: طلب بقيمة 250 جنيهًا من الأصناف، مع رسوم توصيل 25 جنيهًا ورسوم خدمة 7 جنيهات، يكون إجماليه <b>282 جنيهًا</b>.</p><ul>
<li>تُحتسب رسوم التوصيل ورسوم الخدمة <b>مرة واحدة لكل طلب</b>، وليس لكل صنف.</li>
<li>تحدد المطاعم والمتاجر أسعار أصنافها بنفسها، وقد تختلف الأسعار في وددى عن أسعارها في محلاتها.</li>
<li>سترى دائمًا <b>الإجمالي الكامل شاملًا جميع الرسوم قبل تأكيد طلبك</b>، ولا توجد أي رسوم خفية بعد الدفع.</li>
<li>قد تتغير الرسوم من وقت لآخر، والرسوم المعروضة عند الدفع وقت تقديم طلبك هي التي تنطبق عليه.</li>
<li>جميع الأسعار بالجنيه المصري.</li>
<li>بعض العروض، مثل أكواد التوصيل المجاني، تُلغي رسوم التوصيل، وتظهر شروط العرض معه.</li></ul></div></section>

<section id="payment"><h2><span class="n">03</span><span lang="en">Payment</span><span lang="ar">الدفع</span></h2>
<div lang="en"><p>You can pay with the payment methods shown at checkout. Card payments are processed securely by our payment provider, Paymob, and we do not store your full card details. See our <a href="{{ route('terms-and-conditions') }}">Terms</a> for more on payments.</p></div>
<div lang="ar"><p>يمكنك الدفع بوسائل الدفع المعروضة عند إتمام الطلب. تتم معالجة مدفوعات البطاقات بأمان عبر مزوّد الدفع لدينا Paymob، ولا نحتفظ ببيانات بطاقتك كاملة. راجع <a href="{{ route('terms-and-conditions') }}">الشروط</a> لمزيد من التفاصيل حول الدفع.</p></div></section>

<section id="delivery-times"><h2><span class="n">04</span><span lang="en">Delivery times</span><span lang="ar">مواعيد التوصيل</span></h2>
<div lang="en"><ul>
<li>The delivery time shown in the app is an <b>estimate</b>, not a guarantee.</li>
<li>The time depends on how long the restaurant or store takes to prepare your order, the distance, traffic, weather and how busy we are.</li>
<li>If your order is running very late, contact us and we will check on it. See our <a href="{{ route('refund') }}">Refund policy</a> for what we can do.</li></ul></div>
<div lang="ar"><ul>
<li>وقت التوصيل المعروض في التطبيق <b>تقديري</b> وليس مضمونًا.</li>
<li>يعتمد الوقت على مدة تجهيز المطعم أو المتجر لطلبك، والمسافة، والزحام، والطقس، ومدى انشغالنا.</li>
<li>إذا تأخر طلبك كثيرًا، تواصل معنا وسنتابعه. راجع <a href="{{ route('refund') }}">سياسة الاسترداد</a> لمعرفة ما يمكننا فعله.</li></ul></div></section>

<section id="opening-hours"><h2><span class="n">05</span><span lang="en">Opening hours</span><span lang="ar">ساعات العمل</span></h2>
<div lang="en"><p>Delivery is available during the hours each restaurant or store is open and accepting orders through Waddy. Hours can change, and the app shows what is available when you order.</p></div>
<div lang="ar"><p>التوصيل متاح خلال الساعات التي يكون فيها كل مطعم أو متجر مفتوحًا ويستقبل الطلبات عبر وددى. قد تتغير الساعات، ويعرض التطبيق المتاح وقت طلبك.</p></div></section>

<section id="receiving-your-order"><h2><span class="n">06</span><span lang="en">Receiving your order</span><span lang="ar">استلام طلبك</span></h2>
<div lang="en"><ul>
<li>Please enter an <b>accurate address</b>, including building, floor, apartment and any notes that help the rider find you.</li>
<li>Keep your <b>phone available</b> so the rider can reach you when they arrive.</li>
<li>Check your order when it arrives and tell us straight away if anything is wrong.</li>
<li>If the rider cannot reach you or deliver after reasonable attempts, the order may be cancelled, and you may still be charged for it, because the food has already been prepared.</li>
<li>Please treat our riders with respect.</li></ul></div>
<div lang="ar"><ul>
<li>يُرجى إدخال <b>عنوان دقيق</b> يشمل المبنى والدور والشقة وأي ملاحظات تساعد المندوب في الوصول إليك.</li>
<li>اجعل <b>هاتفك متاحًا</b> ليتمكن المندوب من التواصل معك عند وصوله.</li>
<li>افحص طلبك عند وصوله وأخبرنا فورًا إذا كان هناك أي خطأ.</li>
<li>إذا لم يتمكن المندوب من الوصول إليك أو التسليم بعد محاولات معقولة، فقد يُلغى الطلب وقد تُحتسب عليك قيمته، لأن الطعام قد جُهّز بالفعل.</li>
<li>يُرجى معاملة مندوبينا باحترام.</li></ul></div></section>

<section id="changing-your-order"><h2><span class="n">07</span><span lang="en">Changing your address or order</span><span lang="ar">تغيير العنوان أو الطلب</span></h2>
<div lang="en"><ul>
<li>You can change the delivery address or details only <b>before the restaurant or store starts preparing your order</b>. Contact customer service as quickly as you can.</li>
<li>If your order is already being prepared or is on its way, we may not be able to change it.</li></ul></div>
<div lang="ar"><ul>
<li>يمكنك تغيير عنوان التوصيل أو التفاصيل فقط <b>قبل أن يبدأ المطعم أو المتجر في تجهيز طلبك</b>. تواصل مع خدمة العملاء في أسرع وقت ممكن.</li>
<li>إذا كان طلبك قيد التجهيز أو في الطريق إليك، فقد لا نتمكن من تغييره.</li></ul></div></section>

<section id="problems-with-delivery"><h2><span class="n">08</span><span lang="en">Problems with delivery</span><span lang="ar">مشكلات التوصيل</span></h2>
<div lang="en"><p>If your order is late, missing, wrong, damaged or never arrived, see our <a href="{{ route('refund') }}">Refund & cancellation policy</a> for how to report it and what we will do.</p></div>
<div lang="ar"><p>إذا تأخر طلبك أو كان ناقصًا أو خاطئًا أو تالفًا أو لم يصل أبدًا، راجع <a href="{{ route('refund') }}">سياسة الاسترداد والإلغاء</a> لمعرفة كيفية الإبلاغ وما سنفعله.</p></div></section>

<section id="contact-us"><h2><span class="n">09</span><span lang="en">Contact us</span><span lang="ar">تواصل معنا</span></h2>
<div lang="en"><ul>
<li>Hamdies Solutions (Waddy)</li>
<li>Email: <a href="mailto:info@hamdiesolutions.com" dir="ltr">info@hamdiesolutions.com</a></li>
<li>Phone / WhatsApp: <a href="tel:+201036086000" dir="ltr">+20 103 608 6000</a></li>
<li>Address: 41A, 18 Street, Sarayat Al-Maadi, Cairo, Egypt</li></ul></div>
<div lang="ar"><ul>
<li>Hamdies Solutions (وددى)</li>
<li>البريد الإلكتروني: <a href="mailto:info@hamdiesolutions.com" dir="ltr">info@hamdiesolutions.com</a></li>
<li>الهاتف / واتساب: <a href="tel:+201036086000" dir="ltr">+20 103 608 6000</a></li>
<li>العنوان: 41أ، شارع 18، سرايات المعادي، القاهرة، مصر</li></ul></div></section>

<div class="ask"><h3><span lang="en">Questions?</span><span lang="ar">لديك سؤال؟</span></h3><p style="margin:0"><span lang="en">Chat with us from inside the app, or visit our <a href="{{ route('contact-us') }}">contact page</a>.</span><span lang="ar">تحدّث معنا من داخل التطبيق، أو زُر <a href="{{ route('contact-us') }}">صفحة التواصل</a>.</span></p></div></article></div></main>
@endsection
