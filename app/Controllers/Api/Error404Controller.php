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
        // Si NO es API, deja el 404 normal (HTML) para web
        if (! str_starts_with($this->request->getPath(), 'api')) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $default = 'Endpoint no encontrado';
        $msg = $default;

        // En desarrollo permitimos mensaje si viene útil (no vacío)
        if (ENVIRONMENT === 'development') {
            $candidate = trim((string) $message);
            if ($candidate !== '') {
                $msg = $candidate;
            }
        }

        return $this->notFound($msg);
    }
}
