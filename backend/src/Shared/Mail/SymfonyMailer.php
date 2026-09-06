<?php

declare(strict_types=1);

namespace App\Shared\Mail;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Implementacja {@see Mailer} na Symfony Mailer.
 *
 * Transport bierze sie z DSN (solidus.mail.dsn) - w dev `smtp://mailhog:1025`,
 * na produkcji prawdziwy serwer SMTP.
 *
 * DWIE decyzje wymuszone przez hosting produkcyjny (Cyber-Folks):
 *
 *  - **Transport budowany leniwie**, przy pierwszej wysylce, a nie w
 *    konstruktorze. Blad w DSN (`Transport::fromDsn`) nie moze wywracac
 *    budowy kontenera - inaczej KAZDE zadanie do kontrolera, ktory zaleznie
 *    od `Mailer`, konczy sie bledem 500, nie tylko sama wysylka.
 *
 *  - **`send()` lapie `Throwable`, nie tylko wyjatki transportu.** WAF hostingu
 *    ("domain protection") blokuje `proc_open()` w kontekscie web, a Symfony
 *    zglasza to jako `E_USER_WARNING` -> `ErrorException` (nie
 *    `TransportExceptionInterface`). Bez szerokiego `catch` rejestracja by sie
 *    wywracala zamiast oddac `emailSent: false`. Wysylka `sendmail://` przez
 *    `proc_open` na tym hostingu i tak nie zadziala - trzeba SMTP przez gniazdo.
 */
final class SymfonyMailer implements Mailer
{
    private ?TransportInterface $transport = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $fromEmail,
        private readonly string $fromName = 'Solidus',
    ) {}

    public function send(
        string $toEmail,
        string $subject,
        string $textBody,
        ?string $htmlBody = null,
    ): void {
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject($subject)
            ->text($textBody);

        if ($htmlBody !== null) {
            $email->html($htmlBody);
        }

        try {
            $this->transport()->send($email);
        } catch (Throwable $e) {
            throw new MailerException('Nie udalo sie wyslac wiadomosci e-mail: ' . $e->getMessage(), 0, $e);
        }
    }

    private function transport(): TransportInterface
    {
        return $this->transport ??= Transport::fromDsn($this->dsn);
    }
}
