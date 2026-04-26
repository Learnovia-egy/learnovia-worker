<?php

namespace App\Enums;

enum VideoStatusEnum: string
{

    case Pending = 'Pending';
    case Downloaded = 'Processing.Downloading';
    case Chunking = 'Processing.Chunking';
    case Chunked = 'Processing.Chunked';
    case Uploading = 'Processing.Uploading';
    case Processed = 'processed';
    case Failed = 'Failed';
}
