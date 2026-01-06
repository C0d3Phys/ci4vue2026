<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = (string) $request->getHeaderLine('Authorization');

        if ($auth === '' || stripos($auth, 'Bearer ') !== 0) {
            return $this->unauthorized('Falta token Bearer');
        }

        $jwt = trim(substr($auth, 7));
        if ($jwt === '') {
            return $this->unauthorized('Token vacío');
        }

        // 1) Validación criptográfica del JWT
        // Aquí llamas a TU servicio JWT (firma/exp/iss/aud/nbf)
        // Debe retornar payload array o null si inválido.
        $payload = service('jwt')->decode($jwt); // <- tú implementas este service

        if (!is_array($payload)) {
            return $this->unauthorized('Token inválido o expirado');
        }

        // 2) Extraer claims clave
        $userId = $payload['sub'] ?? null;
        $jti    = $payload['jti'] ?? null;

        if (empty($userId) || empty($jti)) {
            return $this->unauthorized('Token sin claims requeridos (sub/jti)');
        }

        // 3) Validación extra contra DB (tabla auth_tokens)
        // Recomendado: guardar jti + user_id + expires_at + revoked_at
        $db = db_connect();

        $row = $db->table('auth_tokens')
            ->select('id, user_id, revoked_at, expires_at')
            ->where('jti', (string) $jti)
            ->where('user_id', (int) $userId)
            ->get()
            ->getRowArray();

        if (!$row) {
            return $this->unauthorized('Token no reconocido');
        }

        if (!empty($row['revoked_at'])) {
            return $this->unauthorized('Token revocado');
        }

        // Opcional: validar expiración contra DB también (además del exp del JWT)
        if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) <= time()) {
            return $this->unauthorized('Token expirado');
        }

        // 4) Guardar contexto para el resto de la request (controladores/servicios)
        // Lo más práctico: un service "authCtx"
        service('authCtx')->set([
            'user_id' => (int) $userId,
            'jti'     => (string) $jti,
            'claims'  => $payload,
        ]);

        return null; // deja pasar
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada
    }

    private function unauthorized(string $msg)
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON([
                'status'  => 'error',
                'data'    => null,
                'message' => $msg,
                'errors'  => (object)[],
            ]);
    }
}
