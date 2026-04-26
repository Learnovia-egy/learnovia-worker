<?php

namespace App\Enums;
enum DebuggerMsgEnum: string
{
    case FAILED_RESPONSE = 'Failed Response';
    case OBJECT_CREATION = 'Object Creation';
    case REQUEST = 'Request Data';

    case VAR = 'Variable';

    case RESPONSE = 'Response';

    case Exception = 'Exception:';

    public function label(string $additionalMsg = ''): string
    {
        return match ($this) {
            self::FAILED_RESPONSE => 'Failed Response, ' . $additionalMsg,
            self::OBJECT_CREATION => $additionalMsg . ' Creation',
            self::REQUEST => $additionalMsg . ' Request',
            self::VAR => $additionalMsg . ' ' . self::VAR->value,
            self::RESPONSE => $additionalMsg . ' Response',
            self::Exception => $additionalMsg . ' ' . self::Exception->value,
        };
    }
}
