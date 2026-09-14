<?php
/**
 * One-off backfill: item descriptions (EN + AR).
 *
 * Run from the project root:  php fill_descriptions.php
 * Boots the framework itself, so it does not depend on tinker's parser.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = [
  220 => ['Vine-ripened tomatoes, picked fresh and packed the same day.','طماطم طازة متشمسة، متقطفة ومتعبأة في نفس اليوم.'],
  221 => ['Sweet ripe bananas, perfect for smoothies or a quick snack.','موز ناضج وحلو، مثالي للسموذي أو سناك سريع.'],
  222 => ['Juhayna full cream milk, rich and fresh in a 1L carton.','لبن جهينة كامل الدسم، غني وطازج في عبوة 1 لتر.'],
  223 => ['Creamy Domty white cheese, an Egyptian breakfast staple.','جبنة بيضاء دومتي كريمي، أساسية على سفرة الفطار المصري.'],
  224 => ['Thirty fresh baladi eggs, straight from local farms.','٣٠ بيضة بلدي طازة، جاية مباشرة من المزارع المحلية.'],
  225 => ['Warm baladi bread, baked the traditional Egyptian way.','عيش بلدي سخن، متخبوز على الطريقة المصرية الأصلية.'],
  226 => ['Crystal sunflower oil, light and clean for everyday cooking.','زيت عباد شمس كريستال، خفيف ونقي لطبخ كل يوم.'],
  227 => ['Premium Egyptian rice, 5kg of short fluffy grains.','أرز مصري فاخر، ٥ كيلو حبة قصيرة ومفلفلة.'],
  228 => ['Nescafe Classic, bold instant coffee to start the day.','نسكافيه كلاسيك، قهوة سريعة التحضير بطعم قوي لبداية اليوم.'],
  229 => ['Baraka natural mineral water, crisp and refreshing.','مياه بركة معدنية طبيعية، منعشة ونقية.'],
  230 => ['Crunchy fresh cucumbers, cool and crisp by the kilo.','خيار طازة مقرمش، بارد ومنعش بالكيلو.'],
  231 => ['Juicy Egyptian oranges, sweet and full of sunshine.','برتقال مصري مليان عصير، حلو ومشبع بالشمس.'],
  232 => ['Almarai laban, smooth and tangy, chilled and ready.','لبن رايب المراعي، ناعم وحامض خفيف، بارد وجاهز.'],
  233 => ['Fresh chicken breast, lean and tender, cleaned and ready to cook.','صدور فراخ طازة، خالية من الدهون وطرية، منضفة وجاهزة للطبخ.'],
  234 => ['Classic Egyptian white cheese, salty and satisfying.','جبنة بيضاء مصري كلاسيك، مالحة ومشبعة.'],
  235 => ['Lipton Yellow Label, 100 bags of proper strong tea.','ليبتون العلامة الصفراء، ١٠٠ فتلة شاي تقيل وأصلي.'],
  236 => ['Chipsy salted crisps, the crunch everyone reaches for.','شيبسي بالملح، المقرمش اللي الكل بيمد إيده عليه.'],
  237 => ['Molto croissants, six soft buttery pastries.','كرواسون مولتو، ٦ قطع هشة ومليانة زبدة.'],
  238 => ['Persil detergent, 3kg of deep-clean laundry power.','منظف بيرسيل، ٣ كيلو قوة تنظيف عميق للغسيل.'],
  239 => ['Fine tissues, 550 soft sheets for every room.','مناديل فاين، ٥٥٠ منديل ناعم لكل غرفة.'],
  240 => ['Imported ribeye, beautifully marbled and cut for the grill.','ريب آي مستورد، مرخم بشكل مثالي ومقطع للشوي.'],
  241 => ['Fresh salmon fillet, pink, buttery and boneless.','فيليه سلمون طازة، وردي وطري وخالي من العظم.'],
  242 => ['Organic baby spinach, tender leaves washed and ready.','سبانخ بيبي أورجانيك، ورق طري مغسول وجاهز.'],
  243 => ['French brie, soft-ripened and creamy to the centre.','جبنة بري فرنسي، طرية وكريمي لحد النص.'],
  244 => ['Extra virgin olive oil, cold-pressed and full of flavour.','زيت زيتون بكر ممتاز، معصور على البارد ومليان طعم.'],
  245 => ['Sourdough loaf, slow-fermented with a crackling crust.','عيش ساوردو، متخمر ببطء وقشرة مقرمشة.'],
  246 => ['Jumbo shrimp, plump and sweet, cleaned and deveined.','جمبري جامبو، ممتلئ وحلو، منضف وجاهز.'],
  247 => ['Belgian dark chocolate, intense and smooth.','شوكولاتة بلجيكي دارك، قوية وناعمة.'],
  248 => ['Mixed roasted nuts, salted and freshly roasted.','مكسرات مشكلة محمصة، مملحة ومحمصة طازة.'],
  249 => ['Thick Greek yogurt, high in protein and seriously creamy.','زبادي يوناني تقيل، غني بالبروتين وكريمي جدًا.'],
  250 => ['Whole sea bass grilled over charcoal with lemon and herbs.','قاروص كامل مشوي على الفحم بالليمون والأعشاب.'],
  251 => ['Golden fried calamari rings with tahina on the side.','حلقات كاليماري مقلية ذهبية مع طحينة جانبية.'],
  252 => ['Jumbo shrimp grilled in garlic butter until just done.','جمبري جامبو مشوي بزبدة الثوم لحد الاستواء المظبوط.'],
  253 => ['Seafood tagine baked in a clay pot with tomato and spices.','طاجن سيفود متحمر في فخار بالطماطم والبهارات.'],
  254 => ['Red mullet grilled whole, crisp outside and flaky within.','بربوني مشوي كامل، مقرمش من بره وطري من جوه.'],
  255 => ['Sayadeya rice simmered in fish stock with caramelised onion.','أرز صيادية متسبك في مرقة السمك مع بصل محمر.'],
  256 => ['Tahina salad whipped smooth with lemon and garlic.','سلطة طحينة مخفوقة ناعمة بالليمون والثوم.'],
  257 => ['Fresh lemon juice, squeezed to order and properly tart.','عصير ليمون طازة، معصور على الطلب وحامض بالظبط.'],
  258 => ['Classic cheeseburger with melted cheddar and house sauce.','تشيز برجر كلاسيك بالشيدر المسايح وصوص البيت.'],
  259 => ['Double beef patties stacked with crispy bacon and cheese.','دبل برجر لحمة مع بيكون مقرمش وجبنة.'],
  260 => ['Beef patty with sauteed mushrooms and Swiss cheese.','برجر لحمة مع مشروم سوتيه وجبنة سويسري.'],
  261 => ['Crispy fried chicken fillet with pickles and mayo.','فيليه فراخ مقلي مقرمش مع مخلل ومايونيز.'],
  262 => ['Fries tossed in truffle oil and parmesan.','بطاطس متقلبة بزيت الكمأة والبارميزان.'],
  263 => ['Chicken wings tossed in proper buffalo hot sauce.','أجنحة فراخ متقلبة في صوص البافلو الحار الأصلي.'],
  264 => ['Crisp romaine, parmesan and croutons in Caesar dressing.','خس روماني مقرمش وبارميزان وخبز محمص بصوص السيزر.'],
  265 => ['Thick Oreo milkshake blended with vanilla ice cream.','ميلك شيك أوريو تقيل مخلوط بآيس كريم فانيليا.'],
  266 => ['Classic koshary with rice, lentils, pasta and crispy onion.','كشري كلاسيك بالأرز والعدس والمكرونة والبصل المحمر.'],
  267 => ['Fresh taameya fried to order in warm baladi bread.','طعمية طازة مقلية على الطلب في عيش بلدي سخن.'],
  268 => ['Foul Iskandarani slow-cooked with tomato and cumin.','فول إسكندراني مطبوخ على نار هادية بالطماطم والكمون.'],
  269 => ['Spiced minced beef baked inside crisp baladi bread.','لحمة مفرومة متبلة متحمرة جوه عيش بلدي مقرمش.'],
  270 => ['Feteer meshaltet, layered flaky and baked golden.','فطير مشلتت، طبقات هشة ومتحمرة ذهبي.'],
  271 => ['Mixed grill of kofta, kebab and shish tawook.','مشويات مشكلة من كفتة وكباب وشيش طاووق.'],
  272 => ['Chilled sobia, sweet, creamy and traditional.','سوبيا مثلجة، حلوة وكريمي وتقليدية.'],
  273 => ['Om Ali baked with nuts, cream and raisins.','أم علي متحمرة بالمكسرات والقشطة والزبيب.'],
  274 => ['Margherita with San Marzano tomato, mozzarella and basil.','مارجريتا بطماطم سان مارزانو وموتزاريلا وريحان.'],
  275 => ['Pepperoni pizza loaded with spicy cured slices.','بيتزا بيبروني مليانة شرائح حارة.'],
  276 => ['Four cheeses melted over a thin crisp base.','أربع أنواع جبنة مسايحة على عجينة رفيعة مقرمشة.'],
  277 => ['Penne in spicy arrabbiata sauce with garlic and chilli.','بيني في صوص أرابياتا حار بالثوم والشطة.'],
  278 => ['Fettuccine folded through a rich parmesan cream.','فيتوتشيني متقلبة في كريمة بارميزان غنية.'],
  279 => ['Lasagna layered with slow-cooked beef ragu and bechamel.','لازانيا طبقات براجو لحمة مطبوخ ببطء وبشاميل.'],
  280 => ['Garlic bread baked with herb butter until golden.','عيش بالثوم متحمر بزبدة الأعشاب لحد الذهبي.'],
  281 => ['Tiramisu with espresso-soaked sponge and mascarpone.','تيراميسو بإسفنج مشرب إسبريسو وماسكاربوني.'],
  282 => ['Molokheya cooked the old way, served with tender rabbit.','ملوخية متطبوخة على الأصول، متقدمة مع أرنب طري.'],
  283 => ['Pigeon stuffed with freekeh and roasted until golden.','حمام محشي فريك ومتحمر لحد الذهبي.'],
  284 => ['A generous platter of mixed grilled meats off the charcoal.','طبق كبير مشويات مشكلة من على الفحم.'],
  285 => ['Kofta kebab, hand-minced, spiced and charcoal-grilled.','كفتة كباب، مفرومة يدوي ومتبلة ومشوية على الفحم.'],
  286 => ['Vine leaves rolled by hand around herbed rice.','ورق عنب ملفوف باليد حوالين أرز بالأعشاب.'],
  287 => ['A mezze spread of Egyptian dips, salads and warm bread.','مازة مصري من تغميسات وسلطات وعيش سخن.'],
  288 => ['Fluffy rice with golden toasted vermicelli.','أرز مفلفل بشعرية محمصة ذهبي.'],
];
$n = 0; $skipped = [];
foreach ($rows as $id => $pair) {
    $item = App\Models\Item::withoutGlobalScope(App\Scopes\StoreScope::class)->find($id);
    if (!$item) { $skipped[] = $id; continue; }
    $item->description = $pair[0];
    $item->save();
    App\Models\Translation::updateOrCreate(
        ['translationable_type'=>'App\Models\Item','translationable_id'=>$id,'locale'=>'en','key'=>'description'],
        ['value'=>$pair[0]]
    );
    App\Models\Translation::updateOrCreate(
        ['translationable_type'=>'App\Models\Item','translationable_id'=>$id,'locale'=>'ar','key'=>'description'],
        ['value'=>$pair[1]]
    );
    $n++;
}
echo "updated: $n".PHP_EOL;
echo "skipped (not found): ".(count($skipped) ? implode(',', $skipped) : 'none').PHP_EOL;
