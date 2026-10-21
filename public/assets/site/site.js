(function () {
  var root = document.documentElement, btn = document.getElementById('lang'), body = document.body;
  function set(l) {
    root.setAttribute('data-lang', l); root.lang = l; root.dir = l === 'ar' ? 'rtl' : 'ltr';
    btn.textContent = l === 'ar' ? 'English' : 'عربي';
    try { localStorage.setItem('waddy-lang', l); } catch (e) {}
  }
  var saved = 'en'; try { saved = localStorage.getItem('waddy-lang') || 'en'; } catch (e) {}
  set(saved);
  btn.addEventListener('click', function () {
    body.classList.add('switching');
    setTimeout(function () { set(root.getAttribute('data-lang') === 'ar' ? 'en' : 'ar'); body.classList.remove('switching'); }, 250);
  });

  var header = document.querySelector('header');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* scroll reveals */
  if (!reduce && 'IntersectionObserver' in window && !navigator.webdriver) {
    var groups = [];
    document.querySelectorAll('.copy, .closing, .sectionhead').forEach(function (g) { groups.push([].slice.call(g.children)); });
    document.querySelectorAll('.ticks, .steps, .cats, .foot .cols').forEach(function (g) { groups.push([].slice.call(g.children)); });
    groups.forEach(function (els) { els.forEach(function (el, i) { el.classList.add('rv'); el.style.setProperty('--i', i); }); });
    document.querySelectorAll('.stage .phone, .foot .mark').forEach(function (el) { el.classList.add('rv'); });
    document.querySelectorAll('.stage .card, .ripe .card, .stage .cut').forEach(function (el, i) { el.classList.add('rv', 'pop'); el.style.setProperty('--i', 4 + (i % 3)); });
    root.classList.add('anim');
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.rv').forEach(function (el) { io.observe(el); });
  }

  /* header + gentle parallax on the phones and cards */
  var stages = [].slice.call(document.querySelectorAll('.stage'));
  var ticking = false;
  function onScroll() {
    header.classList.toggle('scrolled', window.scrollY > 8);
    if (!reduce) {
      var vh = window.innerHeight;
      stages.forEach(function (st) {
        var r = st.getBoundingClientRect(); if (r.bottom < 0 || r.top > vh) return;
        var p = (r.top + r.height / 2 - vh / 2) / vh;
        st.querySelectorAll('.phone').forEach(function (el) { el.style.setProperty('--py', (p * 40).toFixed(1) + 'px'); });
        st.querySelectorAll('.card, .cut').forEach(function (el) { el.style.setProperty('--py', (p * -60).toFixed(1) + 'px'); });
      });
    }
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();

  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.getAttribute('data-copy'), old = b.textContent;
      function done() { b.textContent = root.lang === 'ar' ? 'تم النسخ' : 'Copied'; setTimeout(function () { b.textContent = old; }, 1400); }
      if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, function () {}); 
    });
  });

  /* hero phone follows the pointer a little */
  var hero = document.querySelector('.hero .phone');
  if (hero && !reduce && window.matchMedia('(pointer: fine)').matches) {
    hero.style.transition = 'rotate .4s ease-out';
    document.querySelector('.hero').addEventListener('pointermove', function (e) {
      var x = e.clientX / window.innerWidth - .5;
      hero.style.rotate = (x * 6).toFixed(2) + 'deg';
    });
  }
})();
(function () {
  var links = [].slice.call(document.querySelectorAll('.toc a'));
  if (!links.length || !('IntersectionObserver' in window)) return;
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) links.forEach(function (a) { a.classList.toggle('on', a.getAttribute('href') === '#' + e.target.id); }); });
  }, { rootMargin: '-30% 0px -60% 0px' });
  document.querySelectorAll('article section[id]').forEach(function (s) { io.observe(s); });
})();
