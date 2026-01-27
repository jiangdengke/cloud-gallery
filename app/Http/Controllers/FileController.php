<?php

namespace App\Http\Controllers;

use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Http\Request;
use Jiannei\Response\Laravel\Support\Facades\Response;
use App\Enums\ResponseCodeEnum;
use Illuminate\Support\Facades\Storage;
class FileController extends Controller
{
    /**
     * 下载文件
     * GET /api/files/{id}/download
     */

    public function download($id)
    {
        $file = File::findOrFail($id);

        // 检查是否为文件夹
        if ($file->is_folder) {
            return Response::fail('',ResponseCodeEnum::DOWNLOAD_FOLDER_NOT_SUPPORTED);
        }
        // 检查物理文件是否存在
        if (!Storage::disk('public')->exists($file->disk_path)) {
            return ResponseCodeEnum::FILE_SAVE_ERROR;
        }
        // 执行下载
        return Storage::disk('public')->download($file->disk_path, $file->name);
    }
    /**
     * 获取文件详情
     * GET /api/files/{id}
     */

    public function detail($id)
    {
        // 自动查找，找不到返回404
        $file = File::findOrFail($id);

        return Response::success(FileResource::make($file));
    }
    /**
     * 移动文件/文件夹
     * POST /api/files/move
     */

    public function move(Request $request)
    {
        // 验证
        $request->validate([
            'id' => 'required|exists:files,id',
            'parent_id' => 'nullable|exists:files,id', // 目标文件夹（null代表根目录）
        ]);

        $file = File::find($request->id);
        $targetParentId = $request->parent_id;

        // 如果目标目录和当前目录一样，什么都不做
        if ($file->parent_id == $targetParentId) {
            return Response::success($file);
        }

        // 逻辑检查，如果移动的是文件夹，不能移动到自己或者自己的子目录中
        if ($file->is_folder && $targetParentId) {
            // 不能移动到自己里面
            if ($file->id == $targetParentId) {
                return Response::fail('',ResponseCodeEnum::MOVE_INTO_SELF_OR_CHILD);
            }

            // 不能移动到自己子孙目录里
            // 检查targetParentId的所有父级，看有没有等于$file->id的
            // 循环向上找 parent，看能不能碰到 file->id
            $parent = File::find($targetParentId);
            while ($parent) {
                if ($parent->id == $file->id) {
                    return Response::fail('',ResponseCodeEnum::MOVE_INTO_SELF_OR_CHILD);
                }
                $parent = $parent->parent_id ? File::find($parent->parent_id) : null;
            }
        }

        // 重名检查：目标目录下不能有同名文件
        $exists = File::where('parent_id', $targetParentId)
            ->where('name', $file->name)
            ->exists();
        if ($exists) {
            return Response::fail('',ResponseCodeEnum::NAME_ALREADY_EXISTS);
        }

        // 执行移动
        $file->update(['parent_id' => $targetParentId]);
        return Response::success($file);
    }

    /**
     * 彻底删除文件/文件夹
     * POST /api/files/delete
     */

    public function delete(Request $request)
    {
        // 验证参数
        $request->validate([
            'ids' => 'required|array', // 必须提交 数组格式
            'ids.*' => 'integer|exists:files,id', // 和上一行共同组成校验，上面一行要求是数组，这一行要求数组内的每个元素都是整数且在 files 表中存在
        ]);
        $files = File::whereIn('id', $request->ids)->get();

        foreach ($files as $file) {
            $this->deleteRecursively($file);
        }
        return Response::success(null);
    }

    /**
     * 递归删除辅助函数
     * (用于处理文件夹内部的文件清理)
     */

    private function deleteRecursively($file)
    {
        // 如果是文件夹，先查出来里面的子文件，逐个删掉
        if ($file->is_folder) {
            $childFiles = File::where('parent_id', $file->id)->get();
            foreach ($childFiles as $child) {
                $this->deleteRecursively($child);
            }
        } else {
            // 如果是文件，删除物理文件
            if ($file->disk_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($file->path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($file->disk_path);
            }
        }
        // 删除数据库记录
        $file->delete();
    }
    /**
     * 重命名文件或文件夹
     * POST /api/files/rename
     */
    public function rename(Request $request)
    {
        // 验证参数
        $request->validate([
            'id' => 'required|exists:files,id',
            'name' => 'required|string|max:255', // 新名字
        ]);

        $file = File::find($request->id);
        $newName = $request->name;

        //如果名字没变，直接返回成功，省得查数据库
        if ($file->name === $newName) {
            return Response::success($file);
        }

        // 检查重名
        $exists = File::where('parent_id', $file->parent_id)
            ->where('name', $newName)
            ->where('id', '!=', $file->id) // 排除自己
            ->exists();

        if($exists) {
            return Response::fail('',ResponseCodeEnum::NAME_ALREADY_EXISTS);
        }

        // 改名
        $file->update(['name' => $newName]);

        // 返回更新后的对象
        return Response::success($file);
    }

    /**
     * 文件上传
     * POST /api/files/upload
     */
    public function upload(Request $request)
    {
        // 1. 验证
        $request->validate([
            'file' => 'required|file|max:102400', // 最大 100MB
            'parent_id' => 'nullable|exists:files,id',
        ]);

        $file = $request->file('file');

        // 2. 获取文件基本信息
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        // 3.计算文件哈希（MD5） 后面实现秒传todo
        $hash = md5_file($file->getRealPath());

        // 4. 秒传检测逻辑：如果数据库已有该hash，直接复制引用，不存物理文件
        // $existFile = File::where('hash', $hash)->first();
        // if ($existFile) { ... }

        // 5. 物理存储
        // 存到 storage/app/public/uploads/2026-01-25/ 目录下
        // store() 会自动生成一个随机文件名，防止中文乱码和重名
        $path = $file->store('uploads/' . date('Y-m-d'), 'public');

        if (!$path) {
            return Response::fail( '',ResponseCodeEnum::FILE_SAVE_ERROR);
        }

        // 6. 处理文件名冲突 (如果在同一目录下有同名文件，自动重命名)
        // 比如：简历.pdf -> 简历(1).pdf
        $name = $originalName;
        $counter = 1;
        while (File::where('parent_id', $request->parent_id)->where('name', $name)->exists()) {
            $name = pathinfo($originalName, PATHINFO_FILENAME) .
                "($counter)." . $extension;
            $counter++;
        }

        // 7. 写入数据库
        $newFile = File::create([
            'parent_id' => $request->parent_id,
            'name' => $name, // 最终显示的文件名
            'is_folder' => false,
            'size' => $size,
            'mime_type' => $mimeType,
            'disk_path' => $path, // 物理路径 (uploads/2026-xx-xx/random.jpg)
            'hash' => $hash,
        ]);

        return Response::success($newFile);
    }
    /**
     * 获取文件列表
     * GET /api/files?parent_id=5
     */
    public function index(Request $request)
    {
        // 1. 获取参数 parent_id
        $parentId = $request->input('parent_id');

        // 2. 查询数据库
        $files = File::where('parent_id', $parentId)
            ->orderBy('is_folder', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 3. 统一返回
        return Response::success(
            [
                'list' => $files,
                'parent_id' => $parentId
            ],
            '',
            ResponseCodeEnum::OK
        );
    }

    /**
     * 新建文件夹
     * POST /api/folders
     */
    public function createFolder(Request $request)
    {
        // 1. 验证参数
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:files,id',
        ]);

        // 2. 检查重名
        $exists = File::where('parent_id', $request->parent_id)
            ->where('is_folder', true)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            // 以前是: return Response::fail('文件夹已存在', 422);
            return Response::fail(ResponseCodeEnum::FOLDER_ALREADY_EXISTS);
        }

        // 3. 写入数据库
        $folder = File::create([
            'name' => $request->name,
            'is_folder' => true,
            'parent_id' => $request->parent_id,
            'size' => 0,
            'disk_path' => null,
        ]);

        // 4. 返回成功
        return Response::created($folder);
    }
}
