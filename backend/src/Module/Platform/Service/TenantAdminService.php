<?php

declare(strict_types=1);

namespace App\Module\Platform\Service;

use App\Module\Account\Entity\TenantStatus;
use App\Module\Account\Repository\TenantRepository;
use App\Module\Pricing\Repository\PricingPlanRepository;
use App\Module\Platform\Dto\TenantPaymentInput;
use App\Module\Platform\Entity\TenantOverview;
use App\Module\Platform\Entity\TenantPayment;
use App\Module\Platform\Repository\TenantAdminRepository;
use App\Module\Platform\Repository\TenantPaymentRepository;
use App\Shared\Audit\AuditLogger;
use App\Shared\Validation\ValidationException;
use DateTimeImmutable;

/**
 * Operacje panelu operatora na biurach: zmiana stanu, przypisanie planu
 * z katalogu, reczne ksiegowanie platnosci.
 *
 * WAZNA ROZNICA WOBEC INNYCH SERWISOW W SOLIDUSIE: kazdy inny serwis dziala
 * na danych WLASNEGO tenanta (z TenantContext). Ten dziala na danych
 * DOWOLNEGO biura, wskazanego argumentem $tenantId - bo wlasnie to jest jego
 * zadaniem. Dlatego nie przyjmuje TenantContext i nie sciaga z niego
 * identyfikatora biura.
 *
 * WPIS DO AUDIT LOGU: AuditLogger zapisuje `tenant_id` z BIEZACEGO kontekstu
 * zadania, czyli biura ADMINISTRATORA, nie biura, ktorego dotyczy zmiana -
 * dokladnie ta sama, juz zaakceptowana niedoskonalosc co przy zmianach
 * cennika (patrz PricingService). Wpis i tak jest odnajdywalny po
 * `entity_type = 'tenant'` + `entity_id`, niezaleznie od tego, pod jakim
 * tenant_id trafil do dziennika.
 */
final readonly class TenantAdminService
{
    public const ENTITY_TYPE_TENANT = 'tenant';
    public const ENTITY_TYPE_PAYMENT = 'tenant_payment';

    public function __construct(
        private TenantAdminRepository $overview,
        private TenantRepository $tenants,
        private TenantPaymentRepository $payments,
        private PricingPlanRepository $pricingPlans,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @return TenantOverview[]
     */
    public function list(?string $search, ?string $statusFilter, int $limit, int $offset): array
    {
        return $this->overview->findAll($search, $this->parseStatusFilter($statusFilter), $limit, $offset);
    }

    public function count(?string $search, ?string $statusFilter): int
    {
        return $this->overview->count($search, $this->parseStatusFilter($statusFilter));
    }

    /**
     * @throws TenantNotFoundException
     */
    public function find(int $id): TenantOverview
    {
        return $this->overview->findById($id) ?? throw new TenantNotFoundException($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws TenantNotFoundException
     */
    public function usersFor(int $id): array
    {
        $this->find($id);

        return $this->overview->usersFor($id);
    }

    /**
     * @return TenantPayment[]
     * @throws TenantNotFoundException
     */
    public function paymentsFor(int $id): array
    {
        $this->find($id);

        return $this->payments->findAllForTenant($id);
    }

    /**
     * @throws TenantNotFoundException
     * @throws ValidationException
     */
    public function changeStatus(int $tenantId, string $rawStatus, ?string $ip = null): TenantOverview
    {
        $before = $this->find($tenantId);

        $status = TenantStatus::tryFrom($rawStatus);
        if ($status === null) {
            throw new ValidationException(['status' => ['Nieznany stan biura.']]);
        }

        $this->tenants->updateStatus($tenantId, $status);

        $this->auditLogger->updated(
            self::ENTITY_TYPE_TENANT,
            $tenantId,
            ['status' => $before->tenant->status->value],
            ['status' => $status->value],
            $ip,
        );

        return $this->find($tenantId);
    }

    /**
     * @throws TenantNotFoundException
     * @throws ValidationException
     */
    public function assignPlan(int $tenantId, ?int $pricingPlanId, ?string $ip = null): TenantOverview
    {
        $before = $this->find($tenantId);

        if ($pricingPlanId === null) {
            // Odpiecie od katalogu - biuro zostaje na "wycenie indywidualnej",
            // tekstowa nazwa planu to sygnal, ze nie ma go juz w cenniku.
            $this->tenants->updatePlan($tenantId, null, 'custom');
        } else {
            $plan = $this->pricingPlans->findById($pricingPlanId);
            if ($plan === null) {
                throw new ValidationException(['pricingPlanId' => ['Wybrany plan nie istnieje w cenniku.']]);
            }

            $this->tenants->updatePlan($tenantId, $pricingPlanId, $plan->code);
        }

        $this->auditLogger->updated(
            self::ENTITY_TYPE_TENANT,
            $tenantId,
            ['pricingPlanId' => $before->tenant->pricingPlanId],
            ['pricingPlanId' => $pricingPlanId],
            $ip,
        );

        return $this->find($tenantId);
    }

    /**
     * @throws TenantNotFoundException
     * @throws ValidationException
     */
    public function recordPayment(
        int $tenantId,
        TenantPaymentInput $input,
        int $recordedByUserId,
        ?string $ip = null,
    ): TenantPayment {
        $this->find($tenantId);

        $payment = new TenantPayment(
            id: null,
            tenantId: $tenantId,
            amount: $input->amount,
            currency: $input->currency,
            periodStart: $input->periodStart,
            periodEnd: $input->periodEnd,
            status: $input->status,
            provider: $input->provider,
            providerReference: $input->providerReference,
            note: $input->note,
            recordedByUserId: $recordedByUserId,
            createdAt: new DateTimeImmutable(),
        );

        $id = $this->payments->insert($payment);
        $saved = $this->payments->findById($id) ?? throw new TenantNotFoundException($tenantId);

        $this->auditLogger->created(self::ENTITY_TYPE_PAYMENT, $id, $saved->toArray(), $ip);

        return $saved;
    }

    private function parseStatusFilter(?string $statusFilter): ?TenantStatus
    {
        if ($statusFilter === null || $statusFilter === '') {
            return null;
        }

        return TenantStatus::tryFrom($statusFilter);
    }
}
