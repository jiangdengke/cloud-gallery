<?php

namespace App\Http\Controllers;

/**
 * 分享相关接口（业务代码）。
 *
 * 说明：
 * - 分享通过 token 标识（例如 /s/{token}）
 * - 可选 6 位数字提取码、可选过期时间
 * - 下载文件夹时会临时打包为 zip
 * - 提取码优先用 Header `X-Share-Password` 传递（更不容易进入 URL 日志/历史记录）
 */

use App\Enums\ResponseCodeEnum;
use App\Http\Resources\ShareCreateResource;
use App\Http\Resources\ShareDetailResource;
use App\Http\Resources\ShareFileListResource;
use App\Models\File;
use App\Models\FileShare;
use App\Services\FolderZipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jiannei\Response\Laravel\Support\Facades\Response;

class ShareController extends Controller
{
    /**
     * 提取码最大尝试次数（超过后进入冷却期）。
     */
    private const SHARE_PASSWORD_MAX_ATTEMPTS = 5;

    /**
     * 冷却期时间窗口（秒）。
     */
    private const SHARE_PASSWORD_DECAY_SECONDS = 300;

    /**
     * 获取分享文件夹内部的文件列表（支持在分享根目录范围内导航）。
     *
     * 路由：GET /api/shares/{token}/files?parent_id=...
     */
    public function fileList(Request $request, $token)
    {
        // 1) 基础校验：分享是否存在/是否过期/提取码是否正确
        $share = FileShare::where('token', $token)->first();
        if (!$share) {
            return Response::fail('', ResponseCodeEnum::SHARE_NOT_FOUND);
        }
        if ($share->expired_at && $share->expired_at->isPast()) {
            return Response::fail('', ResponseCodeEnum::SHARE_EXPIRED);
        }
        if ($passwordError = $this->resolveSharePasswordError($request, $share)) {
            return $passwordError;
        }

        // 2) 分享必须绑定一个文件夹作为根目录（目前仅支持分享文件夹的列表浏览）
        $root = $share->file;
        if (!$root) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }
        if (!$root->is_folder) {
            return Response::fail('', ResponseCodeEnum::DOWNLOAD_FOLDER_NOT_SUPPORTED);
        }

        // 3) parent_id 为空表示根目录；不为空时要确保 parent 在分享根目录范围内
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

        // 4) 列表查询：按“文件夹优先 + 新建时间倒序”展示
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
     * 取消分享（管理员接口）。
     *
     * 路由：DELETE /api/shares/{id}
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
     * 查看分享内容（分享落地页/预览页使用）。
     *
     * 路由：GET /api/shares/{token}
     * 说明：验证通过后会增加 click_count。
     */
    public function detail(Request $request, $token)
    {
        // 1) 查找分享记录
        /** @var \App\Models\FileShare|null $share */
        $share = FileShare::where('token', $token)->first();
        if (!$share) {
            return Response::fail('', ResponseCodeEnum::SHARE_NOT_FOUND);
        }

        // 2) 检查过期
        if ($share ->expired_at && $share->expired_at->isPast()) {
            return Response::fail('', ResponseCodeEnum::SHARE_EXPIRED);
        }
        if ($passwordError = $this->resolveSharePasswordError($request, $share)) {
            return $passwordError;
        }

        // 3) 验证通过：增加一次浏览量
        $share->click_count++;
        $share->save();

        // 4) 获取关联的文件信息
        $file = $share->file;

        // 5) 基础兜底：分享目标可能已被删除/物理文件丢失
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
     * 下载分享的文件/文件夹。
     *
     * 路由：GET /api/shares/{token}/download?file_id=...
     * - file_id 为空：下载分享根目录
     * - file_id 不为空：下载分享根目录内的某个子文件/子目录
     */
    public function download(Request $request, $token)
    {
        // 1) 校验分享是否存在/是否过期/提取码是否正确
        $share = FileShare::where('token', $token)->first();
        if (!$share) {
            return Response::fail('', ResponseCodeEnum::SHARE_NOT_FOUND);
        }

        // 检查过期
        if ($share ->expired_at && $share->expired_at->isPast()) {
            return Response::fail('', ResponseCodeEnum::SHARE_EXPIRED);
        }

        if ($passwordError = $this->resolveSharePasswordError($request, $share)) {
            return $passwordError;
        }

        // 2) 确定下载目标（默认下载分享根目录）
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

        // 3) 文件夹：临时打包为 zip
        if ($target->is_folder) {
            try {
                [$zipPath, $downloadName] = app(FolderZipService::class)->createZipForFolder($target);
            } catch (\Throwable $e) {
                return Response::fail('', ResponseCodeEnum::ZIP_CREATE_ERROR);
            }

            return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
        }

        // 4) 文件：检查物理文件是否存在
        if (!$target->disk_path || !Storage::disk('public')->exists($target->disk_path)) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK);
        }

        // 5) 强制下载（Content-Disposition: attachment）
        return Storage::disk('public')->download($target->disk_path, $target->name);
    }

    /**
     * 创建分享链接（管理员接口）。
     *
     * 路由：POST /api/shares/create
     * 参数：
     * - file_id：要分享的文件/文件夹
     * - password：可选 6 位数字提取码
     * - expired_at：可选过期时间（必须是未来）
     */
    public function create(Request $request)
    {
        // 验证
        $request->validate([
            'file_id' => 'required|exists:files,id',
            'password' => 'nullable|digits:6', // 提取码 6 位数字（可选）
            'expired_at' => 'nullable|date|after:now', // 必须是未来的时间
        ]);

        // 检查文件归属（防止恶意分享别人的文件）
        $file = File::find($request->file_id);
        if (!$file) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }

        // 生成唯一 token（循环直到不重复）
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

    /**
     * 提取分享提取码（6 位数字）。
     *
     * 优先级：
     * 1) Header：X-Share-Password（推荐）
     * 2) query：?password=（兼容旧实现/浏览器下载链接）
     */
    private function extractSharePassword(Request $request): string
    {
        $password = $request->header('X-Share-Password');
        if (!is_string($password) || trim($password) === '') {
            $password = $request->query('password');
        }

        $password = is_string($password) ? trim($password) : '';

        return $password;
    }

    /**
     * 生成提取码限流 key（token + IP）。
     */
    private function shareRateLimitKey(Request $request, FileShare $share): string
    {
        $ip = (string) ($request->ip() ?? 'unknown');

        return 'share-pass:' . $share->token . ':' . $ip;
    }

    /**
     * 分享提取码校验与限流。
     *
     * @return mixed 返回 null 表示通过；返回 Response 表示失败（需要输入/错误/限流）
     */
    private function resolveSharePasswordError(Request $request, FileShare $share)
    {
        // 无提取码：公开分享，直接放行，并清掉可能残留的限流计数
        if (!$share->password) {
            RateLimiter::clear($this->shareRateLimitKey($request, $share));
            return null;
        }

        $password = $this->extractSharePassword($request);
        if ($password === '') {
            return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_REQUIRED);
        }

        $rateKey = $this->shareRateLimitKey($request, $share);

        if (RateLimiter::tooManyAttempts($rateKey, self::SHARE_PASSWORD_MAX_ATTEMPTS)) {
            // 已进入冷却期：只允许“正确提取码”解锁
            if (!preg_match('/^\\d{6}$/', $password) || $password !== (string) $share->password) {
                return Response::fail('', ResponseCodeEnum::SHARE_TOO_MANY_ATTEMPTS);
            }

            RateLimiter::clear($rateKey);

            return null;
        }

        if (!preg_match('/^\\d{6}$/', $password)) {
            // 格式不合法也计入一次尝试
            RateLimiter::hit($rateKey, self::SHARE_PASSWORD_DECAY_SECONDS);

            if (RateLimiter::tooManyAttempts($rateKey, self::SHARE_PASSWORD_MAX_ATTEMPTS)) {
                return Response::fail('', ResponseCodeEnum::SHARE_TOO_MANY_ATTEMPTS);
            }

            return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_ERROR);
        }

        if ($password !== (string) $share->password) {
            // 提取码错误：计数 + 达到阈值进入冷却
            RateLimiter::hit($rateKey, self::SHARE_PASSWORD_DECAY_SECONDS);

            if (RateLimiter::tooManyAttempts($rateKey, self::SHARE_PASSWORD_MAX_ATTEMPTS)) {
                return Response::fail('', ResponseCodeEnum::SHARE_TOO_MANY_ATTEMPTS);
            }

            return Response::fail('', ResponseCodeEnum::SHARE_PASSWORD_ERROR);
        }

        // 提取码正确：清除计数
        RateLimiter::clear($rateKey);

        return null;
    }

    /**
     * 判断目标节点是否位于分享根目录内（防止越权访问）。
     *
     * 规则：从目标节点一路向上回溯 parent_id，只要能回溯到 root 即认为合法。
     */
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
