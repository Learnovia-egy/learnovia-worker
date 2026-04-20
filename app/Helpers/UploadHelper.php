<?php

namespace App\Helpers;

use app\StorageTypes;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadHelper
{
    /**
     * @throws \Exception
     */
    public static function upload($file, $type, $fileName): string
    {
        $fileName = Str::Uuid()->toString() . '_' . $fileName;
        $path = self::resolveStoragePath($type, $fileName);
        try {
            UploadHelper::StorageCloudDriver()->put($path, file_get_contents($file));
        } catch (\Exception $e) {
            logger()->error($e->getMessage(), [
                'file' => $file,
                'type' => $type,
                'path' => $path,
            ]);
            throw $e;
        }

        return $path;
    }

    /**
     * @param $type
     * @param string $fileName
     * @return string
     */
    public static function resolveStoragePath($type, string $fileName): string
    {
        return match ($type) {
            StorageTypes::ASSIGNMENT => StorageTypes::ASSIGNMENT . '/' . $fileName,
            StorageTypes::FILE => StorageTypes::FILE . '/' . $fileName,
            StorageTypes::MEDIA => StorageTypes::MEDIA . '/' . $fileName,
            StorageTypes::ANNOUNCEMENT => StorageTypes::ANNOUNCEMENT . '/' . $fileName,
            StorageTypes::COURSE => StorageTypes::COURSE . '/' . $fileName,
            StorageTypes::LOGO => StorageTypes::LOGO . '/' . $fileName,
            StorageTypes::FOR_EDITOR => StorageTypes::FOR_EDITOR . '/' . $fileName,
            StorageTypes::GALLERY => StorageTypes::GALLERY . '/' . $fileName,
            default => StorageTypes::DEFAULT . '/' . $fileName,
        };
    }

    public static function StorageCloudDriver(): Filesystem
    {
        return Storage::disk(config('app.filesystem_cloud'));
    }
}
