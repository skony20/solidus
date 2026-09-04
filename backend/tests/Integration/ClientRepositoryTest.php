<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Module\Client\Repository\ClientRepository;
use App\Module\Client\Service\ClientService;
use App\Shared\Audit\AuditLogger;
use App\Tests\Support\Factory\TenantFactory;
use Yiisoft\Db\Query\Query;

/**
 * Izolacja danych miedzy biurami rachunkowymi.
 *
 * To najwazniejszy test w calym projekcie: gdyby jedno biuro zobaczylo
 * klientow drugiego, byloby to naruszenie ochrony danych, a nie zwykly blad.
 * Dlatego sprawdzamy kazda sciezke dostepu osobno - liste, pojedynczy odczyt,
 * zapis i usuniecie.
 */
final class ClientRepositoryTest extends DatabaseTestCase
{
    private ClientRepository $repository;
    private ClientService $service;
    private TenantFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new TenantFactory($this->db, $this->tenantContext);
        $this->repository = new ClientRepository($this->db, $this->tenantContext);
        $this->service = new ClientService(
            $this->repository,
            new AuditLogger($this->db, $this->tenantContext),
            $this->tenantContext,
        );
    }

    public function testListaZwracaWylacznieKlientowBiezacegoTenanta(): void
    {
        $pierwszeBiuro = $this->factory->create('Biuro A');
        $this->service->create(TenantFactory::clientInput(['name' => 'Klient biura A']));

        $drugieBiuro = $this->factory->create('Biuro B');
        $this->service->create(TenantFactory::clientInput(['name' => 'Klient biura B']));

        // Kontekst wskazuje na drugie biuro - widzimy tylko jego klienta.
        $widziane = $this->repository->findAll();
        self::assertCount(1, $widziane);
        self::assertSame('Klient biura B', $widziane[0]->name);

        // ...a po przelaczeniu wracamy do klienta pierwszego biura.
        $this->factory->switchTo($pierwszeBiuro);
        $widziane = $this->repository->findAll();
        self::assertCount(1, $widziane);
        self::assertSame('Klient biura A', $widziane[0]->name);

        // W bazie sa oba wiersze - filtruje repozytorium, nie brak danych.
        self::assertSame(2, (int) (new Query($this->db))->from('clients')->count());
        self::assertNotSame($pierwszeBiuro, $drugieBiuro);
    }

    public function testNieMoznaOdczytacKlientaPoIdZInnegoTenanta(): void
    {
        $this->factory->create('Biuro A');
        $obcyKlient = $this->service->create(TenantFactory::clientInput(['name' => 'Klient biura A']));

        $this->factory->create('Biuro B');

        // Znamy identyfikator, ale nalezy do innego biura - ma byc niewidoczny.
        self::assertNull($this->repository->findById((int) $obcyKlient->id));
    }

    public function testEdycjaKlientaZInnegoTenantaNieZmieniaDanych(): void
    {
        $pierwszeBiuro = $this->factory->create('Biuro A');
        $obcyKlient = $this->service->create(TenantFactory::clientInput(['name' => 'Nazwa oryginalna']));

        $this->factory->create('Biuro B');

        // Serwis nie znajduje encji w cudzym tenancie, wiec zglasza brak zasobu.
        $this->expectException(\App\Module\Client\Service\ClientNotFoundException::class);

        try {
            $this->service->update((int) $obcyKlient->id, TenantFactory::clientInput(['name' => 'Podmieniona']));
        } finally {
            $this->factory->switchTo($pierwszeBiuro);
            $poProbie = $this->repository->findById((int) $obcyKlient->id);
            self::assertNotNull($poProbie);
            self::assertSame('Nazwa oryginalna', $poProbie->name);
        }
    }

    public function testUsuniecieKlientaZInnegoTenantaNieKasujeWiersza(): void
    {
        $pierwszeBiuro = $this->factory->create('Biuro A');
        $obcyKlient = $this->service->create(TenantFactory::clientInput(['name' => 'Klient biura A']));

        $this->factory->create('Biuro B');

        // Nawet gdyby ktos ominal serwis i wywolal repozytorium wprost,
        // warunek po tenant_id sprawia, ze DELETE nie trafia w zaden wiersz.
        $this->repository->delete((int) $obcyKlient->id);

        $this->factory->switchTo($pierwszeBiuro);
        self::assertNotNull($this->repository->findById((int) $obcyKlient->id));
    }

    public function testUnikalnoscNipObowiazujeTylkoWObrebieBiura(): void
    {
        $this->factory->create('Biuro A');
        $this->service->create(TenantFactory::clientInput(['nip' => '5270000001']));

        $this->factory->create('Biuro B');

        // Ta sama firma moze byc obslugiwana przez dwa rozne biura.
        self::assertFalse($this->repository->existsWithNip('5270000001'));

        $drugi = $this->service->create(TenantFactory::clientInput(['nip' => '5270000001']));
        self::assertNotNull($drugi->id);
    }

    public function testKazdaOperacjaZapisuSieDoDziennikaZWlasciwymTenantem(): void
    {
        $tenantId = $this->factory->create('Biuro A');

        $klient = $this->service->create(TenantFactory::clientInput(['name' => 'Przed zmiana']));
        $this->service->update((int) $klient->id, TenantFactory::clientInput(['name' => 'Po zmianie']));
        $this->service->delete((int) $klient->id);

        $wpisy = (new Query($this->db))
            ->from('audit_log')
            ->where(['tenant_id' => $tenantId, 'entity_type' => 'client'])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        self::assertCount(3, $wpisy);
        self::assertSame(['create', 'update', 'delete'], array_column($wpisy, 'action'));

        // Dziennik ma zapisac tylko to pole, ktore faktycznie sie zmienilo.
        $zmiany = json_decode((string) $wpisy[1]['changes'], true);
        self::assertArrayHasKey('name', $zmiany);
        self::assertSame('Przed zmiana', $zmiany['name']['from']);
        self::assertSame('Po zmianie', $zmiany['name']['to']);
    }
}
