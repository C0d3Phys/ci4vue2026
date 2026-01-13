<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class TokensController extends BaseController
{
    use ApiResponseTrait;

    public function index(): ResponseInterface
    {
        // TODO: tokens del usuario actual
        return $this->ok(['items' => []], 'OK');
    }

    public function indexByUser(int $userId): ResponseInterface
    {
        // TODO: admin only
        return $this->ok(['user_id' => $userId, 'items' => []], 'OK');
    }

    public function revoke(int $id): ResponseInterface
    {
        // TODO: revocar token por id
        return $this->ok(null, 'Token revocado');
    }

    public function revokeByToken(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // expected: { token: "..." }
        return $this->ok(null, 'Token revocado');
    }

    public function revokeAll(): ResponseInterface
    {
        // TODO: revocar todos los tokens del usuario actual
        return $this->ok(null, 'Todos los tokens fueron revocados');
    }

    public function revokeAllByUser(int $userId): ResponseInterface
    {
        // TODO: admin only
        return $this->ok(null, 'Tokens del usuario revocados');
    }
}
