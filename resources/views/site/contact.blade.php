@extends('site.layout')
@section('title', 'Contact us · Waddy')
@section('description', 'Waddy contact us')
@section('content')
<main id="top"><section class="phero"><div class="wrap"><div class="crumb"><a href="{{ route('home') }}"><span lang="en">Home</span><span lang="ar">الرئيسية</span></a> / <span lang="en">Contact us</span><span lang="ar">تواصل معنا</span></div><h1><span lang="en">Contact us</span><span lang="ar">تواصل معنا</span></h1><p><span lang="en">Questions about an order, a store, a refund or your account? Here's how to reach us.</span><span lang="ar">لديك سؤال عن طلب أو متجر أو استرداد أو حسابك؟ هذه طرق التواصل معنا.</span></p></div></section><div class="wrap"><div class="cards"><div class="ccard feature"><i><svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z"/><path d="M8 10h8M8 13h5"/></svg></i><h3><span lang="en">Chat in the app</span><span lang="ar">الدردشة في التطبيق</span></h3><p><span lang="en">The fastest way to get help with an order. Open Waddy → Profile → Help & support.</span><span lang="ar">أسرع طريقة للمساعدة في طلب. افتح وددى ← الملف الشخصي ← المساعدة والدعم.</span></p><span class="v"><span lang="en">Usually replies in minutes</span><span lang="ar">عادةً نرد خلال دقائق</span></span></div><div class="ccard"><i><svg viewBox="0 0 24 24"><path d="M4 9l1.5-5h13L20 9M4 9v11h16V9M4 9h16M9 20v-6h6v6"/></svg></i><h3><span lang="en">Partner with Waddy</span><span lang="ar">كن شريكًا لوددى</span></h3><p><span lang="en">Run a restaurant, store or pet shop in Maadi? Send us a message and our partnerships team will reach out.</span><span lang="ar">تدير مطعمًا أو متجرًا أو متجر حيوانات في المعادي؟ أرسل لنا رسالة وسيتواصل معك فريق الشراكات.</span></p><span class="v"><span lang="en">Grow with your neighborhood</span><span lang="ar">انمُ مع حيّك</span></span><a class="go" href="#message" data-topic="partner"><span lang="en">Send a message</span><span lang="ar">أرسل رسالة</span></a></div><div class="ccard"><i><svg viewBox="0 0 24 24"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="M9 17h6l-2-7h-3M13 10h4l1 4"/></svg></i><h3><span lang="en">Deliver with Waddy</span><span lang="ar">وصّل مع وددى</span></h3><p><span lang="en">Want to ride with us? Send us a message and we will share how to join.</span><span lang="ar">تريد التوصيل معنا؟ راسلنا وسنخبرك كيف تنضم.</span></p><span class="v"><span lang="en">Flexible hours in Maadi</span><span lang="ar">ساعات مرنة في المعادي</span></span><a class="go" href="#message" data-topic="rider"><span lang="en">Send a message</span><span lang="ar">أرسل رسالة</span></a></div><div class="ccard"><i><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></i><h3><span lang="en">Where we are</span><span lang="ar">أين نحن</span></h3><p><span lang="en">We deliver across Maadi, Cairo, and are growing to new areas soon.</span><span lang="ar">نوصّل في كل أنحاء المعادي بالقاهرة، ونتوسع قريبًا إلى مناطق جديدة.</span></p><span class="v"><span lang="en">Maadi, Cairo, Egypt</span><span lang="ar">المعادي، القاهرة، مصر</span></span></div></div><section class="cform rv" id="message"><div class="cf-side"><h2><span lang="en">Send us a message</span><span lang="ar">أرسل لنا رسالة</span></h2><p><span lang="en">Write to us about anything: an order, a refund, your account or working with us. We’ll reply by email.</span><span lang="ar">راسلنا في أي موضوع: طلب أو استرداد أو حسابك أو العمل معنا، وسنرد عليك عبر البريد الإلكتروني.</span></p><ul><li><i><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></i><span><span lang="en">For an order that’s on its way, the in-app chat is the fastest.</span><span lang="ar">للطلب الذي في الطريق، الدردشة داخل التطبيق هي الأسرع.</span></span></li><li><i><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></i><span><span lang="en">Add your order number so we can find it straight away.</span><span lang="ar">أضف رقم الطلب لنجده على الفور.</span></span></li><li><i><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></i><span><span lang="en">We only use your details to reply to you. See our <a href="{{ route('privacy-policy') }}">privacy policy</a>.</span><span lang="ar">نستخدم بياناتك للرد عليك فقط. راجع <a href="{{ route('privacy-policy') }}">سياسة الخصوصية</a>.</span></span></li></ul></div><div class="cf-main"><form class="cf" novalidate data-endpoint="{{ route('contact-us.send') }}" data-mail="">@csrf<div class="row2"><div class="fld" data-f="name"><label for="f-name"><span lang="en">Full name</span><span lang="ar">الاسم بالكامل</span></label><input id="f-name" name="name" type="text" data-ph-en="e.g. Sara Ahmed" data-ph-ar="مثال: سارة أحمد" placeholder="e.g. Sara Ahmed" aria-describedby="e-name" autocomplete="name" maxlength="80" required><span class="err" id="e-name" data-en="Please enter your name." data-ar="يُرجى إدخال اسمك."></span></div><div class="fld" data-f="phone"><label for="f-phone"><span lang="en">Mobile number</span><span lang="ar">رقم الهاتف المحمول</span> <span class="opt"><span lang="en">(optional)</span><span lang="ar">(اختياري)</span></span></label><input id="f-phone" name="phone" type="tel" data-ph-en="010 1234 5678" data-ph-ar="010 1234 5678" placeholder="010 1234 5678" aria-describedby="e-phone" dir="ltr" inputmode="tel" autocomplete="tel" maxlength="20"><span class="err" id="e-phone" data-en="Enter an Egyptian mobile number, e.g. 010 1234 5678." data-ar="أدخل رقم هاتف محمول مصري، مثل 010 1234 5678."></span></div></div><div class="row2"><div class="fld" data-f="email"><label for="f-email"><span lang="en">Email</span><span lang="ar">البريد الإلكتروني</span></label><input id="f-email" name="email" type="email" data-ph-en="you@example.com" data-ph-ar="you@example.com" placeholder="you@example.com" aria-describedby="e-email" dir="ltr" autocomplete="email" maxlength="120" required><span class="err" id="e-email" data-en="Please enter a valid email." data-ar="يُرجى إدخال بريد إلكتروني صحيح."></span></div><div class="fld" data-f="topic"><label for="f-topic"><span lang="en">Topic</span><span lang="ar">الموضوع</span></label><select id="f-topic" name="topic" required aria-describedby="e-topic"><option value="" disabled selected data-en="Choose a topic" data-ar="اختر الموضوع">Choose a topic</option><option value="order" data-en="Help with an order" data-ar="مساعدة في طلب">Help with an order</option><option value="refund" data-en="Refund or payment" data-ar="استرداد أو دفع">Refund or payment</option><option value="account" data-en="My account" data-ar="حسابي">My account</option><option value="partner" data-en="Partner with Waddy" data-ar="الشراكة مع وددى">Partner with Waddy</option><option value="rider" data-en="Deliver with Waddy" data-ar="التوصيل مع وددى">Deliver with Waddy</option><option value="other" data-en="Something else" data-ar="موضوع آخر">Something else</option></select><span class="err" id="e-topic" data-en="Please choose a topic." data-ar="يُرجى اختيار الموضوع."></span></div></div><div class="fld" data-f="order" hidden><label for="f-order"><span lang="en">Order number</span><span lang="ar">رقم الطلب</span> <span class="opt"><span lang="en">(optional)</span><span lang="ar">(اختياري)</span></span></label><input id="f-order" name="order" type="text" data-ph-en="e.g. 100245" data-ph-ar="مثال: 100245" placeholder="e.g. 100245" aria-describedby="e-order" dir="ltr" inputmode="numeric" maxlength="20"><span class="err" id="e-order" data-en="" data-ar=""></span></div><div class="fld" data-f="message"><label for="f-message"><span lang="en">Message</span><span lang="ar">الرسالة</span></label><textarea id="f-message" name="message" maxlength="1000" required aria-describedby="e-message" data-ph-en="How can we help?" data-ph-ar="كيف يمكننا مساعدتك؟" placeholder="How can we help?"></textarea><div class="meta"><span class="count" dir="ltr" aria-hidden="true">0 / 1000</span></div><span class="err" id="e-message" data-en="Tell us a little more (at least 10 characters)." data-ar="أخبرنا بالمزيد (10 أحرف على الأقل)."></span></div><div class="fld" data-f="consent"><label class="consent"><input type="checkbox" name="consent" id="f-consent" aria-describedby="e-consent"><span><span lang="en">I agree that Waddy can use these details to reply to me, as described in the <a href="{{ route('privacy-policy') }}">privacy policy</a>.</span><span lang="ar">أوافق على أن يستخدم وددى هذه البيانات للرد عليّ، كما هو موضح في <a href="{{ route('privacy-policy') }}">سياسة الخصوصية</a>.</span></span></label><span class="err" id="e-consent" data-en="Please agree so we can reply to you." data-ar="يُرجى الموافقة لنتمكن من الرد عليك."></span></div><div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="_gotcha" tabindex="-1" autocomplete="off"></label></div><input type="hidden" name="_subject" value="Waddy website: new message"><input type="hidden" name="lang" value="en"><div class="actions"><button class="send" type="submit"><span class="spin"></span><span lang="en">Send message</span><span lang="ar">إرسال الرسالة</span><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button><p class="status" role="status" aria-live="polite"></p></div></form><div class="done" tabindex="-1"><div class="tick"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div><h3><span lang="en">Message sent</span><span lang="ar">تم إرسال رسالتك</span></h3><p class="msg-sent"><span lang="en">Thanks for writing to us. We’ll reply to you by email soon.</span><span lang="ar">شكرًا لتواصلك معنا. سنرد عليك عبر البريد الإلكتروني قريبًا.</span></p><p class="msg-mail" hidden><span lang="en">Your email app should now open with your message ready. Just press send.</span><span lang="ar">سيفتح تطبيق البريد الآن ورسالتك جاهزة، فقط اضغط إرسال.</span></p><button type="button" class="again"><span lang="en">Send another message</span><span lang="ar">إرسال رسالة أخرى</span></button></div></div></section><div class="faq"><h2><span lang="en">Quick answers</span><span lang="ar">إجابات سريعة</span></h2><details><summary><span lang="en">My order is late. What can I do?</span><span lang="ar">طلبي تأخر، ماذا أفعل؟</span></summary><p><span lang="en">Open the order to see your rider on the live map and the updated arrival time. If it looks stuck, message us from the order screen.</span><span lang="ar">افتح الطلب لترى المندوب على الخريطة ووقت الوصول المحدَّث. إذا بدا متوقفًا، راسلنا من شاشة الطلب.</span></p></details><details><summary><span lang="en">Something was missing or wrong in my order.</span><span lang="ar">كان هناك صنف ناقص أو خاطئ في طلبي.</span></summary><p><span lang="en">Open the order and tap Refund this order within 24 hours. See the <a href="{{ route('refund') }}">refund policy</a> for details.</span><span lang="ar">افتح الطلب واضغط على استرداد هذا الطلب خلال 24 ساعة. راجع <a href="{{ route('refund') }}">سياسة الاسترداد</a> للتفاصيل.</span></p></details><details><summary><span lang="en">How do I delete my account?</span><span lang="ar">كيف أحذف حسابي؟</span></summary><p><span lang="en">In the app go to Profile → Delete account. Read how we handle your data in our <a href="{{ route('privacy-policy') }}">privacy policy</a>.</span><span lang="ar">من التطبيق ادخل إلى الملف الشخصي ← حذف الحساب. اقرأ كيف نتعامل مع بياناتك في <a href="{{ route('privacy-policy') }}">سياسة الخصوصية</a>.</span></p></details><details><summary><span lang="en">Waddy is not in my area yet.</span><span lang="ar">وددى غير متاح في منطقتي بعد.</span></summary><p><span lang="en">Download the app anyway and set your location. We will let you know the day we arrive.</span><span lang="ar">حمّل التطبيق وحدّد موقعك، وسنُعلمك فور وصولنا إليك.</span></p></details></div></div></main>
@endsection

@push('scripts')
<script>
(function () {
  var box = document.getElementById('message'); if (!box) return;
  var f = box.querySelector('form'), root = document.documentElement, sendBtn = f.querySelector('.send'), status = f.querySelector('.status');
  var topic = f.elements.topic, order = f.querySelector('[data-f="order"]'), msg = f.elements.message, count = f.querySelector('.count');
  var TXT = {
    sending: ['Sending…', 'جارٍ الإرسال…'],
    fail: ['We couldn’t send your message. Please try again, or chat with us in the app.', 'تعذّر إرسال رسالتك. حاول مرة أخرى، أو تحدّث معنا من داخل التطبيق.'],
    off: ['Messages from the website aren’t switched on yet. For now, please chat with us in the Waddy app.', 'الرسائل عبر الموقع غير مفعّلة بعد. يُرجى التواصل معنا من داخل تطبيق وددى حاليًا.'],
    fix: ['Please check the highlighted fields.', 'يُرجى مراجعة الحقول المحددة.']
  };
  var TOPIC = {}; [].forEach.call(topic.options, function (o) { if (o.value) TOPIC[o.value] = o.getAttribute('data-en'); });
  var statusKey = null;
  function ar() { return root.getAttribute('data-lang') === 'ar'; }
  function say(k, warn) { statusKey = k; status.textContent = k ? TXT[k][ar() ? 1 : 0] : ''; status.classList.toggle('warn', !!warn); }
  function i18n() {
    var a = ar();
    f.querySelectorAll('[data-ph-en]').forEach(function (el) { el.placeholder = el.getAttribute(a ? 'data-ph-ar' : 'data-ph-en'); });
    f.querySelectorAll('option[data-en]').forEach(function (o) { o.textContent = o.getAttribute(a ? 'data-ar' : 'data-en'); });
    f.querySelectorAll('.err').forEach(function (e) { e.textContent = e.getAttribute(a ? 'data-ar' : 'data-en'); });
    if (statusKey) status.textContent = TXT[statusKey][a ? 1 : 0];
    f.elements.lang.value = a ? 'ar' : 'en';
  }
  i18n(); new MutationObserver(i18n).observe(root, { attributes: true, attributeFilter: ['data-lang'] });

  var CHECKS = {
    name: function () { return f.elements.name.value.trim().length >= 2; },
    phone: function () { var v = f.elements.phone.value.replace(/[\s\-()]/g, ''); return !v || /^(\+?20|0020)?0?1[0125]\d{8}$/.test(v); },
    email: function () { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.elements.email.value.trim()); },
    topic: function () { return !!topic.value; },
    message: function () { return msg.value.trim().length >= 10; },
    consent: function () { return f.elements.consent.checked; }
  };
  function check(n) { var ok = CHECKS[n](), w = f.querySelector('[data-f="' + n + '"]'); w.classList.toggle('bad', !ok); var c = f.elements[n]; if (c && c.setAttribute) c.setAttribute('aria-invalid', ok ? 'false' : 'true'); return ok; }
  Object.keys(CHECKS).forEach(function (n) {
    var c = f.elements[n];
    c.addEventListener('blur', function () { if (n === 'consent' || c.value) check(n); });
    c.addEventListener(n === 'topic' || n === 'consent' ? 'change' : 'input', function () { if (c.closest('.fld').classList.contains('bad')) check(n); });
  });
  function syncTopic() { order.hidden = !(topic.value === 'order' || topic.value === 'refund'); }
  topic.addEventListener('change', syncTopic);
  msg.addEventListener('input', function () { var n = msg.value.length; count.textContent = n + ' / 1000'; count.classList.toggle('max', n >= 1000); });

  function pick(t) { if (!TOPIC[t]) return; topic.value = t; syncTopic(); topic.closest('.fld').classList.remove('bad'); }
  var q = (location.search.match(/[?&]topic=([a-z]+)/) || [])[1]; if (q) pick(q);
  document.querySelectorAll('a[data-topic]').forEach(function (a) { a.addEventListener('click', function () { pick(a.getAttribute('data-topic')); setTimeout(function () { f.elements.name.focus({ preventScroll: true }); }, 500); }); });

  function finish(viaMail) {
    box.querySelector('.msg-sent').hidden = viaMail; box.querySelector('.msg-mail').hidden = !viaMail;
    box.classList.add('sent'); box.querySelector('.done').focus({ preventScroll: true });
    box.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }
  box.querySelector('.again').addEventListener('click', function () {
    f.reset(); syncTopic(); count.textContent = '0 / 1000'; say(null); box.classList.remove('sent'); f.elements.name.focus();
  });

  f.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var bad = Object.keys(CHECKS).filter(function (n) { return !check(n); });
    if (bad.length) { say('fix', true); var first = f.elements[bad[0]]; first.focus(); return; }
    if (f.elements._gotcha.value) { finish(false); return; }
    var endpoint = f.getAttribute('data-endpoint'), mail = f.getAttribute('data-mail');
    if (endpoint) {
      sendBtn.classList.add('busy'); sendBtn.setAttribute('aria-busy', 'true'); say('sending');
      fetch(endpoint, { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error(r.status); say(null); finish(false); })
        .catch(function () { say('fail', true); })
        .then(function () { sendBtn.classList.remove('busy'); sendBtn.removeAttribute('aria-busy'); });
    } else if (mail) {
      var e = f.elements, body = 'Name: ' + e.name.value.trim() + '\nEmail: ' + e.email.value.trim()
        + (e.phone.value.trim() ? '\nPhone: ' + e.phone.value.trim() : '') + '\nTopic: ' + TOPIC[topic.value]
        + (!order.hidden && e.order.value.trim() ? '\nOrder: ' + e.order.value.trim() : '') + '\n\n' + e.message.value.trim();
      location.href = 'mailto:' + mail + '?subject=' + encodeURIComponent('Waddy website: ' + TOPIC[topic.value]) + '&body=' + encodeURIComponent(body);
      finish(true);
    } else {
      say('off', true);
    }
  });
})();
</script>
@endpush
