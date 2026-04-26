<?php

namespace App\Actions;

use App\Debugger;
use App\Enums\DebuggerMsgEnum;
use App\Enums\DebuggerQueueEnum;
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
        Debugger::debug($client_video_id,
            DebuggerMsgEnum::VAR->label('client_video_id'),
            queueEnum: DebuggerQueueEnum::CloudVideoProcess
        );
        //</editor-fold>
        $clientVideo = ClientVideo::find($client_video_id);
        //<editor-fold desc="debug">
        Debugger::debug($clientVideo,
            DebuggerMsgEnum::VAR->label('clientVideo after find'),
            queueEnum: DebuggerQueueEnum::CloudVideoProcess
        );
        //</editor-fold>
        $video = $this->DownloadVideoAction->handle($clientVideo->video_id, $clientVideo->media_path);
        //<editor-fold desc="debug">
        Debugger::debug($video,
            DebuggerMsgEnum::VAR->label('video after download'),
            queueEnum: DebuggerQueueEnum::CloudVideoProcess);
        //</editor-fold>

        new ProcessVideoJob($video, $this->videoService)->handle();
        //<editor-fold desc="debug">
        Debugger::debug($video,
            DebuggerMsgEnum::VAR->label('video after ProcessVideoJob'),
            queueEnum: DebuggerQueueEnum::CloudVideoProcess);
        //</editor-fold>

        if ($video->status == VideoStatusEnum::Chunked->value) {
            $this->videoService->update($clientVideo->video_id, ['status' => VideoStatusEnum::Uploading->value], $video);
            $this->cloudService->uploadKeyAndChunkedFiles($video);
            $this->videoService->update($clientVideo->video_id, ['status' => VideoStatusEnum::Processed->value]);
        }
        \Cache::forget($clientVideo->video_id);
        //<editor-fold desc="debug">
        Debugger::debug('Clear Cache: ' . $clientVideo->video_id, queueEnum: DebuggerQueueEnum::CloudVideoProcess);
        //</editor-fold>

    }
}
