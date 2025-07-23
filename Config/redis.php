<?php

require_once __DIR__.'/../vendor/autoload.php';

use Predis\Client as PredisClient;
use Dotenv\Dotenv;

class Redis {
    private static $client = null;

    public static function getClient() {
        if (self::$client === null) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->load();

            self::$client = new PredisClient([
                // "scheme" => $_ENV['REDIS_SCHEME'],
                "host" => $_ENV['REDIS_HOST'],
                "port" => $_ENV['REDIS_PORT'],
                'database' => $_ENV['REDIS_DATABASE'],
                'username' => $_ENV['REDIS_USERNAME'],
                "password" => $_ENV['REDIS_PASSWORD'],
            ]);
        }

        return self::$client;
    }
}
