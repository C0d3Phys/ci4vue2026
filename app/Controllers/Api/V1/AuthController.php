<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\Base\ApiBaseController;
use App\Models\UserModel;
use App\Models\AuthTokenModel;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthController extends ApiBaseController
{
    private const TOKEN_BYTE_LENGTH = 16;
    private const MAX_ACTIVE_SESSIONS = 5; // Limitar sesiones concurrentes

    private $userModel;
    private $authTokenModel;

    public function __construct()
    {
        $this->userModel = model(UserModel::class);
        $this->authTokenModel = model(AuthTokenModel::class);
    }

    public function login(): ResponseInterface
    {
        try {
            $payload = $this->getRequestPayload();

            // Validación mejorada
            $validation = $this->validateLoginPayload($payload);
            if ($validation !== true) {
                return $this->validationError($validation);
            }

            $tenantId = (int) $payload['tenant_id'];
            $email = strtolower(trim($payload['email']));
            $password = $payload['password'];

            // Buscar usuario
            $user = $this->userModel->findByEmailAndTenant($email, $tenantId);
            if (!$user) {
                return $this->unauthorized('Credenciales inválidas');
            }

            // Verificar estado
            if (!$this->isUserActive($user)) {
                return $this->forbidden('Usuario bloqueado');
            }

            // Verificar contraseña
            if (!$this->verifyPassword($password, $user['password_hash'])) {
                return $this->unauthorized('Credenciales inválidas');
            }

            // Limpiar sesiones antiguas del usuario
            $this->authTokenModel->cleanupExpired((int) $user['id']);
            $this->authTokenModel->enforceSessionLimit((int) $user['id'], self::MAX_ACTIVE_SESSIONS);

            // Generar nuevo token
            [$jwt, $jti, $expiresAt] = $this->issueJwtForUser($user);

            // Guardar sesión
            $this->authTokenModel->createToken(
                (int) $user['id'],
                $jti,
                $expiresAt,
                $this->request->getIPAddress(),
                (string) $this->request->getUserAgent()
            );

            return $this->success([
                'access_token' => $jwt,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt,
                'user' => $this->formatUserResponse($user),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Login error: ' . $e->getMessage());
            return $this->serverError('Error en login', $e);
        }
    }

    public function me(): ResponseInterface
    {
        try {
            $claims = $this->getValidatedClaims();
            if (!$claims) {
                return $this->unauthorized('Token inválido o expirado');
            }

            $uid = (int) $claims['uid'];
            $jti = $claims['jti'];

            // Validar sesión
            $session = $this->authTokenModel->findValidByJti($jti);
            if (!$session) {
                return $this->unauthorized('Sesión revocada o expirada');
            }

            // Cargar usuario
            $user = $this->userModel->find($uid);
            if (!$user) {
                return $this->unauthorized('Usuario no encontrado');
            }

            if (!$this->isUserActive($user)) {
                return $this->forbidden('Usuario bloqueado');
            }

            return $this->success([
                'session' => [
                    'id' => (int) $session['id'],
                    'expires_at' => $session['expires_at'],
                    'last_used_at' => $session['last_used_at'],
                ],
                'user' => $this->formatUserResponse($user),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Me endpoint error: ' . $e->getMessage());
            return $this->serverError('Error en me()', $e);
        }
    }

    public function logout(): ResponseInterface
    {
        try {
            $claims = $this->getValidatedClaims();
            if (!$claims) {
                return $this->unauthorized('Token inválido o expirado');
            }

            $jti = $claims['jti'];
            $this->authTokenModel->revokeByJti($jti);

            return $this->successMessage('Sesión cerrada exitosamente');
        } catch (\Throwable $e) {
            log_message('error', 'Logout error: ' . $e->getMessage());
            return $this->serverError('Error en logout', $e);
        }
    }

    public function refresh(): ResponseInterface
    {
        try {
            $claims = $this->getValidatedClaims();
            if (!$claims) {
                return $this->unauthorized('Token inválido o expirado');
            }

            $uid = (int) $claims['uid'];
            $jti = $claims['jti'];

            // Validar sesión actual
            $session = $this->authTokenModel->findValidByJti($jti);
            if (!$session) {
                return $this->unauthorized('Sesión revocada o expirada');
            }

            // Cargar usuario
            $user = $this->userModel->find($uid);
            if (!$user) {
                return $this->unauthorized('Usuario no encontrado');
            }

            if (!$this->isUserActive($user)) {
                return $this->forbidden('Usuario bloqueado');
            }

            // Revocar token actual
            $this->authTokenModel->revokeByJti($jti);

            // Generar nuevo token
            [$newJwt, $newJti, $expiresAt] = $this->issueJwtForUser($user);

            // Guardar nueva sesión
            $this->authTokenModel->createToken(
                (int) $user['id'],
                $newJti,
                $expiresAt,
                $this->request->getIPAddress(),
                (string) $this->request->getUserAgent()
            );

            return $this->success([
                'access_token' => $newJwt,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Refresh error: ' . $e->getMessage());
            return $this->serverError('Error en refresh', $e);
        }
    }

    // -------------------------
    // Helper Methods (Mejorados y organizados)
    // -------------------------

    private function getRequestPayload(): array
    {
        return $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
    }

    private function validateLoginPayload(array $payload): array|true
    {
        $errors = [];

        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($tenantId <= 0) {
            $errors['tenant_id'] = ['El ID de tenant es requerido'];
        }

        if ($email === '') {
            $errors['email'] = ['El email es requerido'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['El email no es válido'];
        }

        if ($password === '') {
            $errors['password'] = ['La contraseña es requerida'];
        }

        return empty($errors) ? true : $errors;
    }

    private function isUserActive(array $user): bool
    {
        return ($user['status'] ?? 'active') === 'active';
    }

    private function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    private function getBearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        if ($header === '') {
            return null;
        }

        if (!preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }

    private function decodeJwt(string $jwt): ?array
    {
        $secret = $this->getJwtSecret();
        if ($secret === '') {
            log_message('error', 'JWT_SECRET not configured');
            return null;
        }

        try {
            $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Throwable $e) {
            log_message('debug', 'JWT decode failed: ' . $e->getMessage());
            return null;
        }
    }

    private function getValidatedClaims(): ?array
    {
        $jwt = $this->getBearerToken();
        if (!$jwt) {
            return null;
        }

        $claims = $this->decodeJwt($jwt);
        if (!$claims) {
            return null;
        }

        $uid = (int) ($claims['uid'] ?? 0);
        $jti = (string) ($claims['jti'] ?? '');

        if ($uid <= 0 || $jti === '') {
            return null;
        }

        return $claims;
    }

    private function issueJwtForUser(array $user): array
    {
        $secret = $this->getJwtSecret();
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET no configurado');
        }

        $nowTs = time();
        $ttl = $this->getJwtTtl();
        $expTs = $nowTs + $ttl;

        $jti = $this->generateSecureToken();

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

    private function formatUserResponse(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'tenant_id' => (int) $user['tenant_id'],
            'default_branch_id' => $user['default_branch_id'] !== null ? (int) $user['default_branch_id'] : null,
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'status' => (string) ($user['status'] ?? 'active'),
        ];
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTE_LENGTH));
    }

    private function getJwtSecret(): string
    {
        return (string) env('JWT_SECRET', '');
    }

    private function getJwtTtl(): int
    {
        return (int) (env('JWT_TTL_SECONDS') ?: (7 * 24 * 60 * 60));
    }
}
