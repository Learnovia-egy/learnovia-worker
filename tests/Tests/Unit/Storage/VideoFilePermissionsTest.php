<?php

namespace Tests\Unit\Storage;

use Tests\TestCase;

class VideoFilePermissionsTest extends TestCase
{
    protected string $baseDir;
    protected string $targetDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir  = storage_path('app/private/downloaded/new/media');
        $this->targetDir = storage_path('app/private/keys/media');

        // clean slate before each test
        $this->nukeDir($this->baseDir);
        $this->nukeDir($this->targetDir);
    }

    protected function tearDown(): void
    {
        // always restore permissions before cleanup — otherwise rmdir fails
        $this->restorePermissions($this->baseDir);
        $this->restorePermissions($this->targetDir);

        $this->nukeDir($this->baseDir);
        $this->nukeDir($this->targetDir);

        parent::tearDown();
    }

    // ─────────────────────────────────────────
    // 1. Create directory
    // ─────────────────────────────────────────

    public function test_can_create_directory_on_disk(): void
    {
        mkdir($this->baseDir, 0755, true);

        $this->assertDirectoryExists($this->baseDir);
        $this->assertTrue(is_writable($this->baseDir));
    }

    public function test_directory_has_correct_permissions(): void
    {
        mkdir($this->baseDir, 0755, true);

        $perms = substr(sprintf('%o', fileperms($this->baseDir)), -4);

        $this->assertEquals('0755', $perms);
    }

    // ─────────────────────────────────────────
    // 2. Create dummy MP4
    // ─────────────────────────────────────────

    public function test_can_write_mp4_file_to_directory(): void
    {
        mkdir($this->baseDir, 0755, true);

        $path = $this->baseDir . '/test_video.mp4';
        file_put_contents($path, $this->fakeMp4Content());

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
    }

    public function test_www_data_owns_created_file(): void
    {
        mkdir($this->baseDir, 0755, true);

        $path = $this->baseDir . '/owned.mp4';
        file_put_contents($path, $this->fakeMp4Content());

        $owner = posix_getpwuid(fileowner($path))['name'];

        // worker runs as www-data — adjust if you use a different user
        $this->assertEquals('www-data', $owner);
    }

    public function test_cannot_write_to_directory_without_permission(): void
    {
        mkdir($this->baseDir, 0755, true);
        chmod($this->baseDir, 0555); // read + execute only

        $path = $this->baseDir . '/should_fail.mp4';

        $result = @file_put_contents($path, $this->fakeMp4Content());

        $this->assertFalse($result); // false = write blocked
    }

    // ─────────────────────────────────────────
    // 3. Move between directories
    // ─────────────────────────────────────────

    public function test_can_move_mp4_between_directories(): void
    {
        mkdir($this->baseDir, 0755, true);
        mkdir($this->targetDir, 0755, true);

        $source = $this->baseDir . '/moving.mp4';
        $target = $this->targetDir . '/moving.mp4';

        file_put_contents($source, $this->fakeMp4Content());

        rename($source, $target);

        $this->assertFileExists($target);
        $this->assertFileDoesNotExist($source);
    }

    public function test_cannot_move_from_unreadable_directory(): void
    {
        mkdir($this->baseDir, 0755, true);
        mkdir($this->targetDir, 0755, true);

        $source = $this->baseDir . '/locked.mp4';
        file_put_contents($source, $this->fakeMp4Content());

        chmod($this->baseDir, 0000); // no permissions at all

        $result = @rename($source, $this->targetDir . '/locked.mp4');

        $this->assertFalse($result);
    }

    public function test_cannot_move_to_unwritable_directory(): void
    {
        mkdir($this->baseDir, 0755, true);
        mkdir($this->targetDir, 0555, true); // read-only target

        $source = $this->baseDir . '/blocked.mp4';
        file_put_contents($source, $this->fakeMp4Content());

        $result = @rename($source, $this->targetDir . '/blocked.mp4');

        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────
    // 4. Delete file + directory
    // ─────────────────────────────────────────

    public function test_can_delete_mp4_file(): void
    {
        mkdir($this->baseDir, 0755, true);

        $path = $this->baseDir . '/deletable.mp4';
        file_put_contents($path, $this->fakeMp4Content());

        unlink($path);

        $this->assertFileDoesNotExist($path);
    }

    public function test_cannot_delete_file_in_locked_directory(): void
    {
        mkdir($this->baseDir, 0755, true);

        $path = $this->baseDir . '/nodelete.mp4';
        file_put_contents($path, $this->fakeMp4Content());

        chmod($this->baseDir, 0555);

        $result = @unlink($path);

        $this->assertFalse($result);
    }

    public function test_can_delete_directory_recursively(): void
    {
        mkdir($this->baseDir, 0755, true);
        file_put_contents($this->baseDir . '/a.mp4', $this->fakeMp4Content());
        file_put_contents($this->baseDir . '/b.mp4', $this->fakeMp4Content());

        $this->nukeDir($this->baseDir);

        $this->assertDirectoryDoesNotExist($this->baseDir);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    private function fakeMp4Content(): string
    {
        return "\x00\x00\x00\x18" . 'ftyp' . 'isom' . "\x00\x00\x00\x00" . 'isom';
    }

    private function nukeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        // restore perms first so we can actually delete
        $this->restorePermissions($dir);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }

    private function restorePermissions(string $dir): void
    {
        if (is_dir($dir)) {
            chmod($dir, 0755);
        }
    }
}
