<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Enums\ResponseCodeEnum;

class ShareApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKey = $this->setApiKey();
    }

    public function test_create_and_view_share_happy_path(): void
    {
        Storage::fake('public');

        $file = File::create([
            'parent_id' => null,
            'name' => 'demo.jpg',
            'is_folder' => false,
            'size' => 10,
            'mime_type' => 'image/jpeg',
            'disk_path' => 'uploads/demo.jpg',
        ]);

        Storage::disk('public')->put($file->disk_path, 'content');

        $create = $this->withHeader('X-Api-Key', $this->apiKey)
            ->postJson('/api/shares/create', [
                'file_id' => $file->id,
                'password' => '1234',
            ]);

        $create
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $token = $create->json('data.token');
        $this->assertNotEmpty($token);

        $detail = $this->getJson("/api/shares/{$token}?password=1234");
        $detail
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'demo.jpg');
    }

    public function test_share_folder_file_list_supports_nested_navigation(): void
    {
        $root = File::create([
            'parent_id' => null,
            'name' => 'Root',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $childFolder = File::create([
            'parent_id' => $root->id,
            'name' => 'Child',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $childFile = File::create([
            'parent_id' => $childFolder->id,
            'name' => 'note.txt',
            'is_folder' => false,
            'size' => 10,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/note.txt',
        ]);

        $share = FileShare::create([
            'file_id' => $root->id,
            'token' => 'tokennested',
            'password' => null,
            'expired_at' => null,
        ]);

        $rootList = $this->getJson("/api/shares/{$share->token}/files?parent_id={$root->id}");
        $rootList
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.root_id', $root->id);

        $rootIds = collect($rootList->json('data.list'))->pluck('id')->all();
        $this->assertContains($childFolder->id, $rootIds);

        $childList = $this->getJson("/api/shares/{$share->token}/files?parent_id={$childFolder->id}");
        $childList
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $childIds = collect($childList->json('data.list'))->pluck('id')->all();
        $this->assertContains($childFile->id, $childIds);

        $outside = File::create([
            'parent_id' => null,
            'name' => 'Outside',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $forbidden = $this->getJson("/api/shares/{$share->token}/files?parent_id={$outside->id}");
        $forbidden
            ->assertJsonPath('code', ResponseCodeEnum::SHARE_ACCESS_DENIED->value);
    }

    public function test_share_download_allows_file_id_within_shared_folder(): void
    {
        Storage::fake('public');

        $root = File::create([
            'parent_id' => null,
            'name' => 'Root',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $childFile = File::create([
            'parent_id' => $root->id,
            'name' => 'demo.txt',
            'is_folder' => false,
            'size' => 10,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/demo.txt',
        ]);

        Storage::disk('public')->put($childFile->disk_path, 'content');

        $share = FileShare::create([
            'file_id' => $root->id,
            'token' => 'tokendownload',
            'password' => null,
            'expired_at' => null,
        ]);

        $download = $this->get("/api/shares/{$share->token}/download?file_id={$childFile->id}");
        $download
            ->assertStatus(200)
            ->assertDownload('demo.txt');

        $outsideFile = File::create([
            'parent_id' => null,
            'name' => 'secret.txt',
            'is_folder' => false,
            'size' => 10,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/secret.txt',
        ]);

        Storage::disk('public')->put($outsideFile->disk_path, 'content');

        $forbidden = $this->getJson("/api/shares/{$share->token}/download?file_id={$outsideFile->id}");
        $forbidden
            ->assertJsonPath('code', ResponseCodeEnum::SHARE_ACCESS_DENIED->value);
    }

    public function test_share_requires_password(): void
    {
        $file = File::create([
            'parent_id' => null,
            'name' => 'secret.txt',
            'is_folder' => false,
            'size' => 10,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/secret.txt',
        ]);

        $share = FileShare::create([
            'file_id' => $file->id,
            'token' => 'tokensecret',
            'password' => '1234',
            'expired_at' => null,
        ]);

        $response = $this->getJson("/api/shares/{$share->token}");

        $response
            ->assertJsonPath('code', ResponseCodeEnum::SHARE_PASSWORD_REQUIRED->value);
    }

    public function test_share_password_error(): void
    {
        $file = File::create([
            'parent_id' => null,
            'name' => 'secret.txt',
            'is_folder' => false,
            'size' => 10,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/secret.txt',
        ]);

        $share = FileShare::create([
            'file_id' => $file->id,
            'token' => 'tokenwrong',
            'password' => '1234',
            'expired_at' => null,
        ]);

        $response = $this->getJson("/api/shares/{$share->token}?password=0000");

        $response
            ->assertJsonPath('code', ResponseCodeEnum::SHARE_PASSWORD_ERROR->value);
    }

    public function test_share_expired(): void
    {
        $file = File::create([
            'parent_id' => null,
            'name' => 'expired.txt',
            'is_folder' => false,
            'size' => 10,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/expired.txt',
        ]);

        $share = FileShare::create([
            'file_id' => $file->id,
            'token' => 'tokenexpired',
            'password' => null,
            'expired_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/shares/{$share->token}");

        $response
            ->assertJsonPath('code', ResponseCodeEnum::SHARE_EXPIRED->value);
    }

    public function test_share_download_folder_returns_zip(): void
    {
        $folder = File::create([
            'parent_id' => null,
            'name' => 'shared-folder',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $share = FileShare::create([
            'file_id' => $folder->id,
            'token' => 'tokenfolder',
            'password' => null,
            'expired_at' => null,
        ]);

        $response = $this->get("/api/shares/{$share->token}/download");

        $response
            ->assertStatus(200)
            ->assertDownload('shared-folder.zip');
    }

    private function setApiKey(): string
    {
        $key = 'test-key';

        putenv("API_KEY={$key}");
        $_ENV['API_KEY'] = $key;
        $_SERVER['API_KEY'] = $key;

        return $key;
    }
}
