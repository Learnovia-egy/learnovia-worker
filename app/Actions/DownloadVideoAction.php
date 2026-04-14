<?php

namespace App\Actions;

use App\Domains\Video;
use App\Services\CloudService;
use App\Services\VideoService;
use App\VideoModel;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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
        $clientName = $client['client_name'];
        $file_name = PATHINFO($media_path, PATHINFO_BASENAME);

        $relativeFilePath = $this->cloudService->downloadFile($clientName, $media_path);
        $metadata = $this->videoService->getMetadata($relativeFilePath);
        $temp_path = $clientName . '/tmp/' . $file_name;
        $video = new Video($id, $file_name, $temp_path, 'downloaded', $metadata);

        $this->videoService->update($id, [
            'status' => $video->status,
            'metadata' => $video->metadata,
            'temp_path' => $video->temp_path,
        ]);
        Storage::disk('local')->move($relativeFilePath, $video->temp_path);
        return $video;
    }
}
