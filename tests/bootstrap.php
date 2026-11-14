<?php
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env', 'test');
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? '') !== 'test') {
    throw new RuntimeException('Les tests doivent tourner exclusivement en environnement test.');
}
