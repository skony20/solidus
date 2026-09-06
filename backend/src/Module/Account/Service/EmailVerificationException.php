<?php

declare(strict_types=1);

namespace App\Module\Account\Service;

use RuntimeException;

/**
 * Blad na sciezce potwierdzania adresu e-mail.
 *
 * `$reason` to kod maszynowy (np. "code_expired") - kontroler oddaje go w
 * `error.details.reason`, zeby front mogl zareagowac (np. pokazac przycisk
 * "wyslij nowy kod"). Komunikat jest po polsku i nadaje sie do pokazania
 * uzytkownikowi wprost.
 */
final class EmailVerificationException extends RuntimeException
{
    private function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function alreadyVerified(): self
    {
        return new self('already_verified', 'Ten adres e-mail zostal juz potwierdzony. Mozesz sie zalogowac.');
    }

    public static function noPendingCode(): self
    {
        return new self('no_pending_code', 'Nie ma aktywnego kodu dla tego konta. Popros o nowy kod.');
    }

    public static function codeExpired(): self
    {
        return new self('code_expired', 'Kod stracil waznosc. Popros o nowy kod.');
    }

    public static function tooManyAttempts(): self
    {
        return new self('too_many_attempts', 'Za duzo bledych prob. Popros o nowy kod.');
    }

    public static function codeInvalid(): self
    {
        return new self('code_invalid', 'Nieprawidlowy kod.');
    }

    public static function resendTooSoon(int $secondsLeft): self
    {
        return new self(
            'resend_too_soon',
            "Nowy kod mozna wyslac za {$secondsLeft} s.",
        );
    }

    public static function accountNotFound(): self
    {
        return new self('account_not_found', 'Nie znaleziono konta oczekujacego na potwierdzenie.');
    }
}
