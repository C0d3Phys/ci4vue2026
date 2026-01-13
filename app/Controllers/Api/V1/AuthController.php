<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\Base\ApiBaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthController extends ApiBaseController
{
    public function login(): ResponseInterface
    {
        try {
            $payload  = $this->request->getJSON(true) ?? $this->request->getPost();
            $tenantId = (int)($payload['tenant_id'] ?? 0);
            $email    = strtolower(trim((string)($payload['email'] ?? '')));
            $password = (string)($payload['password'] ?? '');

            if ($tenantId <= 0 || $email === '' || $password === '') {
                $errors = [];
                if ($tenantId <= 0)   $errors['tenant_id'] = ['Requerido'];
                if ($email === '')    $errors['email']     = ['Requerido'];
                if ($password === '') $errors['password']  = ['Requerido'];
                return $this->validationError($errors);
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

            if (! password_verify($password, (string) $user['password_hash'])) {
                return $this->unauthorized('Credenciales inválidas');
            }

            [$jwt, $jti, $expiresAt] = $this->issueJwtForUser($user);

            // Guardar JTI para revocación
            $now = date('Y-m-d H:i:s');

            $db->table('auth_tokens')->insert([
                'user_id'      => (int) $user['id'],
                'token'        => $jti,                  // 👈 aquí guardamos JTI
                'expires_at'   => $expiresAt,
                'revoked_at'   => null,
                'created_at'   => $now,
                'last_used_at' => $now,
                'ip'           => $this->request->getIPAddress(),
                'user_agent'   => (string) $this->request->getUserAgent(),
            ]);

            return $this->success([
                'access_token' => $jwt,
                'token_type'   => 'Bearer',
                'expires_at'   => $expiresAt,
                'user' => [
                    'id'                => (int) $user['id'],
                    'tenant_id'         => (int) $user['tenant_id'],
                    'default_branch_id' => $user['default_branch_id'] !== null ? (int) $user['default_branch_id'] : null,
                    'name'              => (string) $user['name'],
                    'email'             => (string) $user['email'],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('Error en login', $e);
        }
    }

    public function me(): ResponseInterface
    {
        try {
            $jwt = $this->getBearerToken();
            if (! $jwt) {
                return $this->unauthorized('Token requerido');
            }

            $claims = $this->decodeJwt($jwt);
            if ($claims === null) {
                return $this->unauthorized('Token inválido o expirado');
            }

            $uid = (int) ($claims['uid'] ?? 0);
            $jti = (string) ($claims['jti'] ?? '');

            if ($uid <= 0 || $jti === '') {
                return $this->unauthorized('Token inválido');
            }

            $session = $this->findValidSessionByJti($jti);
            if (! $session) {
                return $this->unauthorized('Token revocado o no reconocido');
            }

            $db = db_connect();

            $user = $db->table('users')
                ->select('id, tenant_id, default_branch_id, name, email, status')
                ->where('id', $uid)
                ->get()
                ->getRowArray();

            if (! $user) {
                return $this->unauthorized('Token inválido');
            }

            if (($user['status'] ?? 'active') !== 'active') {
                return $this->forbidden('Usuario bloqueado');
            }

            return $this->success([
                'session' => [
                    'id'           => (int) $session['id'],
                    'expires_at'   => (string) $session['expires_at'],
                    'last_used_at' => (string) $session['last_used_at'],
                ],
                'user' => [
                    'id'                => (int) $user['id'],
                    'tenant_id'         => (int) $user['tenant_id'],
                    'default_branch_id' => $user['default_branch_id'] !== null ? (int) $user['default_branch_id'] : null,
                    'name'              => (string) $user['name'],
                    'email'             => (string) $user['email'],
                    'status'            => (string) $user['status'],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('Error en me()', $e);
        }
    }

    public function logout(): ResponseInterface
    {
        try {
            $jwt = $this->getBearerToken();
            if (! $jwt) {
                return $this->unauthorized('Token requerido');
            }

            $claims = $this->decodeJwt($jwt);
            if ($claims === null) {
                return $this->unauthorized('Token inválido o expirado');
            }

            $jti = (string) ($claims['jti'] ?? '');
            if ($jti === '') {
                return $this->unauthorized('Token inválido (sin jti)');
            }

            $db  = db_connect();
            $now = date('Y-m-d H:i:s');

            $db->table('auth_tokens')
                ->where('token', $jti) // token = JTI
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
            $jwt = $this->getBearerToken();
            if (! $jwt) {
                return $this->unauthorized('Token requerido');
            }

            $claims = $this->decodeJwt($jwt);
            if ($claims === null) {
                return $this->unauthorized('Token inválido o expirado');
            }

            $uid = (int) ($claims['uid'] ?? 0);
            $jti = (string) ($claims['jti'] ?? '');
            if ($uid <= 0 || $jti === '') {
                return $this->unauthorized('Token inválido');
            }

            // Asegurar que el token actual no esté revocado
            $session = $this->findValidSessionByJti($jti);
            if (! $session) {
                return $this->unauthorized('Token revocado o no reconocido');
            }

            $db  = db_connect();
            $now = date('Y-m-d H:i:s');

            // Revocar el JTI actual
            $db->table('auth_tokens')
                ->where('token', $jti)
                ->where('revoked_at IS NULL', null, false)
                ->set(['revoked_at' => $now, 'last_used_at' => $now])
                ->update();

            // Cargar user para regenerar claims consistentes
            $user = $db->table('users')
                ->select('id, tenant_id, default_branch_id, name, email, status')
                ->where('id', $uid)
                ->get()
                ->getRowArray();

            if (! $user) {
                return $this->unauthorized('Token inválido');
            }

            if (($user['status'] ?? 'active') !== 'active') {
                return $this->forbidden('Usuario bloqueado');
            }

            [$newJwt, $newJti, $expiresAt] = $this->issueJwtForUser($user);

            // Guardar nuevo JTI
            $db->table('auth_tokens')->insert([
                'user_id'      => (int) $user['id'],
                'token'        => $newJti,
                'expires_at'   => $expiresAt,
                'revoked_at'   => null,
                'created_at'   => $now,
                'last_used_at' => $now,
                'ip'           => $this->request->getIPAddress(),
                'user_agent'   => (string) $this->request->getUserAgent(),
            ]);

            return $this->success([
                'access_token' => $newJwt,
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

    /**
     * Decodifica JWT y valida firma/exp.
     * Retorna claims como array o null si inválido/expirado.
     */
    private function decodeJwt(string $jwt): ?array
    {
        $secret = (string) env('JWT_SECRET');
        if ($secret === '') {
            return null;
        }

        try {
            $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Emite JWT para un usuario, devuelve [jwt, jti, expiresAtYmdHis]
     */
    private function issueJwtForUser(array $user): array
    {
        $secret = (string) env('JWT_SECRET');
        if ($secret === '') {
            // Esto lo controlamos arriba, pero por seguridad:
            throw new \RuntimeException('JWT_SECRET no configurado');
        }

        $nowTs = time();
        $ttl   = (int) (env('JWT_TTL_SECONDS') ?: (7 * 24 * 60 * 60));
        $expTs = $nowTs + $ttl;

        $jti = bin2hex(random_bytes(16)); // 32 hex

        $claims = [
            'iss' => base_url(),
            'iat' => $nowTs,
            'nbf' => $nowTs,
            'exp' => $expTs,
            'jti' => $jti,

            'sub' => (string) $user['id'],
            'uid' => (int) $user['id'],
            'tid' => (int) $user['tenant_id'],
            'bid' => $user['default_branch_id'] !== null ? (int) $user['default_branch_id'] : null,
        ];

        $jwt = JWT::encode($claims, $secret, 'HS256');

        return [$jwt, $jti, date('Y-m-d H:i:s', $expTs)];
    }

    /**
     * Busca la sesión por JTI (guardado en auth_tokens.token).
     * Además hace "touch" de last_used_at.
     */
    private function findValidSessionByJti(string $jti): ?array
    {
        $db = db_connect();

        $row = $db->table('auth_tokens')
            ->select('id, user_id, token, expires_at, revoked_at, last_used_at')
            ->where('token', $jti)
            ->where('revoked_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        if (! $row) {
            return null;
        }

        if (! empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        $db->table('auth_tokens')
            ->where('id', (int) $row['id'])
            ->set(['last_used_at' => date('Y-m-d H:i:s')])
            ->update();

        return $row;
    }
}
