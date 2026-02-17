<?php

namespace Tests\Feature;

use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Enums\ResponseCodeEnum;

class FileApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKey = $this->setApiKey();
    }

    public function test_upload_file_happy_path(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->withHeader('X-Api-Key', $this->apiKey)
            ->post('/api/files/upload', [
                'file' => $file,
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $diskPath = $response->json('data.disk_path');
        $this->assertNotEmpty($diskPath);

        Storage::disk('public')->assertExists($diskPath);
        $this->assertDatabaseHas('files', [
            'disk_path' => $diskPath,
            'is_folder' => 0,
        ]);
    }

    public function test_upload_deduplicates_by_hash(): void
    {
        Storage::fake('public');

        $first = UploadedFile::fake()->createWithContent('a.txt', 'hello');
        $second = UploadedFile::fake()->createWithContent('b.txt', 'hello');

        $firstResponse = $this->withHeader('X-Api-Key', $this->apiKey)
            ->post('/api/files/upload', [
                'file' => $first,
            ]);

        $firstResponse
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $firstPath = $firstResponse->json('data.disk_path');
        $this->assertNotEmpty($firstPath);

        $secondResponse = $this->withHeader('X-Api-Key', $this->apiKey)
            ->post('/api/files/upload', [
                'file' => $second,
            ]);

        $secondResponse
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $secondPath = $secondResponse->json('data.disk_path');
        $this->assertSame($firstPath, $secondPath);

        Storage::disk('public')->assertExists($firstPath);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_upload_rejects_parent_not_folder(): void
    {
        $fileParent = File::create([
            'parent_id' => null,
            'name' => 'note.txt',
            'is_folder' => false,
            'size' => 1,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/note.txt',
        ]);

        $response = $this->withHeader('X-Api-Key', $this->apiKey)
            ->post('/api/files/upload', [
                'file' => UploadedFile::fake()->image('bad.jpg'),
                'parent_id' => $fileParent->id,
            ]);

        $response
            ->assertJsonPath('code', ResponseCodeEnum::PARENT_NOT_FOLDER->value);
    }

    public function test_move_file_happy_path(): void
    {
        $folderA = File::create([
            'parent_id' => null,
            'name' => 'Folder A',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $folderB = File::create([
            'parent_id' => null,
            'name' => 'Folder B',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $file = File::create([
            'parent_id' => $folderA->id,
            'name' => 'readme.txt',
            'is_folder' => false,
            'size' => 12,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/readme.txt',
        ]);

        $response = $this->withHeader('X-Api-Key', $this->apiKey)
            ->postJson('/api/files/move', [
                'id' => $file->id,
                'parent_id' => $folderB->id,
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.parent_id', $folderB->id);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'parent_id' => $folderB->id,
        ]);
    }

    public function test_move_folder_into_child_is_rejected(): void
    {
        $folderA = File::create([
            'parent_id' => null,
            'name' => 'Folder A',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $folderB = File::create([
            'parent_id' => $folderA->id,
            'name' => 'Folder B',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $response = $this->withHeader('X-Api-Key', $this->apiKey)
            ->postJson('/api/files/move', [
                'id' => $folderA->id,
                'parent_id' => $folderB->id,
            ]);

        $response
            ->assertJsonPath('code', ResponseCodeEnum::MOVE_INTO_SELF_OR_CHILD->value);
    }

    public function test_rename_rejects_duplicate_name(): void
    {
        $folder = File::create([
            'parent_id' => null,
            'name' => 'Root',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $first = File::create([
            'parent_id' => $folder->id,
            'name' => 'one.txt',
            'is_folder' => false,
            'size' => 1,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/one.txt',
        ]);

        File::create([
            'parent_id' => $folder->id,
            'name' => 'two.txt',
            'is_folder' => false,
            'size' => 1,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/two.txt',
        ]);

        $response = $this->withHeader('X-Api-Key', $this->apiKey)
            ->postJson('/api/files/rename', [
                'id' => $first->id,
                'name' => 'two.txt',
            ]);

        $response
            ->assertJsonPath('code', ResponseCodeEnum::NAME_ALREADY_EXISTS->value);
    }

    public function test_list_rejects_parent_not_folder(): void
    {
        $fileParent = File::create([
            'parent_id' => null,
            'name' => 'note.txt',
            'is_folder' => false,
            'size' => 1,
            'mime_type' => 'text/plain',
            'disk_path' => 'uploads/note.txt',
        ]);

        $response = $this->getJson("/api/files?parent_id={$fileParent->id}");

        $response
            ->assertJsonPath('code', ResponseCodeEnum::PARENT_NOT_FOLDER->value);
    }

    public function test_download_folder_returns_zip(): void
    {
        $folder = File::create([
            'parent_id' => null,
            'name' => 'Folder',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $response = $this->get("/api/files/{$folder->id}/download");

        $response
            ->assertStatus(200)
            ->assertDownload('Folder.zip');
    }

    public function test_delete_folder_happy_path(): void
    {
        Storage::fake('public');

        $folder = File::create([
            'parent_id' => null,
            'name' => 'Delete Me',
            'is_folder' => true,
            'size' => 0,
            'disk_path' => null,
        ]);

        $child = File::create([
            'parent_id' => $folder->id,
            'name' => 'photo.jpg',
            'is_folder' => false,
            'size' => 10,
            'mime_type' => 'image/jpeg',
            'disk_path' => 'uploads/delete/photo.jpg',
        ]);

        Storage::disk('public')->put($child->disk_path, 'content');

        $response = $this->withHeader('X-Api-Key', $this->apiKey)
            ->deleteJson('/api/files/delete', [
                'ids' => [$folder->id],
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('files', [
            'id' => $folder->id,
        ]);
        $this->assertSoftDeleted('files', [
            'id' => $child->id,
        ]);
        Storage::disk('public')->assertMissing($child->disk_path);
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
