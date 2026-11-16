<?php

namespace App\Tests\Unit;

use App\Tests\Support\TestDatabase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TestDatabaseTest extends TestCase
{
    #[DataProvider('unsafeTargets')]
    public function testUnsafeDatabaseIsRefused(string $environment, string $database): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabase')->willReturn($database);
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(\RuntimeException::class);
        TestDatabase::guard($connection, $environment);
    }

    public static function unsafeTargets(): iterable
    {
        yield ['dev', 'cyber3_test'];
        yield ['prod', 'cyber3_test'];
        yield ['test', 'app'];
        yield ['test', 'cyber3_test_backup'];
    }

    public function testDedicatedTestDatabaseIsAccepted(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('getDatabase')->willReturn('cyber3_test');
        TestDatabase::guard($connection, 'test');
    }
}
