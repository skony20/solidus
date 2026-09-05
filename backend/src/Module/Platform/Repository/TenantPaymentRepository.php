<?php

declare(strict_types=1);

namespace App\Module\Platform\Repository;

use App\Module\Platform\Entity\TenantPayment;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * Historia platnosci abonamentowych. Podobnie jak {@see TenantAdminRepository}
 * NIE uzywa TenantScoped - czyta dane KTOREGOKOLWIEK biura na zadanie
 * operatora, ktory sam nie jest przypisany do zadnego tenanta.
 */
final readonly class TenantPaymentRepository
{
    public const TABLE = 'tenant_payments';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /**
     * @return TenantPayment[]
     */
    public function findAllForTenant(int $tenantId): array
    {
        $rows = (new Query($this->db))
            ->from(self::TABLE)
            ->where(['tenant_id' => $tenantId])
            ->orderBy(['period_start' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        return array_map(TenantPayment::fromRow(...), $rows);
    }

    public function insert(TenantPayment $payment): int
    {
        $this->db->createCommand()->insert(self::TABLE, [
            'tenant_id' => $payment->tenantId,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'period_start' => $payment->periodStart->format('Y-m-d'),
            'period_end' => $payment->periodEnd->format('Y-m-d'),
            'status' => $payment->status->value,
            'provider' => $payment->provider,
            'provider_reference' => $payment->providerReference,
            'note' => $payment->note,
            'recorded_by_user_id' => $payment->recordedByUserId,
            'created_at' => $payment->createdAt->format('Y-m-d H:i:s.u'),
        ])->execute();

        return (int) $this->db->getLastInsertId();
    }

    public function findById(int $id): ?TenantPayment
    {
        $row = (new Query($this->db))->from(self::TABLE)->where(['id' => $id])->one();

        return $row === null ? null : TenantPayment::fromRow($row);
    }
}
