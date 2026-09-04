<?php

declare(strict_types=1);

namespace App\Shared\Audit;

/**
 * Rodzaje zmian rejestrowanych w audit logu.
 * Wartosci odpowiadaja 1:1 kolumnie ENUM w tabeli `audit_log`.
 */
enum AuditAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
