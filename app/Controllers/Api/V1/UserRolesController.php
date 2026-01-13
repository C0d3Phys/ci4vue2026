<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class UserRolesController extends BaseController
{
    use ApiResponseTrait;

    public function index(int $userId): ResponseInterface
    {
        return $this->ok(['user_id' => $userId, 'items' => []], 'OK');
    }

    public function attach(int $userId): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // expected: { role_id: 123 }
        return $this->created(['user_id' => $userId, 'role_id' => (int)($data['role_id'] ?? 0)], 'Rol asignado al usuario');
    }

    public function detach(int $userId, int $roleId): ResponseInterface
    {
        return $this->ok(null, 'Rol removido del usuario');
    }

    // estilo directo
    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // expected: { user_id, role_id }
        return $this->created($data, 'Asignación creada');
    }

    public function delete(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // expected: { user_id, role_id }
        return $this->ok(null, 'Asignación eliminada');
    }
}
