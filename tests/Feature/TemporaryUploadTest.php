<?php

namespace Tests\Feature;

use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

class TemporaryUploadTest extends TestCase
{
    public function test_temporary_uploaded_file_is_built_from_a_stored_file(): void
    {
        $storage = FileUploadConfiguration::storage();
        $name = 'regression-'.uniqid().'.png';

        $storage->put(FileUploadConfiguration::path($name), 'fake-image-bytes');

        $file = TemporaryUploadedFile::createFromLivewire($name);

        $this->assertSame($name, $file->getFilename());
        $this->assertSame(strlen('fake-image-bytes'), $file->getSize());
        $this->assertSame(
            $storage->path(FileUploadConfiguration::path($name)),
            $file->getRealPath(),
        );

        $storage->delete(FileUploadConfiguration::path($name));
    }

    public function test_livewire_sandbox_patch_is_still_applied(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            '.livewire-upload-placeholder',
            $source,
            'Patch vendor Livewire hilang. Jalankan: php scripts/patch-livewire-tempfile.php',
        );
    }
}
