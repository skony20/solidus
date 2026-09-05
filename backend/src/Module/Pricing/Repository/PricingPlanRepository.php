<?php

declare(strict_types=1);

namespace App\Module\Pricing\Repository;

use App\Module\Pricing\Entity\PricingPlan;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * Dostep do tabel `pricing_plans` i `pricing_plan_features`.
 *
 * SWIADOMY BRAK TRAITU TenantScoped. Kazde inne repozytorium domenowe w
 * Solidusie filtruje po `tenant_id`; to nie filtruje, bo cennik jest jeden dla
 * calego systemu i musi byc czytelny takze dla anonimowego goscia strony.
 * Ochrona zapisu nie lezy wiec w SQL, tylko w warstwie HTTP:
 * {@see \App\Shared\Auth\PlatformAdminMiddleware}.
 */
final readonly class PricingPlanRepository
{
    public const TABLE = 'pricing_plans';
    public const FEATURES_TABLE = 'pricing_plan_features';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /**
     * @param bool $onlyActive Strona publiczna pyta o true, panel administratora o false.
     *
     * @return PricingPlan[]
     */
    public function findAll(bool $onlyActive = true): array
    {
        $query = (new Query($this->db))
            ->from(self::TABLE)
            ->orderBy(['position' => SORT_ASC, 'id' => SORT_ASC]);

        if ($onlyActive) {
            $query->where(['is_active' => 1]);
        }

        $rows = $query->all();

        if ($rows === []) {
            return [];
        }

        // Punkty wszystkich planow jednym zapytaniem, nie jednym na plan -
        // przy trzech planach roznica jest zadna, ale ten wzorzec kopiuje sie
        // pozniej do miejsc, gdzie wierszy sa tysiace.
        $featuresByPlan = $this->featuresFor(array_map(static fn(array $row): int => (int) $row['id'], $rows));

        return array_map(
            static fn(array $row): PricingPlan => PricingPlan::fromRow($row, $featuresByPlan[(int) $row['id']] ?? []),
            $rows,
        );
    }

    public function findById(int $id): ?PricingPlan
    {
        $row = (new Query($this->db))->from(self::TABLE)->where(['id' => $id])->one();

        if ($row === null) {
            return null;
        }

        return PricingPlan::fromRow($row, $this->featuresFor([$id])[$id] ?? []);
    }

    public function existsWithCode(string $code, ?int $excludeId = null): bool
    {
        $query = (new Query($this->db))->from(self::TABLE)->where(['code' => $code]);

        if ($excludeId !== null) {
            $query->andWhere(['<>', 'id', $excludeId]);
        }

        return $query->exists();
    }

    /**
     * @return int Identyfikator nowo utworzonego planu.
     */
    public function insert(PricingPlan $plan): int
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $this->db->createCommand()->insert(self::TABLE, $this->columns($plan) + [
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        $id = (int) $this->db->getLastInsertId();
        $this->replaceFeatures($id, $plan->features);

        return $id;
    }

    public function update(int $id, PricingPlan $plan): void
    {
        $this->db->createCommand()->update(
            self::TABLE,
            $this->columns($plan) + ['updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u')],
            ['id' => $id],
        )->execute();

        $this->replaceFeatures($id, $plan->features);
    }

    public function delete(int $id): void
    {
        // Punkty planu usuwa klucz obcy (ON DELETE CASCADE).
        $this->db->createCommand()->delete(self::TABLE, ['id' => $id])->execute();
    }

    /**
     * Podmienia caly zestaw punktow planu.
     *
     * Kasowanie i wstawianie od nowa zamiast wyliczania roznicy: punkty nie maja
     * wlasnej tozsamosci w interfejsie (administrator edytuje liste jako calosc),
     * a serwis wola te metode wewnatrz transakcji, wiec czytajacy nigdy nie
     * zobaczy planu bez punktow.
     *
     * @param string[] $features
     */
    private function replaceFeatures(int $planId, array $features): void
    {
        $this->db->createCommand()->delete(self::FEATURES_TABLE, ['plan_id' => $planId])->execute();

        foreach (array_values($features) as $index => $text) {
            $this->db->createCommand()->insert(self::FEATURES_TABLE, [
                'plan_id' => $planId,
                'text' => $text,
                'position' => ($index + 1) * 10,
            ])->execute();
        }
    }

    /**
     * @param int[] $planIds
     *
     * @return array<int, string[]> Punkty pogrupowane po identyfikatorze planu.
     */
    private function featuresFor(array $planIds): array
    {
        if ($planIds === []) {
            return [];
        }

        $rows = (new Query($this->db))
            ->from(self::FEATURES_TABLE)
            ->where(['plan_id' => $planIds])
            ->orderBy(['plan_id' => SORT_ASC, 'position' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['plan_id']][] = (string) $row['text'];
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(PricingPlan $plan): array
    {
        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'tagline' => $plan->tagline,
            'price_monthly' => $plan->priceMonthly,
            'price_yearly' => $plan->priceYearly,
            'currency' => $plan->currency,
            'cta_label' => $plan->ctaLabel,
            'is_featured' => $plan->isFeatured ? 1 : 0,
            'is_active' => $plan->isActive ? 1 : 0,
            'position' => $plan->position,
        ];
    }
}
