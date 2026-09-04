<?php

declare(strict_types=1);

namespace App\Tests\Support\Factory;

use App\Module\Client\Dto\ClientInput;
use App\Shared\Tenant\TenantContext;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Fabryka danych testowych.
 *
 * Tworzy tenanta i od razu osadza go w kontekscie zadania - bez tego kroku
 * repozytoria rzucaja wyjatkiem, bo nie wiedza, czyje dane maja czytac.
 */
final readonly class TenantFactory
{
    public function __construct(
        private ConnectionInterface $db,
        private TenantContext $tenantContext,
    ) {}

    /**
     * Zaklada tenanta i przelacza na niego kontekst.
     *
     * @return int Identyfikator utworzonego tenanta.
     */
    public function create(string $name = 'Biuro Testowe', ?string $slug = null, int $userId = 1): int
    {
        $slug ??= 'biuro-' . bin2hex(random_bytes(4));

        $this->db->createCommand()->insert('tenants', [
            'name' => $name,
            'slug' => $slug,
            'plan' => 'starter',
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ])->execute();

        $tenantId = (int) $this->db->getLastInsertId();
        $this->switchTo($tenantId, $userId);

        return $tenantId;
    }

    /**
     * Przelacza kontekst na wskazanego tenanta - do sprawdzania, czy
     * jedno biuro nie widzi danych drugiego.
     */
    public function switchTo(int $tenantId, int $userId = 1): void
    {
        $this->tenantContext->set($tenantId, $userId, ['owner']);
    }

    /**
     * Poprawne dane wejsciowe klienta; nadpisz to, co ma byc inne w tescie.
     *
     * @param array<string, mixed> $overrides
     */
    public static function clientInput(array $overrides = []): ClientInput
    {
        return ClientInput::fromArray([
            'name' => 'Firma Testowa sp. z o.o.',
            // NIP z poprawna suma kontrolna - walidacja odrzucilaby losowy ciag.
            'nip' => null,
            'email' => 'kontakt@testowa.pl',
            'status' => 'active',
            ...$overrides,
        ]);
    }
}
