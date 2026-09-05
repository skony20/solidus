<?php

declare(strict_types=1);

namespace App\Console;

use App\Module\Account\Repository\UserRepository;
use App\Shared\Auth\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

/**
 * Nadaje (lub odbiera) role administratora calego systemu.
 *
 * DLACZEGO KONSOLA, A NIE API: rola `platform_admin` daje wladze nad cennikiem
 * widocznym dla wszystkich odwiedzajacych. Gdyby dalo sie ja nadac zadaniem
 * HTTP, wystarczylby jeden blad w warunku uprawnien w dowolnym kontrolerze,
 * zeby klient sam sobie ja przyznal. Dostep do konsoli kontenera to osobna,
 * mocniejsza granica - i tak musi ja miec ten, kto wdraza system.
 *
 * Uzycie:
 *   php yii admin:grant biuro-nowak anna@example.pl
 *   php yii admin:grant biuro-nowak anna@example.pl --revoke
 */
#[AsCommand(
    name: 'admin:grant',
    description: 'Nadaje uzytkownikowi role administratora calego systemu (cennik strony informacyjnej).',
)]
final class GrantPlatformAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Slug biura, do ktorego nalezy konto')
            ->addArgument('email', InputArgument::REQUIRED, 'Adres e-mail uzytkownika')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Odbierz role zamiast ja nadawac');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantSlug = (string) $input->getArgument('tenant');
        $email = (string) $input->getArgument('email');
        $revoke = (bool) $input->getOption('revoke');

        $user = $this->users->findByEmailAndTenantSlug($tenantSlug, $email);

        if ($user === null) {
            $output->writeln(sprintf(
                '<error>Nie znaleziono uzytkownika %s w biurze "%s".</error>',
                $email,
                $tenantSlug,
            ));

            return ExitCode::DATAERR;
        }

        $hasRole = Role::isPlatformAdmin($user->roles);

        if ($revoke === $hasRole) {
            $roles = $revoke
                ? array_values(array_filter($user->roles, static fn(string $r): bool => $r !== Role::PLATFORM_ADMIN))
                : [...$user->roles, Role::PLATFORM_ADMIN];

            $this->users->replaceRoles((int) $user->id, $roles);

            $output->writeln(sprintf(
                '<info>%s roli administratora systemu: %s (%s).</info>',
                $revoke ? 'Odebrano' : 'Nadano',
                $user->email,
                $tenantSlug,
            ));
            // Access token zyje 15 minut i niesie stary zestaw rol - zmiana
            // zadziala najpozniej po tym czasie, a natychmiast po wylogowaniu.
            $output->writeln('Zmiana wejdzie w zycie po odswiezeniu sesji (do 15 minut).');
        } else {
            $output->writeln(sprintf(
                '<comment>Bez zmian - uzytkownik %s ma juz oczekiwany stan uprawnien.</comment>',
                $user->email,
            ));
        }

        return ExitCode::OK;
    }
}
