<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use App\Shared\Tenant\TenantContext;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * Zapisuje kazda zmiane encji do tabeli `audit_log`.
 *
 * To wymog regulacyjny (AML i RODO), a nie wygoda deweloperska: musimy umiec
 * odpowiedziec, kto, kiedy i z jakiego adresu zmienil dane klienta.
 * Serwisy domenowe wolaja ten logger po kazdej udanej operacji zapisu.
 */
final readonly class AuditLogger
{
    private const TABLE = 'audit_log';

    public function __construct(
        private ConnectionInterface $db,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param array<string, mixed> $data Stan encji po utworzeniu.
     */
    public function created(string $entityType, int $entityId, array $data, ?string $ip = null): void
    {
        $this->write($entityType, $entityId, AuditAction::Create, ['after' => $data], $ip);
    }

    /**
     * Zapisuje wylacznie pola, ktore faktycznie sie zmienily - dziennik ma byc
     * czytelny dla audytora, nie byc kopia calej tabeli.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function updated(
        string $entityType,
        int $entityId,
        array $before,
        array $after,
        ?string $ip = null,
    ): void {
        $changes = [];

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$field] = ['from' => $oldValue, 'to' => $newValue];
            }
        }

        if ($changes === []) {
            return;
        }

        $this->write($entityType, $entityId, AuditAction::Update, $changes, $ip);
    }

    /**
     * @param array<string, mixed> $data Stan encji tuz przed usunieciem.
     */
    public function deleted(string $entityType, int $entityId, array $data, ?string $ip = null): void
    {
        $this->write($entityType, $entityId, AuditAction::Delete, ['before' => $data], $ip);
    }

    /**
     * Historia zmian jednej encji - podklad pod zakladke "Historia" w UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyFor(string $entityType, int $entityId, int $limit = 50): array
    {
        return (new Query($this->db))
            ->from(self::TABLE)
            ->where([
                'tenant_id' => $this->tenantContext->tenantId(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function write(
        string $entityType,
        int $entityId,
        AuditAction $action,
        array $changes,
        ?string $ip,
    ): void {
        $this->db->createCommand()->insert(self::TABLE, [
            'tenant_id' => $this->tenantContext->tenantId(),
            'user_id' => $this->tenantContext->userId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action->value,
            'changes' => json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => $ip,
            // DATETIME(6) - przy imporcie masowym kilka zmian potrafi wpasc
            // w tej samej sekundzie, mikrosekundy zachowuja ich kolejnosc.
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ])->execute();
    }
}
