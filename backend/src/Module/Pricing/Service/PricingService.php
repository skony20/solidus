<?php

declare(strict_types=1);

namespace App\Module\Pricing\Service;

use App\Module\Pricing\Dto\PricingPlanInput;
use App\Module\Pricing\Entity\PricingPlan;
use App\Module\Pricing\Repository\PricingPlanRepository;
use App\Shared\Audit\AuditLogger;
use App\Shared\Validation\ValidationException;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Operacje na cenniku.
 *
 * Serwis - jak w module Klienci - jest wlascicielem regul i dziennika zmian.
 * Cena widoczna na stronie sprzedazowej to informacja handlowa: musimy umiec
 * odpowiedziec, kto i kiedy ja zmienil.
 *
 * Wpis w audit logu dostaje `tenant_id` administratora, ktory dokonal zmiany.
 * To niedoskonale - cennik nie nalezy do zadnego biura - ale tabela `audit_log`
 * ma `tenant_id NOT NULL` z kluczem obcym, a dorabianie drugiego, "systemowego"
 * dziennika dla jednej tabeli byloby kosztem wiekszym niz zysk. Odczyt historii
 * cennika i tak idzie po `entity_type`, nie po tenancie.
 *
 * Plan i jego punkty zapisujemy w transakcji: plan z polowa punktow byloby
 * widac na stronie natychmiast.
 */
final readonly class PricingService
{
    public const ENTITY_TYPE = 'pricing_plan';

    public function __construct(
        private ConnectionInterface $db,
        private PricingPlanRepository $repository,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException
     */
    public function create(PricingPlanInput $input, ?string $ip = null): PricingPlan
    {
        $this->assertCodeIsFree($input->code);

        $plan = $this->toEntity($input);

        $transaction = $this->db->beginTransaction();

        try {
            $id = $this->repository->insert($plan);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        $saved = $this->repository->findById($id) ?? throw new PricingPlanNotFoundException($id);
        $this->auditLogger->created(self::ENTITY_TYPE, $id, $saved->toArray(), $ip);

        return $saved;
    }

    /**
     * @throws ValidationException
     * @throws PricingPlanNotFoundException
     */
    public function update(int $id, PricingPlanInput $input, ?string $ip = null): PricingPlan
    {
        $before = $this->repository->findById($id) ?? throw new PricingPlanNotFoundException($id);

        $this->assertCodeIsFree($input->code, $id);

        $transaction = $this->db->beginTransaction();

        try {
            $this->repository->update($id, $this->toEntity($input));
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        $after = $this->repository->findById($id) ?? throw new PricingPlanNotFoundException($id);
        $this->auditLogger->updated(self::ENTITY_TYPE, $id, $before->toArray(), $after->toArray(), $ip);

        return $after;
    }

    /**
     * @throws PricingPlanNotFoundException
     */
    public function delete(int $id, ?string $ip = null): void
    {
        $plan = $this->repository->findById($id) ?? throw new PricingPlanNotFoundException($id);

        $this->repository->delete($id);
        $this->auditLogger->deleted(self::ENTITY_TYPE, $id, $plan->toArray(), $ip);
    }

    /**
     * Kod jest kluczem, po ktorym plan rozpoznaje kod aplikacji, wiec duplikat
     * musi byc bledem walidacji z czytelnym komunikatem, a nie wyjatkiem z bazy.
     *
     * @throws ValidationException
     */
    private function assertCodeIsFree(string $code, ?int $excludeId = null): void
    {
        if ($this->repository->existsWithCode($code, $excludeId)) {
            throw new ValidationException(['code' => ['Plan o tym kodzie juz istnieje.']]);
        }
    }

    private function toEntity(PricingPlanInput $input): PricingPlan
    {
        $now = new DateTimeImmutable();

        return new PricingPlan(
            id: null,
            code: $input->code,
            name: $input->name,
            tagline: $input->tagline,
            priceMonthly: $input->priceMonthly,
            priceYearly: $input->priceYearly,
            currency: $input->currency,
            ctaLabel: $input->ctaLabel,
            isFeatured: $input->isFeatured,
            isActive: $input->isActive,
            position: $input->position,
            features: $input->features,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
