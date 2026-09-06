<?php

declare(strict_types=1);

namespace App\Shared\Mail;

/**
 * Mailer do testow - nie wysyla niczego, tylko zapamietuje wiadomosci.
 *
 * Odpowiednik Fake*ApiClient z modulow zewnetrznych: pozwala sprawdzic w
 * tescie, ze wiadomosc powstala i z jaka trescia, bez dotykania SMTP.
 */
final class FakeMailer implements Mailer
{
    /** @var list<array{to: string, subject: string, text: string, html: ?string}> */
    public array $sent = [];

    public function send(
        string $toEmail,
        string $subject,
        string $textBody,
        ?string $htmlBody = null,
    ): void {
        $this->sent[] = [
            'to' => $toEmail,
            'subject' => $subject,
            'text' => $textBody,
            'html' => $htmlBody,
        ];
    }

    public function lastTo(string $email): ?array
    {
        foreach (array_reverse($this->sent) as $message) {
            if ($message['to'] === $email) {
                return $message;
            }
        }

        return null;
    }
}
