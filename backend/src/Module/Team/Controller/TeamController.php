<?php

declare(strict_types=1);

namespace App\Module\Team\Controller;

use App\Shared\Http\ApiController;
use Psr\Http\Message\ResponseInterface;

/**
 * Zespol - pracownicy biura, ich obciazenie i uprawnienia.
 *
 * SZKIELET: modul ma na razie jeden endpoint sprawdzajacy, ze trasa i
 * uwierzytelnienie dzialaja. Logike domenowa dokladamy na wzor modulu
 * Klienci (Entity -> Dto -> Repository -> Service -> Controller).
 */
final readonly class TeamController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->modulePlaceholder('zespol');
    }
}
