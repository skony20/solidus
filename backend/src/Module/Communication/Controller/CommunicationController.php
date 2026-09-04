<?php

declare(strict_types=1);

namespace App\Module\Communication\Controller;

use App\Shared\Http\ApiController;
use Psr\Http\Message\ResponseInterface;

/**
 * Komunikacja - masowe wysylki e-mail i rozmowy 1:1 z klientami.
 *
 * SZKIELET: modul ma na razie jeden endpoint sprawdzajacy, ze trasa i
 * uwierzytelnienie dzialaja. Logike domenowa dokladamy na wzor modulu
 * Klienci (Entity -> Dto -> Repository -> Service -> Controller).
 */
final readonly class CommunicationController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->modulePlaceholder('komunikacja');
    }
}
