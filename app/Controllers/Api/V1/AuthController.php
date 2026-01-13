<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\Base\ApiBaseController;
use CodeIgniter\HTTP\ResponseInterface;

final class AuthController extends ApiBaseController
{
    public function login(): ResponseInterface
    {
        try {
            $payload = $this->request->getJSON(true) ?? $this->request->getPost();

            $tenantId  = (int)($payload['tenant_id'] ?? 0);
            $email     = strtolower(trim((string)($payload['email'] ?? '')));
            $password  = (string)($payload['password'] ?? '');

            if ($tenantId <= 0 || $email === '' || $password === '') {
                return $this->validationError([
                    'tenant_id' => $tenantId <= 0 ? ['Requerido'] : [],
                    'email'     => $email === '' ? ['Requerido'] : [],
                    'password'  => $password === '' ? ['Requerido'] : [],
                ]);
            }

            $db = db_connect();

            $user = $db->table('users')
                ->select('id, tenant_id, default_branch_id, name, email, password_hash, status')
                ->where('tenant_id', $tenantId)
                ->where('email', $email)
                ->get()
                ->getRowArray();

            if (! $user) {
                return $this->unauthorized('Credenciales inválidas');
            }

            if (($user['status'] ?? 'active') !== 'active') {
                return $this->forbidden('Usuario bloqueado');
            }

            if (! password_verify($password, (string)$user['password_hash'])) {
                return $this->unauthorized('Credenciales inválidas');
            }

            // token random (si luego migras a JWT, aquí guardas jti)
            $token     = bin2hex(random_bytes(32)); // 64 chars
            $now       = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // 7 días

            $db->table('auth_tokens')->insert([
                'user_id'      => (int)$user['id'],
                'token'        => $token,
                'expires_at'   => $expiresAt,
                'revoked_at'   => null,
                'created_at'   => $now,
                'last_used_at' => $now,
                'ip'           => $this->request->getIPAddress(),
                'user_agent'   => (string) $this->request->getUserAgent(),
            ]);

            return $this->success([
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'expires_at'   => $expiresAt,
                'user' => [
                    'id'                => (int)$user['id'],
                    'tenant_id'         => (int)$user['tenant_id'],
                    'default_branch_id' => $user['default_branch_id'] !== null ? (int)$user['default_branch_id'] : null,
                    'name'              => (string)$user['name'],
                    'email'             => (string)$user['email'],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('Error en login', $e);
        }
    }

    public function me(): ResponseInterface
    {
        try {
            $token = $this->getBearerToken();
            if (! $token) {
                return $this->unauthorized('Token requerido');
            }

            $session = $this->findValidSession($token);
            if (! $session) {
                return $this->unauthorized('Token inválido o expirado');
            }

            $db = db_connect();

            $user = $db->table('users')
                ->select('id, tenant_id, default_branch_id, name, email, status')
                ->where('id', (int)$session['user_id'])
                ->get()
                ->getRowArray();

            if (! $user) {
                return $this->unauthorized('Token inválido');
            }

            return $this->success([
                'session' => [
                    'id'          => (int)$session['id'],
                    'expires_at'  => $session['expires_at'],
                    'last_used_at' => $session['last_used_at'],
                ],
                'user' => [
                    'id'                => (int)$user['id'],
                    'tenant_id'         => (int)$user['tenant_id'],
                    'default_branch_id' => $user['default_branch_id'] !== null ? (int)$user['default_branch_id'] : null,
                    'name'              => (string)$user['name'],
                    'email'             => (string)$user['email'],
                    'status'            => (string)$user['status'],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('Error en me()', $e);
        }
    }

    public function logout(): ResponseInterface
    {
        try {
            $token = $this->getBearerToken();
            if (! $token) {
                return $this->unauthorized('Token requerido');
            }

            $db  = db_connect();
            $now = date('Y-m-d H:i:s');

            $db->table('auth_tokens')
                ->where('token', $token)
                ->where('revoked_at IS NULL', null, false)
                ->set(['revoked_at' => $now, 'last_used_at' => $now])
                ->update();

            return $this->successMessage('Sesión cerrada');
        } catch (\Throwable $e) {
            return $this->serverError('Error en logout', $e);
        }
    }

    public function refresh(): ResponseInterface
    {
        try {
            $token = $this->getBearerToken();
            if (! $token) {
                return $this->unauthorized('Token requerido');
            }

            $session = $this->findValidSession($token);
            if (! $session) {
                return $this->unauthorized('Token inválido o expirado');
            }

            $db  = db_connect();
            $now = date('Y-m-d H:i:s');

            // revocar actual
            $db->table('auth_tokens')
                ->where('id', (int)$session['id'])
                ->set(['revoked_at' => $now, 'last_used_at' => $now])
                ->update();

            // emitir nuevo
            $newToken  = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60));

            $db->table('auth_tokens')->insert([
                'user_id'      => (int)$session['user_id'],
                'token'        => $newToken,
                'expires_at'   => $expiresAt,
                'revoked_at'   => null,
                'created_at'   => $now,
                'last_used_at' => $now,
                'ip'           => $this->request->getIPAddress(),
                'user_agent'   => (string) $this->request->getUserAgent(),
            ]);

            return $this->success([
                'access_token' => $newToken,
                'token_type'   => 'Bearer',
                'expires_at'   => $expiresAt,
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('Error en refresh', $e);
        }
    }

    // -------------------------
    // Helpers internos
    // -------------------------

    private function getBearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        if ($header === '') {
            return null;
        }

        if (! preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            return null;
        }

        return $m[1] ?? null;
    }

    private function findValidSession(string $token): ?array
    {
        $db = db_connect();

        $row = $db->table('auth_tokens')
            ->select('id, user_id, token, expires_at, revoked_at, last_used_at')
            ->where('token', $token)
            ->where('revoked_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        if (! $row) {
            return null;
        }

        if (! empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
            return null;
        }

        // touch last_used_at
        $db->table('auth_tokens')
            ->where('id', (int)$row['id'])
            ->set(['last_used_at' => date('Y-m-d H:i:s')])
            ->update();

        return $row;
    }
}
