<?php

declare(strict_types=1);

use App\Lib\Redis\RedisConnector;

/**
 * Cadena de guardado del handler nativo de phpredis. Las credenciales van
 * codificadas porque viajan dentro de una query string.
 *
 * @param array{host:string,port:int,username:string,password:string,db:int,prefix:string,connect_timeout:float,read_timeout:float} $config
 */
function redis_session_save_path(array $config): string
{
    $params = [
        'timeout'      => $config['connect_timeout'],
        'read_timeout' => $config['read_timeout'],
        'database'     => $config['db'],
        'prefix'       => $config['prefix'] . 'sess:',
    ];

    $path = sprintf('tcp://%s:%d?%s', $config['host'], $config['port'], http_build_query($params, '', '&', PHP_QUERY_RFC3986));

    if ($config['password'] !== '') {
        if ($config['username'] !== '') {
            $path .= '&auth[]=' . rawurlencode($config['username']);
        }
        $path .= '&auth[]=' . rawurlencode($config['password']);
    }

    return $path;
}

/**
 * Debe llamarse antes de session_start(). Si Redis no responde devuelve 'files':
 * arrancar la sesión contra un Redis caído la dejaría vacía en silencio.
 */
function configure_session_storage(): string
{
    if (strtolower((string) env('SESSION_DRIVER', 'file')) !== 'redis') {
        return 'files';
    }

    $redis = RedisConnector::fromEnv();
    if ($redis === null) {
        return 'files';
    }

    try {
        $redis->ping();
    } catch (\RedisException $e) {
        error_log('Redis no disponible para sesiones, se usan archivos: ' . $e->getMessage());

        return 'files';
    }

    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', redis_session_save_path(RedisConnector::configFromEnv()));
    // El TTL de la clave en Redis es gc_maxlifetime: debe superar la inactividad que impone la app.
    ini_set('session.gc_maxlifetime', (string) ((int) env('SESSION_TIMEOUT', 1800) + 300));
    // Rechaza IDs de sesión inventados por el cliente (mitiga session fixation).
    ini_set('session.use_strict_mode', '1');
    // Mantiene el bloqueo por sesión del handler files (CSRF rotativo + peticiones AJAX concurrentes).
    ini_set('redis.session.locking_enabled', '1');
    ini_set('redis.session.lock_wait_time', '20000');
    ini_set('redis.session.lock_retries', '100');

    return 'redis';
}
