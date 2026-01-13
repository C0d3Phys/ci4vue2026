<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class AuthController extends BaseController
{
    use ApiResponseTrait;

    public function login(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        // TODO: validar email/password
        // TODO: autenticar usuario
        // TODO: emitir token (JWT) o crear auth_tokens record

        return $this->ok([
            'access_token' => 'TODO',
            'token_type'   => 'Bearer',
            'expires_in'   => 3600,
        ], 'Login exitoso');
    }

    public function logout(): ResponseInterface
    {
        // TODO: revocar token actual (por jti o token string)
        return $this->ok(null, 'Sesión cerrada');
    }

    public function refresh(): ResponseInterface
    {
        // TODO: emitir nuevo access token
        return $this->ok([
            'access_token' => 'TODO_NEW',
            'token_type'   => 'Bearer',
            'expires_in'   => 3600,
        ], 'Token refrescado');
    }

    public function me(): ResponseInterface
    {
        // TODO: obtener usuario del token
        return $this->ok([
            'id'    => 0,
            'name'  => 'TODO',
            'email' => 'TODO',
        ], 'OK');
    }
}
