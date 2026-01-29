<?php

namespace App\Http\Controllers;

use App\Enums\ResponseCodeEnum;
use App\Models\File;
use App\Models\FileShare;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Jiannei\Response\Laravel\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
class ShareController extends Controller
{
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
            // 如果分享设置了密码，且用户没传 password 参数，或者传的密码不对
            if ($request->password !== $share->password) {
                return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_REQUIRED);
            }
            // 如果传了但不对，提示密码错误
            return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_ERROR);
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
        // 返回数据
        $url = null;
        if (!$file->is_folder && $file->disk_path) {
            $url = Storage::disk('public')->url($file->disk_path);
        }
        return Response::success([
            'share_token' => $share->token,
            'name' => $file->name,
            'is_folder' => (bool)$file->is_folder,
            'size' => $file->size,
            'created_at' => $share->created_at->toDateTimeString(),
            'expired_at' => $share->expired_at?->toDateTimeString(),
            'url' => $url, // 如果是图片/视频，给预览链接；如果是文件夹，这个字段没用
        ]);
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
            if ($request->password !== $share->password) {
                // 下载接口通常是浏览器直接访问
                if (empty($request->password)) {
                    return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_REQUIRED);
                }
                return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_ERROR);
            }
        }
        $file = $share->file;

        // 检查是否为文件夹
        if ($file->is_folder) {
            return Response::fail('', ResponseCodeEnum::DOWNLOAD_FOLDER_NOT_SUPPORTED);
        }

        // 检查物理文件是否存在
        if (!Storage::disk('public')->exists($file->disk_path)) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK);
        }

        // 强制下载
        return Storage::disk('public')->download($file->disk_path, $file->name);
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
        $file = File::findOrFail($request->file_id);

        // 生成唯一的分享Token
        // 循环生成知道不重复为止
        do {
            $token = Str::random(10); // 生成10位随机字符串
        } while (File::where('token', $token)->exists());

        // 保存到数据库
        $share = FileShare::create([
            'file_id' => $file->id,
            'token' => $token,
            'password' => $request->password, // 如果没传就是 null (公开分享)
            'expired_at' => $request->expired_at,
        ]);

        // 返回分享信息
        return Response::success([
            'token' => $share->token,
            'link' => url('/s/' . $share->token), // 分享链接
            'expired_at' => $share->expired_at?->toDateTimeString(),
        ]);
    }
}
