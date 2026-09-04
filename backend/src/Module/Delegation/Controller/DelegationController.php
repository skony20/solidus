<?php

declare(strict_types=1);

namespace App\Module\Delegation\Controller;

use App\Shared\Http\ApiController;
use Psr\Http\Message\ResponseInterface;

/**
 * Delegacje - ewidencja prowadzona przez zewnetrzna aplikacje DelegoApp.
 *
 * SZKIELET: modul ma na razie jeden endpoint sprawdzajacy, ze trasa i
 * uwierzytelnienie dzialaja. Logike domenowa dokladamy na wzor modulu
 * Klienci (Entity -> Dto -> Repository -> Service -> Controller).
 */
final readonly class DelegationController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->modulePlaceholder('delegacje');
    }
}
