<?php

namespace App\Actions;

use App\Debugger;
use App\Domains\Video;
use App\Enums\DebuggerMsgEnum;
use App\Enums\DebuggerQueueEnum;
use App\Enums\VideoStatusEnum;
use App\Services\CloudService;
use App\Services\VideoService;
use Exception;
use Illuminate\Support\Facades\Cache;

class DownloadVideoAction
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly CloudService $cloudService,
        private readonly VideoService $videoService,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function handle(string $id, string $media_path): Video
    {
        $client = Cache::get('client_base_url_' . $id);
        //<editor-fold desc="debug">
        Debugger::debug($client,
            DebuggerMsgEnum::VAR->label('cache client_base_url_'),
            queueEnum: DebuggerQueueEnum::Downloading);
//</editor-fold>
        $clientName = $client['client_name'];
        $clientBaseUrl = $client['base_url'];
        $file_name = PATHINFO($media_path, PATHINFO_BASENAME);

        $relativeFilePath = $this->cloudService->downloadFile($media_path);
        //<editor-fold desc="debug">
        Debugger::debug($relativeFilePath,
            DebuggerMsgEnum::VAR->label('relativeFilePath'),
            queueEnum: DebuggerQueueEnum::Downloading);
//</editor-fold>
        $metadata = $this->videoService->getMetadata($clientBaseUrl, $relativeFilePath);
        //<editor-fold desc="debug">
        Debugger::debug($metadata,
            DebuggerMsgEnum::VAR->label('metadata'),
            queueEnum: DebuggerQueueEnum::Downloading);
//</editor-fold>
        //<editor-fold desc="debug">
        Debugger::debug($file_name,
            DebuggerMsgEnum::VAR->label('file_name'),
            queueEnum: DebuggerQueueEnum::Downloading);
//</editor-fold>
        $temp_path = $clientName . '/tmp/' . $file_name;
        //<editor-fold desc="debug">
        Debugger::debug($temp_path,
            DebuggerMsgEnum::VAR->label('temp_path'),
            queueEnum: DebuggerQueueEnum::Downloading);
//</editor-fold>
        $video = new Video($id, $file_name, $temp_path, VideoStatusEnum::Downloaded->value, $metadata);
        //<editor-fold desc="debug">
        Debugger::debug($video, DebuggerMsgEnum::VAR->label('video after new'),
            queueEnum: DebuggerQueueEnum::Downloading);
//</editor-fold>
        $res = $this->videoService->update($id, [
            'status' => $video->status,
            'metadata' => $video->metadata,
            'temp_path' => $video->temp_path,
        ]);
        //<editor-fold desc="debug">
        Debugger::response($res,
            DebuggerMsgEnum::RESPONSE->label('video update'),
            queueEnum: DebuggerQueueEnum::Downloading);
//</editor-fold>
        //<editor-fold desc="debug">
        Debugger::debug('from ' . $relativeFilePath . ' video moved to ' . $video->temp_path,
            DebuggerMsgEnum::VAR->label('video moved to'),
            queueEnum: DebuggerQueueEnum::Downloading);
        //</editor-fold>
        \Storage::disk('local')->move($relativeFilePath, $video->temp_path);
        return $video;
    }
}
