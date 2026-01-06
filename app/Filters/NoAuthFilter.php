<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class NoAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = (string) $request->getHeaderLine('Authorization');
        if ($auth === '' || stripos($auth, 'Bearer ') !== 0) {
            return null; // no hay token, puede loguear
        }

        $jwt = trim(substr($auth, 7));
        if ($jwt === '') {
            return null;
        }

        $payload = service('jwt')->decode($jwt);
        if (!is_array($payload)) {
            return null; // token inválido => permitir login
        }

        return service('response')
            ->setStatusCode(409)
            ->setJSON([
                'status'  => 'error',
                'data'    => null,
                'message' => 'Ya autenticado',
                'errors'  => (object)[],
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada
    }
}
