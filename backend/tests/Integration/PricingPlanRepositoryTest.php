<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Module\Pricing\Dto\PricingPlanInput;
use App\Module\Pricing\Repository\PricingPlanRepository;
use App\Module\Pricing\Service\PricingService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Validation\ValidationException;
use App\Tests\Support\Factory\TenantFactory;
use Yiisoft\Db\Query\Query;

/**
 * Cennik jako dane WSPOLNE dla calego systemu.
 *
 * Ten test jest lustrzanym odbiciem ClientRepositoryTest. Tam sprawdzamy, ze
 * biuro NIE widzi danych innego biura; tutaj - ze cennik jest widoczny tak samo
 * z kazdego kontekstu, bo nie nalezy do zadnego biura. Brak filtrowania po
 * tenancie jest w tym jednym miejscu decyzja projektowa, a nie przeoczeniem,
 * i chcemy, zeby test pekl, gdyby ktos "poprawil" repozytorium, dokladajac
 * do niego trait TenantScoped.
 */
final class PricingPlanRepositoryTest extends DatabaseTestCase
{
    private PricingPlanRepository $repository;
    private PricingService $service;
    private TenantFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new TenantFactory($this->db, $this->tenantContext);
        $this->repository = new PricingPlanRepository($this->db);
        $this->service = new PricingService(
            $this->db,
            $this->repository,
            new AuditLogger($this->db, $this->tenantContext),
        );
    }

    public function testCennikJestTenSamNiezaleznieOdBiura(): void
    {
        $pierwszeBiuro = $this->factory->create('Biuro A');
        $this->service->create($this->input(['code' => 'start', 'name' => 'Start']));

        // Inne biuro - ten sam cennik, bo cennik nalezy do operatora systemu.
        $drugieBiuro = $this->factory->create('Biuro B');
        $widziane = $this->repository->findAll();

        self::assertCount(1, $widziane);
        self::assertSame('Start', $widziane[0]->name);

        $this->factory->switchTo($pierwszeBiuro);
        self::assertCount(1, $this->repository->findAll());
        self::assertNotSame($pierwszeBiuro, $drugieBiuro);
    }

    public function testPubliczneWejscieNieZwracaPlanowUkrytych(): void
    {
        $this->factory->create();

        $this->service->create($this->input(['code' => 'widoczny', 'name' => 'Widoczny']));
        $this->service->create($this->input([
            'code' => 'ukryty',
            'name' => 'Ukryty',
            'isActive' => false,
        ]));

        $publiczne = $this->repository->findAll(onlyActive: true);
        self::assertCount(1, $publiczne);
        self::assertSame('Widoczny', $publiczne[0]->name);

        // Administrator musi widziec oba - inaczej nie mialby jak wlaczyc
        // ukrytego planu z powrotem.
        self::assertCount(2, $this->repository->findAll(onlyActive: false));
    }

    public function testPlanySaSortowaneWedlugKolejnosci(): void
    {
        $this->factory->create();

        $this->service->create($this->input(['code' => 'trzeci', 'name' => 'Trzeci', 'position' => 30]));
        $this->service->create($this->input(['code' => 'pierwszy', 'name' => 'Pierwszy', 'position' => 10]));
        $this->service->create($this->input(['code' => 'drugi', 'name' => 'Drugi', 'position' => 20]));

        $nazwy = array_map(static fn($plan) => $plan->name, $this->repository->findAll());
        self::assertSame(['Pierwszy', 'Drugi', 'Trzeci'], $nazwy);
    }

    public function testPunktyPlanuZachowujaKolejnoscIsaPodmieniane(): void
    {
        $this->factory->create();

        $plan = $this->service->create($this->input([
            'features' => ['Pierwszy punkt', 'Drugi punkt', 'Trzeci punkt'],
        ]));

        self::assertSame(['Pierwszy punkt', 'Drugi punkt', 'Trzeci punkt'], $plan->features);

        // Edycja podmienia caly zestaw, a nie dokleja do starego.
        $zmieniony = $this->service->update((int) $plan->id, $this->input([
            'features' => ['Nowy punkt'],
        ]));

        self::assertSame(['Nowy punkt'], $zmieniony->features);
        self::assertSame(1, (int) (new Query($this->db))->from('pricing_plan_features')->count());
    }

    public function testUsunieciePlanuKasujeJegoPunkty(): void
    {
        $this->factory->create();

        $plan = $this->service->create($this->input(['features' => ['A', 'B']]));
        $this->service->delete((int) $plan->id);

        self::assertNull($this->repository->findById((int) $plan->id));
        // Klucz obcy ma ON DELETE CASCADE - punkty nie zostaja sierotami.
        self::assertSame(0, (int) (new Query($this->db))->from('pricing_plan_features')->count());
    }

    public function testKodPlanuJestUnikalnyWCalymSystemie(): void
    {
        $this->factory->create('Biuro A');
        $this->service->create($this->input(['code' => 'start']));

        // Nawet z innego biura - kod jest globalny, bo cennik jest jeden.
        $this->factory->create('Biuro B');

        $this->expectException(ValidationException::class);
        $this->service->create($this->input(['code' => 'start', 'name' => 'Inny']));
    }

    public function testZmianaCenyTrafiaDoDziennikaZmian(): void
    {
        $this->factory->create();

        $plan = $this->service->create($this->input(['priceMonthly' => 14900]));
        $this->service->update((int) $plan->id, $this->input(['priceMonthly' => 19900]));

        $wpisy = (new Query($this->db))
            ->from('audit_log')
            ->where(['entity_type' => PricingService::ENTITY_TYPE, 'entity_id' => $plan->id])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        self::assertCount(2, $wpisy);
        self::assertSame('create', $wpisy[0]['action']);
        self::assertSame('update', $wpisy[1]['action']);

        $zmiany = json_decode((string) $wpisy[1]['changes'], true);
        self::assertSame(14900, $zmiany['priceMonthly']['from']);
        self::assertSame(19900, $zmiany['priceMonthly']['to']);
    }

    public function testPustaCenaOznaczaWyceneIndywidualna(): void
    {
        $this->factory->create();

        $plan = $this->service->create($this->input(['priceMonthly' => null, 'priceYearly' => null]));

        self::assertNull($plan->priceMonthly);
        self::assertNull($plan->priceYearly);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function input(array $overrides = []): PricingPlanInput
    {
        return PricingPlanInput::fromArray($overrides + [
            'code' => 'plan-testowy',
            'name' => 'Plan testowy',
            'tagline' => 'Opis planu.',
            'priceMonthly' => 14900,
            'priceYearly' => 149000,
            'currency' => 'PLN',
            'ctaLabel' => 'Wybierz',
            'isFeatured' => false,
            'isActive' => true,
            'position' => 10,
            'features' => ['Punkt A'],
        ]);
    }
}
