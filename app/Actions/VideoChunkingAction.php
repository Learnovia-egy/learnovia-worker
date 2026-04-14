<?php

namespace App\Actions;

use App\Jobs\ProcessVideoJob;
use App\Models\ClientVideo;
use App\Services\CloudService;
use App\Services\VideoService;
use Exception;

readonly class VideoChunkingAction
{
    public function __construct(
        private DownloadVideoAction $DownloadVideoAction,
        private VideoService        $videoService,
        private CloudService        $cloudService,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function handle(int $client_video_id): void
    {
        $clientVideo = ClientVideo::find($client_video_id);
        $video = $this->DownloadVideoAction->handle($clientVideo->video_id, $clientVideo->media_path);
        ProcessVideoJob::dispatchSync($video, $this->videoService);

        if ($video->status == 'completed') {
            $this->videoService->update($clientVideo->video_id, ['status' => 'uploading'], $video);
            $this->cloudService->uploadKeyAndChunkedFiles($video);
            $this->videoService->update($clientVideo->video_id, ['status' => 'uploaded']);
        }
        \Cache::forget('client_base_url_' . $clientVideo->video_id);

    }
}
