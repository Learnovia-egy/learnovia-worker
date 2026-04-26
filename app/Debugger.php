<?php

namespace app;

use App\Enums\DebuggerMsgEnum;
use App\Enums\DebuggerQueueEnum;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Promises\LazyPromise;

class Debugger
{
    public static $method = 'ds';

    public static function debug($data, ?string $msg = null, ?string $custom_method = null, ?DebuggerQueueEnum $queueEnum = null): void
    {
        if (config('app.debug')) {
            $method = $custom_method ?: self::$method;
            match ($method) {
                'dd' => self::dd($data),
                'dump' => self::dump($data),
                'ds' => self::ds($data, $msg, $queueEnum),
                'print' => self::print($data, $msg),
            };
        }
    }

    public static function dd($data)
    {
        dd($data);
    }

    public static function dump($data)
    {
        dump($data);
    }

    public static function ds($data, $msg, ?DebuggerQueueEnum $queueEnum = null): void
    {
        $i = 1;
        do {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $caller = $backtrace[$i];
            $file = basename($caller['file']);
            $i++;
        } while ($file == basename(__FILE__));

        $file = basename($caller['file']);
        if (is_null($msg))
            ds($data);
        else {
            ds($data)->toScreen($queueEnum?->value ?? 'no queue');
            ds($msg . '__' . $file)->toScreen($queueEnum?->value ?? 'no queue');
        }
    }

    public static function response(LazyPromise|PromiseInterface|\Illuminate\Http\Client\Response|null $res, string $label, ?DebuggerQueueEnum $queueEnum = null): void
    {
        if (is_null($res)) self::debug(null, $label);
        else
            static::debug([
                'body' => $res->body(),
                'status' => $res->status()
            ], $label,
                queueEnum: $queueEnum);
    }

    private static function ddPrint($data)
    {
        echo print_r($data, true);
        die();
    }

    private static function print($data)
    {
        echo print_r($data, true);
    }

    public static function exception(\Throwable $e, string $label = 'exception', ?DebuggerQueueEnum $queueEnum = null): void
    {
        static::debug([
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace(),
        ], DebuggerMsgEnum::Exception->label($label),
            queueEnum: $queueEnum
        );
    }

}
