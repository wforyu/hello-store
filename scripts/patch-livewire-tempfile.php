<?php

/**
 * Idempotent patcher for Livewire's temporary-upload handling.
 *
 * WHY
 * ---
 * `Livewire\Features\SupportFileUploads\TemporaryUploadedFile::__construct()`
 * builds a dummy path with `tmpfile()` and hands it to Symfony's
 * `UploadedFile`/`File` constructor, which runs `is_file($path)`.
 *
 * On sandboxed hosts (shared hosting such as InfinityFree, or anything that
 * sets `open_basedir`), the PHP system temp dir is either unwritable or simply
 * not visible to the stream layer, so:
 *
 *   - `tmpfile()` failing  => `stream_get_meta_data(false)` => `TypeError`
 *     (Livewire answers 419, not 500)
 *   - `is_file()` failing  => `Symfony\...\FileNotFoundException`
 *     (a RuntimeException, so Livewire lets it bubble up as HTTP 500)
 *
 * The second case is the one that breaks Filament uploads: every
 * `_finishUpload` call dies with a bare "Server Error".
 *
 * WHAT
 * ----
 * Stop depending on the system temp dir. Keep `tmpfile()` (it is cheap and
 * works fine when the host allows it), but if its path is not visible to
 * `is_file()`, fall back to a placeholder inside the app's own storage root,
 * which is always inside `open_basedir`.
 *
 * The path itself is irrelevant: `TemporaryUploadedFile` overrides
 * `getRealPath()`, `getPath()` and `getPathname()` to point at the real
 * uploaded file, so the constructor argument is only used for the
 * existence check.
 *
 * Usage:  php scripts/patch-livewire-tempfile.php
 * Exit 0 when the patch is present (either applied now or already there).
 */
$root = dirname(__DIR__);
$target = $root.'/vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php';

$original = <<<'PHP'
        $tmpFile = tmpfile();

        parent::__construct(stream_get_meta_data($tmpFile)['uri'], $this->path);
PHP;

$patched = <<<'PHP'
        $tmpFile = tmpfile();

        $tmpPath = $tmpFile !== false ? stream_get_meta_data($tmpFile)['uri'] : null;

        // Sandboxed hosts (shared hosting with open_basedir, or a system temp
        // dir we cannot write to) make that path invisible to is_file(), and
        // Symfony's File::__construct() then throws a FileNotFoundException
        // which surfaces as a bare HTTP 500. Fall back to a placeholder inside
        // the app's own storage root, which is always inside open_basedir.
        if ($tmpPath === null || ! @is_file($tmpPath)) {
            $tmpPath = $this->storage->path('.livewire-upload-placeholder');
            @touch($tmpPath);
        }

        parent::__construct($tmpPath, $this->path);
PHP;

if (! is_file($target)) {
    fwrite(STDERR, "SKIP: {$target} not found (run composer install first)\n");

    exit(0);
}

$contents = file_get_contents($target);

if (str_contains($contents, '.livewire-upload-placeholder')) {
    echo "OK: already patched (no change)\n";

    exit(0);
}

if (! str_contains($contents, $original)) {
    fwrite(STDERR, "FAIL: could not find the tmpfile() block in TemporaryUploadedFile.php\n");
    fwrite(STDERR, "      Livewire may have changed this code; re-check and adjust this patch.\n");

    exit(1);
}

file_put_contents($target, str_replace($original, $patched, $contents));

echo 'PATCHED: '.$target."\n";
