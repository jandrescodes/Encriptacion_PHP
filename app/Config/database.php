<?php

declare(strict_types=1);

namespace App\Config;

class Database
{
    private static ?\mysqli $instance = null;

    public static function getConnection(): \mysqli
    {
        if (self::$instance === null) {
            $conn = new \mysqli(
                env('DB_HOST', 'localhost'),
                env('DB_USERNAME', 'root'),
                env('DB_PASSWORD', ''),
                env('DB_DATABASE')
            );

            if ($conn->connect_error) {
                error_log("DB connection failed: " . $conn->connect_error);
                http_response_code(503);
                require __DIR__ . '/../../views/errors/500.php';
                exit;
            }

            self::$instance = $conn;
        }

        return self::$instance;
    }
}

$connection = Database::getConnection();
