/* Waddy admin panel — design handoff v4. Data comes from /admin/panel/api/*. */
(function () {
  'use strict';

  var BOOT = window.PANEL;
  var RES = BOOT.resources;

  // ---------------------------------------------------------------- design constants
  var IC = { home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z', orders: 'M6 2h12v20l-3-2-3 2-3-2-3 2zM9 7h6M9 11h6M9 15h4', couriers: 'M5 18a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 18a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM5 15h4l3-6h4l3 6M12 9l-2-4H7', zones: 'M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z', tickets: 'M4 5h16v11H8l-4 4zM8 9h8M8 12h5', merchants: 'M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6', products: 'M3 7.5 12 3l9 4.5v9L12 21l-9-4.5zM3 7.5 12 12l9-4.5M12 12v9', promotions: 'M3 12V3h9l9 9-9 9zM7.5 7.5h.01', customers: 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21v-1a6 6 0 0 1 12 0v1M16 3.5a4 4 0 0 1 0 7M18 15a5 5 0 0 1 4 5v1', admins: 'M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6zM9 12l2 2 4-4', payouts: 'M3 7h18v13H3zM3 7l3-4h12l3 4M16 13.5h2', settings: 'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M14 4v4M8 10v4M16 16v4', reviews: 'M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z', appointments: 'M4 5h16v16H4zM4 10h16M8 3v4M16 3v4M8 14h3', trend: 'M3 17l6-6 4 4 8-8M15 7h6v6', truck: 'M2 6h12v10H2zM14 9h4l3 3v4h-7M6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', alert: 'M12 3 2 20h20zM12 10v4M12 17h.01', calendar: 'M4 5h16v16H4zM4 10h16M8 3v4M16 3v4', store: 'M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18', all: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z', food: 'M7 3v8M5 3v4a2 2 0 0 0 4 0V3M7 11v10M17 3c-2 0-3 2-3 6s1 5 3 5v7', grocery: 'M3 9h18l-2 11H5zM8 9l4-6 4 6M9 13v4M15 13v4', pets: 'M12 21c-3 0-6-2-6-5 0-2.5 3-4.5 6-4.5s6 2 6 4.5c0 3-3 5-6 5zM5 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM19 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM9 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM15 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', spots: 'M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21zM12 6l1.1 2.3 2.4.3-1.8 1.7.5 2.4-2.2-1.2-2.2 1.2.5-2.4-1.8-1.7 2.4-.3z', search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-5-5', bell: 'M6 16v-5a6 6 0 0 1 12 0v5l2 2H4zM10 21h4', download: 'M12 3v12M7 10l5 5 5-5M4 21h16', menu: 'M4 6h16M4 12h16M4 18h16' };
  IC.spotPlaces = IC.spots; IC.spotVotes = 'M4 4h16v16H4zM8 12l3 3 5-6'; IC.spotReviews = IC.reviews; IC.spotSubmissions = 'M6 3h12l3 6-9 12L3 9zM3 9h18M9 3l3 18M15 3l-3 18'; IC.spotWinners = 'M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4'; IC.spotPrizes = 'M3 7h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4zM14 7v12'; IC.spotVoters = IC.customers; IC.spotTools = IC.settings;
  var AV = [['#E6F4F3', '#134E4A'], ['#E6FCF3', '#0A6B47'], ['#FFF8E1', '#7A5600'], ['#FFEEEE', '#C23B3B'], ['#134E4A', '#1EF2A0'], ['#DDEFED', '#1D706A']];
  var MODS = ['all', 'food', 'grocery', 'pets', 'spots'];
  var ML = { all: ['All modules', 'كل الأقسام'], food: ['Food', 'طعام'], grocery: ['Grocery', 'بقالة'], pets: ['Pets', 'حيوانات أليفة'], spots: ['Waddy Spots', 'أماكن ودي'], other: ['Other', 'أخرى'] };
  var MSUB = { all: ['Everything in Maadi', 'كل شيء في المعادي'], food: ['Restaurants, cafés & bakeries', 'مطاعم ومقاهي ومخابز'], grocery: ['Supermarkets & fresh produce', 'سوبرماركت ومنتجات طازجة'], pets: ['Pet shops, vets & grooming', 'متاجر وعيادات وتجميل'], spots: ['Weekly vote, draw & vouchers', 'تصويت أسبوعي وسحب وقسائم'] };
  var MC = { all: '#CDEBE8', food: '#FF6B6B', grocery: '#1EF2A0', pets: '#FFBE0B', spots: '#7ECAC3' };
  var TONES = { ok: ['#E6FCF3', '#0A6B47'], info: ['#E6F4F3', '#134E4A'], warn: ['#FFF8E1', '#7A5600'], danger: ['#FFEEEE', '#C23B3B'], muted: ['#F5F7F6', '#6B7876'] };
  var MODTONES = { food: ['#FFEEEE', '#C23B3B'], grocery: ['#E6FCF3', '#0A6B47'], pets: ['#FFF8E1', '#7A5600'], spots: ['#E6F4F3', '#134E4A'], all: ['#F5F7F6', '#6B7876'], other: ['#F5F7F6', '#6B7876'] };
  var STATUS = {
    new: ['New', 'جديد', 'warn'], preparing: ['Preparing', 'قيد التحضير', 'info'], onway: ['On the way', 'في الطريق', 'info'], delivered: ['Delivered', 'تم التوصيل', 'ok'], cancelled: ['Cancelled', 'ملغي', 'muted'], refunded: ['Refunded', 'مسترد', 'warn'], refundreq: ['Refund requested', 'طلب استرداد', 'warn'],
    active: ['Active', 'نشط', 'ok'], paused: ['Paused', 'متوقف', 'muted'], pending: ['Pending', 'قيد الانتظار', 'warn'],
    hidden: ['Hidden', 'مخفي', 'muted'], out: ['Out of stock', 'نفد المخزون', 'danger'], blocked: ['Blocked', 'محظور', 'danger'],
    online: ['Online', 'متصل', 'ok'], offline: ['Offline', 'غير متصل', 'muted'], suspended: ['Suspended', 'موقوف', 'danger'],
    scheduled: ['Scheduled', 'مجدول', 'info'], completed: ['Completed', 'مكتمل', 'ok'], expired: ['Expired', 'منتهي', 'muted'], redeemed: ['Redeemed', 'مستخدم', 'ok'], counted: ['Counted', 'محتسب', 'ok'], crowned: ['Crowned', 'متوّج', 'ok'], malformed: ['Malformed period', 'فترة غير صالحة', 'danger'], approved: ['Approved', 'مقبول', 'ok'], rejected: ['Rejected', 'مرفوض', 'muted'],
    paid: ['Paid', 'مدفوع', 'ok'], failed: ['Failed', 'فشل', 'danger'], denied: ['Denied', 'مرفوض', 'danger'],
    operating: ['Operating', 'يعمل', 'ok'], busy: ['Busy', 'مزدحم', 'warn'], closed: ['Closed', 'مغلق', 'danger'],
    open: ['Open', 'مفتوح', 'warn'], awaiting: ['Awaiting customer', 'بانتظار العميل', 'info'], resolved: ['Resolved', 'تم الحل', 'ok'],
    published: ['Published', 'منشور', 'ok'], flagged: ['Flagged', 'مُبلغ عنه', 'danger'],
    invited: ['Invited', 'تمت الدعوة', 'info'], disabled: ['Disabled', 'معطل', 'muted']
  };
  var NAV = [[['Overview', 'نظرة عامة'], ['home']], [['Operations', 'العمليات'], ['orders', 'appointments', 'couriers', 'zones', 'tickets']], [['Catalog', 'الكتالوج'], ['merchants', 'products', 'reviews', 'promotions']], [['People', 'الأشخاص'], ['customers', 'admins']], [['Finance', 'المالية'], ['payouts']], [['System', 'النظام'], ['settings']]];
  var SPOTS_NAV = [[['Overview', 'نظرة عامة'], ['home']], [['This week', 'هذا الأسبوع'], ['spotPlaces', 'spotVotes', 'spotReviews', 'spotSubmissions']], [['Weekly draw', 'السحب الأسبوعي'], ['spotWinners', 'spotPrizes', 'spotVoters']], [['People', 'الأشخاص'], ['customers', 'tickets']], [['System', 'النظام'], ['spotTools']]];
  var NL = { home: ['Home', 'الرئيسية'], orders: ['Orders', 'الطلبات'], appointments: ['Appointments', 'المواعيد'], couriers: ['Couriers', 'المندوبين'], zones: ['Zones & fees', 'المناطق والرسوم'], tickets: ['Support', 'الدعم'], merchants: ['Merchants', 'المتاجر'], products: ['Products', 'المنتجات'], reviews: ['Reviews', 'التقييمات'], promotions: ['Promotions', 'العروض'], customers: ['Customers', 'العملاء'], admins: ['Admin users', 'المشرفين'], payouts: ['Payouts', 'المدفوعات'], settings: ['Settings', 'الإعدادات'], spotPlaces: ['Places', 'الأماكن'], spotVotes: ['Votes', 'الأصوات'], spotReviews: ['Reviews', 'التقييمات'], spotSubmissions: ['Submissions', 'الاقتراحات'], spotWinners: ['Hall of Fame', 'قاعة المشاهير'], spotPrizes: ['Vouchers', 'القسائم'], spotVoters: ['Top voters', 'أكثر المصوتين'], spotTools: ['Race settings', 'إعدادات السباق'] };
  var NLM = { merchants: { food: ['Restaurants', 'المطاعم'], grocery: ['Stores', 'المتاجر'], pets: ['Pet stores & vets', 'متاجر وعيادات'] }, products: { food: ['Menu items', 'قوائم الطعام'], grocery: ['Catalog', 'الكتالوج'], pets: ['Pet products', 'منتجات الحيوانات'] } };
  var KIND = { spotVotes: 'live', spotReviews: 'alert', spotSubmissions: 'warn', spotPrizes: 'live', orders: 'live', tickets: 'alert', merchants: 'warn', products: 'warn', payouts: 'warn' };
  var CC = ['#134E4A', '#1EF2A0', '#1D706A', '#7ECAC3', '#D5E0DD'];

  var T = {
    en: { adminSub: 'Admin', liveOps: 'Live ops dashboard', moduleLbl: 'Module', quickSearch: 'Quick search', search: 'Search', morning: 'Good morning', afternoon: 'Good afternoon', evening: 'Good evening', openLive: 'Open live ops', hToday: 'Orders today', hRev: 'Revenue today', hEta: 'Avg. delivery', hOn: 'Couriers online', dailyOrders: 'Daily orders', last30: 'Last 30 days', attention: 'Needs attention', recentOrders: 'Latest orders', viewAll: 'View all', byModule: 'Orders by module', byCategory: 'Orders by category', orders30: 'orders · 30d', orders30s: 'Orders · 30d', liveNowS: 'Active now', noResults: 'No results match your filters', prev: 'Previous', next: 'Next', of: 'of', showing: 'Showing', edit: 'Edit', del: 'Delete', confirmDel: 'Confirm delete', cancel: 'Cancel', save: 'Save changes', create: 'Create', newX: 'New', saveChanges: 'Save settings', discard: 'Discard', unsaved: 'Unsaved changes', saved: 'Settings saved', replyPh: 'Write a reply to the customer…', send: 'Send reply', replySent: 'Reply sent', yes: 'Yes', no: 'No', deleted: 'Deleted', savedRow: 'Saved', created: 'Created', required: 'is required', kRev: 'Revenue · 30 days', kOrd: 'Orders · 30 days', kCust: 'New customers · 30 days', kMerch: 'Active merchants', newStores: 'new this month', pendingStores: 'Merchants awaiting approval', openTickets: 'Open support tickets', refunds: 'Refund requests', pendingPay: 'Payout requests to review', outStock: 'Items out of stock', all: 'All', date: 'Date', zone: 'Zone', merchant: 'Merchant', anyTime: 'Any time', today: 'Today', d7: 'Last 7 days', d30: 'Last 30 days', custom: 'Custom range', from: 'From', to: 'To', allZones: 'All', allMerchants: 'All', zonesN: 'zones', clear: 'Clear filters', exportCsv: 'Export CSV', exported: 'Exported', rows: 'rows', results: 'results', cmdPh: 'Search pages, orders, stores, customers…', cmdHint: '↑ ↓ to move · Enter to open · Esc to close · ⌘K anywhere', goTo: 'Go to page', noMatch: 'No matches', sections: 'Page', switchTo: 'Switch module', heroAll: 'Here is how Waddy is doing across Maadi today.', heroMod: 'Showing only {m} — switch modules from the sidebar.', liveNew: 'new order just came in', cairo: 'Cairo time', locksIn: 'This week locks', dd: 'days', hh: 'hours', mm: 'min', ss: 'sec', sVotes: 'Votes this week', sPlaces: 'Places in race', sGap: 'Lead over #2', liveBoard: "This week's race", viewBoard: 'All places', lastDraw: 'Last draw', champion: 'Champion', pool: 'Draw pool', vouchers: 'Vouchers', poolNote: 'Voters of the champion, minus flagged votes and anyone who won in the last {c} days. {w} picked at random.', pushNote: 'Every entrant got the same “see if the claw picked you” push, so the claw reveals the result.', noDraw: 'No week has been crowned yet.', vActive: 'Active', vRedeemed: 'Redeemed', vExpired: 'Expired', vRate: 'Redemption rate', unusedNote: '{n} vouchers are still active. Each expires {v} days after the draw. No reissue, no extension.', moderation: 'Moderation queue', modReviews: 'Flagged reviews', modSubs: 'Pending submissions', modVotes: 'Flagged votes', jobs: 'Scheduled jobs', nextRun: 'Next:', runNow: 'Run now', hourly: 'Hourly', onDemand: 'On demand', jobClose: 'Crown last week, run the draw, push all entrants', jobFinal: 'Push every scope where #1 leads #2 by {g} or less', jobExpire: 'Expire old vouchers, remind when under 24 h', jobCache: 'Forget leaderboard and top-voter caches', gapPush: 'Gap is {g}: final-hours push fires {when}', gapNoPush: 'Gap is {g}: no final-hours push', health: 'Health checks', hTie: 'Tie-break has no data', hTieOk: 'Tie-break has data', hTieNow: 'Places in the top 3 are tied right now.', hTieD: 'Ties sort by votes_avg_rating, and no vote this week carries a rating (reviews moved to place_reviews on 09-03), so row order picks the champion.', hTieOkD: 'Votes this week carry ratings, so ties sort by average rating.', hMal: 'Malformed periods', hMalD: '{n} rows in history ({p}). Vouchers on these periods can’t be replayed in the claw.', hMalOk: 'Every period in the Hall of Fame is a valid ISO week.', hEn: 'Race pushes are English-only', hEnD: 'Lead-change and final-hours text is hard-coded in PlacePushService.', hClock: 'App countdown uses device time', hClockD: 'The server locks on Cairo time, so phones in another timezone show a different deadline.', rule1: '1 vote per user per week', rWinners: '{n} winners', rCool: '{n}-day cooldown', rValid: '{n}-day vouchers', uniform: 'Uniform odds', drawOff: 'Weekly draw is off', classic: 'Classic admin', openClassic: 'Open in classic', logout: 'Log out', vsPrev: 'vs previous 30 days', loadFail: 'Could not load this page.', retry: 'Retry', minS: 'min', noOrders: 'No orders yet.', late: 'Late {n}', denied: 'You do not have access to this page.' },
    ar: { adminSub: 'لوحة الإدارة', liveOps: 'لوحة العمليات المباشرة', moduleLbl: 'القسم', quickSearch: 'بحث سريع', search: 'بحث', morning: 'صباح الخير', afternoon: 'مساء الخير', evening: 'مساء الخير', openLive: 'فتح العمليات المباشرة', hToday: 'طلبات اليوم', hRev: 'إيرادات اليوم', hEta: 'متوسط التوصيل', hOn: 'مندوبين متصلين', dailyOrders: 'الطلبات اليومية', last30: 'آخر ٣٠ يوم', attention: 'يحتاج إلى متابعة', recentOrders: 'أحدث الطلبات', viewAll: 'عرض الكل', byModule: 'الطلبات حسب القسم', byCategory: 'الطلبات حسب الفئة', orders30: 'طلب · ٣٠ يوم', orders30s: 'طلبات · ٣٠ يوم', liveNowS: 'نشط الآن', noResults: 'لا توجد نتائج مطابقة', prev: 'السابق', next: 'التالي', of: 'من', showing: 'عرض', edit: 'تعديل', del: 'حذف', confirmDel: 'تأكيد الحذف', cancel: 'إلغاء', save: 'حفظ التغييرات', create: 'إنشاء', newX: 'إضافة', saveChanges: 'حفظ الإعدادات', discard: 'تجاهل', unsaved: 'تغييرات غير محفوظة', saved: 'تم حفظ الإعدادات', replyPh: 'اكتب ردًا للعميل…', send: 'إرسال الرد', replySent: 'تم إرسال الرد', yes: 'نعم', no: 'لا', deleted: 'تم الحذف', savedRow: 'تم الحفظ', created: 'تمت الإضافة', required: 'مطلوب', kRev: 'الإيرادات · ٣٠ يوم', kOrd: 'الطلبات · ٣٠ يوم', kCust: 'عملاء جدد · ٣٠ يوم', kMerch: 'المتاجر النشطة', newStores: 'جديد هذا الشهر', pendingStores: 'متاجر بانتظار الاعتماد', openTickets: 'تذاكر دعم مفتوحة', refunds: 'طلبات استرداد', pendingPay: 'طلبات سحب للمراجعة', outStock: 'منتجات نفد مخزونها', all: 'الكل', date: 'التاريخ', zone: 'المنطقة', merchant: 'المتجر', anyTime: 'أي وقت', today: 'اليوم', d7: 'آخر ٧ أيام', d30: 'آخر ٣٠ يوم', custom: 'فترة مخصصة', from: 'من', to: 'إلى', allZones: 'الكل', allMerchants: 'الكل', zonesN: 'مناطق', clear: 'مسح الفلاتر', exportCsv: 'تصدير CSV', exported: 'تم التصدير', rows: 'صف', results: 'نتيجة', cmdPh: 'ابحث في الصفحات والطلبات والمتاجر والعملاء…', cmdHint: '↑ ↓ للتنقل · Enter للفتح · Esc للإغلاق · ⌘K من أي مكان', goTo: 'الانتقال إلى الصفحة', noMatch: 'لا توجد نتائج', sections: 'صفحة', switchTo: 'تبديل القسم', heroAll: 'هذا أداء ودي في المعادي اليوم.', heroMod: 'يتم عرض {m} فقط — بدّل القسم من القائمة الجانبية.', liveNew: 'طلب جديد وصل الآن', cairo: 'بتوقيت القاهرة', locksIn: 'يُغلق الأسبوع', dd: 'يوم', hh: 'ساعة', mm: 'دقيقة', ss: 'ثانية', sVotes: 'أصوات الأسبوع', sPlaces: 'أماكن في السباق', sGap: 'الفارق عن الثاني', liveBoard: 'سباق هذا الأسبوع', viewBoard: 'كل الأماكن', lastDraw: 'آخر سحب', champion: 'البطل', pool: 'مجموعة السحب', vouchers: 'القسائم', poolNote: 'المصوتون للبطل بعد استبعاد الأصوات المُبلغ عنها ومن فاز خلال {c} يومًا. يتم اختيار {w} عشوائيًا.', pushNote: 'وصل لكل المشاركين نفس التنبيه، لتكشف الآلة النتيجة.', noDraw: 'لم يتم تتويج أي أسبوع بعد.', vActive: 'نشطة', vRedeemed: 'مستخدمة', vExpired: 'منتهية', vRate: 'نسبة الاستخدام', unusedNote: '{n} قسائم ما زالت نشطة. تنتهي كل قسيمة بعد {v} أيام من السحب. لا إعادة إصدار ولا تمديد.', moderation: 'قائمة المراجعة', modReviews: 'تقييمات مُبلغ عنها', modSubs: 'اقتراحات معلقة', modVotes: 'أصوات مُبلغ عنها', jobs: 'المهام المجدولة', nextRun: 'التالي:', runNow: 'تشغيل الآن', hourly: 'كل ساعة', onDemand: 'عند الطلب', jobClose: 'تتويج الأسبوع الماضي وإجراء السحب وتنبيه المشاركين', jobFinal: 'تنبيه حيث يتقدم الأول على الثاني بـ{g} أو أقل', jobExpire: 'إنهاء القسائم القديمة والتذكير قبل ٢٤ ساعة', jobCache: 'مسح ذاكرة لوحات الترتيب', gapPush: 'الفارق {g}: تنبيه الساعات الأخيرة {when}', gapNoPush: 'الفارق {g}: لا تنبيه للساعات الأخيرة', health: 'فحوصات', hTie: 'كسر التعادل بلا بيانات', hTieOk: 'كسر التعادل يعمل', hTieNow: 'هناك تعادل في المراكز الثلاثة الأولى الآن.', hTieD: 'التعادل يُحسم بـ votes_avg_rating، ولا يحمل أي صوت هذا الأسبوع تقييمًا، فترتيب الصفوف يحدد البطل.', hTieOkD: 'أصوات هذا الأسبوع تحمل تقييمات، فالتعادل يُحسم بمتوسط التقييم.', hMal: 'فترات غير صالحة', hMalD: '{n} صفوف في السجل ({p}). قسائم هذه الفترات لا يمكن إعادة عرضها.', hMalOk: 'كل فترات قاعة المشاهير أسابيع صحيحة.', hEn: 'تنبيهات السباق بالإنجليزية فقط', hEnD: 'نص تغيير الصدارة والساعات الأخيرة مكتوب ثابتًا في PlacePushService.', hClock: 'العد التنازلي بتوقيت الجهاز', hClockD: 'الخادم يغلق بتوقيت القاهرة، فالهواتف في مناطق زمنية أخرى تعرض موعدًا مختلفًا.', rule1: 'صوت واحد لكل مستخدم أسبوعيًا', rWinners: '{n} فائزين', rCool: 'انتظار {n} يومًا', rValid: 'قسائم {n} أيام', uniform: 'فرص متساوية', drawOff: 'السحب الأسبوعي متوقف', classic: 'اللوحة الكلاسيكية', openClassic: 'فتح في الكلاسيكية', logout: 'تسجيل الخروج', vsPrev: 'مقارنة بالـ٣٠ يومًا السابقة', loadFail: 'تعذر تحميل الصفحة.', retry: 'إعادة المحاولة', minS: 'د', noOrders: 'لا توجد طلبات بعد.', late: 'متأخر {n}', denied: 'ليست لديك صلاحية لهذه الصفحة.' }
  };

  // ------------------------------------------------------------------------ state
  var store = {
    get: function (k, d) { try { var v = localStorage.getItem('waddy.' + k); return v === null ? d : v; } catch (e) { return d; } },
    set: function (k, v) { try { localStorage.setItem('waddy.' + k, v); } catch (e) { /* private mode */ } }
  };
  function resetF() { return { q: '', fStatus: [], fZones: [], fMerchant: '', fDate: 'all', dFrom: '', dTo: '', openMenu: null, page: 0, sort: null }; }
  var S = Object.assign({
    lang: store.get('lang', BOOT.lang), mod: store.get('mod', 'all'), modOpen: false, sec: 'home', navOpen: false,
    counts: {}, home: null, homeFor: null, list: null, listBusy: false, err: null,
    sel: null, detail: null, mode: 'view', draft: {}, confirmDel: false, reply: '', acting: false, fieldErr: null,
    settings: null, sDraft: null, race: null, rDraft: null,
    toast: null, toastBad: false, cmdOpen: false, cmdQ: '', cmdIdx: 0, cmdRecords: [], pulseAt: 0
  }, resetF());
  if (MODS.indexOf(S.mod) < 0) S.mod = 'all';

  // ---------------------------------------------------------------------- helpers
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function L(p) { return Array.isArray(p) ? p[S.lang === 'ar' ? 1 : 0] : (p || ''); }
  function t() { return T[S.lang]; }
  function n(v) { return Math.round(Number(v) || 0).toLocaleString('en-US'); }
  function pad(x) { return String(x).padStart(2, '0'); }
  function fill(s, map) { return String(s).replace(/\{(\w+)\}/g, function (m, k) { return map[k] != null ? map[k] : m; }); }
  function icon(name, size, stroke) { var d = IC[name] || name; return '<svg class="ico" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" style="stroke-width:' + (stroke || 1.8) + '"><path d="' + d + '"></path></svg>'; }
  function av(str) {
    str = String(str || '?'); var h = 0;
    for (var i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) >>> 0;
    var c = AV[h % AV.length], p = str.replace(/[^\p{L}\p{N} ]/gu, '').trim().split(/\s+/);
    return { ini: (((p[0] || '?')[0] || '?') + (p[1] ? p[1][0] : ((p[0] || '')[1] || ''))).toUpperCase(), bg: c[0], fg: c[1] };
  }
  /** Photo or logo when there is one, initials underneath as the fallback. */
  var broken = {};
  function avatar(text, url, style) {
    var a = av(text), img = url && !broken[url] ? '<img src="' + esc(url) + '" alt="" loading="lazy" decoding="async">' : '';
    return '<span class="av" style="' + (style || '') + ';background:' + a.bg + ';color:' + a.fg + '">' + esc(a.ini) + img + '</span>';
  }
  function egp(v) { return S.lang === 'ar' ? n(v) + ' ج.م' : 'EGP ' + n(v); }
  function egpShort(v) {
    v = Number(v) || 0; var s = v >= 1e6 ? (v / 1e6).toFixed(2) + 'M' : v >= 1e4 ? (v / 1e3).toFixed(1) + 'K' : n(v);
    return S.lang === 'ar' ? s + ' ج.م' : 'EGP ' + s;
  }
  function fmt(v, f) {
    var ar = S.lang === 'ar';
    if (f === 'status') { var s = STATUS[v]; return s ? s[ar ? 1 : 0] : (v || '—'); }
    if (f === 'module') return L(ML[v] || ML.all);
    if (v === undefined || v === null || v === '') return '—';
    switch (f) {
      case 'egp': return egp(v);
      case 'int': return n(v);
      case 'pct': return v + '%';
      case 'rating': return v === '—' ? '—' : '★ ' + v;
      case 'stars': v = Math.max(0, Math.min(5, Number(v) || 0)); return '★★★★★'.slice(0, v) + '☆☆☆☆☆'.slice(0, 5 - v);
      case 'min': return v + (ar ? ' د' : ' min');
      case 'km': return v + (ar ? ' كم' : ' km');
      case 'bool': return v ? t().yes : t().no;
      case 'date': { var d = new Date(String(v).slice(0, 10) + 'T12:00:00'); if (isNaN(d)) return v; return d.toLocaleDateString(ar ? 'ar-EG' : 'en-GB', { day: 'numeric', month: 'short' }); }
      default: return typeof v === 'boolean' ? (v ? t().yes : t().no) : String(v);
    }
  }
  function tone(code) { var s = STATUS[code]; return TONES[s ? s[2] : 'muted']; }
  function pill(text, tn) { return '<span class="pill" style="background:' + tn[0] + ';color:' + tn[1] + '"><i></i>' + esc(text) + '</span>'; }
  /** 75 -> "1h 15m", 30 -> "30 min" (Arabic: "1س 15د", "30 د"). */
  function dur(min) {
    var ar = S.lang === 'ar', h = Math.floor(min / 60), m = min % 60;
    if (h >= 24) return Math.floor(h / 24) + (ar ? ' يوم' : 'd') + ' ' + (h % 24) + (ar ? 'س' : 'h');
    return h ? h + (ar ? 'س ' : 'h ') + m + (ar ? 'د' : 'm') : m + (ar ? ' د' : ' min');
  }
  function late(x) { return x && x.late ? '<span class="late">' + esc(fill(t().late, { n: dur(x.late) })) + '</span>' : ''; }
  function cairo(iso) { return new Date(iso).toLocaleString(S.lang === 'ar' ? 'ar-EG' : 'en-GB', { timeZone: 'Africa/Cairo', weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); }
  function secLabel(k, mod) { var m = NLM[k]; return L((m && m[mod || S.mod]) || NL[k]); }
  function navFor(mod) { return mod === 'spots' ? SPOTS_NAV : NAV; }
  function exists(k) { return k === 'home' || k === 'spotTools' || (k === 'settings' && !!BOOT.settings) || !!RES[k]; }
  function navVisible(k, mod) { return exists(k) && navFor(mod).some(function (g) { return g[1].indexOf(k) >= 0; }); }
  function today() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

  // -------------------------------------------------------------------------- api
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  function api(method, path, body) {
    var opts = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(BOOT.base + '/api/' + path, opts).then(function (r) {
      if (r.status === 401 || r.status === 419 || (r.redirected && /login/.test(r.url))) { location.reload(); throw new Error('session'); }
      return r.json().catch(function () { return {}; }).then(function (data) {
        if (!r.ok) { var e = new Error(data.message || (r.status === 403 ? t().denied : 'Error ' + r.status)); e.status = r.status; e.data = data; throw e; }
        return data;
      });
    });
  }
  function qs(params) {
    var parts = [];
    Object.keys(params).forEach(function (k) {
      var v = params[k];
      if (v === null || v === undefined || v === '') return;
      if (Array.isArray(v)) v.forEach(function (x) { parts.push(encodeURIComponent(k + '[]') + '=' + encodeURIComponent(x)); });
      else parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
    });
    return parts.length ? '?' + parts.join('&') : '';
  }
  function listParams(extra) {
    return Object.assign({ mod: S.mod, q: S.q.trim(), status: S.fStatus, zones: S.fZones, merchant: S.fMerchant, date: S.fDate === 'all' ? '' : S.fDate, from: S.fDate === 'custom' ? S.dFrom : '', to: S.fDate === 'custom' ? S.dTo : '', sort: S.sort ? S.sort.key : '', dir: S.sort ? S.sort.dir : '', page: S.page }, extra || {});
  }

  // ------------------------------------------------------------------- loaders
  var seq = 0;
  function loadCounts() {
    return api('GET', 'counts' + qs({ mod: S.mod })).then(function (c) {
      var prev = S.counts.latestOrder;
      S.counts = c;
      if (prev && c.latestOrder > prev) {
        S.pulseAt = Date.now();
        setTimeout(render, 2700);
        if (S.sec === 'home' && S.mod !== 'spots') loadHome(true);
        if (S.sec === 'orders' && !S.sel && S.page === 0) loadList(true);
      }
      render();
    }).catch(function () {});
  }
  function loadHome(quiet) {
    var mine = ++seq, key = S.mod;
    if (!quiet) { S.err = null; if (S.homeFor !== key) S.home = null; render(); }
    return api('GET', 'home' + qs({ mod: key })).then(function (d) { if (mine !== seq && !quiet) return; S.home = d; S.homeFor = key; render(); })
      .catch(function (e) { if (!quiet) { S.err = e.message; render(); } });
  }
  var listTimer = null;
  function loadList(quiet) {
    var mine = ++seq;
    if (!quiet) { S.listBusy = true; S.err = null; render(); }
    return api('GET', 'res/' + S.sec + qs(listParams())).then(function (d) {
      if (mine !== seq) return;
      S.list = d; S.page = d.page; S.listBusy = false; render();
    }).catch(function (e) { if (mine !== seq) return; S.listBusy = false; S.err = e.message; render(); });
  }
  function loadListSoon() { clearTimeout(listTimer); listTimer = setTimeout(loadList, 250); }
  function loadDetail(id) {
    return api('GET', 'res/' + S.sec + '/' + encodeURIComponent(id) + qs({ mod: S.mod })).then(function (d) {
      if (String(S.sel) !== String(id)) return;
      S.detail = d; render();
    }).catch(function (e) { flash(e.message, true); S.sel = null; render(); });
  }
  function loadSettings() {
    S.err = null;
    if (S.sec === 'spotTools') return api('GET', 'race').then(function (d) { S.race = d; S.rDraft = Object.assign({}, d.values); render(); }).catch(function (e) { S.err = e.message; render(); });
    return api('GET', 'settings').then(function (d) { S.settings = d; S.sDraft = Object.assign({}, d); render(); }).catch(function (e) { S.err = e.message; render(); });
  }
  function load() {
    if (S.sec === 'home') return loadHome();
    if (S.sec === 'settings' || S.sec === 'spotTools') return loadSettings();
    if (RES[S.sec]) return loadList();
  }

  // ---------------------------------------------------------------- navigation
  function syncHash() {
    var h = '#/' + S.mod + '/' + S.sec + (S.sel ? '/' + S.sel : '');
    if (location.hash !== h) history.replaceState(null, '', h);
  }
  function go(sec, preset, sel) {
    Object.assign(S, resetF(), { sec: sec, fStatus: preset ? [preset] : [], sel: sel || null, detail: null, mode: 'view', confirmDel: false, reply: '', modOpen: false, navOpen: false, list: null, err: null });
    syncHash(); render(); load();
    if (sel) loadDetail(sel);
    window.scrollTo(0, 0);
  }
  function setMod(m) {
    var sec = navVisible(S.sec, m) ? S.sec : 'home';
    S.mod = m; store.set('mod', m);
    Object.assign(S, { modOpen: false, fStatus: [], fMerchant: '', fZones: [], page: 0, sel: null, detail: null, sec: sec, list: null, navOpen: false });
    syncHash(); render(); load(); loadCounts();
  }
  function flash(msg, bad) {
    S.toast = msg; S.toastBad = !!bad; render();
    clearTimeout(flash._t); flash._t = setTimeout(function () { S.toast = null; render(); }, bad ? 4000 : 2400);
  }

  // ----------------------------------------------------------------------- views
  function sidebar() {
    var mod = S.mod, c = S.counts, live = Date.now() - S.pulseAt < 2600, h = '';
    h += '<aside class="side">';
    h += '<div class="brand"><span class="brand-tile"><img src="' + esc(BOOT.logo) + '" alt="Waddy"></span><span class="brand-txt"><b>Waddy</b><span>' + esc(t().adminSub) + '</span></span></div>';
    h += '<div class="modsw"><button class="u modsw-btn" data-a="toggleMod"><span class="modsw-tile" style="background:' + MC[mod] + '">' + icon(mod, 20, 1.9) + '</span><span class="modsw-txt"><small>' + esc(t().moduleLbl) + '</small><b>' + esc(L(ML[mod])) + '</b></span><span class="modsw-caret">▾</span></button>';
    if (S.modOpen) {
      h += '<div class="scrim-clear" data-a="toggleMod"></div><div class="modsw-pop">';
      MODS.forEach(function (m) {
        h += '<button class="u modsw-opt' + (m === mod ? ' on' : '') + '" data-a="setMod" data-v="' + m + '"><span class="modsw-opt-tile" style="background:' + (m === 'all' ? '#E6F4F3' : MC[m]) + '">' + icon(m, 18, 1.9) + '</span><span class="modsw-opt-txt"><b>' + esc(L(ML[m])) + '</b><small>' + esc(L(MSUB[m])) + '</small></span><span class="modsw-check">' + (m === mod ? '✓' : '') + '</span></button>';
      });
      h += '</div>';
    }
    h += '</div>';
    h += '<button class="u qs" data-a="openCmd">' + icon('search', 16, 1.9) + '<span>' + esc(t().quickSearch) + '</span><span class="kbd">⌘K</span></button>';
    h += '<nav class="nav">';
    navFor(mod).forEach(function (g) {
      var items = g[1].filter(function (k) { return navVisible(k, mod); });
      if (!items.length) return;
      h += '<div class="nav-group"><span class="nav-title">' + esc(L(g[0])) + '</span>';
      items.forEach(function (k) {
        var on = S.sec === k, b = c[k] || 0, kind = KIND[k], pulse = k === 'orders' && live;
        var cls = pulse ? 'pulse' : kind || 'live';
        h += '<button class="u nav-item' + (on ? ' on' : '') + '" data-a="go" data-v="' + k + '"' + (pulse ? ' title="' + esc(t().liveNew) + '"' : '') + '>' + icon(k, 18) + '<span class="lbl">' + esc(secLabel(k)) + '</span>';
        if (b > 0) h += '<span class="badge ' + cls + '">' + (kind === 'live' ? '<i></i>' : '') + n(b) + '</span>';
        h += '</button>';
      });
      h += '</div>';
    });
    h += '</nav>';
    h += '<a class="liveops" href="' + esc(BOOT.live) + '"><span><span class="liveops-dot"></span>' + esc(t().liveOps) + '</span><span class="liveops-go">↗</span></a>';
    h += '<div class="side-foot"><a href="' + esc(BOOT.classic) + '">' + esc(t().classic) + '</a><a href="' + esc(BOOT.logout) + '">' + esc(t().logout) + '</a></div>';
    return h + '</aside>';
  }

  function header() {
    var mod = S.mod, ar = S.lang === 'ar', title = S.sec === 'home' ? (mod === 'all' ? L(NL.home) : L(ML[mod])) : secLabel(S.sec);
    var a = av(BOOT.admin.name);
    var h = '<header class="top"><div style="display:flex;align-items:center;gap:12px"><button class="u burger" data-a="toggleNav">' + icon('menu', 20) + '</button><div class="crumbs">';
    h += '<span class="crumb"><i style="background:' + (mod === 'all' ? '#134E4A' : MC[mod]) + '"></i>' + esc(L(ML[mod])) + ' <em>/</em> ' + esc(secLabel(S.sec)) + '</span>';
    h += '<h1>' + esc(title) + '</h1></div></div><div class="top-tools">';
    h += '<button class="u hsearch" data-a="openCmd">' + icon('search', 17, 1.9) + '<span>' + esc(t().search) + '…</span><span class="kbd">⌘K</span></button>';
    if (RES.tickets) h += '<button class="u bell" data-a="goTickets">' + icon('bell', 19) + (S.counts.tickets ? '<i></i>' : '') + '</button>';
    h += '<div class="seg"><button class="u' + (!ar ? ' on' : '') + '" data-a="lang" data-v="en">EN</button><button class="u' + (ar ? ' on' : '') + '" data-a="lang" data-v="ar">عربي</button></div>';
    h += '<span class="avatar" title="' + esc(BOOT.admin.name) + '">' + esc(a.ini) + '</span>';
    return h + '</div></header>';
  }

  function failBox() {
    return '<div class="card empty-card" style="display:flex;align-items:center;gap:12px;justify-content:space-between"><span>' + esc(t().loadFail) + ' ' + esc(S.err) + '</span><button class="u chip-btn" data-a="reload">' + esc(t().retry) + '</button></div>';
  }

  // --------------------------------------------------------------- home: modules
  function spark(values, color) {
    var v = values && values.length ? values : [0, 0], lo = Math.min.apply(null, v), hi = Math.max.apply(null, v), last = v.length - 1 || 1;
    var pts = v.map(function (x, i) { return (i * 96 / last).toFixed(1) + ',' + (36 - (x - lo) / ((hi - lo) || 1) * 32).toFixed(1); }).join(' ');
    return '<svg class="spark" width="96" height="40"><polyline points="' + pts + '" style="fill:none;stroke:' + color + ';stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round"></polyline></svg>';
  }
  function change(pct) {
    if (pct === null || pct === undefined) return '<span class="kpi-chg" style="background:#F5F7F6;color:#6B7876">—</span>';
    var up = pct >= 0, tn = up ? TONES.ok : TONES.danger;
    return '<span class="kpi-chg" style="background:' + tn[0] + ';color:' + tn[1] + '">' + (up ? '↑ ' : '↓ ') + Math.abs(pct) + '%</span>';
  }
  function greeting() {
    var hr = Number(new Date().toLocaleString('en-GB', { timeZone: 'Africa/Cairo', hour: '2-digit', hour12: false }));
    var g = hr < 12 ? t().morning : hr < 17 ? t().afternoon : t().evening;
    return S.lang === 'ar' ? g + ' يا ' + BOOT.admin.first : g + ', ' + BOOT.admin.first;
  }

  function home() {
    var d = S.home, mod = S.mod, ar = S.lang === 'ar';
    if (!d || S.homeFor !== mod) return S.err ? failBox() : '<div class="loading">…</div>';
    var h = '<div class="stack">';
    // hero
    var todayStr = new Date().toLocaleDateString(ar ? 'ar-EG' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
    h += '<div class="hero"><img class="hero-mark" src="' + esc(BOOT.logo) + '" alt=""><div class="hero-l"><div class="hero-hello">';
    h += '<span class="hero-chip"><i style="background:' + (mod === 'all' ? '#134E4A' : MC[mod]) + '"></i>' + esc(L(ML[mod])) + ' · ' + esc(todayStr) + '</span>';
    h += '<b>' + esc(greeting()) + '</b><span>' + esc(mod === 'all' ? t().heroAll : fill(t().heroMod, { m: L(ML[mod]) })) + '</span></div><div class="hero-stats">';
    [[t().hToday, n(d.hero.ordersToday), '#fff'], [t().hRev, egpShort(d.hero.revenueToday), '#1EF2A0'], [t().hEta, d.hero.avgDelivery === null ? '—' : fmt(d.hero.avgDelivery, 'min'), '#fff'], [t().hOn, d.hero.couriersOnline + ' / ' + d.hero.couriersTotal, '#fff']].forEach(function (x) {
      h += '<div class="hstat"><small>' + esc(x[0]) + '</small><b style="color:' + x[2] + '">' + esc(x[1]) + '</b></div>';
    });
    h += '</div></div><a class="hero-cta" href="' + esc(BOOT.live) + '">' + esc(t().openLive) + ' <span>↗</span></a></div>';
    // module cards
    if (mod === 'all') {
      var tot = ['food', 'grocery', 'pets'].reduce(function (a, m) { return a + d.byModule[m].orders30; }, 0) || 1;
      h += '<div class="modcards">';
      ['food', 'grocery', 'pets', 'spots'].forEach(function (m) {
        var k1, k2, v1, v2, share, bar;
        if (m === 'spots') { k1 = t().sVotes; k2 = t().sPlaces; v1 = n(d.spots.votes); v2 = n(d.spots.places); share = Math.round(d.spots.weekDone * 100); bar = '#7ECAC3'; }
        else { k1 = t().orders30s; k2 = t().liveNowS; v1 = n(d.byModule[m].orders30); v2 = n(d.byModule[m].active); share = Math.round(d.byModule[m].orders30 / tot * 100); bar = m === 'grocery' ? '#0A6B47' : MODTONES[m][1]; }
        h += '<button class="u modcard" data-a="setMod" data-v="' + m + '"><div class="modcard-h"><span class="tile" style="background:' + MC[m] + '">' + icon(m, 20, 1.9) + '</span><b>' + esc(L(ML[m])) + '</b><span class="arrow">' + (ar ? '←' : '→') + '</span></div>';
        h += '<div class="modcard-k"><div><small>' + esc(k1) + '</small><b>' + v1 + '</b></div><div><small>' + esc(k2) + '</small><b>' + v2 + '</b></div></div>';
        h += '<div class="bar6"><div style="width:' + share + '%;background:' + bar + '"></div></div></button>';
      });
      h += '</div>';
    }
    // KPIs
    var k = d.kpis;
    h += '<div class="kpis">';
    [
      [t().kRev, egpShort(k.revenue.value), change(k.revenue.change), 'trend', '#134E4A', '#1EF2A0', '#134E4A', k.revenue.spark],
      [t().kOrd, n(k.orders.value), change(k.orders.change), 'orders', '#E6FCF3', '#0A6B47', '#1EF2A0', k.orders.spark],
      [t().kCust, n(k.customers.value), change(k.customers.change), 'customers', '#FFF8E1', '#7A5600', '#FFBE0B', k.customers.spark],
      [mod === 'all' ? t().kMerch : secLabel('merchants'), n(k.merchants.value), '<span class="kpi-chg" style="background:#E6FCF3;color:#0A6B47">↑ ' + n(k.merchants.new) + ' ' + esc(t().newStores) + '</span>', 'merchants', '#E6F4F3', '#134E4A', '#1D706A', k.merchants.spark]
    ].forEach(function (x) {
      h += '<div class="card kpi"><div class="kpi-h"><span class="tile" style="background:' + x[4] + ';color:' + x[5] + '">' + icon(x[3], 20) + '</span><span>' + esc(x[0]) + '</span></div>';
      h += '<div class="kpi-b"><div class="kpi-v"><b>' + esc(x[1]) + '</b>' + x[2] + '</div>' + spark(x[7], x[6]) + '</div></div>';
    });
    h += '</div>';
    // daily chart + donut
    var mx = Math.max.apply(null, d.daily.map(function (x) { return x.v; })) || 1, sum = d.daily.reduce(function (a, x) { return a + x.v; }, 0);
    h += '<div class="row"><div class="card card-pad chart" style="flex:2 1 540px"><div class="chart-h"><div><b>' + esc(t().dailyOrders) + '</b><span class="meta">' + esc(t().last30) + '</span></div><div><strong>' + n(sum) + '</strong>';
    if (d.dailyChange !== null) h += '<span style="font-size:12px;font-weight:500;color:' + (d.dailyChange >= 0 ? '#0A6B47' : '#C23B3B') + '">' + (d.dailyChange >= 0 ? '↑ ' : '↓ ') + Math.abs(d.dailyChange) + '%</span>';
    h += '</div></div><div class="bars">';
    [0, 0.5, 1].forEach(function (f) { h += '<div class="ytick" style="bottom:' + (f * 90) + '%"><span>' + n(mx * f) + '</span></div>'; });
    d.daily.forEach(function (x, i) {
      var day = new Date(x.date + 'T12:00:00').getDay(), last = i === d.daily.length - 1;
      var color = last ? '#1EF2A0' : (day === 5 || day === 6) ? '#1D706A' : '#134E4A';
      var tip = new Date(x.date + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + ' · ' + n(x.v);
      h += '<div class="dbar" title="' + esc(tip) + '" style="height:' + Math.max(1, Math.round(x.v / mx * 90)) + '%;background:' + color + '"></div>';
    });
    h += '</div><div class="xlabels">';
    d.daily.forEach(function (x, i) { h += '<span>' + (i % 6 === 5 ? esc(new Date(x.date + 'T12:00:00').toLocaleDateString(ar ? 'ar-EG' : 'en-GB', { day: 'numeric', month: 'short' })) : '') + '</span>'; });
    h += '</div></div>';
    var parts, colors, dTitle;
    if (mod === 'all') { parts = ['food', 'grocery', 'pets'].map(function (m) { return [L(ML[m]), d.byModule[m].orders30]; }); colors = [MC.food, MC.grocery, MC.pets]; dTitle = t().byModule; }
    else { parts = d.categories.map(function (c) { return [c.name, c.v]; }); colors = CC; dTitle = t().byCategory; }
    var ptot = parts.reduce(function (a, p) { return a + p[1]; }, 0), acc = 0;
    var grad = ptot ? 'conic-gradient(' + parts.map(function (p, i) { var a0 = acc; acc += p[1] / ptot * 100; return colors[i % colors.length] + ' ' + a0.toFixed(2) + '% ' + acc.toFixed(2) + '%'; }).join(',') + ')' : '#EEF2F1';
    h += '<div class="card card-pad donut-card" style="flex:1 1 300px"><span class="card-title">' + esc(dTitle) + '</span><div class="donut" style="background:' + grad + '"><div><b>' + n(ptot) + '</b><small>' + esc(t().orders30) + '</small></div></div><div class="legend">';
    parts.forEach(function (p, i) { h += '<div><i style="background:' + colors[i % colors.length] + '"></i><span>' + esc(p[0]) + '</span><em>' + (ptot ? Math.round(p[1] / ptot * 100) : 0) + '%</em></div>'; });
    h += '</div></div></div>';
    // attention + latest
    var ATT = { pending: [t().pendingStores, 'warn', 'merchants', 'pending', 'merchants'], tickets: [t().openTickets, 'warn', 'tickets', 'open', 'tickets'], refunds: [t().refunds, 'danger', 'orders', 'refundreq', 'alert'], payouts: [t().pendingPay, 'warn', 'payouts', 'pending', 'payouts'], stock: [t().outStock, 'danger', 'products', 'out', 'products'] };
    h += '<div class="row"><div class="card lcard" style="flex:1 1 320px"><span class="lcard-title">' + esc(t().attention) + '</span>';
    d.attention.forEach(function (a) {
      var x = ATT[a.key]; if (!x || !RES[x[2]]) return;
      var tn = TONES[a.value ? x[1] : 'muted'];
      h += '<button class="u att" data-a="goPreset" data-v="' + x[2] + '" data-p="' + x[3] + '"><span class="tile" style="background:' + tn[0] + ';color:' + tn[1] + '">' + icon(x[4], 18) + '</span><span>' + esc(x[0]) + '</span><b style="color:' + tn[1] + '">' + n(a.value) + '</b><span class="arrow">' + (ar ? '←' : '→') + '</span></button>';
    });
    h += '</div><div class="card" style="flex:2 1 540px;overflow:hidden"><div class="recent-h"><span class="card-title">' + esc(t().recentOrders) + '<span class="live-dot"></span></span>' + (RES.orders ? '<button class="u chip-btn" data-a="go" data-v="orders">' + esc(t().viewAll) + '</button>' : '') + '</div>';
    if (!d.latest.length) h += '<div class="empty-card">' + esc(t().noOrders) + '</div>';
    d.latest.forEach(function (o) {
      var tn = tone(o.status);
      h += '<div class="recent" data-a="openRecord" data-v="orders" data-id="' + o.id + '"><div class="who">' + avatar(o.customer, o.avatars && o.avatars.customer, 'width:36px;height:36px;border-radius:50%;font-size:12px') + '<div><b>' + esc(o.customer) + '</b><small><i style="background:' + (MC[o.module] || '#C5CFCD') + '"></i>' + esc(o.merchant) + '</small></div></div>';
      h += '<span class="meta">#' + esc(o.id) + '</span><span style="font-size:14px;font-weight:500">' + esc(fmt(o.total, 'egp')) + '</span>' + '<span style="display:flex;flex-direction:column;align-items:flex-end;gap:3px">' + pill(fmt(o.status, 'status'), tn) + late(o) + '</span></div>';
    });
    h += '</div></div></div>';
    return h;
  }

  // ------------------------------------------------------------------ spots home
  function spotsHome() {
    var d = S.home, ar = S.lang === 'ar', TN = TONES;
    if (!d || S.homeFor !== 'spots') return S.err ? failBox() : '<div class="loading">…</div>';
    var left = Math.max(0, new Date(d.lock) - Date.now()), secs = Math.floor(left / 1000);
    var push = d.totalVotes > 0 && d.gap <= d.finalHoursGap;
    var h = '<div class="stack">';
    h += '<div class="hero spots"><img class="hero-mark" src="' + esc(BOOT.logo) + '" alt=""><div class="hero-l">';
    h += '<span class="hero-chip"><i style="background:#7ECAC3"></i>' + esc(d.period) + ' · ' + esc(t().cairo) + '</span>';
    h += '<div style="display:flex;flex-direction:column;gap:10px"><span class="lockline">' + esc(t().locksIn) + ' <b>' + esc(cairo(d.lock)) + '</b></span><div class="cd">';
    [[Math.floor(secs / 86400), t().dd], [Math.floor(secs % 86400 / 3600), t().hh], [Math.floor(secs % 3600 / 60), t().mm], [secs % 60, t().ss]].forEach(function (c) { h += '<div><b>' + pad(c[0]) + '</b><small>' + esc(c[1]) + '</small></div>'; });
    h += '</div></div><div class="rules">';
    var rules = [t().rule1];
    if (d.rules.enabled) rules.push(fill(t().rWinners, { n: d.rules.winners }), fill(t().rCool, { n: d.rules.cooldown }), fill(t().rValid, { n: d.rules.validity }), t().uniform);
    else rules.push(t().drawOff);
    rules.forEach(function (r) { h += '<span>' + esc(r) + '</span>'; });
    h += '</div></div><div class="hero-r">';
    [[t().sVotes, n(d.totalVotes)], [t().sPlaces, n(d.places)], [t().sGap, String(d.gap)]].forEach(function (x) { h += '<div class="hstat"><small>' + esc(x[0]) + '</small><b>' + esc(x[1]) + '</b></div>'; });
    h += '</div></div>';

    // board + last draw
    var top = d.board.length ? d.board[0].votes || 1 : 1, gtn = push ? TN.warn : TN.muted;
    var finalAt = new Date(new Date(d.lock).getTime() - 3 * 3600e3).toISOString();
    h += '<div class="row"><div class="card card-pad" style="flex:2 1 540px"><div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap"><span class="card-title">' + esc(t().liveBoard) + '<span class="live-dot"></span></span>';
    h += '<span class="gapchip" style="background:' + gtn[0] + ';color:' + gtn[1] + '">' + esc(fill(push ? t().gapPush : t().gapNoPush, { g: d.gap, when: cairo(finalAt) })) + '</span></div><div>';
    d.board.forEach(function (p) {
      var lead = p.rank === 1 && p.votes > 0, dl = p.prevRank ? p.prevRank - p.rank : 0;
      h += '<div class="board-row" data-a="openRecord" data-v="spotPlaces" data-id="' + p.id + '"><span class="rank" style="background:' + (lead ? '#1EF2A0' : '#F3F6F5') + ';color:' + (lead ? '#134E4A' : '#3D4744') + '">' + (p.tied ? 'T' + p.rank : pad(p.rank)) + '</span>';
      h += '<div class="nm"><b>' + esc(p.name) + '</b><small>' + esc([p.zone, p.category].filter(Boolean).join(' · ')) + '</small></div>';
      h += '<div class="bar8"><div style="width:' + Math.round(p.votes / top * 100) + '%;background:' + (lead ? '#1EF2A0' : '#134E4A') + '"></div></div>';
      h += '<span class="vt">' + n(p.votes) + '</span><span class="dl" style="color:' + (dl > 0 ? '#0A6B47' : dl < 0 ? '#C23B3B' : '#9EAAA8') + '">' + (dl > 0 ? '▲' + dl : dl < 0 ? '▼' + (-dl) : '–') + '</span></div>';
    });
    h += '</div>' + (RES.spotPlaces ? '<button class="u chip-btn" data-a="go" data-v="spotPlaces">' + esc(t().viewBoard) + ' ' + (ar ? '←' : '→') + '</button>' : '') + '</div>';
    var w = d.draw;
    h += '<div class="card card-pad" style="flex:1 1 320px"><span class="meta">' + esc(t().lastDraw) + ' · ' + esc(w ? w.period : '—') + '</span>';
    if (!w) h += '<span class="note">' + esc(t().noDraw) + '</span>';
    else {
      h += '<div class="champ" data-a="openRecord" data-v="spotWinners" data-id="' + w.id + '"><span class="tile">' + icon('spotWinners', 22) + '</span><div><b>' + esc(w.place) + '</b><span class="meta">' + esc(t().champion) + (w.zone ? ' · ' + esc(w.zone) : '') + '</span></div></div>';
      h += '<div class="mini2"><div class="mini"><small>' + esc(t().pool) + '</small><b>' + n(w.pool) + '</b></div><div class="mini"><small>' + esc(t().vouchers) + '</small><b>' + n(w.winners.length) + '</b></div></div>';
      h += '<span class="note">' + esc(fill(t().poolNote, { c: d.rules.cooldown, w: d.rules.winners })) + '</span><div>';
      w.winners.forEach(function (p) {
        var tn = tone(p.status);
        h += '<div class="winner" data-a="openRecord" data-v="spotPrizes" data-id="' + p.id + '">' + avatar(p.name, p.img, 'width:30px;height:30px;border-radius:50%;font-size:11px') + '<span class="nm">' + esc(p.name) + '</span><span class="code">' + esc(p.code) + '</span><span class="pill" style="font-size:11px;padding:3px 8px;background:' + tn[0] + ';color:' + tn[1] + '">' + esc(fmt(p.status, 'status')) + '</span></div>';
      });
      h += '</div><span class="note box">' + esc(t().pushNote) + '</span>';
    }
    h += '</div></div>';

    // vouchers / moderation / jobs
    var v = d.vouchers;
    h += '<div class="row"><div class="card card-pad" style="flex:1 1 300px"><span class="card-title">' + esc(t().vouchers) + '</span><div class="vstats">';
    [[t().vActive, v.active, TN.info, 'active'], [t().vRedeemed, v.redeemed, TN.ok, 'redeemed'], [t().vExpired, v.expired, TN.muted, 'expired']].forEach(function (x) {
      h += '<button class="u vstat" data-a="goPreset" data-v="spotPrizes" data-p="' + x[3] + '" style="background:' + x[2][0] + ';color:' + x[2][1] + '"><b>' + n(x[1]) + '</b><small>' + esc(x[0]) + '</small></button>';
    });
    var rate = Math.round(v.redeemed / ((v.redeemed + v.expired) || 1) * 100) + '%';
    h += '</div><div class="rate"><div><span>' + esc(t().vRate) + '</span><b>' + rate + '</b></div><div class="bar8"><div style="width:' + rate + ';background:#1EF2A0"></div></div></div>';
    h += '<span class="note">' + esc(fill(t().unusedNote, { n: v.active, v: d.rules.validity })) + '</span></div>';
    h += '<div class="card lcard" style="flex:1 1 300px"><span class="lcard-title" style="padding-bottom:8px">' + esc(t().moderation) + '</span>';
    [[t().modReviews, d.queue.reviews, 'danger', 'spotReviews', 'flagged'], [t().modSubs, d.queue.submissions, 'warn', 'spotSubmissions', 'pending'], [t().modVotes, d.queue.votes, 'danger', 'spotVotes', 'flagged']].forEach(function (x) {
      var tn = TN[x[1] ? x[2] : 'muted'];
      h += '<button class="u att" data-a="goPreset" data-v="' + x[3] + '" data-p="' + x[4] + '"><span class="tile" style="background:' + tn[0] + ';color:' + tn[1] + '">' + icon(x[3], 18) + '</span><span>' + esc(x[0]) + '</span><b style="color:' + tn[1] + '">' + n(x[1]) + '</b><span class="arrow">' + (ar ? '←' : '→') + '</span></button>';
    });
    h += '</div><div class="card card-pad" style="flex:1.4 1 400px;padding-bottom:8px;gap:4px"><span class="card-title" style="padding-bottom:8px">' + esc(t().jobs) + '</span>' + jobsList(d.jobs, d.finalHoursGap) + '</div></div>';

    // health
    var hl = d.health, mal = hl.malformed.length;
    var checks = [
      [hl.tieBreakEmpty ? t().hTie : t().hTieOk, (d.tieTop ? t().hTieNow + ' ' : '') + (hl.tieBreakEmpty ? t().hTieD : t().hTieOkD), 'alert', hl.tieBreakEmpty ? TN.warn : TN.ok, 'spotPlaces', ''],
      [t().hMal, mal ? fill(t().hMalD, { n: mal, p: hl.malformed.join(', ') }) : t().hMalOk, 'spotWinners', mal ? TN.danger : TN.ok, 'spotWinners', mal ? 'malformed' : ''],
      [t().hEn, t().hEnD, 'tickets', TN.info, '', ''],
      [t().hClock, t().hClockD, 'calendar', TN.info, '', '']
    ];
    h += '<div class="card card-pad"><span class="card-title">' + esc(t().health) + '</span><div class="health">';
    checks.forEach(function (c) {
      h += '<button class="u hcheck"' + (c[4] ? ' data-a="goPreset" data-v="' + c[4] + '" data-p="' + c[5] + '"' : '') + '><span class="tile" style="background:' + c[3][0] + ';color:' + c[3][1] + '">' + icon(c[2], 17) + '</span><span><b>' + esc(c[0]) + '</b><small>' + esc(c[1]) + '</small></span></button>';
    });
    h += '</div></div></div>';
    return h;
  }
  function jobsList(jobs, gap) {
    var h = '<div>';
    jobs.forEach(function (j) {
      var desc = fill(t()[j.desc] || '', { g: gap }), when = j.when === 'hourly' ? t().hourly : j.when === 'onDemand' ? t().onDemand : j.when;
      h += '<div class="job"><div><code>' + esc(j.name) + '</code><span class="meta">' + esc(desc) + '</span></div><span class="job-when">' + esc(when) + '</span><span class="job-next">' + (j.next ? esc(t().nextRun + ' ' + cairo(j.next)) : '—') + '</span><button class="u run" data-a="runJob" data-v="' + esc(j.name) + '">' + esc(t().runNow) + '</button></div>';
    });
    return h + '</div>';
  }

  // ------------------------------------------------------------------- list page
  function cols(R) { return R.columns.filter(function (c) { return !(c.allOnly && S.mod !== 'all'); }); }
  function listPage() {
    var R = RES[S.sec], d = S.list, ar = S.lang === 'ar';
    if (!d) return S.err ? failBox() : '<div class="loading">…</div>';
    var h = '<div class="stack" style="gap:16px">';
    // status chips
    var codes = ['all'].concat(R.filter.options.filter(function (o) { return d.counts[o] || S.fStatus.indexOf(o) >= 0; }));
    h += '<div class="chips">';
    codes.forEach(function (code) {
      var on = code === 'all' ? S.fStatus.length === 0 : S.fStatus.indexOf(code) >= 0, tn = code === 'all' ? TONES.info : tone(code);
      var count = code === 'all' ? d.all : (d.counts[code] || 0);
      h += '<button class="u chip' + (code === 'all' ? ' all' : '') + (on ? ' on' : '') + '" data-a="toggleStatus" data-v="' + code + '"><span><i style="background:' + tn[1] + '"></i>' + esc(code === 'all' ? t().all : fmt(code, 'status')) + '</span><b>' + n(count) + '</b><span class="chip-box">' + (on && code !== 'all' ? '✓' : '') + '</span></button>';
    });
    h += '</div>';
    // toolbar
    h += '<div class="card' + (S.listBusy ? ' busy' : '') + '"><div class="toolbar">';
    h += '<label class="tsearch">' + icon('search', 17, 1.9) + '<input data-input="q" value="' + esc(S.q) + '" placeholder="' + esc(t().search + ' ' + secLabel(S.sec).toLowerCase() + '…') + '"></label>';
    var menus = [];
    var DATES = [['all', t().anyTime], ['today', t().today], ['7d', t().d7], ['30d', t().d30], ['custom', t().custom]];
    if (R.dateKey) {
      var dl = S.fDate === 'custom' ? fmt(S.dFrom, 'date') + ' – ' + fmt(S.dTo, 'date') : DATES.filter(function (x) { return x[0] === S.fDate; })[0][1];
      menus.push({ id: 'date', icon: 'calendar', label: t().date, value: dl, active: S.fDate !== 'all', opts: DATES.map(function (x) { return { label: x[1], on: S.fDate === x[0], a: 'setDate', v: x[0] }; }), isDate: S.fDate === 'custom' });
    }
    if (R.zoneKey && d.zones.length) {
      var zl = S.fZones.length === 0 ? t().allZones : S.fZones.length === 1 ? (d.zones.filter(function (z) { return String(z.id) === String(S.fZones[0]); })[0] || {}).name : S.fZones.length + ' ' + t().zonesN;
      menus.push({ id: 'zone', icon: 'zones', label: t().zone, value: zl, active: S.fZones.length > 0, multi: true, opts: d.zones.map(function (z) { return { label: z.name, on: S.fZones.indexOf(String(z.id)) >= 0, a: 'toggleZone', v: z.id, count: z.count }; }) });
    }
    if (R.merchantKey) {
      var mm = d.merchants.filter(function (m) { return String(m.id) === String(S.fMerchant); })[0];
      menus.push({ id: 'merchant', icon: 'store', label: R.merchantLabel ? L(R.merchantLabel) : t().merchant, value: mm ? mm.name : t().allMerchants, active: !!S.fMerchant,
        opts: [{ label: t().allMerchants, on: !S.fMerchant, a: 'setMerchant', v: '' }].concat(d.merchants.map(function (m) { return { label: m.name, on: String(S.fMerchant) === String(m.id), a: 'setMerchant', v: m.id, count: m.count }; })) });
    }
    menus.forEach(function (m) {
      h += '<div class="menu"><button class="u menu-btn' + (m.active ? ' on' : '') + '" data-a="toggleMenu" data-v="' + m.id + '">' + icon(m.icon, 15, 1.9) + '<span>' + esc(m.label) + '</span><b>' + esc(m.value) + '</b><em>▾</em></button>';
      if (S.openMenu === m.id) {
        h += '<div class="pop">';
        m.opts.forEach(function (o) { h += '<button class="u opt' + (o.on ? ' on' : '') + '" data-a="' + o.a + '" data-v="' + esc(o.v) + '"><span class="opt-box' + (m.multi ? ' multi' : '') + '">' + (o.on ? '✓' : '') + '</span><span>' + esc(o.label) + '</span><small>' + (o.count == null ? '' : n(o.count)) + '</small></button>'; });
        if (m.isDate) h += '<div class="range"><label>' + esc(t().from) + '<input type="date" data-change="dFrom" value="' + esc(S.dFrom) + '"></label><label>' + esc(t().to) + '<input type="date" data-change="dTo" value="' + esc(S.dTo) + '"></label></div>';
        h += '</div>';
      }
      h += '</div>';
    });
    h += '<div class="grow"></div>';
    h += '<button class="u btn-out" data-a="exportCsv">' + icon('download', 15, 1.9) + esc(t().exportCsv) + '</button>';
    if (R.newable) h += '<button class="u btn-new" data-a="onNew"><span>+</span>' + esc(L(R.one)) + '</button>';
    else if (R.newUrl) h += '<a class="btn-new" href="' + esc(R.newUrl) + '" target="_blank" rel="noopener" title="' + esc(t().openClassic) + '"><span>+</span>' + esc(L(R.one)) + ' ↗</a>';
    h += '</div>';
    // active pills
    var pills = [];
    S.fStatus.forEach(function (c) { pills.push([fmt(c, 'status'), 'toggleStatus', c]); });
    if (S.fDate !== 'all') pills.push([t().date + ': ' + menus.filter(function (m) { return m.id === 'date'; })[0].value, 'clearDate', '']);
    S.fZones.forEach(function (z) { var zz = d.zones.filter(function (x) { return String(x.id) === String(z); })[0]; pills.push([zz ? zz.name : z, 'toggleZone', z]); });
    if (S.fMerchant) { var mx = d.merchants.filter(function (m) { return String(m.id) === String(S.fMerchant); })[0]; pills.push([mx ? mx.name : S.fMerchant, 'setMerchant', '']); }
    if (S.q.trim()) pills.push(['“' + S.q.trim() + '”', 'clearQ', '']);
    if (pills.length) {
      h += '<div class="pills">';
      pills.forEach(function (p) { h += '<button class="u fpill" data-a="' + p[1] + '" data-v="' + esc(p[2]) + '">' + esc(p[0]) + '<span>×</span></button>'; });
      h += '<button class="u clear" data-a="clearAll">' + esc(t().clear) + '</button><span class="meta">· ' + n(d.total) + ' ' + esc(t().results) + '</span></div>';
    }
    // table
    var C = cols(R), grid = C.map(function (c) { return 'minmax(0,' + (c.w || '1fr') + ')'; }).join(' ');
    h += '<div class="tablewrap"><div class="table"><div class="thead" style="grid-template-columns:' + grid + '">';
    C.forEach(function (c) {
      var on = S.sort && c.sort && S.sort.key === c.sort;
      h += '<span class="th' + (c.sort ? ' sortable' : '') + '"' + (c.sort ? ' data-a="sort" data-v="' + c.key + '"' : '') + '>' + esc(L(c.label)) + '<span>' + (on ? (S.sort.dir > 0 ? '↑' : '↓') : '') + '</span></span>';
    });
    h += '</div>';
    var square = !!R.square;
    d.rows.forEach(function (x) {
      h += '<div class="trow' + (String(S.sel) === String(x.id) ? ' sel' : '') + '" style="grid-template-columns:' + grid + '" data-a="openRow" data-v="' + esc(x.id) + '">';
      C.forEach(function (c) {
        var v = x[c.key];
        if (c.fmt === 'status') { h += '<div class="cell"><div>' + pill(fmt(v, 'status'), tone(v)) + late(x) + '</div></div>'; return; }
        if (c.fmt === 'module') { h += '<div class="cell">' + pill(fmt(v, 'module'), MODTONES[v] || MODTONES.all) + '</div>'; return; }
        var pics = x.avatars || {}, hasAv = c.key in pics || (c.strong && ['name', 'customer', 'payee', 'code', 'pet', 'merchant', 'voter', 'place'].indexOf(c.key) >= 0) || c.key === 'customer' || c.key === 'payee';
        h += '<div class="cell">';
        if (hasAv) h += avatar(v, pics[c.key], 'border-radius:' + (square && c.key !== 'customer' && c.key !== 'voter' ? '12px' : '50%'));
        var text = c.key === 'id' && typeof v === 'number' ? '#' + v : fmt(v, c.fmt);
        h += '<div><span class="t' + (c.mono ? ' mono' : '') + '" style="font-weight:' + (c.strong ? 500 : 400) + ';color:' + (c.fmt === 'stars' ? '#C79200' : c.muted ? '#6B7876' : '#1A1F1E') + '">' + esc(text) + '</span>';
        if (c.sub) h += '<span class="s">' + esc(fmt(x[c.sub])) + '</span>';
        h += '</div></div>';
      });
      h += '</div>';
    });
    if (!d.rows.length) h += '<div class="empty">' + esc(t().noResults) + '<button class="u" data-a="clearAll">' + esc(t().clear) + '</button></div>';
    h += '</div></div>';
    var from = d.page * 12 + 1, to = Math.min(d.total, d.page * 12 + 12);
    h += '<div class="pager"><span>' + (d.total ? esc(t().showing + ' ' + from + '–' + to + ' ' + t().of + ' ' + n(d.total)) : '') + '</span><div>';
    h += '<button class="u" data-a="page" data-v="-1"' + (d.page > 0 ? '' : ' disabled') + '>' + esc(t().prev) + '</button>';
    h += '<button class="u" data-a="page" data-v="1"' + (d.page < d.pages - 1 ? '' : ' disabled') + '>' + esc(t().next) + '</button></div></div>';
    h += '</div></div>';
    return h;
  }

  // ------------------------------------------------------------------- settings
  function fieldInput(f, value, scope, opts) {
    var type = f.type || 'text', ro = f.readonly || (f.perModule && S.mod === 'all'), h = '';
    var label = L(f.label) + (f.required && !ro ? ' *' : '');
    h += '<label class="field" style="grid-column:' + (f.wide ? '1 / -1' : 'auto') + '"><span>' + esc(label) + '</span>';
    if (ro) h += '<span class="ro">' + esc(f.show && opts && opts.row ? fmt(opts.row[f.show], f.fmt) : fmt(value, f.fmt || (f.status ? 'status' : f.mod ? 'module' : null))) + '</span>';
    else if (type === 'toggle') h += '<button type="button" class="u toggle' + (value ? ' on' : '') + '" data-a="toggleField" data-scope="' + scope + '" data-v="' + f.key + '"><span></span></button>';
    else if (type === 'select') {
      var options = (opts && opts.options && opts.options[f.key]) || (f.options || []).map(function (o) { return { value: o, label: f.status ? fmt(o, 'status') : f.mod ? fmt(o, 'module') : o }; });
      h += '<select class="inp" data-change="field" data-scope="' + scope + '" data-k="' + f.key + '">';
      var found = false;
      options.forEach(function (o) { var sel = String(o.value) === String(value == null ? '' : value); found = found || sel; h += '<option value="' + esc(o.value) + '"' + (sel ? ' selected' : '') + '>' + esc(o.label) + '</option>'; });
      if (!found && value != null && value !== '') h += '<option value="' + esc(value) + '" selected>' + esc(value) + '</option>';
      h += '</select>';
    } else if (type === 'textarea') h += '<textarea class="inp" rows="3" data-input="field" data-scope="' + scope + '" data-k="' + f.key + '">' + esc(value) + '</textarea>';
    else h += '<input class="inp' + (opts && opts.err === f.key ? ' err' : '') + '" type="' + (type === 'number' ? 'number' : type) + '" step="any" data-input="field" data-scope="' + scope + '" data-k="' + f.key + '" value="' + esc(value == null ? '' : value) + '">';
    return h + '</label>';
  }
  function settingsPage() {
    var race = S.sec === 'spotTools', groups = race ? BOOT.race.groups : BOOT.settings.groups;
    var saved = race ? (S.race && S.race.values) : S.settings, draft = race ? S.rDraft : S.sDraft;
    if (!saved) return S.err ? failBox() : '<div class="loading">…</div>';
    var h = '<div class="settings">';
    if (race) h += '<div class="card card-pad" style="padding:22px 24px 10px;gap:4px"><span class="card-title">' + esc(t().jobs) + '</span><span class="meta" style="padding-bottom:8px">' + esc(t().cairo) + '</span>' + jobsList(S.race.jobs, saved.finalHoursGap) + '</div>';
    groups.forEach(function (g) {
      h += '<div class="card sgroup"><div class="sgroup-h"><span class="tile">' + icon(g.icon, 18) + '</span><b>' + esc(L(g.title)) + '</b></div><div class="sfields">';
      g.fields.forEach(function (f) { h += fieldInput(f, draft[f.key], race ? 'race' : 'settings'); });
      h += '</div></div>';
    });
    var dirty = JSON.stringify(draft) !== JSON.stringify(saved);
    h += '<div class="save-row"><button class="u btn-primary" data-a="saveSettings">' + esc(t().saveChanges) + '</button><button class="u btn-ghost" data-a="resetSettings">' + esc(t().discard) + '</button>' + (dirty ? '<span class="dirty">' + esc(t().unsaved) + '</span>' : '') + '</div>';
    return h + '</div>';
  }

  // -------------------------------------------------------------------- drawer
  function drawer() {
    var R = RES[S.sec];
    if (!R || !(S.mode === 'new' || S.sel)) return '';
    var d = S.detail, isForm = S.mode !== 'view';
    var h = '<div class="scrim" data-a="closeDrawer"></div><aside class="drawer">';
    if (!d) return h + '<div class="loading" style="margin:auto">…</div></aside>';
    var row = d.row, cur = isForm ? S.draft : row, st = cur && cur[R.statusKey];
    var title = S.mode === 'new' ? (cur[R.titleKey] || L(R.one)) : (R.titleKey === 'id' ? '#' + row.id : String(row[R.titleKey] == null ? '—' : row[R.titleKey]));
    var headText = S.mode === 'new' ? L(R.one) : String(row[R.titleKey]);
    var modTxt = cur && R.modKey && cur[R.modKey] ? ' · ' + fmt(cur[R.modKey], 'module') : '';
    h += '<div class="dhead">' + avatar(headText, S.mode === 'new' ? null : row.img) + '<div><small>' + esc((S.mode === 'new' ? t().newX + ' · ' + L(R.one) : L(R.one) + ' · ' + row.id) + modTxt) + '</small><b>' + esc(title) + '</b>' + (st ? pill(fmt(st, 'status'), tone(st)) : '') + '</div><button class="u dclose" data-a="closeDrawer">×</button></div>';
    if (!isForm) {
      h += '<div class="dbody">';
      if (d.actions.length) {
        h += '<div class="dacts">';
        d.actions.forEach(function (x) { h += '<button class="u dact ' + x.tone + '" data-a="rowAction" data-v="' + x.key + '"' + (S.acting ? ' disabled' : '') + '>' + esc(L(x.label)) + '</button>'; });
        h += '</div>';
      }
      var photo = row.photoUrl || (R.photo ? row.img : null);
      if (photo && !broken[photo]) h += '<a class="dphoto" href="' + esc(photo) + '" target="_blank" rel="noopener"><img src="' + esc(photo) + '" alt=""></a>';
      h += '<div class="dgrid">';
      R.fields.forEach(function (f) {
        var v = f.show ? row[f.show] : row[f.key], text = esc(f.show ? fmt(v, f.fmt) : fmt(v, f.fmt || (f.status ? 'status' : f.mod ? 'module' : null)));
        var target = f.link && RES[f.link[0]] && row[f.link[1]];
        if (target) text = '<button class="u dlink" data-a="openRecord" data-v="' + f.link[0] + '" data-id="' + esc(target) + '">' + text + ' ' + (S.lang === 'ar' ? '←' : '→') + '</button>';
        else if (f.tel && v) text = '<a class="dlink" href="tel:' + esc(String(v).replace(/[^+\d]/g, '')) + '">' + text + '</a>';
        h += '<div class="dfield" style="grid-column:' + (f.wide ? '1 / -1' : 'auto') + '"><small>' + esc(L(f.label)) + '</small><span>' + text + '</span>' + (f.status ? late(row) : '') + '</div>';
      });
      h += '</div>';
      if (R.lines && d.lines.length) {
        h += '<div class="lines"><b>' + esc(L(R.lines.title)) + '</b>';
        if (R.lines.thread) {
          h += '<div class="thread">';
          d.lines.forEach(function (m) { h += '<div class="msg' + (m[3] ? ' mine' : '') + '"><div><b>' + esc(m[0]) + '</b><span>' + esc(m[2]) + '</span></div><span>' + esc(m[1]) + '</span></div>'; });
          h += '</div>';
        } else {
          h += '<div class="plain">';
          d.lines.forEach(function (m) { h += '<div><span>' + esc(m[0]) + '</span><span>' + esc(m[1]) + '</span><span>' + esc(R.lines.fmt ? fmt(m[2], R.lines.fmt) : m[2]) + '</span></div>'; });
          h += '</div>';
        }
        h += '</div>';
      }
      if (R.reply && row.status !== 'resolved') h += '<div class="replybox"><textarea rows="3" data-input="reply" placeholder="' + esc(t().replyPh) + '">' + esc(S.reply) + '</textarea><button class="u" data-a="sendReply"' + (S.acting ? ' disabled' : '') + '>' + esc(t().send) + '</button></div>';
      h += '</div><div class="dfoot">';
      if (R.editable) h += '<button class="u main-btn" data-a="onEdit">' + esc(t().edit) + '</button>';
      if (d.classicUrl) h += '<a class="' + (R.editable ? 'side-btn' : 'main-btn') + '" href="' + esc(d.classicUrl) + '" target="_blank" rel="noopener">' + esc(t().openClassic) + ' ↗</a>';
      if (R.deletable) h += '<button class="u del' + (S.confirmDel ? ' confirm' : '') + '" data-a="onDelete">' + esc(S.confirmDel ? t().confirmDel : t().del) + '</button>';
      h += '</div>';
    } else {
      h += '<div class="dbody form"><div class="fgrid">';
      R.fields.forEach(function (f) { h += fieldInput(f, S.draft[f.key], 'draft', { options: d.options, row: S.draft, err: S.fieldErr }); });
      h += '</div></div><div class="dfoot"><button class="u main-btn" data-a="onSave"' + (S.acting ? ' disabled' : '') + '>' + esc(S.mode === 'new' ? t().create : t().save) + '</button><button class="u side-btn" data-a="onCancel">' + esc(t().cancel) + '</button></div>';
    }
    return h + '</aside>';
  }

  // ------------------------------------------------------------------------ ⌘K
  var cmdList = [];
  function cmdResults() {
    var qq = S.cmdQ.trim().toLowerCase(), res = [], mod = S.mod;
    navFor(mod).forEach(function (g) {
      g[1].filter(function (k) { return navVisible(k, mod); }).forEach(function (k) {
        var label = secLabel(k);
        if (!qq || label.toLowerCase().indexOf(qq) >= 0 || L(NL[k]).toLowerCase().indexOf(qq) >= 0) res.push({ kind: t().sections, title: label, sub: t().goTo, icon: k, tint: '#E6F4F3', ink: '#134E4A', run: function () { S.cmdOpen = false; go(k); } });
      });
    });
    if (qq) MODS.forEach(function (m) { var label = L(ML[m]); if (label.toLowerCase().indexOf(qq) >= 0) res.push({ kind: t().switchTo, title: label, sub: L(MSUB[m]), icon: m, tint: m === 'all' ? '#E6F4F3' : MC[m], ink: '#134E4A', run: function () { S.cmdOpen = false; setMod(m); } }); });
    if (qq.length >= 2) S.cmdRecords.forEach(function (r) {
      var R = RES[r.sec]; if (!R) return; var a = av(r.title);
      res.push({ img: r.img, kind: L(NL[r.sec]), title: r.title, sub: r.id + (r.sub ? ' · ' + fmt(r.sub) : '') + (r.module && r.module !== 'all' && r.module !== 'other' ? ' · ' + fmt(r.module, 'module') : ''), icon: r.sec, tint: a.bg, ink: a.fg, run: function () { S.cmdOpen = false; openRecord(r.sec, r.id, r.module); } });
    });
    cmdList = res.slice(0, qq ? 12 : 9);
    return cmdList;
  }
  function cmd() {
    if (!S.cmdOpen) return '';
    var list = cmdResults(), h = '<div class="cscrim" data-a="closeCmd"></div><div class="cmd"><div class="cmd-in">' + icon('search', 20, 1.9) + '<input id="cmdq" data-input="cmdQ" value="' + esc(S.cmdQ) + '" placeholder="' + esc(t().cmdPh) + '" autocomplete="off"><span>Esc</span></div><div class="cmd-list">';
    list.forEach(function (r, i) {
      h += '<button class="u cres' + (i === S.cmdIdx ? ' on' : '') + '" data-a="cmdRun" data-v="' + i + '" data-hover="' + i + '">' + (r.img && !broken[r.img] ? avatar(r.title, r.img, 'width:36px;height:36px;border-radius:11px;font-size:12px') : '<span class="tile" style="background:' + r.tint + ';color:' + r.ink + '">' + icon(r.icon, 18) + '</span>') + '<span><b>' + esc(r.title) + '</b><small>' + esc(r.sub) + '</small></span><em>' + esc(r.kind) + '</em></button>';
    });
    if (!list.length) h += '<div class="cmd-empty">' + esc(t().noMatch) + '</div>';
    return h + '</div><div class="cmd-hint">' + esc(t().cmdHint) + '</div></div>';
  }
  function openRecord(sec, id, module) {
    var R = RES[sec]; if (!R) return;
    var spot = sec.indexOf('spot') === 0, mod = S.mod;
    var keep = spot ? mod === 'spots' : (!R.modKey || mod === 'all' || !module || module === mod || module === 'all');
    var target = keep ? mod : (spot ? 'spots' : 'all');
    if (!navVisible(sec, target)) target = spot ? 'spots' : 'all';
    if (target !== S.mod) { S.mod = target; store.set('mod', target); loadCounts(); }
    go(sec, null, id);
  }

  // -------------------------------------------------------------------- render
  function view() {
    var page;
    if (S.sec === 'home') page = S.mod === 'spots' ? spotsHome() : home();
    else if (S.sec === 'settings' || S.sec === 'spotTools') page = settingsPage();
    else if (RES[S.sec]) page = listPage();
    else page = '<div class="card empty-card">' + esc(t().denied) + '</div>';
    var h = '<div class="shell' + (S.navOpen ? ' nav-open' : '') + '">' + sidebar() + '<main class="main">' + header() + page + '</main>';
    if (S.openMenu) h += '<div style="position:fixed;inset:0;z-index:50" data-a="closeMenus"></div>';
    if (S.navOpen) h += '<div class="scrim" style="z-index:25" data-a="toggleNav"></div>';
    h += drawer() + cmd();
    if (S.toast) h += '<div class="toast' + (S.toastBad ? ' bad' : '') + '"><span>' + (S.toastBad ? '!' : '✓') + '</span>' + esc(S.toast) + '</div>';
    return h + '</div>';
  }

  /** Patches `from` to look like `to`, keeping focus, scroll and typed values. */
  function morph(from, to) {
    if (from.nodeType !== to.nodeType || from.nodeName !== to.nodeName) { from.replaceWith(to); return; }
    if (from.nodeType === 3 || from.nodeType === 8) { if (from.nodeValue !== to.nodeValue) from.nodeValue = to.nodeValue; return; }
    var i, attr;
    for (i = from.attributes.length - 1; i >= 0; i--) { attr = from.attributes[i].name; if (!to.hasAttribute(attr)) from.removeAttribute(attr); }
    for (i = 0; i < to.attributes.length; i++) { attr = to.attributes[i]; if (from.getAttribute(attr.name) !== attr.value) from.setAttribute(attr.name, attr.value); }
    var tag = from.nodeName;
    if (tag === 'INPUT') { if (document.activeElement !== from && from.value !== (to.getAttribute('value') || '')) from.value = to.getAttribute('value') || ''; return; }
    if (tag === 'TEXTAREA') { if (document.activeElement !== from && from.value !== to.value) from.value = to.value; return; }
    var a = from.childNodes, b = Array.prototype.slice.call(to.childNodes), nb = b.length;
    for (i = 0; i < nb; i++) {
      if (i < a.length) morph(a[i], b[i]);
      else from.appendChild(b[i]);
    }
    while (a.length > nb) from.removeChild(from.lastChild);
    if (tag === 'SELECT') { var opt = to.querySelector('option[selected]'); if (opt && from.value !== opt.value) from.value = opt.value; }
  }
  var root = document.getElementById('app'), queued = false;
  function render() {
    if (queued) return; queued = true;
    requestAnimationFrame(function () {
      queued = false;
      document.documentElement.lang = S.lang; document.documentElement.dir = S.lang === 'ar' ? 'rtl' : 'ltr';
      var tpl = document.createElement('template'); tpl.innerHTML = view();
      var next = tpl.content.firstElementChild;
      if (!root.firstElementChild || root.firstElementChild.className.indexOf('shell') < 0) { root.innerHTML = ''; root.appendChild(next); }
      else morph(root.firstElementChild, next);
      if (S.cmdOpen) { var ci = document.getElementById('cmdq'); if (ci && document.activeElement !== ci && !render.cmdFocused) { ci.focus(); render.cmdFocused = true; } }
      else render.cmdFocused = false;
    });
  }

  // ------------------------------------------------------------------- actions
  function exportCsv() {
    var R = RES[S.sec], C = cols(R);
    api('GET', 'res/' + S.sec + '/export' + qs(listParams({ page: '' }))).then(function (rows) {
      var e = function (v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; };
      var head = C.map(function (c) { return e(L(c.label)); }).join(',');
      var body = rows.map(function (x) { return C.map(function (c) { return e(c.fmt === 'status' || c.fmt === 'module' ? fmt(x[c.key], c.fmt) : x[c.key]); }).join(','); });
      var blob = new Blob(['﻿' + [head].concat(body).join('\n')], { type: 'text/csv;charset=utf-8' });
      var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'waddy-' + S.sec + '-' + S.mod + '-' + today() + '.csv';
      document.body.appendChild(link); link.click(); link.remove();
      flash(t().exported + ' · ' + rows.length + ' ' + t().rows);
    }).catch(function (err) { flash(err.message, true); });
  }
  function afterChange(msg) {
    flash(msg);
    loadList(true); loadCounts();
    if (S.sel) loadDetail(S.sel);
  }

  var A = {
    toggleMod: function () { S.modOpen = !S.modOpen; },
    setMod: function (v) { setMod(v); },
    toggleNav: function () { S.navOpen = !S.navOpen; },
    openCmd: function () { S.cmdOpen = true; S.cmdQ = ''; S.cmdIdx = 0; S.cmdRecords = []; },
    closeCmd: function () { S.cmdOpen = false; },
    cmdRun: function (v) { var r = cmdList[+v]; if (r) r.run(); },
    go: function (v) { go(v); },
    goPreset: function (v, el) { go(v, el.getAttribute('data-p') || null); },
    goTickets: function () { go('tickets', 'open'); },
    openRecord: function (v, el) { openRecord(v, el.getAttribute('data-id')); },
    lang: function (v) { S.lang = v; store.set('lang', v); },
    reload: function () { load(); },
    closeMenus: function () { S.openMenu = null; },
    toggleMenu: function (v) { S.openMenu = S.openMenu === v ? null : v; },
    toggleStatus: function (v) {
      S.page = 0;
      if (v === 'all') S.fStatus = [];
      else S.fStatus = S.fStatus.indexOf(v) >= 0 ? S.fStatus.filter(function (x) { return x !== v; }) : S.fStatus.concat([v]);
      loadList();
    },
    setDate: function (v) { S.fDate = v; S.page = 0; S.openMenu = v === 'custom' ? 'date' : null; if (v !== 'custom' || (S.dFrom || S.dTo)) loadList(); },
    clearDate: function () { S.fDate = 'all'; S.dFrom = ''; S.dTo = ''; loadList(); },
    toggleZone: function (v) { v = String(v); S.page = 0; S.fZones = S.fZones.indexOf(v) >= 0 ? S.fZones.filter(function (x) { return x !== v; }) : S.fZones.concat([v]); loadList(); },
    setMerchant: function (v) { S.fMerchant = v; S.page = 0; S.openMenu = null; loadList(); },
    clearQ: function () { S.q = ''; loadList(); },
    clearAll: function () { Object.assign(S, resetF()); loadList(); },
    sort: function (v) {
      var c = RES[S.sec].columns.filter(function (x) { return x.key === v; })[0];
      S.sort = S.sort && S.sort.key === c.sort ? { key: c.sort, dir: -S.sort.dir } : { key: c.sort, dir: c.fmt && c.fmt !== 'status' ? -1 : 1 };
      S.page = 0; loadList();
    },
    page: function (v) { S.page = Math.max(0, S.page + Number(v)); loadList(); },
    exportCsv: exportCsv,
    openRow: function (v) { S.sel = v; S.detail = null; S.mode = 'view'; S.confirmDel = false; S.reply = ''; S.openMenu = null; syncHash(); loadDetail(v); },
    closeDrawer: function () { S.sel = null; S.detail = null; S.mode = 'view'; S.confirmDel = false; S.fieldErr = null; syncHash(); },
    onNew: function () {
      S.sel = null; S.mode = 'new'; S.detail = null; S.fieldErr = null; render();
      api('GET', 'res/' + S.sec + '/new' + qs({ mod: S.mod })).then(function (d) { S.draft = d.row; S.detail = { row: d.row, options: d.options, actions: [], lines: [] }; render(); })
        .catch(function (e) { S.mode = 'view'; flash(e.message, true); });
    },
    onEdit: function () { S.mode = 'edit'; S.draft = Object.assign({}, S.detail.row); S.confirmDel = false; S.fieldErr = null; },
    onCancel: function () { if (S.mode === 'new') { S.mode = 'view'; S.detail = null; } else S.mode = 'view'; S.fieldErr = null; },
    onSave: function () {
      var R = RES[S.sec], miss = R.fields.filter(function (f) { return f.required && !f.readonly && !String(S.draft[f.key] == null ? '' : S.draft[f.key]).trim(); })[0];
      if (miss) { S.fieldErr = miss.key; flash(L(miss.label) + ' ' + t().required, true); return; }
      S.acting = true;
      var isNew = S.mode === 'new';
      api(isNew ? 'POST' : 'PUT', 'res/' + S.sec + (isNew ? '' : '/' + encodeURIComponent(S.sel)), { data: S.draft, mod: S.mod }).then(function (r) {
        S.acting = false; S.fieldErr = null;
        if (isNew) { S.mode = 'view'; S.detail = null; Object.assign(S, resetF()); flash(r.message || t().created); loadList(); loadCounts(); }
        else { S.mode = 'view'; afterChange(r.message || t().savedRow); }
      }).catch(function (e) { S.acting = false; flash(e.message, true); });
    },
    onDelete: function () {
      if (!S.confirmDel) { S.confirmDel = true; return; }
      api('DELETE', 'res/' + S.sec + '/' + encodeURIComponent(S.sel) + qs({ mod: S.mod })).then(function (r) {
        S.sel = null; S.detail = null; S.confirmDel = false; syncHash(); flash(r.message || t().deleted); loadList(true); loadCounts();
      }).catch(function (e) { S.confirmDel = false; flash(e.message, true); });
    },
    rowAction: function (v) {
      S.acting = true;
      api('POST', 'res/' + S.sec + '/' + encodeURIComponent(S.sel) + '/' + v, { mod: S.mod }).then(function (r) { S.acting = false; afterChange(r.message || t().savedRow); })
        .catch(function (e) { S.acting = false; flash(e.message, true); });
    },
    sendReply: function () {
      var txt = S.reply.trim(); if (!txt) return;
      S.acting = true;
      api('POST', 'tickets/' + S.sel + '/reply', { text: txt }).then(function () { S.acting = false; S.reply = ''; afterChange(t().replySent); })
        .catch(function (e) { S.acting = false; flash(e.message, true); });
    },
    toggleField: function (v, el) {
      var scope = el.getAttribute('data-scope'), target = scope === 'race' ? S.rDraft : scope === 'settings' ? S.sDraft : S.draft;
      target[v] = !target[v];
    },
    saveSettings: function () {
      var race = S.sec === 'spotTools';
      api('POST', race ? 'race' : 'settings', { data: race ? S.rDraft : S.sDraft }).then(function (r) {
        flash(race ? t().saved : (r.message || t().saved));
        loadSettings();
      }).catch(function (e) { flash(e.message, true); });
    },
    resetSettings: function () { if (S.sec === 'spotTools') S.rDraft = Object.assign({}, S.race.values); else S.sDraft = Object.assign({}, S.settings); },
    runJob: function (v) {
      api('POST', 'jobs', { job: v }).then(function (r) { flash(r.message); if (S.sec === 'home') loadHome(true); else loadSettings(); })
        .catch(function (e) { flash(e.message, true); });
    }
  };

  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-a]');
    if (!el || !root.contains(el)) return;
    if (el.tagName === 'A') return;
    var fn = A[el.getAttribute('data-a')];
    if (!fn) return;
    if (el.tagName === 'LABEL' || el.closest('label.field')) e.preventDefault();
    fn(el.getAttribute('data-v'), el, e);
    render();
  });
  document.addEventListener('error', function (e) {
    var el = e.target;
    if (el.tagName === 'IMG' && el.parentNode && (el.parentNode.classList.contains('av') || el.parentNode.classList.contains('dphoto'))) {
      broken[el.getAttribute('src')] = true;
      (el.parentNode.classList.contains('dphoto') ? el.parentNode : el).remove();
    }
  }, true);
  document.addEventListener('mouseover', function (e) {
    var el = e.target.closest('[data-hover]');
    if (el && S.cmdOpen && +el.getAttribute('data-hover') !== S.cmdIdx) { S.cmdIdx = +el.getAttribute('data-hover'); render(); }
  });
  var cmdTimer = null;
  document.addEventListener('input', function (e) {
    var el = e.target, k = el.getAttribute('data-input');
    if (!k) return;
    if (k === 'q') { S.q = el.value; S.page = 0; loadListSoon(); }
    else if (k === 'reply') S.reply = el.value;
    else if (k === 'cmdQ') {
      S.cmdQ = el.value; S.cmdIdx = 0;
      clearTimeout(cmdTimer);
      var q = el.value.trim();
      if (q.length >= 2) cmdTimer = setTimeout(function () { api('GET', 'search' + qs({ q: q, mod: S.mod })).then(function (r) { if (S.cmdQ.trim() === q) { S.cmdRecords = r; render(); } }).catch(function () {}); }, 180);
      else S.cmdRecords = [];
    } else if (k === 'field') setField(el);
    render();
  });
  document.addEventListener('change', function (e) {
    var el = e.target, k = el.getAttribute('data-change');
    if (!k) return;
    if (k === 'dFrom' || k === 'dTo') { S[k] = el.value; S.page = 0; loadList(); }
    else if (k === 'field') setField(el);
    render();
  });
  function setField(el) {
    var scope = el.getAttribute('data-scope'), key = el.getAttribute('data-k'), target = scope === 'race' ? S.rDraft : scope === 'settings' ? S.sDraft : S.draft;
    target[key] = el.value;
    if (S.fieldErr === key) S.fieldErr = null;
  }
  window.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); if (S.cmdOpen) S.cmdOpen = false; else A.openCmd(); render(); return; }
    if (!S.cmdOpen) {
      if (e.key === 'Escape') { if (S.openMenu || S.modOpen) { S.openMenu = null; S.modOpen = false; } else if (S.sel || S.mode === 'new') A.closeDrawer(); render(); }
      return;
    }
    var count = cmdList.length;
    if (e.key === 'Escape') S.cmdOpen = false;
    else if (e.key === 'ArrowDown') { e.preventDefault(); S.cmdIdx = Math.min(S.cmdIdx + 1, count - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); S.cmdIdx = Math.max(S.cmdIdx - 1, 0); }
    else if (e.key === 'Enter') { var r = cmdList[S.cmdIdx]; if (r) r.run(); }
    else return;
    render();
  });

  // ---------------------------------------------------------------------- boot
  function readHash() {
    var p = location.hash.replace(/^#\/?/, '').split('/');
    return { mod: MODS.indexOf(p[0]) >= 0 ? p[0] : S.mod, sec: p[1] || 'home', sel: p[2] ? decodeURIComponent(p[2]) : null };
  }
  (function fromHash() {
    var h = readHash();
    S.mod = h.mod;
    if (navVisible(h.sec, S.mod)) S.sec = h.sec;
    if (h.sel && RES[S.sec]) S.sel = h.sel;
  })();
  // Back/forward and pasted links: follow the hash when it differs from the page shown.
  window.addEventListener('hashchange', function () {
    var h = readHash();
    if (h.mod === S.mod && h.sec === S.sec && String(h.sel) === String(S.sel)) return;
    if (h.mod !== S.mod) { S.mod = h.mod; store.set('mod', h.mod); loadCounts(); }
    if (h.sec === S.sec && RES[S.sec]) { if (h.sel) A.openRow(h.sel); else A.closeDrawer(); render(); return; }
    go(navVisible(h.sec, S.mod) ? h.sec : 'home', null, h.sel && RES[h.sec] ? h.sel : null);
  });
  render(); load(); loadCounts();
  if (S.sel) loadDetail(S.sel);
  setInterval(function () { if (!document.hidden) loadCounts(); }, 15000);
  setInterval(function () { if (S.mod === 'spots' && S.sec === 'home' && !S.cmdOpen) render(); }, 1000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) loadCounts(); });
})();
