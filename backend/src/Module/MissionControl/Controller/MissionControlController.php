<?php

declare(strict_types=1);

namespace App\Module\MissionControl\Controller;

use App\Shared\Http\ApiController;
use Psr\Http\Message\ResponseInterface;

/**
 * Centrum Dowodzenia - pulpit z podsumowaniem pracy calego biura.
 *
 * SZKIELET: modul ma na razie jeden endpoint sprawdzajacy, ze trasa i
 * uwierzytelnienie dzialaja. Logike domenowa dokladamy na wzor modulu
 * Klienci (Entity -> Dto -> Repository -> Service -> Controller).
 */
final readonly class MissionControlController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->modulePlaceholder('mission-control');
    }
}
