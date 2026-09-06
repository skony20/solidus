<?php

declare(strict_types=1);

namespace App\Shared\Mail;

use RuntimeException;

/**
 * Blad transportu poczty. Wyjatki konkretnej biblioteki (Symfony Mailer) nie
 * przeciekaja do warstwy domenowej - tak samo jak wyjatki Guzzle sa tam
 * tlumaczone na ExternalApiException.
 */
final class MailerException extends RuntimeException
{
}
