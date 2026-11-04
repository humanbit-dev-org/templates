<?php

namespace Tests\Feature\Support;

use App\Http\Requests\ContattoRequest;
use App\Models\Contatto;
use App\Models\Page;
use App\Services\ApiLab\ApiLab;
use App\Services\Cache\CacheFlush;
use App\Services\Cache\CacheKeyService;
use App\Services\DataCleanup\DataCleanupService;
use App\Support\ApiSandbox\EndpointsCatalog;
use App\Support\ApiSandbox\SampleCallResolver;
use App\Support\Backpack\CsvTransformer;
use App\Support\Backpack\Log\LogHelper;
use App\Support\Media\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The infrastructure kept from the merge ships with empty, documented
 * registries instead of domain data. These tests pin that contract: every
 * extension point works when empty and accepts entries in the documented
 * shape.
 */
class GenericExtensionPointsTest extends TestCase
{
    public function test_data_cleanup_starts_without_commands_and_rejects_unknown_ones(): void
    {
        $service = new DataCleanupService;

        $this->assertSame([], $service->getCommands());
        $this->assertFalse($service->preview('missing')['can_execute']);
        $this->assertFalse($service->execute('missing')['success']);
    }

    public function test_sandbox_catalogs_start_empty(): void
    {
        $this->assertSame([], SampleCallResolver::operations());
        $this->assertNull(SampleCallResolver::resolve('anything', 'demo'));
        $this->assertSame([], EndpointsCatalog::all());
    }

    public function test_sandbox_and_portal_clone_no_route_by_default(): void
    {
        $this->assertSame([], config('sandbox.routes'));
        $this->assertSame([], config('portal.routes'));
        $this->assertArrayHasKey(config('sandbox.default_profile'), config('sandbox.profiles'));
    }

    public function test_csv_transformer_passes_rows_through(): void
    {
        $row = ['title' => 'Hello'];

        $this->assertSame($row, CsvTransformer::transform($row, ['Hello'], [0 => 'title'], ['TITLE'], Page::class));
    }

    public function test_import_log_channel_falls_back_to_the_shared_import_channel(): void
    {
        $this->assertNotNull(config('logging.channels.import'));
        $this->assertNull(config('logging.channels.user'));

        // No dedicated channel: same logger as the shared one.
        $this->assertSame(Log::channel('import'), LogHelper::channel('user'));
    }

    public function test_cache_registries_start_empty_and_the_flush_is_harmless(): void
    {
        $this->assertSame([], CacheKeyService::forModel(Page::class));

        CacheFlush::afterImport(Page::class);

        $this->assertTrue(true);
    }

    public function test_media_storage_uses_the_owner_segment_only_when_given(): void
    {
        Storage::fake('public');

        $plain = MediaStorage::storePublic(UploadedFile::fake()->image('logo.png'), 'logos');
        $owned = MediaStorage::storePublic(UploadedFile::fake()->image('logo.png'), 'logos', 'acme');

        $this->assertStringStartsWith('uploads/logos/', $plain);
        $this->assertStringNotContainsString('acme', $plain);
        $this->assertStringStartsWith('uploads/logos/acme/', $owned);
        Storage::disk('public')->assertExists([$plain, $owned]);
    }

    public function test_contact_subjects_come_from_the_model(): void
    {
        $rules = (new ContattoRequest)->rules();

        $valid = Validator::make(['email' => 'a@b.it', 'oggetto' => 'assistenza', 'messaggio' => 'Ciao'], $rules);
        $invalid = Validator::make(['email' => 'a@b.it', 'oggetto' => 'gestione_credito', 'messaggio' => 'Ciao'], $rules);

        $this->assertTrue($valid->passes());
        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('assistenza', Contatto::OGGETTI);
    }

    public function test_api_lab_catalog_lists_only_existing_generic_routes(): void
    {
        $keys = app(ApiLab::class)->endpoints()->pluck('route_name')->all();

        $this->assertContains('seo.index', $keys);
        $this->assertContains('password.forgot', $keys);
    }
}
