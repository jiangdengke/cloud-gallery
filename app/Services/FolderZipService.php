<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class FolderZipService
{
    /**
     * @return array{0: string, 1: string} zipPath, downloadName
     */
    public function createZipForFolder(File $folder): array
    {
        $zipPath = $this->createTempZipPath();

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('Unable to create zip file.');
        }

        $rootName = $this->sanitizeZipSegment($folder->name ?: 'folder');
        $this->addFolderToZip($zip, $folder, $rootName);

        $zip->close();

        return [$zipPath, $rootName . '.zip'];
    }

    private function addFolderToZip(ZipArchive $zip, File $folder, string $zipPath): void
    {
        $zip->addEmptyDir($zipPath);

        $children = File::where('parent_id', $folder->id)
            ->orderBy('is_folder', 'desc')
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            $childName = $this->sanitizeZipSegment($child->name ?: ($child->is_folder ? 'folder' : 'file'));
            $childZipPath = $zipPath . '/' . $childName;

            if ($child->is_folder) {
                $this->addFolderToZip($zip, $child, $childZipPath);
                continue;
            }

            $diskPath = $child->disk_path;
            if (!$diskPath || !Storage::disk('public')->exists($diskPath)) {
                throw new RuntimeException('File missing on disk.');
            }

            $absolutePath = Storage::disk('public')->path($diskPath);
            if ($zip->addFile($absolutePath, $childZipPath) !== true) {
                throw new RuntimeException('Unable to add file to zip.');
            }
        }
    }

    private function createTempZipPath(): string
    {
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Unable to create temp directory.');
        }

        return $tmpDir . DIRECTORY_SEPARATOR . Str::uuid()->toString() . '.zip';
    }

    private function sanitizeZipSegment(string $name): string
    {
        $name = trim($name);

        $name = str_replace(["\0", '/', '\\'], '_', $name);

        while (str_contains($name, '..')) {
            $name = str_replace('..', '_', $name);
        }

        return $name === '' ? 'item' : $name;
    }
}

