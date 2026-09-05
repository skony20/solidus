<?php

declare(strict_types=1);

namespace App\Module\Pricing\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Cennik ze strony informacyjnej: `pricing_plans` i `pricing_plan_features`.
 *
 * CELOWY WYJATEK OD WZORCA: te tabele NIE MAJA kolumny `tenant_id`.
 *
 * Cennik jest wlasnoscia operatora Solidusa, nie biura rachunkowego - ta sama
 * jedna lista planow jest pokazywana wszystkim odwiedzajacym strone, takze
 * niezalogowanym. Dopisanie tu `tenant_id` sugerowaloby, ze kazde biuro ma
 * wlasny cennik, i wymagaloby wyboru "czyj" cennik pokazac anonimowemu
 * gosciowi - pytanie bez sensownej odpowiedzi.
 *
 * Konsekwencja: repozytorium tych tabel swiadomie nie uzywa traitu
 * TenantScoped, a zapis chroni {@see \App\Shared\Auth\PlatformAdminMiddleware}.
 *
 * Ceny trzymamy w GROSZACH (BIGINT), nie w DECIMAL i nie w liczbach
 * zmiennoprzecinkowych - kwota pieniezna w `float` predzej czy pozniej
 * wyswietli sie jako 149.99999997.
 */
final class M250905120000CreatePricingPlans implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    private const OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('pricing_plans', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            // Stabilny identyfikator tekstowy ("start", "biuro", "siec").
            // Nazwa handlowa moze sie zmieniac, kod nie - po nim odwoluje sie
            // do planu kod aplikacji i przyszla integracja z platnosciami.
            'code' => 'VARCHAR(40) NOT NULL',
            'name' => 'VARCHAR(80) NOT NULL',
            'tagline' => 'VARCHAR(160) NULL',
            // Ceny w groszach. NULL = plan bez ceny w cenniku ("wycena
            // indywidualna"), co jest czyms innym niz cena 0 zl.
            'price_monthly' => 'BIGINT NULL',
            'price_yearly' => 'BIGINT NULL',
            'currency' => "CHAR(3) NOT NULL DEFAULT 'PLN'",
            'cta_label' => 'VARCHAR(60) NULL',
            // Plan wyrozniony wizualnie - "najczesciej wybierany".
            'is_featured' => 'TINYINT(1) NOT NULL DEFAULT 0',
            // Ukrycie planu zamiast usuwania: historia cen zostaje, a plan
            // znika ze strony natychmiast.
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'position' => 'INT NOT NULL DEFAULT 0',
            'created_at' => 'DATETIME(6) NOT NULL',
            'updated_at' => 'DATETIME(6) NOT NULL',
        ], self::OPTIONS);

        $b->createIndex('pricing_plans', 'ux_pricing_plans_code', ['code'], 'UNIQUE');
        // Strona pobiera plany aktywne w kolejnosci wyswietlania - jeden indeks
        // obsluguje i filtr, i sortowanie.
        $b->createIndex('pricing_plans', 'ix_pricing_plans_active_position', ['is_active', 'position']);

        $b->createTable('pricing_plan_features', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'plan_id' => 'BIGINT NOT NULL',
            'text' => 'VARCHAR(200) NOT NULL',
            'position' => 'INT NOT NULL DEFAULT 0',
        ], self::OPTIONS);

        $b->createIndex('pricing_plan_features', 'ix_pricing_features_plan', ['plan_id', 'position']);
        // CASCADE: punkty cennika nie maja sensu bez planu, ktorego dotycza.
        $b->addForeignKey(
            'pricing_plan_features',
            'fk_pricing_features_plan',
            'plan_id',
            'pricing_plans',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('pricing_plan_features');
        $b->dropTable('pricing_plans');
    }
}
