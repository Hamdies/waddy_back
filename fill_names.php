<?php
/**
 * One-off backfill: Arabic item names.
 *
 * Run from the project root:  php fill_names.php
 *
 * Writes the `ar` name translation row for each item, and an `en` row from
 * the existing name column so both language tabs are populated. The name
 * column itself (the default-language value) is left untouched.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = [
  220 => 'طماطم طازة ١ كجم',
  221 => 'موز ١ كجم',
  222 => 'لبن جهينة كامل الدسم ١ لتر',
  223 => 'جبنة بيضاء دومتي ٥٠٠ جم',
  224 => 'بيض بلدي ٣٠ بيضة',
  225 => 'عيش بلدي ١٠ أرغفة',
  226 => 'زيت عباد شمس كريستال ١ لتر',
  227 => 'أرز مصري ٥ كجم',
  228 => 'نسكافيه كلاسيك ٢٠٠ جم',
  229 => 'مياه بركة الطبيعية ١.٥ لتر',
  230 => 'خيار طازة ١ كجم',
  231 => 'برتقال مصري ٢ كجم',
  232 => 'لبن رايب المراعي ١ لتر',
  233 => 'صدور فراخ طازة ١ كجم',
  234 => 'جبنة بيضاء مصري ٢٥٠ جم',
  235 => 'شاي ليبتون العلامة الصفراء ١٠٠ فتلة',
  236 => 'شيبسي بالملح ١٧٠ جم',
  237 => 'كرواسون مولتو ٦ قطع',
  238 => 'منظف بيرسيل ٣ كجم',
  239 => 'مناديل فاين ٥٥٠ منديل',
  240 => 'ريب آي مستورد ٥٠٠ جم',
  241 => 'فيليه سلمون طازة ٥٠٠ جم',
  242 => 'سبانخ بيبي أورجانيك ٢٠٠ جم',
  243 => 'جبنة بري فرنسي ٢٠٠ جم',
  244 => 'زيت زيتون بكر ممتاز ٥٠٠ مل',
  245 => 'عيش ساوردو',
  246 => 'جمبري جامبو ٥٠٠ جم',
  247 => 'شوكولاتة بلجيكي دارك ١٠٠ جم',
  248 => 'مكسرات مشكلة محمصة ٥٠٠ جم',
  249 => 'زبادي يوناني ٥٠٠ جم',
  250 => 'قاروص مشوي',
  251 => 'كاليماري مقلي',
  252 => 'جمبري جامبو مشوي',
  253 => 'طاجن سيفود',
  254 => 'بربوني مشوي',
  255 => 'أرز صيادية',
  256 => 'سلطة طحينة',
  257 => 'عصير ليمون طازة',
  258 => 'تشيز برجر كلاسيك',
  259 => 'دبل بيكون برجر',
  260 => 'مشروم سويس برجر',
  261 => 'كريسبي تشيكن برجر',
  262 => 'بطاطس ترافل',
  263 => 'أجنحة فراخ بافلو',
  264 => 'سلطة سيزر',
  265 => 'ميلك شيك أوريو',
  266 => 'كشري كلاسيك',
  267 => 'ساندويتش طعمية',
  268 => 'فول إسكندراني',
  269 => 'حواوشي لحمة',
  270 => 'فطير مشلتت',
  271 => 'مشويات مصري مشكلة',
  272 => 'مشروب سوبيا',
  273 => 'أم علي',
  274 => 'بيتزا مارجريتا',
  275 => 'بيتزا بيبروني',
  276 => 'بيتزا كواترو فورماجي',
  277 => 'بيني أرابياتا',
  278 => 'فيتوتشيني ألفريدو',
  279 => 'لازانيا بولونيز',
  280 => 'عيش بالثوم',
  281 => 'تيراميسو',
  282 => 'ملوخية بالأرانب',
  283 => 'حمام محشي',
  284 => 'طبق مشويات مشكلة',
  285 => 'كفتة كباب',
  286 => 'ورق عنب محشي',
  287 => 'مازة مصري مشكلة',
  288 => 'أرز بالشعرية',
];
$n = 0; $skipped = [];
foreach ($rows as $id => $ar) {
    $item = App\Models\Item::withoutGlobalScope(App\Scopes\StoreScope::class)->find($id);
    if (!$item) { $skipped[] = $id; continue; }
    App\Models\Translation::updateOrCreate(
        ['translationable_type'=>'App\Models\Item','translationable_id'=>$id,'locale'=>'ar','key'=>'name'],
        ['value'=>$ar]
    );
    App\Models\Translation::updateOrCreate(
        ['translationable_type'=>'App\Models\Item','translationable_id'=>$id,'locale'=>'en','key'=>'name'],
        ['value'=>$item->getRawOriginal('name')]
    );
    $n++;
}
echo "updated: $n".PHP_EOL;
echo "skipped (not found): ".(count($skipped) ? implode(',', $skipped) : 'none').PHP_EOL;
