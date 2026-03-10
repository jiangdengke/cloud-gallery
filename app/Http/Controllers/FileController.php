<?php

namespace App\Http\Controllers;

/**
 * 文件/文件夹相关接口控制器（业务代码）。
 *
 * 主要能力：
 * - 文件列表/详情查询
 * - 上传、重命名、移动、删除（管理员）
 * - 下载：支持文件直接下载、文件夹打包下载
 * - 下载安全：通过短期签名 URL 实现“先取链接再下载”，避免把 Key 放到 URL 中
 * - 访问控制：公开/私有（私有使用 6 位数字 Key）
 *
 * 权限模型（简化说明）：
 * - 管理员：携带正确的 API Key（Header: X-Api-Key）即可进行管理操作，并绕过私有访问校验
 * - 访客：可看到列表，但访问/下载私有资源时需要提供 6 位数字 Key（Header: X-Access-Key）
 */

use App\Enums\ResponseCodeEnum;
use App\Http\Resources\FileListResource;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Services\FolderZipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Jiannei\Response\Laravel\Support\Facades\Response;

class FileController extends Controller
{
    /**
     * 私有 Key 输入错误的最大次数（达到后进入冷却期）。
     */
    private const ACCESS_KEY_MAX_ATTEMPTS = 5;

    /**
     * 冷却期时间窗口（秒）。
     * 在该窗口内连续输错会触发限流，降低暴力猜解风险。
     */
    private const ACCESS_KEY_DECAY_SECONDS = 300;

    /**
     * 下载文件/文件夹（直接下载入口）。
     *
     * 路由：GET /api/files/{id}/download
     *
     * 访问控制：
     * - 管理员请求：可直接下载（绕过私有 Key 校验）
     * - 普通请求：若目标节点或其祖先为私有，则必须提供 6 位数字 Key（Header: X-Access-Key）
     *
     * 说明：
     * - 更推荐使用 downloadUrl() + signedDownload() 的两段式流程，
     *   以避免把 Key 放到 URL 查询参数里。
     */
    public function download(Request $request, $id)
    {
        $file = File::find($id);
        if (!$file) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }

        $isAdmin = $this->isAdminRequest($request);
        if ($accessError = $this->resolveAccessError($file, $request, $isAdmin)) {
            return $accessError;
        }

        // 文件夹下载：服务端临时打包为 zip
        if ($file->is_folder) {
            try {
                // publicOnly=true 时会过滤掉私有子项（管理员下载文件夹时可包含全部内容）
                [$zipPath, $downloadName] = app(FolderZipService::class)->createZipForFolder($file, publicOnly: !$isAdmin);
            } catch (\Throwable $e) {
                return Response::fail('', ResponseCodeEnum::ZIP_CREATE_ERROR);
            }

            return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
        }

        // 文件下载：必须先确认物理文件存在
        if (!$file->disk_path || !Storage::disk('public')->exists($file->disk_path)) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK);
        }

        // 执行下载（Laravel 会设置 Content-Disposition，浏览器会走下载流程）
        return Storage::disk('public')->download($file->disk_path, $file->name);
    }

    /**
     * 获取短期下载链接（签名 URL）。
     *
     * 路由：POST /api/files/{id}/download-url
     *
     * 设计目的：
     * - 先通过 Header（X-Access-Key）完成私有访问校验；
     * - 再返回一个短期有效的 signed URL 给前端跳转；
     * - 从而避免把 Key 暴露到 URL 查询参数（日志/历史记录/Referer）里。
     */
    public function downloadUrl(Request $request, $id)
    {
        $file = File::find($id);
        if (!$file) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }

        $isAdmin = $this->isAdminRequest($request);
        if ($accessError = $this->resolveAccessError($file, $request, $isAdmin)) {
            return $accessError;
        }

        // signed URL 有效期：短期即可，降低泄漏风险
        $expiresAt = now()->addMinutes(5);

        // scope 用于控制“文件夹打包时是否包含私有内容”：
        // - all：允许包含所有内容（仅管理员下载文件夹时使用）
        // - public：只包含公开内容
        $scope = ($file->is_folder && $isAdmin) ? 'all' : 'public';

        // 生成带签名的临时路由，签名内容包含过期时间与查询参数（scope）
        $url = URL::temporarySignedRoute('files.download.signed', $expiresAt, [
            'id' => $file->id,
            'scope' => $scope,
        ]);

        return Response::success([
            'url' => $url,
            'expires_at' => $expiresAt->toDateTimeString(),
        ], '', ResponseCodeEnum::OK);
    }

    /**
     * 真实下载入口（签名校验）。
     *
     * 路由：GET /api/files/{id}/download-signed
     * 中间件：ValidateSignature（校验 signature 与 expires）
     *
     * 注意：
     * - 本接口不再读取 Key；Key 校验已在 downloadUrl() 中完成；
     * - 因此只要 signed URL 在有效期内且签名正确，就允许下载。
     */
    public function signedDownload(Request $request, $id)
    {
        $file = File::find($id);
        if (!$file) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }

        // 约定：scope=all 表示打包文件夹时包含私有子项；其他情况视为 public
        $scope = (string) $request->query('scope', 'public');
        $publicOnly = $scope !== 'all';

        // 文件夹：即时打包为 zip（根据 publicOnly 决定是否过滤私有子项）
        if ($file->is_folder) {
            try {
                [$zipPath, $downloadName] = app(FolderZipService::class)->createZipForFolder($file, publicOnly: $publicOnly);
            } catch (\Throwable $e) {
                return Response::fail('', ResponseCodeEnum::ZIP_CREATE_ERROR);
            }

            return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
        }

        // 检查物理文件是否存在
        if (!$file->disk_path || !Storage::disk('public')->exists($file->disk_path)) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND_ON_DISK);
        }

        // 执行下载
        return Storage::disk('public')->download($file->disk_path, $file->name);
    }

    /**
     * 获取文件详情。
     *
     * 路由：GET /api/files/{id}
     * 说明：若目标节点处于私有保护链路中，则需要提供 6 位数字 Key。
     */
    public function detail(Request $request, $id)
    {
        // 自动查找，找不到返回统一错误
        $file = File::find($id);
        if (!$file) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }

        $isAdmin = $this->isAdminRequest($request);
        if ($accessError = $this->resolveAccessError($file, $request, $isAdmin)) {
            return $accessError;
        }

        return Response::success(FileResource::make($file), '', ResponseCodeEnum::OK);
    }

    /**
     * 移动文件/文件夹（管理员接口）。
     *
     * 路由：POST /api/files/move
     * 规则：
     * - parent_id 为 null 表示移动到根目录；
     * - 目标 parent_id 必须是文件夹；
     * - 文件夹不能移动到自身或子孙节点下（防止形成环）；
     * - 同一目录下不允许重名。
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

        // 目标目录必须是文件夹
        if ($targetParentId) {
            $targetParent = File::find($targetParentId);
            if (!$targetParent || !$targetParent->is_folder) {
                return Response::fail('', ResponseCodeEnum::PARENT_NOT_FOLDER);
            }
        }

        // 如果目标目录和当前目录一样，什么都不做
        if ($file->parent_id == $targetParentId) {
            return Response::success($file, '', ResponseCodeEnum::OK);
        }

        // 逻辑检查，如果移动的是文件夹，不能移动到自己或者自己的子目录中
        if ($file->is_folder && $targetParentId) {
            // 不能移动到自己里面
            if ($file->id == $targetParentId) {
                return Response::fail('', ResponseCodeEnum::MOVE_INTO_SELF_OR_CHILD);
            }

            // 不能移动到自己子孙目录里
            // 检查targetParentId的所有父级，看有没有等于$file->id的
            // 循环向上找 parent，看能不能碰到 file->id
            $parent = File::find($targetParentId);
            while ($parent) {
                if ($parent->id == $file->id) {
                    return Response::fail('', ResponseCodeEnum::MOVE_INTO_SELF_OR_CHILD);
                }
                $parent = $parent->parent_id ? File::find($parent->parent_id) : null;
            }
        }

        // 重名检查：目标目录下不能有同名文件
        $exists = File::where('parent_id', $targetParentId)
            ->where('name', $file->name)
            ->exists();
        if ($exists) {
            return Response::fail('', ResponseCodeEnum::NAME_ALREADY_EXISTS);
        }

        // 执行移动
        $file->update(['parent_id' => $targetParentId]);
        return Response::success($file, '', ResponseCodeEnum::OK);
    }

    /**
     * 删除文件/文件夹（管理员接口，软删除 + 物理文件清理）。
     *
     * 路由：DELETE /api/files/delete
     *
     * 参数说明：
     * - ids：要删除的文件/文件夹 ID 数组
     *
     * 规则说明：
     * - 删除文件夹时会递归删除其全部子项；
     * - 数据库使用 SoftDeletes：这里只会软删除记录；
     * - 物理文件仅在“没有其他记录引用同一 disk_path”时才会真正删除，
     *   以避免秒传/去重复用导致误删。
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
        return Response::success(null, '', ResponseCodeEnum::OK);
    }

    /**
     * 递归删除辅助函数（处理文件夹内部的子项清理）。
     *
     * @param mixed $file 通常为 \App\Models\File 实例
     *
     * 执行顺序：
     * 1) 若为文件夹：先递归删除子项；
     * 2) 若为文件：按 disk_path 删除物理文件（若无其他引用）；
     * 3) 最后删除当前节点数据库记录（软删除）。
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
            if ($file->disk_path && Storage::disk('public')->exists($file->disk_path)) {
                // 秒传/去重会让多个记录复用同一个 disk_path，这里需要避免误删
                $hasOtherReferences = File::where('disk_path', $file->disk_path)
                    ->where('id', '!=', $file->id)
                    ->exists();

                if (!$hasOtherReferences) {
                    Storage::disk('public')->delete($file->disk_path);
                }
            }
        }
        // 删除数据库记录
        $file->delete();
    }

    /**
     * 重命名文件或文件夹（管理员接口）。
     *
     * 路由：POST /api/files/rename
     * 规则：同一目录下不允许出现重名。
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

        // 如果名字没变，直接返回成功，省得查数据库
        if ($file->name === $newName) {
            return Response::success($file, '', ResponseCodeEnum::OK);
        }

        // 检查重名
        $exists = File::where('parent_id', $file->parent_id)
            ->where('name', $newName)
            ->where('id', '!=', $file->id) // 排除自己
            ->exists();

        if ($exists) {
            return Response::fail('', ResponseCodeEnum::NAME_ALREADY_EXISTS);
        }

        // 改名
        $file->update(['name' => $newName]);

        // 返回更新后的对象
        return Response::success($file, '', ResponseCodeEnum::OK);
    }

    /**
     * 文件上传（管理员接口）。
     *
     * 路由：POST /api/files/upload
     *
     * 关键点：
     * - parent_id 存在时，必须指向一个文件夹；
     * - 若父目录处于私有保护链路中，仍需通过私有 Key 校验（避免绕过访问控制）；
     * - 通过文件内容 MD5 实现秒传/去重：相同内容复用同一 disk_path；
     * - 同目录同名时自动重命名（xxx(1).ext）。
     */
    public function upload(Request $request)
    {
        $isAdmin = $this->isAdminRequest($request);

        // 1. 验证
        $request->validate([
            'file' => 'required|file|max:102400', // 最大 100MB
            'parent_id' => 'nullable|exists:files,id',
        ]);

        // 2. 父目录必须是文件夹
        if ($request->parent_id) {
            $parent = File::find($request->parent_id);
            if (!$parent || !$parent->is_folder) {
                return Response::fail('', ResponseCodeEnum::PARENT_NOT_FOLDER);
            }

            if ($accessError = $this->resolveAccessError($parent, $request, $isAdmin)) {
                return $accessError;
            }
        }

        $file = $request->file('file');

        // 3. 获取文件基本信息
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        // 4. 计算文件哈希（MD5），用于秒传/去重
        $hash = md5_file($file->getRealPath());

        // 5. 秒传/去重：如果数据库已有该 hash 且物理文件存在，直接复用 disk_path
        $existFile = File::where('hash', $hash)
            ->where('is_folder', false)
            ->whereNotNull('disk_path')
            ->first();

        if ($existFile && Storage::disk('public')->exists($existFile->disk_path)) {
            $path = $existFile->disk_path;
        } else {
            // 6. 物理存储
            // 存到 storage/app/public/uploads/2026-01-25/ 目录下
            // store() 会自动生成一个随机文件名，防止中文乱码和重名
            $path = $file->store('uploads/' . date('Y-m-d'), 'public');

            if (!$path) {
                return Response::fail('', ResponseCodeEnum::FILE_SAVE_ERROR);
            }
        }

        // 7. 处理文件名冲突 (如果在同一目录下有同名文件，自动重命名)
        // 比如：简历.pdf -> 简历(1).pdf
        $name = $originalName;
        $counter = 1;
        while (File::where('parent_id', $request->parent_id)->where('name', $name)->exists()) {
            $name = pathinfo($originalName, PATHINFO_FILENAME) .
                "($counter)." . $extension;
            $counter++;
        }

        // 8. 写入数据库
        $newFile = File::create([
            'parent_id' => $request->parent_id,
            'name' => $name, // 最终显示的文件名
            'is_folder' => false,
            'size' => $size,
            'mime_type' => $mimeType,
            'disk_path' => $path, // 物理路径 (uploads/2026-xx-xx/random.jpg)
            'hash' => $hash,
        ]);

        return Response::success($newFile, '', ResponseCodeEnum::OK);
    }

    /**
     * 获取文件列表（公开接口）。
     *
     * 路由：GET /api/files?parent_id=...
     * 说明：
     * - 列表会返回所有子项（包含私有项），但私有项会标记 is_protected；
     * - 当请求进入某个私有目录时，需要提供 6 位数字 Key 才能列出其子项。
     */
    public function index(Request $request)
    {
        // 1. 验证参数 parent_id
        $request->validate([
            'parent_id' => 'nullable|exists:files,id',
        ]);

        // 2. 获取参数 parent_id
        $isAdmin = $this->isAdminRequest($request);

        $parentId = $request->input('parent_id');

        // 3. parent_id 必须是文件夹
        if ($parentId) {
            $parent = File::find($parentId);
            if (!$parent || !$parent->is_folder) {
                return Response::fail('', ResponseCodeEnum::PARENT_NOT_FOLDER);
            }

            if ($accessError = $this->resolveAccessError($parent, $request, $isAdmin)) {
                return $accessError;
            }
        }

        // 4. 查询数据库
        $files = File::where('parent_id', $parentId)
            ->orderBy('is_folder', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 5. 统一返回
        return Response::success(
            FileListResource::make([
                'list' => $files,
                'parent_id' => $parentId,
            ]),
            '',
            ResponseCodeEnum::OK
        );
    }

    /**
     * 新建文件夹（管理员接口）。
     *
     * 路由：POST /api/folders
     * 说明：同一目录下不允许出现重名文件夹。
     */
    public function createFolder(Request $request)
    {
        // 1. 验证参数
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:files,id',
        ]);

        // 2. 父目录必须是文件夹
        if ($request->parent_id) {
            $parent = File::find($request->parent_id);
            if (!$parent || !$parent->is_folder) {
                return Response::fail('', ResponseCodeEnum::PARENT_NOT_FOLDER);
            }
        }

        // 3. 检查重名
        $exists = File::where('parent_id', $request->parent_id)
            ->where('is_folder', true)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            // 以前是: return Response::fail('文件夹已存在', 422);
            return Response::fail('', ResponseCodeEnum::FOLDER_ALREADY_EXISTS);
        }

        // 4. 写入数据库
        $folder = File::create([
            'name' => $request->name,
            'is_folder' => true,
            'parent_id' => $request->parent_id,
            'size' => 0,
            'disk_path' => null,
        ]);

        // 5. 返回成功
        return Response::success($folder, '', ResponseCodeEnum::OK);
    }

    /**
     * 更新文件/文件夹访问控制（管理员接口）。
     *
     * 路由：POST /api/files/access
     *
     * 规则：
     * - is_public=true：公开访问（清空 password_hash）；
     * - is_public=false：私有访问（需要 6 位数字 Key，后端仅保存 Hash）；
     * - 不允许“嵌套私有”（祖先/后代存在私有时，拒绝对当前节点设私有），避免多层 Key。
     */
    public function updateAccess(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:files,id',
            'is_public' => 'required|boolean',
            'password' => 'sometimes|nullable|digits:6',
        ]);

        $file = File::find($request->id);
        if (!$file) {
            return Response::fail('', ResponseCodeEnum::FILE_NOT_FOUND);
        }

        $isPublic = (bool) $request->boolean('is_public');

        $updates = [
            'is_public' => $isPublic,
        ];

        if ($isPublic) {
            $updates['password_hash'] = null;
        } else {
            if ($this->hasProtectedAncestor($file) || $this->hasProtectedDescendant($file)) {
                return Response::fail('', ResponseCodeEnum::ACCESS_PASSWORD_NESTED_NOT_ALLOWED);
            }

            if ($request->has('password')) {
                $password = $request->input('password');
                if (!is_string($password) || trim($password) === '') {
                    return Response::fail('', ResponseCodeEnum::ACCESS_PASSWORD_REQUIRED);
                }

                $updates['password_hash'] = Hash::make($password);
            } elseif (!$file->password_hash) {
                return Response::fail('', ResponseCodeEnum::ACCESS_PASSWORD_REQUIRED);
            }
        }

        $file->update($updates);

        return Response::success(FileResource::make($file->fresh()), '', ResponseCodeEnum::OK);
    }

    /**
     * 判断某个节点是否“受保护”（需要 Key 才能访问）。
     *
     * 说明：
     * - 正常情况下，私有节点会设置 is_public=false 且写入 password_hash；
     * - 这里同时兼容 password_hash 字段存在但 is_public 可能未同步的历史数据。
     */
    private function isProtectedNode(File $file): bool
    {
        return !$file->is_public || ($file->password_hash !== null && $file->password_hash !== '');
    }

    /**
     * 判断当前节点是否存在“受保护祖先”。
     *
     * 用途：在 updateAccess() 中用于禁止嵌套私有（避免多层 Key）。
     */
    private function hasProtectedAncestor(File $file): bool
    {
        $current = $file;

        while ($current->parent_id) {
            $current = File::find($current->parent_id);
            if (!$current) {
                break;
            }

            if ($this->isProtectedNode($current)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断当前节点是否存在“受保护后代”。
     *
     * 说明：
     * - 仅对文件夹有意义；
     * - 使用队列（BFS）遍历，避免深层递归造成栈溢出。
     */
    private function hasProtectedDescendant(File $file): bool
    {
        if (!$file->is_folder) {
            return false;
        }

        $queue = [$file->id];

        while (!empty($queue)) {
            $children = File::query()
                ->whereIn('parent_id', $queue)
                ->get(['id', 'is_folder', 'is_public', 'password_hash']);

            foreach ($children as $child) {
                if ($this->isProtectedNode($child)) {
                    return true;
                }
            }

            $queue = $children
                ->where('is_folder', true)
                ->pluck('id')
                ->all();
        }

        return false;
    }

    /**
     * 判断当前请求是否为管理员请求。
     *
     * 管理员鉴权方式：
     * - 优先读取 Header：X-Api-Key（推荐）
     * - 兼容旧版：读取 URL 查询参数 ?key=
     *
     * 安全说明：使用 hash_equals 做恒定时间比较，避免时序攻击。
     */
    private function isAdminRequest(Request $request): bool
    {
        $validKey = trim((string) env('API_KEY', ''));
        if ($validKey === '') {
            return false;
        }

        $inputKey = $request->header('X-Api-Key') ?? $request->query('key');
        if (!is_string($inputKey)) {
            return false;
        }

        $inputKey = trim($inputKey);
        if ($inputKey === '') {
            return false;
        }

        return hash_equals($validKey, $inputKey);
    }

    /**
     * 从请求中提取私有访问 Key（6 位数字）。
     *
     * 优先级：
     * 1) Header：X-Access-Key（推荐，避免进入 URL 日志/历史记录）
     * 2) 兼容旧版：请求参数 password（query/body）
     */
    private function extractAccessPassword(Request $request): string
    {
        $password = $request->header('X-Access-Key');

        if (!is_string($password) || trim($password) === '') {
            $password = $request->input('password');
        }

        $password = is_string($password) ? trim($password) : '';

        return $password;
    }

    /**
     * 生成私有 Key 限流的唯一标识。
     *
     * 维度：受保护节点 ID + 来源 IP
     * 目的：限制对同一个私有节点的暴力猜解，同时不影响其他节点的访问。
     */
    private function accessRateLimitKey(Request $request, File $protectedNode): string
    {
        $ip = (string) ($request->ip() ?? 'unknown');

        return 'access-key:' . $protectedNode->id . ':' . $ip;
    }

    /**
     * 访问控制校验：判断当前请求是否需要 Key、Key 是否正确、是否触发限流等。
     *
     * @return mixed 返回 null 表示允许访问；返回 Response 表示拒绝访问（含错误码与提示）
     */
    private function resolveAccessError(File $file, Request $request, bool $isAdmin)
    {
        if ($isAdmin) {
            return null;
        }

        // 从当前节点一路向上查找，收集受保护节点（含自身）
        $protectedNodes = [];
        $current = $file;

        while ($current) {
            if ($this->isProtectedNode($current)) {
                $protectedNodes[] = $current;
            }

            if (!$current->parent_id) {
                break;
            }

            $current = File::find($current->parent_id);
        }

        if (count($protectedNodes) === 0) {
            return null;
        }

        if (count($protectedNodes) > 1) {
            // 理论上 updateAccess() 已禁止嵌套私有；但为兼容历史数据，这里仍做兜底处理
            return Response::fail('', ResponseCodeEnum::ACCESS_DENIED);
        }

        $protectedNode = $protectedNodes[0];

        if (!$protectedNode->password_hash) {
            return Response::fail('', ResponseCodeEnum::ACCESS_DENIED);
        }

        $password = $this->extractAccessPassword($request);

        if ($password === '') {
            return Response::fail('', ResponseCodeEnum::ACCESS_PASSWORD_REQUIRED);
        }

        // 限流 key：按 IP + 受保护节点维度计数
        $rateKey = $this->accessRateLimitKey($request, $protectedNode);

        if (RateLimiter::tooManyAttempts($rateKey, self::ACCESS_KEY_MAX_ATTEMPTS)) {
            // 已进入冷却期：如果仍然错误，直接返回“尝试次数过多”
            if (!preg_match('/^\\d{6}$/', $password) || !Hash::check($password, $protectedNode->password_hash)) {
                return Response::fail('', ResponseCodeEnum::ACCESS_TOO_MANY_ATTEMPTS);
            }

            // 允许正确 Key 解锁，并清除限流，避免真实用户在冷却期内永远无法进入
            RateLimiter::clear($rateKey);

            return null;
        }

        if (!preg_match('/^\\d{6}$/', $password)) {
            // 格式不合法也计入一次尝试（降低爆破效率）
            RateLimiter::hit($rateKey, self::ACCESS_KEY_DECAY_SECONDS);

            if (RateLimiter::tooManyAttempts($rateKey, self::ACCESS_KEY_MAX_ATTEMPTS)) {
                return Response::fail('', ResponseCodeEnum::ACCESS_TOO_MANY_ATTEMPTS);
            }

            return Response::fail('', ResponseCodeEnum::ACCESS_PASSWORD_ERROR);
        }

        if (!Hash::check($password, $protectedNode->password_hash)) {
            // Key 校验失败：计数 + 在达到阈值时进入冷却
            RateLimiter::hit($rateKey, self::ACCESS_KEY_DECAY_SECONDS);

            if (RateLimiter::tooManyAttempts($rateKey, self::ACCESS_KEY_MAX_ATTEMPTS)) {
                return Response::fail('', ResponseCodeEnum::ACCESS_TOO_MANY_ATTEMPTS);
            }

            return Response::fail('', ResponseCodeEnum::ACCESS_PASSWORD_ERROR);
        }

        // Key 正确：清除计数
        RateLimiter::clear($rateKey);

        return null;
    }
}
