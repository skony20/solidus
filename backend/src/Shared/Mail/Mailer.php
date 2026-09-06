<?php

declare(strict_types=1);

namespace App\Shared\Mail;

/**
 * Wysylka pojedynczej wiadomosci e-mail.
 *
 * Kod domenowy zna wylacznie ten interfejs - tak jak przy klientach aplikacji
 * zewnetrznych (patrz Module\*\Client). Produkcyjnie wpiety jest
 * {@see SymfonyMailer}; testy dostaja {@see FakeMailer} i nic nie wychodzi
 * poza proces.
 *
 * Brak kolejki jest tu swiadomy: wysylka idzie synchronicznie w cyklu zadania
 * HTTP. Dla jednej wiadomosci (kod weryfikacyjny przy rejestracji) to
 * akceptowalne - problem dotyczy masowych wysylek, ktore i tak czekaja na
 * decyzje o wariancie kolejki (docs/ARCHITECTURE.md, sekcja "Kolejka").
 */
interface Mailer
{
    /**
     * @throws MailerException gdy wiadomosci nie udalo sie oddac transportowi
     */
    public function send(
        string $toEmail,
        string $subject,
        string $textBody,
        ?string $htmlBody = null,
    ): void;
}
