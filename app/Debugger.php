<?php

namespace App;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Promises\LazyPromise;

class Debugger
{
    public static $method = 'ds';

    public static function debug($data, ?string $msg = null, ?string $custom_method = null): void
    {
        if (config('app.debug')) {
            $method = $custom_method ?: self::$method;
            match ($method) {
                'dd' => self::dd($data),
                'dump' => self::dump($data),
                'ds' => self::ds($data, $msg),
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

    public static function ds($data, $msg): void
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
        else
            ds($data)->toScreen($file)->label($msg);
    }

    public static function response(LazyPromise|PromiseInterface|\Illuminate\Http\Client\Response|null $res, string $label)
    {
        if (is_null($res)) self::debug(null, $label);
        else
            static::debug([
                'body' => $res->body(),
                'status' => $res->status()
            ], $label);
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

    public static function exception(\Throwable $e, string $label = 'exception'): void
    {
        static::debug([
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace(),
        ], DebuggerMsgEnum::Exception->label($label));
    }

}
