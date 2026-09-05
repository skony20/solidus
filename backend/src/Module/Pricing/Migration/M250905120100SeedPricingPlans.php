<?php

declare(strict_types=1);

namespace App\Module\Pricing\Migration;

use DateTimeImmutable;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Startowa zawartosc cennika.
 *
 * Migracja z danymi, a nie fixture testowy: sekcja cennika na stronie
 * informacyjnej ma dzialac zaraz po postawieniu srodowiska, a pusta strona
 * sprzedazowa jest bledem, nie stanem poczatkowym. Te wartosci sa
 * PROPOZYCJA - administrator systemu zmienia je w panelu, bez wdrozenia kodu.
 *
 * `down()` kasuje wylacznie plany o tych kodach, wiec cofniecie migracji nie
 * zabiera ze soba planow dodanych pozniej przez administratora.
 */
final class M250905120100SeedPricingPlans implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    private const CODES = ['start', 'biuro', 'siec'];

    public function up(MigrationBuilder $b): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $plans = [
            [
                'code' => 'start',
                'name' => 'Start',
                'tagline' => 'Dla jednoosobowego biura, które musi mieć AML w porządku.',
                'price_monthly' => 14900,
                'price_yearly' => 149000,
                'cta_label' => 'Zacznij za darmo',
                'is_featured' => 0,
                'position' => 10,
                'features' => [
                    'Do 30 klientów',
                    'Ocena ryzyka AML z harmonogramem przeglądów',
                    'Rejestr szkoleń i certyfikatów',
                    'Dokumentacja gotowa do kontroli GIIF',
                    'Wsparcie e-mail',
                ],
            ],
            [
                'code' => 'biuro',
                'name' => 'Biuro',
                'tagline' => 'Dla biura z zespołem — podział obowiązków i pełny audyt zmian.',
                'price_monthly' => 34900,
                'price_yearly' => 349000,
                'cta_label' => 'Wybierz plan Biuro',
                'is_featured' => 1,
                'position' => 20,
                'features' => [
                    'Do 150 klientów',
                    'Wszystko z planu Start',
                    'Konta dla zespołu z uprawnieniami',
                    'Kanał zgłoszeń sygnalistów',
                    'Delegacje i rozliczenia podróży',
                    'Dziennik zmian (audit log) bez limitu',
                    'Wsparcie priorytetowe',
                ],
            ],
            [
                'code' => 'siec',
                'name' => 'Sieć',
                'tagline' => 'Dla grup biur i franczyz. Wycena zależy od skali.',
                'price_monthly' => null,
                'price_yearly' => null,
                'cta_label' => 'Porozmawiajmy',
                'is_featured' => 0,
                'position' => 30,
                'features' => [
                    'Bez limitu klientów',
                    'Wszystko z planu Biuro',
                    'Wiele biur pod jednym kontem',
                    'Integracje na zamówienie (KSeF, Fakturownia)',
                    'Umowa SLA i dedykowany opiekun',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            $features = $plan['features'];
            unset($plan['features']);

            $b->insert('pricing_plans', $plan + [
                'currency' => 'PLN',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Identyfikator planu bierzemy z polaczenia, bo insert() go nie zwraca.
            $planId = (int) $b->getDb()->getLastInsertId();

            foreach (array_values($features) as $index => $text) {
                $b->insert('pricing_plan_features', [
                    'plan_id' => $planId,
                    'text' => $text,
                    // Skok co 10 zostawia miejsce na wstawienie punktu miedzy
                    // istniejace bez przenumerowania calej listy.
                    'position' => ($index + 1) * 10,
                ]);
            }
        }
    }

    public function down(MigrationBuilder $b): void
    {
        // Punkty znikaja same - klucz obcy ma ON DELETE CASCADE.
        $b->delete('pricing_plans', ['code' => self::CODES]);
    }
}
