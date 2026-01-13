<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class UserBranchesController extends BaseController
{
    use ApiResponseTrait;

    public function index(int $userId): ResponseInterface
    {
        // TODO: listar branches permitidas para el usuario
        return $this->ok(['user_id' => $userId, 'items' => []], 'OK');
    }

    public function attach(int $userId): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // expected: { branch_id: 123 }
        return $this->created(['user_id' => $userId, 'branch_id' => (int)($data['branch_id'] ?? 0)], 'Sucursal asignada al usuario');
    }

    public function detach(int $userId, int $branchId): ResponseInterface
    {
        // TODO: borrar pivote user_branches
        return $this->ok(null, 'Sucursal removida del usuario');
    }

    // estilo directo
    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // expected: { user_id, branch_id }
        return $this->created($data, 'Asignación creada');
    }

    public function delete(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // expected: { user_id, branch_id }
        return $this->ok(null, 'Asignación eliminada');
    }
}
