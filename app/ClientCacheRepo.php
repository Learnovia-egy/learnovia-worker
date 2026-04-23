<?php

namespace App;

use Psr\SimpleCache\InvalidArgumentException;

class ClientCacheRepo
{

    public static function put(string $string, array $data): void
    {
        self::driver()->put($string, $data);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function get(string $string)
    {
        return self::driver()->get($string);
    }

    public static function driver()
    {
        return \Cache::driver('client_base');
    }
}
