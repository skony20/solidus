<?php

declare(strict_types=1);

namespace App\Shared\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Implementacja {@see Mailer} na Symfony Mailer.
 *
 * Transport bierze sie z DSN (solidus.mail.dsn) - w dev `smtp://mailhog:1025`,
 * na produkcji prawdziwy serwer SMTP. Adres nadawcy jest jeden dla calego
 * systemu (solidus.mail.from).
 */
final readonly class SymfonyMailer implements Mailer
{
    private TransportInterface $transport;

    public function __construct(
        string $dsn,
        private string $fromEmail,
        private string $fromName = 'Solidus',
    ) {
        $this->transport = Transport::fromDsn($dsn);
    }

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
            $this->transport->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new MailerException('Nie udalo sie wyslac wiadomosci e-mail: ' . $e->getMessage(), 0, $e);
        }
    }
}
