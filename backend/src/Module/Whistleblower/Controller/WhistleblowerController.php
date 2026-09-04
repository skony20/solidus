<?php

declare(strict_types=1);

namespace App\Module\Whistleblower\Controller;

use App\Shared\Http\ApiController;
use Psr\Http\Message\ResponseInterface;

/**
 * Sygnalisci - kanal zgloszen obslugiwany przez zewnetrzna aplikacje.
 *
 * SZKIELET: modul ma na razie jeden endpoint sprawdzajacy, ze trasa i
 * uwierzytelnienie dzialaja. Logike domenowa dokladamy na wzor modulu
 * Klienci (Entity -> Dto -> Repository -> Service -> Controller).
 */
final readonly class WhistleblowerController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->modulePlaceholder('sygnalisci');
    }
}
