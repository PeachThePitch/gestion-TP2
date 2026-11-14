<?php

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;

final class TestDatabase
{
    public static function guard(Connection $connection, string $environment): void
    {
        // Vérification avant toute suppression, y compris lors de la préparation du schéma.
        if ($environment !== 'test' || !preg_match('/_test(?:[0-9]+)?$/D', $connection->getDatabase() ?? '')) {
            throw new \RuntimeException('Opération refusée : environnement ou base de tests non dédié.');
        }
    }
}
