<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Jiannei\Response\Laravel\Support\Facades\Response;
use App\Enums\ResponseCodeEnum;

class FileController extends Controller
{
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
