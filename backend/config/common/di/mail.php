<?php

declare(strict_types=1);

use App\Shared\Mail\Mailer;
use App\Shared\Mail\SymfonyMailer;

/** @var array $params */

$mail = $params['solidus']['mail'];

return [
    // Kod domenowy zalezy od interfejsu Mailer. W testach podmieniany jest na
    // FakeMailer - zaden test nie dotyka SMTP.
    Mailer::class => [
        'class' => SymfonyMailer::class,
        '__construct()' => [
            'dsn' => $mail['dsn'],
            'fromEmail' => $mail['fromEmail'],
            'fromName' => $mail['fromName'],
        ],
    ],
];
