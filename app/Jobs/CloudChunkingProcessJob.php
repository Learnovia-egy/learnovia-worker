<?php

namespace App\Jobs;

use App\Actions\VideoChunkingAction;
use App\Debugger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CloudChunkingProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $client_video_id,
    )
    {
        //
    }

    /**
     * Execute the job.
     * @throws \Exception
     */
    public function handle(VideoChunkingAction $videoChunkingAction): void
    {
        try {
            $videoChunkingAction->handle($this->client_video_id);
        } catch (\Throwable $e) {
            //<editor-fold desc="debug">
            Debugger::exception($e, 'job exception');
            //</editor-fold>
            throw new \Exception($e->getMessage());
        }
    }
}
