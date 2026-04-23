<?php

namespace App\Services;

use App\ClientCacheRepo;
use App\Debugger;
use App\Domains\Video;
use App\Enums\DebuggerMsgEnum;
use App\Enums\DebuggerQueueEnum;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
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

    /**
     * @throws Exception
     */
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

            try {
                Http::timeout(10)
                    ->post($clientBaseUrl . 'api/video', [
                        'metadata' => $metadata
                    ]);
            } catch (Exception $e) {
                Log::channel('uncompleted_uploads')
                    ->warning('Failed to post metadata, skipping: ' . $e->getMessage());
            }

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
     * @throws Exception
     */
    public function update(string $id, $data, ?Video &$video = null): LazyPromise|PromiseInterface|Response|null
    {
        $client = ClientCacheRepo::get($id);
        $clientBaseUrl = $client['base_url'];
        //<editor-fold desc="debug">
        Debugger::debug($data, DebuggerMsgEnum::REQUEST->label('video update'),
            queueEnum: DebuggerQueueEnum::Callback);
//</editor-fold>

        if ($video !== null) {
            foreach ($data as $key => $value) {
                if (property_exists($video, $key))
                    $video->$key = $value;
            }
        }
        $api = $clientBaseUrl . 'api/videos/' . $id;
        //<editor-fold desc="debug">
        Debugger::debug($video, DebuggerMsgEnum::VAR->label('video updated'),
            queueEnum: DebuggerQueueEnum::Callback);
        Debugger::debug($api, DebuggerMsgEnum::VAR->label('callback video update api'),
            queueEnum: DebuggerQueueEnum::Callback);
        //</editor-fold>

        try {
            $res = Http::timeout(30)->patch($api, $data);
            if ($res->failed()) {
                //<editor-fold desc="debug">
                Debugger::response($res,
                    DebuggerMsgEnum::RESPONSE->label('video update failed'),
                    queueEnum: DebuggerQueueEnum::Callback);
                //</editor-fold>
            }
            //<editor-fold desc="debug">
            Debugger::debug($res,
                DebuggerMsgEnum::RESPONSE->label('video api update response'),
                queueEnum: DebuggerQueueEnum::Callback);
            //</editor-fold>
            return $res;
        } catch (Exception $e) {
            Log::warning('Video update skipped, could not reach client: ' . $e->getMessage());
            return null;
        }
    }
}
