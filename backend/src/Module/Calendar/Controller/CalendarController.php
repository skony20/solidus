<?php

declare(strict_types=1);

namespace App\Module\Calendar\Controller;

use App\Shared\Http\ApiController;
use Psr\Http\Message\ResponseInterface;

/**
 * Kalendarz - terminy podatkowe i Radar Zmian w przepisach.
 *
 * SZKIELET: modul ma na razie jeden endpoint sprawdzajacy, ze trasa i
 * uwierzytelnienie dzialaja. Logike domenowa dokladamy na wzor modulu
 * Klienci (Entity -> Dto -> Repository -> Service -> Controller).
 */
final readonly class CalendarController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->modulePlaceholder('kalendarz');
    }
}
