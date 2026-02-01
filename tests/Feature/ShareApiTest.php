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

    public function test_share_download_rejects_folder(): void
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

        $response = $this->getJson("/api/shares/{$share->token}/download");

        $response
            ->assertJsonPath('code', ResponseCodeEnum::DOWNLOAD_FOLDER_NOT_SUPPORTED->value);
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
