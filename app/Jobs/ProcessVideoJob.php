<?php

namespace App\Jobs;

use App\Domains\Video;
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

    public $timeout = 3600; // Allow 1 hour for processing
    public $tries = 3;        // 1 initial + 2 retries
    public $backoff = [60, 180]; // wait 1min before retry 1, 3min before retry 2

    public function __construct(
        private readonly Video        &$video,
        private readonly VideoService $videoService,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $videoId = $this->video->id;
        $client = Cache::get('client_base_url_' . $videoId);
        $clientName = $client['client_name'];
        $clientBaseUrl = $client['base_url'];

        $this->videoService->update($videoId, [
            'status' => 'processing',
        ], $this->video);

        try { // --- PATH CONFIGURATION ---
            $tempVideoPath = storage_path("app/{$this->video->temp_path}");
            $videoPath = $clientName . "/videos/{$videoId}";
            // Output Directory (Public for .m3u8 and .ts)
            $publicDir = storage_path('app/public/' . $videoPath);

            // Secure Directory (Private for .key files)
            $keysDir = storage_path("app/{$clientName}/keys");

            // Create Directories
            if (!is_dir($publicDir)) mkdir($publicDir, 0755, true);
            if (!is_dir($keysDir)) mkdir($keysDir, 0755, true); // Private!

            // --- STEP 1: GENERATE INITIAL KEY ---
            $initialKey = random_bytes(16);
            $initialKeyPath = "{$publicDir}/initial.key";
            file_put_contents($initialKeyPath, $initialKey);

            // --- STEP 2: CREATE KEY INFO FILE ---
            $keyInfoPath = storage_path("app/{$clientName}/temp/{$videoId}.keyinfo");
            // We use a placeholder URL here that we will replace in Step 5
            $placeholderUrl = "https://placeholder-url.com/key";

            // Format: URL \n Path
            file_put_contents($keyInfoPath, "{$placeholderUrl}\n{$initialKeyPath}");

            // --- STEP 3: INSPECT VIDEO (FFPROBE) ---

            // Default to "Safe Mode" (Re-encode)
            $videoCodecFlag = 'libx264';
            $audioCodecFlag = 'aac';

            // Run ffprobe to get stream info in JSON format
            $probeCmd = "ffprobe -v quiet -print_format json -show_streams " . escapeshellarg($tempVideoPath);
            $jsonOutput = shell_exec($probeCmd);
            $data = json_decode($jsonOutput, true);

            if (isset($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    if ($stream['codec_type'] === 'video') {
                        // If it's already H.264, we can COPY (Fast!)
                        if ($stream['codec_name'] === 'h264') {
                            $videoCodecFlag = 'copy';
                        }
                    } elseif ($stream['codec_type'] === 'audio') {
                        // If it's already AAC, we can COPY (Fast!)
                        if ($stream['codec_name'] === 'aac') {
                            $audioCodecFlag = 'copy';
                        }
                    }
                }
            }

            Log::info("Video Processing Strategy: Video=[{$videoCodecFlag}] Audio=[{$audioCodecFlag}] for Video ID: {$videoId}");

            // --- STEP 4: RUN FFMPEG ---
            $playlistPath = "{$publicDir}/playlist.m3u8";

            $cmd = [
                'ffmpeg',
                '-i', escapeshellarg($tempVideoPath),
                '-c:v', $videoCodecFlag,
                '-c:a', $audioCodecFlag,
                '-hls_time', '10',
                '-hls_list_size', '0',
                '-hls_key_info_file', escapeshellarg($keyInfoPath),
                '-hls_segment_filename', escapeshellarg("{$publicDir}/seg_%03d.ts"),
                escapeshellarg($playlistPath),
                '2>&1'
            ];

            $commandString = implode(' ', $cmd);
            exec($commandString, $output, $returnCode);

            // --- NEW: FALLBACK MECHANISM ---
            // If it failed AND we were trying to take the fast route (copy)...
            if ($returnCode !== 0 && ($videoCodecFlag === 'copy' || $audioCodecFlag === 'copy')) {
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
            }

            // Handle Hard Errors (if re-encoding fails, or if it wasn't a copy to begin with)
            if ($returnCode !== 0) {
                $this->videoService->update($videoId, [
                    'status' => 'failed',
                ], $this->video);
                Log::error("FFmpeg Failed: " . implode("\n", $output));
                throw new Exception('FFmpeg Failed');
            }

            // --- STEP 5: SECURE THE KEYS (The Cleanup) ---

            // 1. Generate a secure UUID for the key we just used
            $keyUuid = Str::uuid()->toString();

            // 2. Move the initial key from Public -> Secure folder
            // Note: FFmpeg used "initial.key" physically. We move it now.
            if (file_exists($initialKeyPath)) {
                rename($initialKeyPath, "{$keysDir}/{$keyUuid}");
            }

            // 3. Update the Playlist to point to YOUR API
            if (file_exists($playlistPath)) {
                $content = file_get_contents($playlistPath);

                // Replace the placeholder URL with your actual Laravel Route
                // Ensure you have a named route 'api.video.key' defined in api.php
                $newUrl = route('api.video.key', ['clientName' => $clientName, 'key' => $keyUuid]);
                $content = str_replace($placeholderUrl, $newUrl, $content);

                file_put_contents($playlistPath, $content);
            }

            // --- STEP 6: CLEANUP & FINISH ---
            Storage::delete($this->video->temp_path); // Delete original upload
            if (file_exists($keyInfoPath)) unlink($keyInfoPath); // Delete info file

            $this->videoService->update($videoId,
                [
                    'status' => 'completed',
                    'segments_count' => count(glob("{$publicDir}/seg_*.ts")),
                    'playlist_url' => $clientBaseUrl . "api/videos/{$videoId}/playlist.m3u8",
                    'files_path' => $videoPath
                ], $this->video);
        } catch (Exception $e) {
            Log::error("Error Processing Video ID: {$videoId} - " . $e->getMessage());

            // Only re-throw if we still have attempts left,
            // so the job gets re-queued by Laravel
//
            if ($this->attempts() < $this->tries) {
                $this->videoService->update($videoId, [
                    'retries' => $this->attempts() - 1,
                    'status' => 'pending',
                ], $this->video);
                throw $e; // triggers the retry
            } else
                $data = [
                    'status' => 'failed',
                    'retries' => $this->attempts()
                ];

            $this->videoService->update($videoId, $data, $this->video);

            // On the final attempt, stay as 'failed' silently
        }
    }
}
