<?php

namespace App\Http\Controllers;

use App\Enums\ResponseCodeEnum;
use App\Http\Resources\ShareCreateResource;
use App\Http\Resources\ShareDetailResource;
use App\Http\Resources\ShareFileListResource;
use App\Models\File;
use App\Models\FileShare;
use App\Services\FolderZipService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Jiannei\Response\Laravel\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
class ShareController extends Controller
{

    /**
     * 获取分享文件夹内部的文件列表
     * GET /api/shares/{token}/files
     */
    public function fileList(Request $request, $token)
    {
        // 基础验证
        $share = FileShare::where('token', $token)->first();
        if (!$share) {
            return Response::fail('', ResponseCodeEnum::SHARE_NOT_FOUND);
        }
        if ($share->expired_at && $share->expired_at->isPast()) {
            return Response::fail('', ResponseCodeEnum::SHARE_EXPIRED);
        }
        if ($share->password) {
            if (!$request->filled('password')) {
                return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_REQUIRED);
            }
            if ($request->password !== $share->password) {
                return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_ERROR);
            }
        }

        $root = $share->file;
        if (!$root) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }
        if (!$root->is_folder) {
            return Response::fail('', ResponseCodeEnum::DOWNLOAD_FOLDER_NOT_SUPPORTED);
        }

        $parentId = $request->query('parent_id');
        $parent = $root;
        if ($parentId !== null && $parentId !== '') {
            $parent = File::find($parentId);
            if (!$parent) {
                return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
            }
            if (!$parent->is_folder) {
                return Response::fail('', ResponseCodeEnum::PARENT_NOT_FOLDER);
            }
            if (!$this->isWithinRoot($parent, $root)) {
                return Response::fail('', ResponseCodeEnum::SHARE_ACCESS_DENIED);
            }
        }

        $files = File::where('parent_id', $parent->id)
            ->orderBy('is_folder', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Response::success(
            ShareFileListResource::make([
                'list' => $files,
                'parent_id' => $parent->id,
                'root_id' => $root->id,
            ]),
            '',
            ResponseCodeEnum::OK
        );
    }

    /**
     * 取消分享
     * DELETE /api/shares/{id}
     */
    public function destroy($id)
    {
        // 查找并删除
        // 直接根据ID删除即可
        $deleted = FileShare::destroy($id);
        if (!$deleted) {
            return Response::fail('', ResponseCodeEnum::SHARE_NOT_FOUND);
        }
        return Response::success([], '分享已取消', ResponseCodeEnum::OK);

    }

    /**
     * 查看分享内容
     * GET /api/shares/{token}
     */
    public function detail(Request $request, $token)
    {
        // 查看分享记录
        /** @var \App\Models\FileShare $share */  // 👈 加上这一行
        $share = FileShare::where('token', $token)->first();
        if (!$share) {
            return Response::fail('', ResponseCodeEnum::SHARE_NOT_FOUND);
        }
        // 检查过期
        if ($share ->expired_at && $share->expired_at->isPast()) {
            return Response::fail('', ResponseCodeEnum::SHARE_EXPIRED);
        }
        // 检查密码逻辑
        if ($share->password) {
            // 如果分享设置了密码，且用户没传 password 参数
            if (!$request->filled('password')) {
                return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_REQUIRED);
            }
            // 如果传了但不对，提示密码错误
            if ($request->password !== $share->password) {
                return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_ERROR);
            }
        }
        // 验证通过，增加一次浏览量
        $share->click_count++;
        $share->save();

        // 获取关联的文件信息
        $file = $share->file;

        // 如果物理文件丢了
        if (!$file) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK);
        }
        // 如果是文件，检查物理文件是否存在
        if (!$file->is_folder && !$file->disk_path) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK);
        }
        if (!$file->is_folder && $file->disk_path && !Storage::disk('public')->exists($file->disk_path)) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK);
        }
        // 返回数据
        return Response::success(ShareDetailResource::make($share), '', ResponseCodeEnum::OK);
    }
    /**
     * 下载分享的文件
     * GET /api/shares/{token}/download
     */

    public function download(Request $request, $token)
    {
        // 查找分享
        $share = FileShare::where('token', $token)->first();
        if (!$share) {
            return Response::fail('', ResponseCodeEnum::SHARE_NOT_FOUND);
        }

        // 检查过期
        if ($share ->expired_at && $share->expired_at->isPast()) {
            return Response::fail('', ResponseCodeEnum::SHARE_EXPIRED);
        }

        // 检查密码
        if ($share->password) {
            if (!$request->filled('password')) {
                // 下载接口通常是浏览器直接访问
                return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_REQUIRED);
            }
            if ($request->password !== $share->password) {
                return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_ERROR);
            }
        }

        $root = $share->file;
        if (!$root) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }

        $target = $root;
        $targetId = $request->query('file_id');
        if ($targetId !== null && $targetId !== '') {
            $target = File::find($targetId);
            if (!$target) {
                return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
            }
            if (!$this->isWithinRoot($target, $root)) {
                return Response::fail('', ResponseCodeEnum::SHARE_ACCESS_DENIED);
            }
        }

        // 检查是否为文件夹
        if ($target->is_folder) {
            try {
                [$zipPath, $downloadName] = app(FolderZipService::class)->createZipForFolder($target);
            } catch (\Throwable $e) {
                return Response::fail('', ResponseCodeEnum::ZIP_CREATE_ERROR);
            }

            return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
        }

        // 检查物理文件是否存在
        if (!$target->disk_path || !Storage::disk('public')->exists($target->disk_path)) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK);
        }

        // 强制下载
        return Storage::disk('public')->download($target->disk_path, $target->name);
    }
    /**
     * 创建分享链接
     * POST /api/shares/create
     */
    public function create(Request $request)
    {
        // 验证
        $request->validate([
            'file_id' => 'required|exists:files,id',
            'password' => 'nullable|string|min:4|max:6', // 提取码通常4-6位
            'expired_at' => 'nullable|date|after:now', // 必须是未来的时间
        ]);

        // 检查文件归属（防止恶意分享别人的文件）
        $file = File::find($request->file_id);
        if (!$file) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }

        // 生成唯一的分享Token
        // 循环生成知道不重复为止
        do {
            $token = Str::random(10); // 生成10位随机字符串
        } while (FileShare::where('token', $token)->exists());

        // 保存到数据库
        $share = FileShare::create([
            'file_id' => $file->id,
            'token' => $token,
            'password' => $request->password, // 如果没传就是 null (公开分享)
            'expired_at' => $request->expired_at,
        ]);

        // 返回分享信息
        return Response::success(ShareCreateResource::make($share), '', ResponseCodeEnum::OK);
    }

    private function isWithinRoot(File $file, File $root): bool
    {
        $current = $file;

        while ($current) {
            if ($current->id === $root->id) {
                return true;
            }

            if (!$current->parent_id) {
                break;
            }

            $current = File::find($current->parent_id);
        }

        return false;
    }
}
