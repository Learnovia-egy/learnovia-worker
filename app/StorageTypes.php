<?php

namespace app;

final class StorageTypes
{
    const ASSIGNMENT = 'assignment';

    const FILE = 'files';

    const MEDIA = 'media';

    const ANNOUNCEMENT = 'announcement';

    const COURSE = 'course';

    const LOGO = 'logo';

    const USER = 'User';

    const FOR_EDITOR = 'for-editor';
    const GALLERY = 'gallery';
    const DEFAULT = 'public';

    public static function all(): array
    {
        return [
            self::ASSIGNMENT,
            self::FILE,
            self::MEDIA,
            self::ANNOUNCEMENT,
            self::COURSE,
            self::LOGO,
            self::USER,
            self::FOR_EDITOR,
            self::GALLERY,
        ];
    }
}
