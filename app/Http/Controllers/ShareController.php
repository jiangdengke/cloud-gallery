<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileShare;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Jiannei\Response\Laravel\Support\Facades\Response;
class ShareController extends Controller
{
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
