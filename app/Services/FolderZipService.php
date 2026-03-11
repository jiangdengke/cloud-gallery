<?php

namespace App\Services;

/**
 * 文件夹打包服务（业务代码）。
 *
 * 用途：
 * - 将某个文件夹递归打包为 zip，供下载接口返回
 *
 * 说明：
 * - zip 文件生成在 storage/app/tmp 下，通常会在响应发送完成后删除（deleteFileAfterSend）
 * - publicOnly=true 时会过滤掉私有子项（用于“公开范围下载”）
 */

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class FolderZipService
{
    /**
     * 为文件夹创建 zip 并返回临时路径与下载文件名。
     *
     * @param File $folder 要打包的文件夹节点
     * @param bool $publicOnly 是否只打包公开内容
     * @return array{0: string, 1: string} zipPath, downloadName
     */
    public function createZipForFolder(File $folder, bool $publicOnly = false): array
    {
        // 1) 生成一个临时 zip 文件路径
        $zipPath = $this->createTempZipPath();

        // 2) 创建 zip
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('Unable to create zip file.');
        }

        // 3) 以文件夹名作为 zip 根目录名（并做安全清洗）
        $rootName = $this->sanitizeZipSegment($folder->name ?: 'folder');
        $this->addFolderToZip($zip, $folder, $rootName, $publicOnly);

        // 4) 关闭 zip，返回临时路径与下载名
        $zip->close();

        return [$zipPath, $rootName . '.zip'];
    }

    private function addFolderToZip(ZipArchive $zip, File $folder, string $zipPath, bool $publicOnly): void
    {
        // zip 内创建目录
        $zip->addEmptyDir($zipPath);

        // 查询子项：可选过滤私有内容
        $children = File::where('parent_id', $folder->id)
            ->when($publicOnly, fn ($q) => $q
                ->where('is_public', true)
                ->where(function ($query) {
                    $query->whereNull('password_hash')->orWhere('password_hash', '');
                }))
            ->orderBy('is_folder', 'desc')
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            // 子项路径：对名称做清洗，防止路径穿越/非法字符
            $childName = $this->sanitizeZipSegment($child->name ?: ($child->is_folder ? 'folder' : 'file'));
            $childZipPath = $zipPath . '/' . $childName;

            if ($child->is_folder) {
                // 文件夹：递归
                $this->addFolderToZip($zip, $child, $childZipPath, $publicOnly);
                continue;
            }

            // 文件：必须存在于 public disk
            $diskPath = $child->disk_path;
            if (!$diskPath || !Storage::disk('public')->exists($diskPath)) {
                throw new RuntimeException('File missing on disk.');
            }

            // 将物理文件加入 zip
            $absolutePath = Storage::disk('public')->path($diskPath);
            if ($zip->addFile($absolutePath, $childZipPath) !== true) {
                throw new RuntimeException('Unable to add file to zip.');
            }
        }
    }

    private function createTempZipPath(): string
    {
        // 临时目录：不存在则创建
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Unable to create temp directory.');
        }

        // 使用 UUID 作为文件名，避免冲突
        return $tmpDir . DIRECTORY_SEPARATOR . Str::uuid()->toString() . '.zip';
    }

    private function sanitizeZipSegment(string $name): string
    {
        // 基础清洗：去空白、替换路径分隔符、消除 .. 等
        $name = trim($name);

        $name = str_replace(["\0", '/', '\\'], '_', $name);

        while (str_contains($name, '..')) {
            $name = str_replace('..', '_', $name);
        }

        return $name === '' ? 'item' : $name;
    }
}
