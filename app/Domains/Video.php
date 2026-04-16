<?php

namespace App\Domains;

class Video
{
    public function __construct(
        public string  $id,
        public string  $name,
        public string  $temp_path,
        public string  $status,
        public array   $metadata,
        public ?string $files_path = null,
        public ?string $key_path = null
    )
    {
    }
}
