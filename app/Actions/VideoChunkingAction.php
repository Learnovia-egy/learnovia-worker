<?php

namespace App\Actions;

use App\Enums\DebuggerMsgEnum;
use App\Debugger;
use App\Enums\VideoStatusEnum;
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
        //<editor-fold desc="debug">
        Debugger::debug($client_video_id, DebuggerMsgEnum::VAR->label('client_video_id'));
        //</editor-fold>
        $clientVideo = ClientVideo::find($client_video_id);
        //<editor-fold desc="debug">
        Debugger::debug($clientVideo, DebuggerMsgEnum::VAR->label('clientVideo after find'));
        //</editor-fold>
        $video = $this->DownloadVideoAction->handle($clientVideo->video_id, $clientVideo->media_path);
        //<editor-fold desc="debug">
        Debugger::debug($video, DebuggerMsgEnum::VAR->label('video after download'));
        //</editor-fold>

        new ProcessVideoJob($video, $this->videoService)->handle();
        //<editor-fold desc="debug">
        Debugger::debug($video, DebuggerMsgEnum::VAR->label('video after ProcessVideoJob'));
        //</editor-fold>

        if ($video->status == 'completed') {
            $this->videoService->update($clientVideo->video_id, ['status' => VideoStatusEnum::Uploading->value], $video);
            $this->cloudService->uploadKeyAndChunkedFiles($video);
            $this->videoService->update($clientVideo->video_id, ['status' => VideoStatusEnum::Processed->value]);
        }
        \Cache::forget('client_base_url_' . $clientVideo->video_id);

    }
}
