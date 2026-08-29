<?php

namespace Tests\Unit;

use App\Services\ImageVariantService;
use App\Traits\HasImageVariants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deliberately DB-free: everything here runs against a faked disk, so the
 * suite passes on a machine with no migrated database.
 */
class ImageVariantServiceTest extends TestCase
{
    private ImageVariantService $variants;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        ImageVariantService::forgetRequested();

        config([
            'imagevariants.enabled' => true,
            'imagevariants.directories' => ['store' => ['thumb', 'card']],
            'imagevariants.sizes' => ['thumb' => 150, 'card' => 400],
            'imagevariants.fallback_format' => 'jpg',
        ]);

        $this->variants = new ImageVariantService();
    }

    protected function tearDown(): void
    {
        ImageVariantService::forgetRequested();

        parent::tearDown();
    }

    private function putSource(string $name = 'logo.png', int $size = 500): void
    {
        Storage::disk('public')->put(
            'store/' . $name,
            UploadedFile::fake()->image($name, $size, $size)->get()
        );
    }

    public function test_it_generates_a_webp_and_a_fallback_for_every_configured_size(): void
    {
        $this->putSource();

        $written = $this->variants->generate('public', 'store/', 'logo.png');

        $this->assertCount(4, $written);
        foreach (['thumb', 'card'] as $size) {
            Storage::disk('public')->assertExists("store/variants/{$size}/logo.webp");
            Storage::disk('public')->assertExists("store/variants/{$size}/logo.jpg");
        }
    }

    public function test_variants_are_smaller_than_the_source(): void
    {
        $this->putSource();
        $this->variants->generate('public', 'store/', 'logo.png');

        $this->assertLessThan(
            Storage::disk('public')->size('store/logo.png'),
            Storage::disk('public')->size('store/variants/card/logo.webp')
        );
    }

    public function test_a_size_larger_than_the_source_is_not_upscaled(): void
    {
        $this->putSource('small.png', 120);
        $this->variants->generate('public', 'store/', 'small.png');

        $bytes = Storage::disk('public')->get('store/variants/card/small.webp');
        [$width] = getimagesizefromstring($bytes);

        $this->assertSame(120, $width, 'card is 400px, but a 120px source must not be upscaled');
    }

    public function test_it_skips_directories_and_formats_it_is_not_configured_for(): void
    {
        $this->assertFalse($this->variants->shouldGenerate('conversation/', 'chat.png'));
        $this->assertFalse($this->variants->shouldGenerate('store/', 'contract.pdf'));
        $this->assertFalse($this->variants->shouldGenerate('store/', 'animation.gif'));
        $this->assertFalse($this->variants->shouldGenerate('store/', 'def.png'));
        $this->assertTrue($this->variants->shouldGenerate('store/', 'logo.png'));
    }

    public function test_it_is_idempotent_unless_forced(): void
    {
        $this->putSource();
        $this->variants->generate('public', 'store/', 'logo.png');

        $this->assertSame([], $this->variants->generate('public', 'store/', 'logo.png'));
        $this->assertCount(4, $this->variants->generate('public', 'store/', 'logo.png', true));
    }

    public function test_a_missing_source_is_a_no_op_rather_than_an_error(): void
    {
        $this->assertSame([], $this->variants->generate('public', 'store/', 'nothing-here.png'));
    }

    public function test_delete_removes_every_variant(): void
    {
        $this->putSource();
        $this->variants->generate('public', 'store/', 'logo.png');

        $this->variants->delete('public', 'store/', 'logo.png');

        $this->assertFalse($this->variants->variantsPresent('public', 'store', 'logo.png'));
        $this->assertEmpty(Storage::disk('public')->files('store/variants/card'));
    }

    public function test_urls_are_null_until_the_variants_exist(): void
    {
        $this->putSource();

        $this->assertNull($this->variants->urls('store/', 'logo.png'));

        $this->variants->generate('public', 'store/', 'logo.png');
        $fresh = new ImageVariantService(); // past the per-request memo

        $urls = $fresh->urls('store/', 'logo.png');

        $this->assertSame(['thumb', 'card'], array_keys($urls['webp']));
        $this->assertStringEndsWith('/store/variants/card/logo.webp', $urls['webp']['card']);
        $this->assertStringEndsWith('/store/variants/thumb/logo.jpg', $urls['jpg']['thumb']);
        $this->assertStringEndsWith('/store/logo.png', $urls['original']);
    }

    public function test_the_opt_in_gate_reads_the_header_and_the_query_string(): void
    {
        $this->app->instance('request', Request::create('/api/v1/stores', 'GET'));
        $this->assertFalse(ImageVariantService::requested());

        ImageVariantService::forgetRequested();
        $this->app->instance('request', Request::create('/api/v1/stores?image_variants=1', 'GET'));
        $this->assertTrue(ImageVariantService::requested());

        ImageVariantService::forgetRequested();
        $this->app->instance('request', Request::create(
            '/api/v1/stores', 'GET', [], [], [], ['HTTP_X_IMAGE_VARIANTS' => '1']
        ));
        $this->assertTrue(ImageVariantService::requested());

        // An explicit falsy header opts back out, so a client can turn the
        // feature off without changing the URLs it calls.
        ImageVariantService::forgetRequested();
        $this->app->instance('request', Request::create(
            '/api/v1/stores', 'GET', [], [], [], ['HTTP_X_IMAGE_VARIANTS' => '0']
        ));
        $this->assertFalse(ImageVariantService::requested());
    }

    public function test_a_client_that_does_not_opt_in_gets_an_unchanged_payload(): void
    {
        $this->putSource();
        $this->variants->generate('public', 'store/', 'logo.png');

        $this->app->instance('request', Request::create('/api/v1/stores', 'GET'));
        ImageVariantService::forgetRequested();
        $without = FakeVariantModel::hydrateOne()->toArray();

        ImageVariantService::forgetRequested();
        $this->app->instance('request', Request::create('/api/v1/stores?image_variants=1', 'GET'));
        $with = FakeVariantModel::hydrateOne()->toArray();

        $this->assertArrayNotHasKey('logo_variants', $without);
        $this->assertArrayHasKey('logo_variants', $with);
        $this->assertSame($without['logo'], $with['logo'], 'the existing fields must not move');
        $this->assertStringEndsWith('/store/variants/card/logo.webp', $with['logo_variants']['webp']['card']);
    }
}

/**
 * A model that uses the trait without needing a table, so the serialization
 * behaviour can be asserted without a database.
 */
class FakeVariantModel extends Model
{
    use HasImageVariants;

    protected $table = 'fake_variant_models';

    protected $guarded = [];

    public static function hydrateOne(): self
    {
        return (new self())->newFromBuilder(['id' => 1, 'logo' => 'logo.png']);
    }

    public function imageVariantAppends(): array
    {
        return ['logo_variants'];
    }

    public function getLogoVariantsAttribute(): ?array
    {
        return $this->imageVariantUrls('store', $this->logo, 'logo');
    }
}
