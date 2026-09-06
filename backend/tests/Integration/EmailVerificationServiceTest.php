<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Module\Account\Entity\User;
use App\Module\Account\Repository\TenantRepository;
use App\Module\Account\Repository\UserRepository;
use App\Module\Account\Service\EmailVerificationException;
use App\Module\Account\Service\EmailVerificationService;
use App\Shared\Mail\FakeMailer;
use DateTimeImmutable;

/**
 * Potwierdzanie adresu e-mail kodem przy rejestracji biura.
 *
 * Sprawdzane na prawdziwym MySQL, bo cala mechanika (waznosc kodu, licznik
 * prob, odstep miedzy wyslaniami) opiera sie na kolumnach tabeli `users`
 * i ich aktualizacji - test na atrapie repozytorium sprawdzalby wylacznie
 * wlasna atrape. Poczta idzie przez {@see FakeMailer}, wiec nic nie wychodzi
 * poza proces.
 */
final class EmailVerificationServiceTest extends DatabaseTestCase
{
    private const EMAIL = 'wlasciciel@biuro-test.pl';
    private const PASSWORD = 'bardzo-dlugie-haslo';

    private TenantRepository $tenants;
    private UserRepository $users;
    private FakeMailer $mailer;
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants = new TenantRepository($this->db);
        $this->users = new UserRepository($this->db);
        $this->mailer = new FakeMailer();
        $this->service = new EmailVerificationService($this->users, $this->mailer);
    }

    public function testStartWysylaKodNaMailIniePotwierdzaKonta(): void
    {
        [$tenant, $user] = $this->registerOffice();

        $this->service->startForNewUser($user, $tenant);

        $message = $this->mailer->lastTo(self::EMAIL);
        self::assertNotNull($message);
        self::assertMatchesRegularExpression('/\b\d{6}\b/', $message['text']);

        $fresh = $this->users->findByEmail($tenant->id, self::EMAIL);
        self::assertInstanceOf(User::class, $fresh);
        self::assertFalse($fresh->isEmailVerified());
    }

    public function testPoprawnyKodAktywujeKonto(): void
    {
        [$tenant, $user] = $this->registerOffice();
        $this->service->startForNewUser($user, $tenant);
        $code = $this->lastCode();

        $verified = $this->service->verify($user, $code);

        self::assertTrue($verified->isEmailVerified());
        self::assertTrue(
            $this->users->findByEmail($tenant->id, self::EMAIL)?->isEmailVerified(),
        );
    }

    public function testKodMoznaWpisacZOdstepami(): void
    {
        [$tenant, $user] = $this->registerOffice();
        $this->service->startForNewUser($user, $tenant);
        $code = $this->lastCode();

        $spaced = implode(' ', str_split($code, 2));

        self::assertTrue($this->service->verify($user, $spaced)->isEmailVerified());
    }

    public function testZlyKodJestOdrzucanyIZliczaProbe(): void
    {
        [$tenant, $user] = $this->registerOffice();
        $this->service->startForNewUser($user, $tenant);

        try {
            $this->service->verify($user, $this->wrongCode());
            self::fail('Zly kod powinien rzucic wyjatek.');
        } catch (EmailVerificationException $e) {
            self::assertSame('code_invalid', $e->reason);
        }

        self::assertSame(1, $this->users->verificationState((int) $user->id)['attempts']);
    }

    public function testPoWyczerpaniuProbNawetPoprawnyKodNieDziala(): void
    {
        [$tenant, $user] = $this->registerOffice();
        $this->service->startForNewUser($user, $tenant);
        $code = $this->lastCode();

        for ($i = 0; $i < EmailVerificationService::MAX_ATTEMPTS; $i++) {
            try {
                $this->service->verify($user, $this->wrongCode());
            } catch (EmailVerificationException) {
                // celowo - zliczamy proby
            }
        }

        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessageMatches('/nowy kod/i');
        $this->service->verify($user, $code);
    }

    public function testWygaslyKodJestOdrzucany(): void
    {
        [$tenant, $user] = $this->registerOffice();
        $this->service->startForNewUser($user, $tenant);
        $code = $this->lastCode();

        $this->backdate($user, ['verification_code_expires_at' => '-1 minute']);

        try {
            $this->service->verify($user, $code);
            self::fail('Wygasly kod powinien zostac odrzucony.');
        } catch (EmailVerificationException $e) {
            self::assertSame('code_expired', $e->reason);
        }
    }

    public function testWznowieniePrzedUplywemOdstepuJestBlokowane(): void
    {
        [$tenant, $user] = $this->registerOffice();
        $this->service->startForNewUser($user, $tenant);

        $this->expectException(EmailVerificationException::class);

        try {
            $this->service->resend($tenant, $this->reloadUser($tenant->id));
        } catch (EmailVerificationException $e) {
            self::assertSame('resend_too_soon', $e->reason);
            throw $e;
        }
    }

    public function testWznowienieWysylaNowyKodIUniewazniaPoprzedni(): void
    {
        [$tenant, $user] = $this->registerOffice();
        $this->service->startForNewUser($user, $tenant);
        $oldCode = $this->lastCode();

        $this->backdate($user, [
            'verification_code_sent_at' => '-2 minutes',
        ]);

        $this->service->resend($tenant, $this->reloadUser($tenant->id));
        $newCode = $this->lastCode();

        self::assertNotSame($oldCode, $newCode, 'Nowy kod powinien byc inny niz poprzedni.');

        try {
            $this->service->verify($user, $oldCode);
            self::fail('Poprzedni kod powinien przestac dzialac.');
        } catch (EmailVerificationException $e) {
            self::assertSame('code_invalid', $e->reason);
        }

        self::assertTrue($this->service->verify($user, $newCode)->isEmailVerified());
    }

    public function testJuzPotwierdzonegoKontaNieDaSiePotwierdzicPonownie(): void
    {
        [$tenant, $user] = $this->registerOffice();
        $this->service->startForNewUser($user, $tenant);
        $this->service->verify($user, $this->lastCode());

        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessageMatches('/juz/i');
        $this->service->verify($this->reloadUser($tenant->id), $this->lastCode());
    }

    /**
     * @return array{0: \App\Module\Account\Entity\Tenant, 1: User}
     */
    private function registerOffice(): array
    {
        $tenant = $this->tenants->create('Biuro Testowe', 'biuro-' . bin2hex(random_bytes(4)));
        $user = $this->users->create(
            $tenant->id,
            self::EMAIL,
            self::PASSWORD,
            'Jan Kowalski',
            ['owner'],
            emailVerified: false,
        );

        return [$tenant, $user];
    }

    private function reloadUser(int $tenantId): User
    {
        $user = $this->users->findByEmail($tenantId, self::EMAIL);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function lastCode(): string
    {
        $message = $this->mailer->lastTo(self::EMAIL);
        self::assertNotNull($message);
        self::assertSame(1, preg_match('/\b(\d{6})\b/', $message['text'], $m));

        return $m[1];
    }

    private function wrongCode(): string
    {
        // Kod, ktory na pewno nie jest tym wyslanym.
        $actual = $this->lastCode();
        $wrong = str_pad((string) (((int) $actual + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);

        return $wrong;
    }

    /**
     * @param array<string, string> $shifts kolumna => modyfikator DateTime (np. "-1 minute")
     */
    private function backdate(User $user, array $shifts): void
    {
        $now = new DateTimeImmutable();
        $values = [];

        foreach ($shifts as $column => $shift) {
            $values[$column] = $now->modify($shift)->format('Y-m-d H:i:s.u');
        }

        $this->db->createCommand()->update('users', $values, ['id' => (int) $user->id])->execute();
    }
}
