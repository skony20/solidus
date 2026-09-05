<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Module\Account\Entity\TenantStatus;
use App\Module\Account\Repository\TenantRepository;
use App\Module\Account\Repository\UserRepository;
use App\Module\Platform\Dto\TenantPaymentInput;
use App\Module\Platform\Entity\PaymentStatus;
use App\Module\Platform\Repository\TenantAdminRepository;
use App\Module\Platform\Repository\TenantPaymentRepository;
use App\Module\Platform\Service\TenantAdminService;
use App\Module\Platform\Service\TenantNotFoundException;
use App\Module\Pricing\Dto\PricingPlanInput;
use App\Module\Pricing\Repository\PricingPlanRepository;
use App\Module\Pricing\Service\PricingService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Validation\ValidationException;
use App\Tests\Support\Factory\TenantFactory;
use Yiisoft\Db\Query\Query;

/**
 * Panel operatora: przeglad WSZYSTKICH biur, zmiana ich stanu, przypisanie
 * planu z katalogu i reczna historia platnosci.
 *
 * Lustrzane odbicie testu izolacji z ClientRepositoryTest, ale w druga strone:
 * tam sprawdzamy, ze biuro NIE widzi cudzych danych; tutaj - ze operator widzi
 * WSZYSTKIE biura na raz, niezaleznie od tego, na ktore aktualnie wskazuje
 * TenantContext. To jest svaidome zaprzeczenie zasadzie "kazde repozytorium
 * filtruje po tenant_id", udokumentowane w ARCHITECTURE.md sekcja 2.11 -
 * ten test ma pekniec, gdyby ktos "poprawil" TenantAdminRepository, dokladajac
 * do niego TenantScoped.
 */
final class TenantAdminServiceTest extends DatabaseTestCase
{
    private TenantAdminService $service;
    private TenantRepository $tenants;
    private UserRepository $users;
    private PricingService $pricing;
    private TenantFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new TenantFactory($this->db, $this->tenantContext);
        $this->tenants = new TenantRepository($this->db);
        $this->users = new UserRepository($this->db);

        $auditLogger = new AuditLogger($this->db, $this->tenantContext);
        $pricingRepository = new PricingPlanRepository($this->db);

        $this->pricing = new PricingService($this->db, $pricingRepository, $auditLogger);
        $this->service = new TenantAdminService(
            new TenantAdminRepository($this->db),
            $this->tenants,
            new TenantPaymentRepository($this->db),
            $pricingRepository,
            $auditLogger,
        );
    }

    public function testListaWidziWszystkieBiuraNiezaleznieOdBiezacegoKontekstu(): void
    {
        $this->factory->create('Biuro A');
        $this->factory->create('Biuro B');

        // Kontekst wskazuje na "Biuro B" (ostatnio zalozone), a mimo to
        // operator widzi oba - w odroznieniu od kazdego innego repozytorium
        // w systemie, ktore zwrocilyby tylko dane biezacego tenanta.
        $nazwy = array_map(static fn($o) => $o->tenant->name, $this->service->list(null, null, 50, 0));

        self::assertCount(2, $nazwy);
        self::assertContains('Biuro A', $nazwy);
        self::assertContains('Biuro B', $nazwy);
    }

    public function testFiltrowaniePoStatusieZwracaWylacznieDopasowane(): void
    {
        $aktywne = $this->factory->create('Aktywne');
        $zawieszone = $this->factory->create('Zawieszone');

        $this->service->changeStatus($zawieszone, TenantStatus::Suspended->value);

        $wynik = $this->service->list(null, TenantStatus::Suspended->value, 50, 0);

        self::assertCount(1, $wynik);
        self::assertSame('Zawieszone', $wynik[0]->tenant->name);
        self::assertNotSame($aktywne, $zawieszone);
    }

    public function testZmianaStatusuBlokujeLogowanieWgEnuma(): void
    {
        $tenantId = $this->factory->create('Biuro testowe');

        // Domyslny stan po zalozeniu w tescie to 'active' (default kolumny) -
        // logowanie ma byc mozliwe.
        $przed = $this->service->find($tenantId);
        self::assertTrue($przed->tenant->status->allowsLogin());

        $this->service->changeStatus($tenantId, TenantStatus::Suspended->value);

        $po = $this->service->find($tenantId);
        self::assertSame(TenantStatus::Suspended, $po->tenant->status);
        self::assertFalse($po->tenant->status->allowsLogin());
    }

    public function testNieznanyStatusRzucaWyjatekWalidacji(): void
    {
        $tenantId = $this->factory->create();

        $this->expectException(ValidationException::class);
        $this->service->changeStatus($tenantId, 'nie-taki-status');
    }

    public function testPrzypisaniePlanuAktualizujePowiazanieITekstowaNazwe(): void
    {
        $tenantId = $this->factory->create();
        $plan = $this->pricing->create($this->planInput(['code' => 'biuro', 'name' => 'Biuro']));

        $wynik = $this->service->assignPlan($tenantId, (int) $plan->id);

        self::assertSame((int) $plan->id, $wynik->tenant->pricingPlanId);
        self::assertSame('biuro', $wynik->tenant->plan);
        self::assertSame('Biuro', $wynik->planName);

        // Odpiecie od katalogu - biuro zostaje bez powiazania, ale nie znika.
        $odpiete = $this->service->assignPlan($tenantId, null);
        self::assertNull($odpiete->tenant->pricingPlanId);
        self::assertSame('custom', $odpiete->tenant->plan);
    }

    public function testPrzypisanieNieistniejacegoPlanuRzucaWyjatekWalidacji(): void
    {
        $tenantId = $this->factory->create();

        $this->expectException(ValidationException::class);
        $this->service->assignPlan($tenantId, 999999);
    }

    public function testUsunieciePlanuZCennikaNieKasujeBiura(): void
    {
        $tenantId = $this->factory->create();
        $plan = $this->pricing->create($this->planInput(['code' => 'znikajacy']));
        $this->service->assignPlan($tenantId, (int) $plan->id);

        $this->pricing->delete((int) $plan->id);

        // ON DELETE SET NULL - biuro zostaje, traci tylko powiazanie z katalogiem.
        $po = $this->service->find($tenantId);
        self::assertNull($po->tenant->pricingPlanId);
    }

    public function testRecznaPlatnoscTrafiaDoHistoriiIAuditLogu(): void
    {
        $tenantId = $this->factory->create('Biuro placace');
        $operatorId = $this->users->create($tenantId, 'admin@solidus.pl', 'haslo1234567890', 'Operator')->id;

        // Daty wzgledem "dzis", a nie na sztywno - test ma dzialac tego
        // samego dnia niezaleznie od tego, kiedy jest uruchamiany.
        $dzis = new \DateTimeImmutable('today');
        $poczatek = $dzis->modify('-3 days');
        $koniec = $dzis->modify('+27 days');

        $input = TenantPaymentInput::fromArray([
            'amount' => 34900,
            'currency' => 'PLN',
            'periodStart' => $poczatek->format('Y-m-d'),
            'periodEnd' => $koniec->format('Y-m-d'),
            'status' => 'paid',
            'provider' => 'manual',
            'note' => 'Przelew z 3.09',
        ]);

        $payment = $this->service->recordPayment($tenantId, $input, (int) $operatorId);

        self::assertSame(34900, $payment->amount);
        self::assertSame(PaymentStatus::Paid, $payment->status);

        $historia = $this->service->paymentsFor($tenantId);
        self::assertCount(1, $historia);
        self::assertSame('Przelew z 3.09', $historia[0]->note);

        // Data "oplacone do" w przegladzie ma odzwierciedlac ta platnosc,
        // a poniewaz koniec okresu jest w przyszlosci - biuro jest na biezaco.
        $przeglad = $this->service->find($tenantId);
        self::assertSame($koniec->format('Y-m-d'), $przeglad->paidUntil?->format('Y-m-d'));
        self::assertTrue($przeglad->toArray()['isPaidUpToDate']);

        $wpisy = (new Query($this->db))
            ->from('audit_log')
            ->where(['entity_type' => TenantAdminService::ENTITY_TYPE_PAYMENT, 'entity_id' => $payment->id])
            ->all();

        self::assertCount(1, $wpisy);
        self::assertSame('create', $wpisy[0]['action']);
    }

    public function testPrzeterminowanaPlatnoscNieJestUznawanaZaAktualna(): void
    {
        $tenantId = $this->factory->create('Biuro zalegajace');
        $operatorId = $this->users->create($tenantId, 'admin@solidus.pl', 'haslo1234567890', 'Operator')->id;

        $koniec = (new \DateTimeImmutable('today'))->modify('-400 days');
        $poczatek = $koniec->modify('-29 days');

        $this->service->recordPayment($tenantId, TenantPaymentInput::fromArray([
            'amount' => 14900,
            'periodStart' => $poczatek->format('Y-m-d'),
            'periodEnd' => $koniec->format('Y-m-d'),
        ]), (int) $operatorId);

        $przeglad = $this->service->find($tenantId);

        self::assertNotNull($przeglad->paidUntil);
        self::assertFalse($przeglad->toArray()['isPaidUpToDate']);
    }

    public function testKoniecOkresuWczesniejszyNizPoczatekJestBledemWalidacji(): void
    {
        $this->expectException(ValidationException::class);

        TenantPaymentInput::fromArray([
            'amount' => 100,
            'periodStart' => '2026-09-30',
            'periodEnd' => '2026-09-01',
        ]);
    }

    public function testUzytkownicyBiuraZwracajaWylacznieMetadaneKonta(): void
    {
        $tenantId = $this->factory->create('Biuro z zespolem');
        $this->users->create($tenantId, 'a@biuro.pl', 'haslo1234567890', 'Anna');
        $this->users->create($tenantId, 'b@biuro.pl', 'haslo1234567890', 'Bartek', ['member']);

        $uzytkownicy = $this->service->usersFor($tenantId);

        self::assertCount(2, $uzytkownicy);
        foreach ($uzytkownicy as $u) {
            // Sam ksztalt odpowiedzi jest dowodem: nie ma tu nic poza
            // identyfikacja konta. Zadnego pola z danymi klientow biura.
            self::assertSame(['id', 'email', 'name', 'roles', 'isActive', 'createdAt'], array_keys($u));
        }
    }

    public function testNieistniejaceBiuroRzucaWyjatekNotFound(): void
    {
        $this->expectException(TenantNotFoundException::class);
        $this->service->find(999999);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function planInput(array $overrides = []): PricingPlanInput
    {
        return PricingPlanInput::fromArray($overrides + [
            'code' => 'plan-testowy-' . bin2hex(random_bytes(3)),
            'name' => 'Plan testowy',
            'features' => ['Punkt A'],
        ]);
    }
}
