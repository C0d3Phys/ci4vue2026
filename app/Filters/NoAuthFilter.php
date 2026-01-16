<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\AuthTokenModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class NoAuthFilter implements FilterInterface
{
    private AuthTokenModel $authTokenModel;

    public function __construct()
    {
        $this->authTokenModel = model(AuthTokenModel::class);
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        // Si NO hay Bearer -> permitir (puede ir a /auth/login, /auth/forgot, /auth/reset)
        $jwt = $this->extractBearerToken($request);
        if ($jwt === null) {
            return null;
        }

        // Si el JWT es inválido/expirado -> permitir (para que se re-loguee)
        $payload = $this->decodeJwt($jwt);
        if ($payload === null) {
            return null;
        }

        // Claims mínimos
        $userId = (int) ($payload['uid'] ?? 0);
        $jti    = (string) ($payload['jti'] ?? '');

        if ($userId <= 0 || $jti === '') {
            // JWT raro -> no bloqueamos login
            return null;
        }

        // Validar revocación en DB (igual criterio que AuthFilter)
        $tokenRecord = $this->authTokenModel->findValidByJti($jti);
        if ($tokenRecord === null) {
            // Token ya revocado / no reconocido -> permitir login
            return null;
        }

        // Token válido y activo -> bloquear rutas "solo no autenticados"
        return $this->alreadyAuthenticated();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    // -------------------------
    // Helpers (mismos que AuthFilter)
    // -------------------------

    private function extractBearerToken(RequestInterface $request): ?string
    {
        $auth = $request->getHeaderLine('Authorization');
        if ($auth === '' || stripos($auth, 'Bearer ') !== 0) {
            return null;
        }

        $jwt = trim(substr($auth, 7));
        return $jwt !== '' ? $jwt : null;
    }

    private function decodeJwt(string $jwt): ?array
    {
        $secret = (string) env('JWT_SECRET', '');
        if ($secret === '') {
            log_message('error', 'JWT_SECRET no configurado en NoAuthFilter');
            return null; // si no hay secret, no podemos afirmar que está autenticado
        }

        try {
            $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            return null;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function alreadyAuthenticated(): ResponseInterface
    {
        return service('response')
            ->setStatusCode(409)
            ->setJSON([
                'status'  => 'error',
                'data'    => null,
                'message' => 'Ya autenticado',
                'errors'  => new \stdClass(),
            ]);
    }
}
