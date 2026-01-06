<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class Error404Controller extends BaseController
{
    use ApiResponseTrait;

    /**
     * Handler para rutas no encontradas (override 404).
     *
     * CI4 puede pasar un mensaje opcional dependiendo del flujo/versión.
     */
    public function index(?string $message = null): ResponseInterface
    {
        // Mensaje por defecto si no viene nada
        $msg = (ENVIRONMENT === 'development')
            ? ($message ?? 'Endpoint no encontrado')
            : 'Endpoint no encontrado';

        // Tu estándar de error:
        // { status:"error", data:null, message:"...", errors:{} }
        return $this->notFound($msg);
    }
}
