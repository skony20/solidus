<?php

declare(strict_types=1);

namespace App\Module\Aml\Controller;

use App\Shared\Http\ApiController;
use Psr\Http\Message\ResponseInterface;

/**
 * Ryzyko AML - Solidus tylko prezentuje scoring; liczy go zewnetrzna aplikacja.
 *
 * SZKIELET: modul ma na razie jeden endpoint sprawdzajacy, ze trasa i
 * uwierzytelnienie dzialaja. Logike domenowa dokladamy na wzor modulu
 * Klienci (Entity -> Dto -> Repository -> Service -> Controller).
 */
final readonly class AmlController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->modulePlaceholder('aml');
    }
}
