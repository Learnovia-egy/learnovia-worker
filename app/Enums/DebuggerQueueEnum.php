<?php

namespace App\Enums;
enum DebuggerQueueEnum: string
{
    case VideoChunkingController = 'Handling Video Chunking Request';
    case CloudVideoProcess = 'Handling cloud video process';

    case Downloading = 'Downloading from cloud';

    case Chunking = 'Chunking Process';
    case Uploading = 'Uploading to cloud';

    case Callback = 'Callback: apis';
}
