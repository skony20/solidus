<?php

declare(strict_types=1);

use App\Console;

return [
    'hello' => Console\HelloCommand::class,

    // Nadanie roli administratora calego systemu - jedyna droga do uprawnien
    // nad cennikiem. Swiadomie poza API.
    'admin:grant' => Console\GrantPlatformAdminCommand::class,
];
