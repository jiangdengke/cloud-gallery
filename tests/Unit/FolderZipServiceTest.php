<?php

namespace Tests\Unit;

use App\Models\File;
use App\Services\FolderZipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class FolderZipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_zip_public_only_excludes_private_children(): void
    {
        Storage::fake('public');

        $folder = File::create([
            'parent_id' => null,
            'name' => 'Folder',
            'is_folder' => true,
            'is_public' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $public = File::create([
            'parent_id' => $folder->id,
            'name' => 'public.txt',
            'is_folder' => false,
            'is_public' => true,
            'size' => 1,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/public.txt',
        ]);

        $private = File::create([
            'parent_id' => $folder->id,
            'name' => 'private.txt',
            'is_folder' => false,
            'is_public' => false,
            'size' => 1,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/private.txt',
        ]);

        Storage::disk('public')->put($public->disk_path, 'a');
        Storage::disk('public')->put($private->disk_path, 'b');

        [$zipPath] = app(FolderZipService::class)->createZipForFolder($folder, publicOnly: true);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $zip->close();
        @unlink($zipPath);

        $this->assertContains('Folder/public.txt', $names);
        $this->assertNotContains('Folder/private.txt', $names);
    }

    public function test_create_zip_includes_private_children_when_not_public_only(): void
    {
        Storage::fake('public');

        $folder = File::create([
            'parent_id' => null,
            'name' => 'Folder',
            'is_folder' => true,
            'is_public' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $private = File::create([
            'parent_id' => $folder->id,
            'name' => 'private.txt',
            'is_folder' => false,
            'is_public' => false,
            'size' => 1,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/private.txt',
        ]);

        Storage::disk('public')->put($private->disk_path, 'b');

        [$zipPath] = app(FolderZipService::class)->createZipForFolder($folder, publicOnly: false);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $zip->close();
        @unlink($zipPath);

        $this->assertContains('Folder/private.txt', $names);
    }
}

