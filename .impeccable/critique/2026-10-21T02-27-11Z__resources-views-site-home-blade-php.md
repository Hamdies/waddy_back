---
target: waddyapp.com homepage
total_score: 18
max_score: 32
na_heuristics: 7,10
p0_count: 1
p1_count: 2
timestamp: 2026-10-21T02-27-11Z
slug: resources-views-site-home-blade-php
---
# Waddy homepage critique — 18/32 (Acceptable)
Heuristics: 1=2 visibility (no nav active state) · 2=3 match (mixed Arabic/Western numerals) · 3=2 control (marquee/drift unpausable, lang not in URL) · 4=2 consistency ("Get the app" -> #top) · 5=3 · 6=2 (no nav <880px) · 7=n/a · 8=3 (template sameness, ~6 perpetual animations) · 9=1 (Android "coming soon" dead end) · 10=n/a
## Priority issues
- [P0] Android dead end at hero: Play badge is a disabled span, no waitlist/alternative. clarify+onboard
- [P1] Single download point: header CTA href #top (layout.blade.php:22), hidden <=420px (site.css:150); no closing CTA (.closing CSS unused site.css:136), no footer store badges (.foot .store unused :161). layout
- [P1] Trust gap: no fee/ETA/coverage/rating/social proof; shipping policy not linked from home. clarify
- [P2] Template sameness: 5 bands same split+phone+card recipe; Spots deserves own layout. bolder/layout
- [P2] Focus ring mint on mint bands (site.css:16). audit
## Detector
Templates: 0 (blade not parsed, parser modules missing — unverified). site.css: 8 warnings — bounce-easing x6 (176,193,224,234,240,245), side-tab 261, layout-transition min-height 199. No lazy loading on 31 imgs, ~1MB images. No aria-current.
## Minor
lang="en" dir="ltr" always server-side (English flash for AR users), no hreflang; language pill <44px; second ripeness card hidden <=420px; chips look tappable but aren't; title is just "Waddy"; footer h2->h4 skip; "three errands" vs five features.
