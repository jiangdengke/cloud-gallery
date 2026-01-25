<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Jiannei\Response\Laravel\Support\Facades\Response;
use App\Enums\ResponseCodeEnum;

class FileController extends Controller
{


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
            '', // 留空，系统会自动去 lang 文件抓取 "操作成功"
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
