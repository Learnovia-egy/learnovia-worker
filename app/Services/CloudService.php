<?php

namespace App\Services;

use App\Constants\StorageTypes;
use App\Domains\Video;
use App\Helpers\UploadHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloudService
{
    public static function generateUploadPath($type, $filename): string
    {
        $filename = Str::random(10) . '.' . $filename;
        return UploadHelper::resolveStoragePath($type, $filename);
    }

    public static function generateUploadUrl($path): array
    {
        return UploadHelper::StorageCloudDriver()->temporaryUploadUrl($path, now()->addDay());
    }

    /**
     * @throws \Exception
     */
    public static function deleteFileFromCloud($path)
    {
        UploadHelper::StorageCloudDriver()->delete($path);
        \Cache::driver('upload_url_queue')->forget(request()->upload_id);
        throw new \Exception(__('messages.error.invalid_module'));
    }

    public static function forgetCache(): bool
    {
        return \Cache::driver('upload_url_queue')->forget(request()->upload_id);
    }

    public function uploadFile(string $from, string $dest): bool
    {
        return UploadHelper::StorageCloudDriver()->put($dest, fopen($from . $dest, 'r'));
    }

    /**
     * @throws \Exception
     */
    public function downloadFile(string $clientName, string $media_path): string
    {
        $localDisk = Storage::disk('local');

        $relativePath = $clientName . '/downloaded/' . $media_path;

        $stream = UploadHelper::StorageCloudDriver()->readStream(StorageTypes::MEDIA . '/' . $media_path);
        $localDisk->writeStream($relativePath, $stream);
        if ($localDisk->exists($relativePath))
            return $relativePath;
//            return $localDisk->readStream($relativePath);
        else
            throw new \Exception(__('messages.cloud.download_failed'));
    }

    /**
     * @throws \Exception
     */
    public function uploadKeyAndChunkedFiles(Video $video): bool
    {
        $clientName = Cache::get('client_base_url_' . $video->id);
        $videosPath= "app/public/$clientName";
        $keyPath = "$clientName/keys/$video->id";
        if (!Storage::exists($keyPath))
            throw new \Exception('Key file not found');

        $this->uploadFile("app/$clientName", $keyPath);
        $chunkFilesPath = Storage::disk('public')->files($video->files_path);
        foreach ($chunkFilesPath as $chunkFilePath) {
            if ($this->uploadFile($videosPath, $chunkFilePath))
                Storage::disk('public')->delete($chunkFilePath);
            else
                throw new \Exception('Uploading file failed... ' . $chunkFilePath);
        }
        Storage::disk('public')->deleteDirectory($video->files_path);
        return true;
    }


}
