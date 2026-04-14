<?php

namespace App\Services;

use App\Domains\Video;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class VideoService
{
    public function __construct()
    {
    }

    public function __invoke(): void
    {
        //
    }

    public function getMetadata(string $clientBaseUrl, string $filePath): array
    {
        try {
            // 3. Open the file from STORAGE
            $media = FFMpeg::fromDisk('local')->open($filePath);

            $videoStream = $media->getVideoStream();

            // 4. Safe Metadata Extraction
            $metadata = [
                'bitrate' => $videoStream->get('bit_rate') ?? 0,
                'resolution' => $videoStream->getDimensions()->getWidth() . 'x' . $videoStream->getDimensions()->getHeight(),
                'codec' => $videoStream->get('codec_name'),
                'duration' => $media->getDurationInSeconds()
            ];

            Http::post($clientBaseUrl . 'api/video', [

                'metadata' => $metadata
            ]);
            return $metadata;
        } catch (Exception $e) {
            // Cleanup: If FFmpeg fails, delete the file
            // Note: Since we explicitly saved to 'local', we explicitly delete from 'local'
//            Storage::disk('local')->delete($tempPath);
            Log::channel('uncompleted_uploads')
                ->error("Unable to process video file: " . $e->getMessage());
            throw new Exception("Unable to process video file: " . $e->getMessage());
        }
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function update(string $id, $data, ?Video &$video = null): LazyPromise|PromiseInterface|Response
    {
        $client = Cache::get('client_base_url_' . $id);
        $clientBaseUrl = $client['base_url'];
        $res = Http::patch($clientBaseUrl . 'api/video/' . $id, $data);
        if ($res->failed()) {
            // slack webhook
            throw new Exception('Video processing failed');
        }
        if ($video !== null) {
            foreach ($data as $key => $value) {
                $video->$key = $value;
            }
        }
        return $res;
    }
}
