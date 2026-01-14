<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\AuthTokenModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthFilter implements FilterInterface
{
    private $authTokenModel;

    public function __construct()
    {
        $this->authTokenModel = model(AuthTokenModel::class);
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        // 1) Extraer Bearer Token
        $jwt = $this->extractBearerToken($request);
        if ($jwt === null) {
            return $this->unauthorized('Token Bearer requerido');
        }

        // 2) Decodificar y validar JWT
        $payload = $this->decodeJwt($jwt);
        if ($payload === null) {
            return $this->unauthorized('Token inválido o expirado');
        }

        // 3) Extraer claims requeridos
        $userId = (int) ($payload['uid'] ?? 0);
        $jti = (string) ($payload['jti'] ?? '');

        if ($userId <= 0 || $jti === '') {
            return $this->unauthorized('Token sin claims requeridos');
        }

        // 4) Validar contra la base de datos
        $tokenRecord = $this->authTokenModel->findValidByJti($jti);
        if ($tokenRecord === null) {
            return $this->unauthorized('Token no reconocido o revocado');
        }

        // 5) Guardar contexto del usuario autenticado
        $this->setAuthContext([
            'user_id' => $userId,
            'tenant_id' => (int) ($payload['tid'] ?? 0),
            'branch_id' => $payload['bid'] ?? null,
            'jti' => $jti,
            'claims' => $payload,
        ]);

        return null; // Permitir continuar
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No se requiere procesamiento después de la respuesta
        return $response;
    }

    // -------------------------
    // Private Helper Methods
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
        $secret = $this->getJwtSecret();

        if ($secret === '') {
            log_message('error', 'JWT_SECRET no configurado en AuthFilter');
            return null;
        }

        try {
            $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            log_message('debug', 'Token expirado: ' . $e->getMessage());
            return null;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            log_message('warning', 'Firma JWT inválida: ' . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            log_message('error', 'Error decodificando JWT: ' . $e->getMessage());
            return null;
        }
    }

    private function setAuthContext(array $context): void
    {
        // Opción 1: Usar la request para almacenar el contexto
        service('request')->auth = (object) $context;

        // Opción 2: Si tienes un servicio AuthContext, úsalo
        // service('authContext')->set($context);
    }

    private function getJwtSecret(): string
    {
        return (string) env('JWT_SECRET', '');
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON([
                'status' => 'error',
                'data' => null,
                'message' => $message,
                'errors' => new \stdClass(),
            ]);
    }
}
