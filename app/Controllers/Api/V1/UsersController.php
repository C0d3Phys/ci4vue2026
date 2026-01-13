<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class UsersController extends BaseController
{
    use ApiResponseTrait;

    public function index(): ResponseInterface
    {
        // TODO: listar por tenant del token
        return $this->ok(['items' => []], 'OK');
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // TODO: validar + hash password + insertar
        return $this->created(['id' => 1], 'Usuario creado');
    }

    public function show(int $id): ResponseInterface
    {
        // TODO: validar tenant
        return $this->ok(['id' => $id], 'OK');
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        return $this->ok(['id' => $id], 'Usuario actualizado');
    }

    public function patch(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        return $this->ok(['id' => $id], 'Usuario actualizado (parcial)');
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->ok(null, 'Usuario eliminado');
    }

    public function setPassword(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // TODO: validar password + confirmar + hash
        return $this->ok(null, 'Password actualizado');
    }

    public function setDefaultBranch(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // expected: { default_branch_id: 123 }
        return $this->ok(null, 'Sucursal por defecto actualizada');
    }
}
