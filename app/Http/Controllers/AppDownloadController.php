<?php

namespace App\Http\Controllers;

/**
 * Test-build download for the client: /download/app shows the build and how
 * to install it, /download/app/file streams it. The APK is uploaded to
 * storage/app/apk/ by hand (scp); it is never in git or under public/.
 */
class AppDownloadController extends Controller
{
    private const FILE = 'app/apk/part-synch.apk';

    private const META = 'app/apk/apk.json';

    public function show()
    {
        return view('public.app-download', ['build' => $this->build()]);
    }

    public function file()
    {
        $build = $this->build();
        abort_unless($build, 404);

        return response()->download(storage_path(self::FILE), $build['download_name'], [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return array{version: string, size: string, built_at: ?string, download_name: string}|null */
    private function build(): ?array
    {
        $path = storage_path(self::FILE);
        if (! is_file($path)) {
            return null;
        }

        $meta = is_file(storage_path(self::META))
            ? (json_decode((string) file_get_contents(storage_path(self::META)), true) ?: [])
            : [];
        $version = preg_replace('/[^0-9A-Za-z.+-]/', '', (string) ($meta['version'] ?? '')) ?: 'test';

        return [
            'version' => $version,
            'size' => number_format(filesize($path) / 1048576, 1) . ' MB',
            'built_at' => isset($meta['built_at']) ? (string) $meta['built_at'] : null,
            'download_name' => 'PartSynch-' . str_replace('+', '-build', $version) . '-debug.apk',
        ];
    }
}
