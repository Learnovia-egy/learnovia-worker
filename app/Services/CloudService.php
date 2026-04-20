<?php

namespace App\Services;

use App\Debugger;
use App\Domains\Video;
use App\Enums\DebuggerMsgEnum;
use App\Enums\DebuggerQueueEnum;
use App\Helpers\UploadHelper;
use Cache;
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

    public function uploadFile(string $in, string $localFile): bool
    {
        return UploadHelper::StorageCloudDriver()->put($in, fopen(storage_path($localFile), 'r'));
    }

    /**
     * @throws \Exception
     */
    public function downloadFile(string $media_path): string
    {
        $localDisk = Storage::disk('local');

        $relativePath = '/downloaded/' . $media_path;
        $localDisk->makeDirectory($relativePath); // creates it if missing, no-op if exists
        //<editor-fold desc="debug">
        Debugger::debug($media_path,
            DebuggerMsgEnum::VAR->label('downloading from cloud storage on path:'),
            queueEnum: DebuggerQueueEnum::Downloading);
        //</editor-fold>
        $stream = UploadHelper::StorageCloudDriver()->readStream($media_path);
        //<editor-fold desc="debug">
        Debugger::debug($relativePath,
            DebuggerMsgEnum::VAR->label('writing stream to relativePath'),
            queueEnum: DebuggerQueueEnum::Downloading);
        //</editor-fold>
        $localDisk->writeStream($relativePath, $stream);
        //<editor-fold desc="debug">
        Debugger::debug($localDisk->exists($relativePath),
            DebuggerMsgEnum::VAR->label('is file exists in / downloaded ? '),
            queueEnum: DebuggerQueueEnum::Downloading);
//</editor-fold>

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
        $client = Cache::get('client_base_url_' . $video->id);
        //<editor-fold desc="debug">
        Debugger::debug($client, DebuggerMsgEnum::VAR->label('Cache client_base_url_'),
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>

        if ($client === null) throw new \Exception('Client not found');
        $clientName = $client['client_name'];
        $videosPath = "app/public/$clientName";
        //<editor-fold desc="debug">
        Debugger::debug($videosPath,
            DebuggerMsgEnum::VAR->label('local video path'),
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>

        $keyPath = $video->key_path;
        //<editor-fold desc="debug">
        Debugger::debug($keyPath,
            DebuggerMsgEnum::VAR->label('key path'),
            queueEnum: DebuggerQueueEnum::Uploading);
        Debugger::debug(Storage::disk('local')->path($keyPath),
            DebuggerMsgEnum::VAR->label('storage path'),
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>

        if (!Storage::disk('local')->exists($keyPath))
            throw new \Exception('Key file not found');
        $localFile = 'app/private/' . $keyPath;
        //<editor-fold desc="debug">
        Debugger::debug($localFile,
            DebuggerMsgEnum::VAR->label('uploading key file to cloud storage'),
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>
        $stat = $this->uploadFile($keyPath, $localFile);
        //<editor-fold desc="debug">
        Debugger::debug($stat,
            DebuggerMsgEnum::VAR->label('upload status'),
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>

        $chunkFilesPath = Storage::disk('public')->files($video->files_path);
        if (empty($chunkFilesPath)) throw new \Exception('Chunk files not found');
        //<editor-fold desc="debug">
        Debugger::debug($chunkFilesPath,
            DebuggerMsgEnum::VAR->label('ts files path'),
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>

        foreach ($chunkFilesPath as $chunkFilePath) {
            $localFile = 'app/public/' . $chunkFilePath;
            if ($this->uploadFile($chunkFilePath, $localFile))
                Storage::disk('public')->delete($chunkFilePath);
            else
                throw new \Exception('Uploading file failed... ' . $chunkFilePath);
        }
        //<editor-fold desc="debug">
        Debugger::debug('Finished uploading...',
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>
        //<editor-fold desc="debug">
        Debugger::debug($video->files_path,
            DebuggerMsgEnum::VAR->label('files_path'),
            queueEnum: DebuggerQueueEnum::Uploading
        );
        //</editor-fold>

        $stat = Storage::disk('public')->deleteDirectory($video->files_path);
        //<editor-fold desc="debug">
        Debugger::debug($stat,
            DebuggerMsgEnum::VAR->label('are chunked files deleted?'),
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>
        $stat = Storage::disk('local')->delete($video->key_path);
        //<editor-fold desc="debug">
        Debugger::debug($stat,
            DebuggerMsgEnum::VAR->label('is key deleted?'),
            queueEnum: DebuggerQueueEnum::Uploading);
        //</editor-fold>

        return true;
    }


}
