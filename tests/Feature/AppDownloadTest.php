<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /download/app: the client's Android test build, uploaded to
 * storage/app/apk/ by hand.
 */
class AppDownloadTest extends TestCase
{
    private string $apk;

    private string $meta;

    private ?string $savedApk = null;

    private ?string $savedMeta = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apk = storage_path('app/apk/part-synch.apk');
        $this->meta = storage_path('app/apk/apk.json');

        // Never lose a real uploaded build while the tests run.
        $this->savedApk = is_file($this->apk) ? file_get_contents($this->apk) : null;
        $this->savedMeta = is_file($this->meta) ? file_get_contents($this->meta) : null;
        @unlink($this->apk);
        @unlink($this->meta);
    }

    protected function tearDown(): void
    {
        @unlink($this->apk);
        @unlink($this->meta);
        if ($this->savedApk !== null) {
            file_put_contents($this->apk, $this->savedApk);
        }
        if ($this->savedMeta !== null) {
            file_put_contents($this->meta, $this->savedMeta);
        }

        parent::tearDown();
    }

    public function test_without_a_build_the_page_says_so_and_the_file_is_404(): void
    {
        $this->get(route('app.download'))
            ->assertOk()
            ->assertSee('available right now')
            ->assertDontSee('Download APK');

        $this->get(route('app.download.file'))->assertNotFound();
    }

    public function test_with_a_build_the_page_shows_it_and_the_file_downloads(): void
    {
        file_put_contents($this->apk, str_repeat('A', 2048));
        file_put_contents($this->meta, json_encode(['version' => '1.6.0+2', 'built_at' => '21 Oct 2026']));

        $this->get(route('app.download'))
            ->assertOk()
            ->assertSee('Test build 1.6.0+2')
            ->assertSee('Download APK')
            ->assertSee('install unknown apps')
            ->assertDontSee('Administrator access only.');

        $response = $this->get(route('app.download.file'))->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('PartSynch-1.6.0-build2-debug.apk', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_a_hostile_version_in_the_metadata_cannot_shape_the_file_name(): void
    {
        file_put_contents($this->apk, 'APK');
        file_put_contents($this->meta, json_encode(['version' => '../../etc/passwd"; x']));

        $disposition = (string) $this->get(route('app.download.file'))->headers->get('Content-Disposition');
        $this->assertStringNotContainsString('/', str_replace('attachment; filename=', '', $disposition));
        $this->assertStringNotContainsString('"; x', $disposition);
    }
}
