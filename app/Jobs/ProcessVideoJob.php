<?php

namespace App\Jobs;

use App\Debugger;
use App\Domains\Video;
use App\Enums\DebuggerMsgEnum;
use App\Enums\DebuggerQueueEnum;
use App\Enums\VideoStatusEnum;
use App\Services\VideoService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Added for logging

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 3;        // 1 initial + 2 retries
    public $backoff = [60, 180]; // wait 1min before retry 1, 3min before retry 2

    public function __construct(
        private Video                 &$video,
        private readonly VideoService $videoService,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        //<editor-fold desc="debug">
        Debugger::debug('Starting ProcessVideoJob',
            queueEnum: DebuggerQueueEnum::Chunking);
        //</editor-fold>
        $videoId = $this->video->id;
        //<editor-fold desc="debug">
        Debugger::debug($videoId,
            DebuggerMsgEnum::VAR->label('videoId in ProcessVideoJob'),
            queueEnum: DebuggerQueueEnum::Chunking);
        //</editor-fold>
        $client = Cache::get('client_base_url_' . $videoId);
        //<editor-fold desc="debug">
        Debugger::debug($client,
            DebuggerMsgEnum::VAR->label('get cache key: client_base_url_'),
            queueEnum: DebuggerQueueEnum::Chunking);
        //</editor-fold>
        $clientName = $client['client_name'];
        $clientBaseUrl = $client['base_url'];

        $this->videoService->update($videoId, [
            'status' => VideoStatusEnum::Chunking->value,
        ], $this->video);

        try { // --- PATH CONFIGURATION ---
            $tempVideoPath = storage_path("app/private/{$this->video->temp_path}");
            //<editor-fold desc="debug">
            Debugger::debug($tempVideoPath,
                DebuggerMsgEnum::VAR->label('temporary video path'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            $videoPath = $clientName . "/videos/{$videoId}";
            //<editor-fold desc="debug">
            Debugger::debug($videoPath,
                DebuggerMsgEnum::VAR->label('videoPath in ProcessVideoJob'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            // Output Directory (Public for .m3u8 and .ts)
            $publicDir = storage_path('app/public/' . $videoPath);
            //<editor-fold desc="debug">
            Debugger::debug($publicDir,
                DebuggerMsgEnum::VAR->label('publicDir in ProcessVideoJob'),
                queueEnum: DebuggerQueueEnum::Chunking);
//</editor-fold>
            // Secure Directory (Private for .key files)
            $keysDir = storage_path("app/private/{$clientName}/keys");
            //<editor-fold desc="debug">
            Debugger::debug($keysDir,
                DebuggerMsgEnum::VAR->label('keysDir in ProcessVideoJob'),
                queueEnum: DebuggerQueueEnum::Chunking);
//</editor-fold>
            // Create Directories
            //<editor-fold desc="debug">
            Debugger::debug(!is_dir($publicDir),
                'is publicDir dir ?',
                queueEnum: DebuggerQueueEnum::Chunking);
            Debugger::debug(!is_dir($keysDir),
                'is publicDir dir ?',
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            if (!is_dir($publicDir)) mkdir($publicDir, 0755, true);
            if (!is_dir($keysDir)) mkdir($keysDir, 0755, true); // Private!

            // --- STEP 1: GENERATE INITIAL KEY ---
            $initialKey = random_bytes(16);
            $initialKeyPath = "{$publicDir}/initial.key";
            //<editor-fold desc="debug">
            Debugger::debug($initialKey,
                DebuggerMsgEnum::VAR->label('initialKey in ProcessVideoJob'),
                queueEnum: DebuggerQueueEnum::Chunking);
            Debugger::debug($initialKeyPath,
                DebuggerMsgEnum::VAR->label('initialKeyPath in ProcessVideoJob'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            file_put_contents($initialKeyPath, $initialKey);

            // --- STEP 2: CREATE KEY INFO FILE ---
            $dir = "app/private/{$clientName}/tmp";
            $keyInfoPath = storage_path("$dir/{$videoId}.keyinfo");
            //<editor-fold desc="debug">
            Debugger::debug($keyInfoPath,
                DebuggerMsgEnum::VAR->label('KeyInfo Path'),
                queueEnum: DebuggerQueueEnum::Chunking);
            Debugger::debug(Storage::exists($dir),
                DebuggerMsgEnum::VAR->label('is keyInfoPath\'s dir exists ?'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            // We use a placeholder URL here that we will replace in Step 5
            $placeholderUrl = "https://placeholder-url.com/key";

            $data = "{$placeholderUrl}\n{$initialKeyPath}";
            //<editor-fold desc="debug">
            Debugger::debug($data,
                DebuggerMsgEnum::VAR->label('{$placeholderUrl}\n{$initialKeyPath}'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            // Format: URL \n Path
            file_put_contents($keyInfoPath, $data);

            // --- STEP 3: INSPECT VIDEO (FFPROBE) ---

            // Default to "Safe Mode" (Re-encode)
            $videoCodecFlag = 'libx264';
            $audioCodecFlag = 'aac';

            // Run ffprobe to get stream info in JSON format
            $probeCmd = "ffprobe -v quiet -print_format json -show_streams " . escapeshellarg($tempVideoPath);
            $jsonOutput = shell_exec($probeCmd);
            $data = json_decode($jsonOutput, true);
            //<editor-fold desc="debug">
            Debugger::debug($probeCmd,
                DebuggerMsgEnum::VAR->label('ffprobe command'),
                queueEnum: DebuggerQueueEnum::Chunking);
            Debugger::debug($jsonOutput,
                DebuggerMsgEnum::VAR->label('shell exec output from ffprobe'),
                queueEnum: DebuggerQueueEnum::Chunking);
            Debugger::debug($data,
                DebuggerMsgEnum::VAR->label('after decoding'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>

            //<editor-fold desc="debug">
            Debugger::debug(isset($data['streams']),
                DebuggerMsgEnum::VAR->label('is data streams exist'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            if (isset($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    $isCodecTypeVideo = $stream['codec_type'] === 'video';
                    $isCodecTypeAudio = $stream['codec_type'] === 'audio';
                    //<editor-fold desc="debug">
                    Debugger::debug($isCodecTypeVideo,
                        DebuggerMsgEnum::VAR->label('is stream CodecType = Video'),
                        queueEnum: DebuggerQueueEnum::Chunking);
                    Debugger::debug($isCodecTypeAudio,
                        DebuggerMsgEnum::VAR->label('is stream CodecType = Audio'),
                        queueEnum: DebuggerQueueEnum::Chunking);
                    //</editor-fold>
                    if ($isCodecTypeVideo) {
                        // If it's already H.264, we can COPY (Fast!)
                        $isCodecNameH264 = $stream['codec_name'] === 'h264';
                        //<editor-fold desc="debug">
                        Debugger::debug($isCodecNameH264,
                            DebuggerMsgEnum::VAR->label('is stream CodecName = H264'),
                            queueEnum: DebuggerQueueEnum::Chunking);
                        //</editor-fold>
                        if ($isCodecNameH264) {
                            $videoCodecFlag = 'copy';
                        }
                    } elseif ($isCodecTypeAudio) {
                        // If it's already AAC, we can COPY (Fast!)
                        $isCodecNameAac = $stream['codec_name'] === 'aac';
                        //<editor-fold desc="debug">
                        Debugger::debug($isCodecNameAac,
                            DebuggerMsgEnum::VAR->label('$is stream CodecName = Aac'),
                            queueEnum: DebuggerQueueEnum::Chunking);
                        //</editor-fold>
                        if ($isCodecNameAac) {
                            $audioCodecFlag = 'copy';
                        }
                    }
                }
            }

            Log::info("Video Processing Strategy: Video=[{$videoCodecFlag}] Audio=[{$audioCodecFlag}] for Video ID: {$videoId}");

            // --- STEP 4: RUN FFMPEG ---
            $playlistPath = "{$publicDir}/playlist.m3u8";

            //<editor-fold desc="debug">
            Debugger::debug($playlistPath,
                DebuggerMsgEnum::VAR->label('playlistPath in ProcessVideoJob'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>

            $cmd = [
                'ffmpeg',
                '-i', escapeshellarg($tempVideoPath),
                '-c:v', $videoCodecFlag,
                '-c:a', $audioCodecFlag,
                '-hls_time', '10',
                '-hls_base_url', config('app.cloud_base_url'),
                '-hls_list_size', '0',
                '-hls_key_info_file', escapeshellarg($keyInfoPath),
                '-hls_segment_filename', escapeshellarg("{$publicDir}/seg_%03d.ts"),
                escapeshellarg($playlistPath),
                '2>&1'
            ];

            $commandString = implode(' ', $cmd);
            exec($commandString, $output, $returnCode);
            //<editor-fold desc="debug">
            Debugger::debug($commandString,
                DebuggerMsgEnum::VAR->label('ffmpeg command string'),
                queueEnum: DebuggerQueueEnum::Chunking);
            Debugger::debug($output,
                DebuggerMsgEnum::VAR->label('ffmpeg output'),
                queueEnum: DebuggerQueueEnum::Chunking);
            Debugger::debug($returnCode,
                DebuggerMsgEnum::VAR->label('ffmpeg returnCode'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>

            // --- NEW: FALLBACK MECHANISM ---
            // If it failed AND we were trying to take the fast route (copy)...
            $isFastCopyFailed = $returnCode !== 0 && ($videoCodecFlag === 'copy' || $audioCodecFlag === 'copy');
            //<editor-fold desc="debug">
            Debugger::debug($isFastCopyFailed,
                DebuggerMsgEnum::VAR->label('is fast copy failed ?'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            if ($isFastCopyFailed) {
                Log::warning("Fast copy failed for Video ID: {$videoId} (likely corrupt packet). Falling back to re-encoding.");

                // Delete the partially generated files from the failed attempt
                array_map('unlink', glob("{$publicDir}/*.*"));

                // Re-create the initial key since we wiped the directory
                file_put_contents($initialKeyPath, $initialKey);

                // Swap out the 'copy' flags for the actual encoders
                $cmd[array_search('-c:v', $cmd) + 1] = 'libx264';
                $cmd[array_search('-c:a', $cmd) + 1] = 'aac';

                // Run it again
                $commandString = implode(' ', $cmd);
                $output = []; // Clear previous output
                exec($commandString, $output, $returnCode);

                //<editor-fold desc="debug">
                Debugger::debug($commandString,
                    DebuggerMsgEnum::VAR->label('new ffmpeg command string'),
                    queueEnum: DebuggerQueueEnum::Chunking);
                Debugger::debug($output,
                    DebuggerMsgEnum::VAR->label('new ffmpeg output'),
                    queueEnum: DebuggerQueueEnum::Chunking);
                Debugger::debug($returnCode,
                    DebuggerMsgEnum::VAR->label('new ffmpeg returnCode'),
                    queueEnum: DebuggerQueueEnum::Chunking);
                //</editor-fold>
            }

            // Handle Hard Errors (if re-encoding fails, or if it wasn't a copy to begin with)
            if ($returnCode !== 0) {
                //<editor-fold desc="debug">
                Debugger::debug('YES',
                    DebuggerMsgEnum::VAR->label('is returnCode 0'),
                    queueEnum: DebuggerQueueEnum::Chunking);
                //</editor-fold>
                $this->videoService->update($videoId, [
                    'status' => VideoStatusEnum::Failed->value,
                ], $this->video);
                Log::error("FFmpeg Failed: " . implode("\n", $output));
                throw new Exception('FFmpeg Failed');
            }

            // --- STEP 5: SECURE THE KEYS (The Cleanup) ---

            // 1. Generate a secure UUID for the key we just used
            $keyUuid = Str::uuid()->toString();
            $keyPath = "{$keysDir}/{$keyUuid}";
            $relativeKeyPath = str_replace(storage_path('app/private') . '/', '', $keyPath);
            //<editor-fold desc="debug">
            Debugger::debug($relativeKeyPath,
                DebuggerMsgEnum::VAR->label('keyUuid in ProcessVideoJob'),
                queueEnum: DebuggerQueueEnum::Chunking);
            Debugger::debug(file_exists($initialKeyPath),
                DebuggerMsgEnum::VAR->label('is initialKeyPath exist ?'),
                queueEnum: DebuggerQueueEnum::Chunking);
//</editor-fold>
            // 2. Move the initial key from Public -> Secure folder
            // Note: FFmpeg used "initial.key" physically. We move it now.
            if (file_exists($initialKeyPath)) {
                //<editor-fold desc="debug">
                Debugger::debug('renaming initialKeyPath to ' . "{$keysDir}/{$keyUuid}",
                    DebuggerMsgEnum::VAR->label('renaming initialKeyPath'),
                    queueEnum: DebuggerQueueEnum::Chunking);
                //</editor-fold>
                rename($initialKeyPath, $keyPath);
            }

            // 3. Update the Playlist to point to YOUR API
            //<editor-fold desc="debug">
            Debugger::debug(file_exists($playlistPath),
                DebuggerMsgEnum::VAR->label('Update the Playlist to point to YOUR API'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            if (file_exists($playlistPath)) {
                $content = file_get_contents($playlistPath);

                // Replace the placeholder URL with your actual Laravel Route
                // Ensure you have a named route 'api.video.key' defined in api.php
                $newUrl = config('app.cloud_base_url') . $relativeKeyPath;
                $content = str_replace($placeholderUrl, $newUrl, $content);
                //<editor-fold desc="debug">
                Debugger::debug($newUrl,
                    DebuggerMsgEnum::VAR->label('route url'),
                    queueEnum: DebuggerQueueEnum::Chunking);
                Debugger::debug($content,
                    DebuggerMsgEnum::VAR->label('playlist content'),
                    queueEnum: DebuggerQueueEnum::Chunking);
                //</editor-fold>

                file_put_contents($playlistPath, $content);
            }

            //<editor-fold desc="debug">
            Debugger::debug('remove temp_path: ' . $this->video->temp_path,
                DebuggerMsgEnum::VAR->label('remove temp_path:'),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
            // --- STEP 6: CLEANUP & FINISH ---
            Storage::delete($this->video->temp_path); // Delete original upload
            if (file_exists($keyInfoPath)) unlink($keyInfoPath); // Delete info file

            $this->videoService->update($videoId,
                [
                    'status' => VideoStatusEnum::Chunked->value,
                    'segments_count' => count(glob("{$publicDir}/seg_*.ts")),
                    'playlist_url' => $clientBaseUrl . "api/videos/{$videoId}/playlist.m3u8",
                    'key_path' => $relativeKeyPath,
                    'files_path' => $videoPath
                ], $this->video);
            //<editor-fold desc="debug">
            Debugger::debug('FINISHED ProcessVideoJob',
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>
        } catch (Exception $e) {
            Log::error("Error Processing Video ID: {$videoId} - " . $e->getMessage());

            // Only re-throw if we still have attempts left,
            // so the job gets re-queued by Laravel
//
            $isAttempLessThanTries = $this->attempts() < $this->tries;
            //<editor-fold desc="debug">
            Debugger::debug($isAttempLessThanTries,
                DebuggerMsgEnum::VAR->label('is attempLessThanTries ' . $this->tries),
                queueEnum: DebuggerQueueEnum::Chunking);
            //</editor-fold>

            //</editor-fold>
            if ($isAttempLessThanTries) {
                $this->videoService->update($videoId, [
                    'retries' => $this->attempts() - 1,
                    'status' => VideoStatusEnum::Pending->value,
                ], $this->video);
                throw $e; // triggers the retry
            } else
                $data = [
                    'status' => VideoStatusEnum::Failed->value,
                    'retries' => $this->attempts()
                ];

            $this->videoService->update($videoId, $data, $this->video);

            // On the final attempt, stay as 'failed' silently
        }
    }
}
