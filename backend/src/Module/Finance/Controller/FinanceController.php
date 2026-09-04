<?php

declare(strict_types=1);

namespace App\Module\Finance\Controller;

use App\Shared\Http\ApiController;
use Psr\Http\Message\ResponseInterface;

/**
 * Finanse - ksiegowosc, dokumenty i generator pism.
 *
 * SZKIELET: modul ma na razie jeden endpoint sprawdzajacy, ze trasa i
 * uwierzytelnienie dzialaja. Logike domenowa dokladamy na wzor modulu
 * Klienci (Entity -> Dto -> Repository -> Service -> Controller).
 */
final readonly class FinanceController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->modulePlaceholder('finanse');
    }
}
