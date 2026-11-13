<?php

declare(strict_types=1);

use App\Lib\Cache\CacheInterface;
use App\Lib\Cache\FileCache;
use App\Lib\Cache\NullCache;
use App\Lib\Cache\RedisCache;
use App\Lib\Redis\RedisConnector;

function appCache(): CacheInterface
{
    static $cache = null;

    if ($cache instanceof CacheInterface) {
        return $cache;
    }

    // Saltar verificación del directorio si el caché está explícitamente deshabilitado.
    if (!filter_var(env('CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
        $cache = new NullCache();
        return $cache;
    }

    if (strtolower((string) env('CACHE_DRIVER', 'file')) === 'redis') {
        $redis   = RedisConnector::fromEnv();
        $version = (string) env('CACHE_VERSION', 'v1');
        if (!RedisConnector::isValidNamespace($version)) {
            $version = 'v1';
        }

        $cache = $redis !== null
            ? new RedisCache($redis, RedisConnector::configFromEnv()['prefix'] . $version . ':cache:')
            : new NullCache();
        return $cache;
    }

    $cachePath = __DIR__ . '/../../storage/cache';
    if (!is_dir($cachePath)) {
        if (!mkdir($cachePath, 0777, true) && !is_dir($cachePath)) {
            trigger_error('Unable to create cache directory: ' . $cachePath, E_USER_WARNING);
            $cache = new NullCache();
            return $cache;
        }
    }

    if (!is_writable($cachePath)) {
        @chmod($cachePath, 0777);
    }

    if (!is_writable($cachePath)) {
        trigger_error('Cache directory is not writable: ' . $cachePath, E_USER_WARNING);
        $cache = new NullCache();
        return $cache;
    }

    $cache = new FileCache($cachePath);

    return $cache;
}
