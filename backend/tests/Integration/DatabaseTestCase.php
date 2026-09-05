<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Tenant\TenantContext;
use PHPUnit\Framework\TestCase;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
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

    /**
     * Testy uzywaja WYLACZNIE zmiennych TEST_DB_*, nigdy DB_* aplikacji.
     *
     * Rozdzielenie jest celowe: setUp() kasuje i tworzy tabele od nowa.
     * Gdyby konfiguracja testow dziedziczyla DB_NAME ze srodowiska (a w
     * kontenerze php dziedziczy), uruchomienie testow skasowaloby dane
     * robocze deweloperowi. Dodatkowo nizej stoi bezpiecznik na nazwe bazy.
     */
    private static function createConnection(): ConnectionInterface
    {
        $database = (string) (getenv('TEST_DB_NAME') ?: 'solidus_test');

        if (!str_contains($database, 'test')) {
            self::fail(
                "Odmawiam uruchomienia testow na bazie \"{$database}\" - jej nazwa nie zawiera "
                . '"test", a testy kasuja tabele. Ustaw TEST_DB_NAME na baze testowa.',
            );
        }

        $dsn = new Dsn(
            host: (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
            databaseName: $database,
            port: (string) (getenv('TEST_DB_PORT') ?: '3306'),
            options: ['charset' => 'utf8mb4'],
        );

        return new Connection(
            new Driver(
                (string) $dsn,
                (string) (getenv('TEST_DB_USER') ?: 'solidus'),
                (string) (getenv('TEST_DB_PASSWORD') ?: 'solidus'),
            ),
            new SchemaCache(new ArrayCache()),
        );
    }

    /**
     * Kazdy test dostaje swiezy schemat - dzieki temu kolejnosc testow
     * nie ma znaczenia i zaden nie dziedziczy smieci po poprzednim.
     */
    private function createSchema(): void
    {
        $this->dropSchema();

        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // Cennik NAJPIERW - `tenants.pricing_plan_id` ma do niego klucz obcy.
        $this->db->createCommand()->createTable('pricing_plans', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'code' => 'VARCHAR(40) NOT NULL',
            'name' => 'VARCHAR(80) NOT NULL',
            'tagline' => 'VARCHAR(160) NULL',
            'price_monthly' => 'BIGINT NULL',
            'price_yearly' => 'BIGINT NULL',
            'currency' => "CHAR(3) NOT NULL DEFAULT 'PLN'",
            'cta_label' => 'VARCHAR(60) NULL',
            'is_featured' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'position' => 'INT NOT NULL DEFAULT 0',
            'created_at' => 'DATETIME(6) NOT NULL',
            'updated_at' => 'DATETIME(6) NOT NULL',
        ], $options)->execute();

        $this->db->createCommand()
            ->createIndex('pricing_plans', 'ux_pricing_plans_code', ['code'], 'UNIQUE')
            ->execute();

        $this->db->createCommand()->createTable('pricing_plan_features', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'plan_id' => 'BIGINT NOT NULL',
            'text' => 'VARCHAR(200) NOT NULL',
            'position' => 'INT NOT NULL DEFAULT 0',
        ], $options)->execute();

        $this->db->createCommand()->addForeignKey(
            'pricing_plan_features',
            'fk_pricing_features_plan',
            'plan_id',
            'pricing_plans',
            'id',
            'CASCADE',
            'CASCADE',
        )->execute();

        $this->db->createCommand()->createTable('tenants', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'name' => 'VARCHAR(255) NOT NULL',
            'slug' => 'VARCHAR(100) NOT NULL',
            'plan' => "VARCHAR(50) NOT NULL DEFAULT 'starter'",
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
            'pricing_plan_id' => 'BIGINT NULL',
            'created_at' => 'DATETIME(6) NOT NULL',
        ], $options)->execute();

        $this->db->createCommand()
            ->addForeignKey('tenants', 'fk_tenants_pricing_plan', 'pricing_plan_id', 'pricing_plans', 'id', 'SET NULL', 'CASCADE')
            ->execute();

        // Uzytkownicy - potrzebni PricingPlanRepositoryTest nie sa, ale
        // TenantAdminServiceTest musi umiec zalozyc konto pracownika biura
        // i platnosc z `recorded_by_user_id` wskazujacym na nie.
        $this->db->createCommand()->createTable('users', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'tenant_id' => 'BIGINT NOT NULL',
            'email' => 'VARCHAR(255) NOT NULL',
            'password_hash' => 'VARCHAR(255) NOT NULL',
            'name' => 'VARCHAR(255) NOT NULL',
            'roles' => 'JSON NOT NULL',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'created_at' => 'DATETIME(6) NOT NULL',
            'updated_at' => 'DATETIME(6) NOT NULL',
        ], $options)->execute();

        $this->db->createCommand()
            ->addForeignKey('users', 'fk_users_tenant', 'tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE')
            ->execute();

        $this->db->createCommand()->createTable('tenant_payments', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'tenant_id' => 'BIGINT NOT NULL',
            'amount' => 'BIGINT NOT NULL',
            'currency' => "CHAR(3) NOT NULL DEFAULT 'PLN'",
            'period_start' => 'DATE NOT NULL',
            'period_end' => 'DATE NOT NULL',
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'paid'",
            'provider' => "VARCHAR(30) NOT NULL DEFAULT 'manual'",
            'provider_reference' => 'VARCHAR(120) NULL',
            'note' => 'VARCHAR(255) NULL',
            'recorded_by_user_id' => 'BIGINT NULL',
            'created_at' => 'DATETIME(6) NOT NULL',
        ], $options)->execute();

        $this->db->createCommand()
            ->addForeignKey('tenant_payments', 'fk_tenant_payments_tenant', 'tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE')
            ->execute();
        $this->db->createCommand()
            ->addForeignKey('tenant_payments', 'fk_tenant_payments_recorded_by', 'recorded_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE')
            ->execute();

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
        // Kolejnosc odwrotna do tworzenia - kazda tabela znika, zanim
        // usuniemy ta, do ktorej ma klucz obcy.
        $tables = [
            'tenant_payments',
            'audit_log',
            'clients',
            'users',
            'tenants',
            'pricing_plan_features',
            'pricing_plans',
        ];

        foreach ($tables as $table) {
            $this->db->createCommand('DROP TABLE IF EXISTS ' . $this->db->getQuoter()->quoteTableName($table))->execute();
        }
    }
}
