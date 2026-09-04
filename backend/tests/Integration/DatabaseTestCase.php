<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Tenant\TenantContext;
use PHPUnit\Framework\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;
use Yiisoft\Db\Mysql\Dsn;

/**
 * Baza dla testow, ktore faktycznie dotykaja MySQL-a.
 *
 * Izolacja tenantow to reguła egzekwowana przez bazę (klucze obce, indeksy,
 * warunki WHERE), więc test na atrapie repozytorium niczego by nie dowiódł -
 * sprawdzałby wyłącznie własną atrapę. Dlatego te testy potrzebują prawdziwej
 * bazy i pomijają się, gdy jej nie ma.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected ConnectionInterface $db;
    protected TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = self::createConnection();

        try {
            $this->db->open();
        } catch (\Throwable $e) {
            self::markTestSkipped(
                'Brak polaczenia z MySQL (' . $e->getMessage() . '). '
                . 'Uruchom: docker compose -f docker/docker-compose.yml up -d mysql',
            );
        }

        $this->createSchema();
        $this->tenantContext = new TenantContext();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        $this->db->close();

        parent::tearDown();
    }

    private static function createConnection(): ConnectionInterface
    {
        $dsn = new Dsn(
            host: (string) (getenv('DB_HOST') ?: '127.0.0.1'),
            databaseName: (string) (getenv('DB_NAME') ?: 'solidus_test'),
            port: (string) (getenv('DB_PORT') ?: '3306'),
            options: ['charset' => 'utf8mb4'],
        );

        return new Connection(
            new Driver((string) $dsn, (string) (getenv('DB_USER') ?: 'root'), (string) (getenv('DB_PASSWORD') ?: 'root')),
            new \Yiisoft\Db\Cache\SchemaCache(new \Yiisoft\Cache\ArrayCache()),
        );
    }

    /**
     * Kazdy test dostaje swiezy schemat - dzieki temu kolejnosc testow
     * nie ma znaczenia i zaden nie dziedziczy smieci po poprzednim.
     */
    private function createSchema(): void
    {
        $this->dropSchema();

        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

        $this->db->createCommand()->createTable('tenants', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'name' => 'VARCHAR(255) NOT NULL',
            'slug' => 'VARCHAR(100) NOT NULL',
            'plan' => "VARCHAR(50) NOT NULL DEFAULT 'starter'",
            'created_at' => 'DATETIME(6) NOT NULL',
        ], $options)->execute();

        $this->db->createCommand()->createTable('clients', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'tenant_id' => 'BIGINT NOT NULL',
            'name' => 'VARCHAR(255) NOT NULL',
            'nip' => 'VARCHAR(10) NULL',
            'email' => 'VARCHAR(255) NULL',
            'phone' => 'VARCHAR(30) NULL',
            'address' => 'VARCHAR(255) NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'lead'",
            'notes' => 'TEXT NULL',
            'created_at' => 'DATETIME(6) NOT NULL',
            'updated_at' => 'DATETIME(6) NOT NULL',
        ], $options)->execute();

        $this->db->createCommand()->createTable('audit_log', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'tenant_id' => 'BIGINT NOT NULL',
            'user_id' => 'BIGINT NULL',
            'entity_type' => 'VARCHAR(100) NOT NULL',
            'entity_id' => 'BIGINT NOT NULL',
            'action' => "ENUM('create','update','delete') NOT NULL",
            'changes' => 'JSON NOT NULL',
            'ip' => 'VARCHAR(45) NULL',
            'created_at' => 'DATETIME(6) NOT NULL',
        ], $options)->execute();
    }

    private function dropSchema(): void
    {
        foreach (['audit_log', 'clients', 'tenants'] as $table) {
            $this->db->createCommand('DROP TABLE IF EXISTS ' . $this->db->getQuoter()->quoteTableName($table))->execute();
        }
    }
}
