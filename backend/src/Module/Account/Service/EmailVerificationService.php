<?php

declare(strict_types=1);

namespace App\Module\Account\Service;

use App\Module\Account\Entity\Tenant;
use App\Module\Account\Entity\User;
use App\Module\Account\Repository\UserRepository;
use App\Shared\Mail\Mailer;
use DateTimeImmutable;

/**
 * Potwierdzanie adresu e-mail kodem wyslanym na skrzynke.
 *
 * Konto zalozone przez formularz rejestracji jest niezweryfikowane i nie
 * przechodzi logowania (patrz AuthController::login), dopoki wlasciciel nie
 * poda 6-cyfrowego kodu. Kod trzymany jest jako hash, ma 15 minut waznosci
 * i limit 5 bledych prob - po jego przekroczeniu trzeba poprosic o nowy.
 * Nowy kod mozna wyslac nie czesciej niz raz na 60 sekund.
 *
 * Serwis, a nie kontroler, jest wlascicielem tych regul - tak jak w
 * pozostalych modulach.
 */
final readonly class EmailVerificationService
{
    /** Dlugosc waznosci kodu w sekundach. */
    public const CODE_TTL = 900;

    /** Po tylu bledych probach kod przestaje dzialac - trzeba wyslac nowy. */
    public const MAX_ATTEMPTS = 5;

    /** Minimalny odstep miedzy wyslaniami kolejnych kodow, w sekundach. */
    public const RESEND_INTERVAL = 60;

    public function __construct(
        private UserRepository $users,
        private Mailer $mailer,
    ) {}

    /**
     * Generuje pierwszy kod dla swiezo utworzonego konta i wysyla go mailem.
     * Wolane z RegistrationController::register(), w tej samej sciezce co
     * utworzenie tenanta i uzytkownika.
     */
    public function startForNewUser(User $user, Tenant $tenant): void
    {
        $this->issueAndSend($user, $tenant, new DateTimeImmutable());
    }

    /**
     * Ponowne wyslanie kodu (uzytkownik nie dostal maila / kod wygasl).
     *
     * @throws EmailVerificationException
     */
    public function resend(Tenant $tenant, User $user): void
    {
        if ($user->isEmailVerified()) {
            throw EmailVerificationException::alreadyVerified();
        }

        $now = new DateTimeImmutable();
        $sentAt = $user->id === null ? null : $this->lastSentAt($user);

        if ($sentAt !== null) {
            $elapsed = $now->getTimestamp() - $sentAt->getTimestamp();
            if ($elapsed < self::RESEND_INTERVAL) {
                throw EmailVerificationException::resendTooSoon(self::RESEND_INTERVAL - $elapsed);
            }
        }

        $this->issueAndSend($user, $tenant, $now);
    }

    /**
     * Sprawdza kod. Po sukcesie konto jest potwierdzone i metoda zwraca
     * odswiezona encje uzytkownika (juz z ustawionym `emailVerifiedAt`).
     *
     * @throws EmailVerificationException
     */
    public function verify(User $user, string $code): User
    {
        if ($user->isEmailVerified()) {
            throw EmailVerificationException::alreadyVerified();
        }

        $userId = (int) $user->id;

        // Kolumny kodu nie sa czescia encji User (to szczegol tej sciezki),
        // wiec czytamy je osobnym zapytaniem.
        $state = $this->codeState($userId);

        if ($state['hash'] === null || $state['expiresAt'] === null) {
            throw EmailVerificationException::noPendingCode();
        }

        if ($state['attempts'] >= self::MAX_ATTEMPTS) {
            throw EmailVerificationException::tooManyAttempts();
        }

        if ($state['expiresAt'] < new DateTimeImmutable()) {
            throw EmailVerificationException::codeExpired();
        }

        if (!password_verify($this->normalize($code), $state['hash'])) {
            $this->users->incrementVerificationAttempts($userId);

            if ($state['attempts'] + 1 >= self::MAX_ATTEMPTS) {
                throw EmailVerificationException::tooManyAttempts();
            }

            throw EmailVerificationException::codeInvalid();
        }

        $verifiedAt = new DateTimeImmutable();
        $this->users->markEmailVerified($userId, $verifiedAt);

        return $this->users->findById($userId) ?? $user;
    }

    private function issueAndSend(User $user, Tenant $tenant, DateTimeImmutable $now): void
    {
        $code = $this->generateCode();

        $this->users->storeVerificationCode(
            userId: (int) $user->id,
            codeHash: password_hash($code, PASSWORD_DEFAULT),
            expiresAt: $now->modify('+' . self::CODE_TTL . ' seconds'),
            sentAt: $now,
        );

        $minutes = intdiv(self::CODE_TTL, 60);

        $text = <<<TXT
        Czesc {$user->name},

        dziekujemy za zalozenie biura "{$tenant->name}" w Solidusie.

        Twoj kod potwierdzajacy adres e-mail:

            {$code}

        Wpisz go na ekranie rejestracji, aby aktywowac konto. Kod jest wazny
        przez {$minutes} minut.

        Jesli to nie Ty zakladales konto - zignoruj te wiadomosc.

        --
        Solidus
        TXT;

        $html = sprintf(
            '<p>Czesc %s,</p>'
            . '<p>dziekujemy za zalozenie biura <strong>%s</strong> w Solidusie.</p>'
            . '<p>Twoj kod potwierdzajacy adres e-mail:</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:6px;margin:16px 0">%s</p>'
            . '<p>Wpisz go na ekranie rejestracji, aby aktywowac konto. Kod jest wazny przez %d minut.</p>'
            . '<p style="color:#6b7280;font-size:13px">Jesli to nie Ty zakladales konto - zignoruj te wiadomosc.</p>',
            htmlspecialchars($user->name, ENT_QUOTES),
            htmlspecialchars($tenant->name, ENT_QUOTES),
            $code,
            $minutes,
        );

        $this->mailer->send(
            toEmail: $user->email,
            subject: 'Kod potwierdzajacy adres e-mail - Solidus',
            textBody: $text,
            htmlBody: $html,
        );
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Kod wpisany przez uzytkownika bywa z odstepami albo myslnikiem -
     * zostawiamy same cyfry.
     */
    private function normalize(string $code): string
    {
        return preg_replace('/\D+/', '', $code) ?? '';
    }

    private function lastSentAt(User $user): ?DateTimeImmutable
    {
        return $this->codeState((int) $user->id)['sentAt'];
    }

    /**
     * @return array{hash: ?string, expiresAt: ?DateTimeImmutable, sentAt: ?DateTimeImmutable, attempts: int}
     */
    private function codeState(int $userId): array
    {
        return $this->users->verificationState($userId);
    }
}
