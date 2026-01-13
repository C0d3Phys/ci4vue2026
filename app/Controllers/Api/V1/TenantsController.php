<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class TenantsController extends BaseController
{
    use ApiResponseTrait;

    public function index(): ResponseInterface
    {
        // TODO: superadmin only
        return $this->ok([
            'items' => [],
        ], 'OK');
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // TODO: validar + insertar
        return $this->created(['id' => 1], 'Tenant creado');
    }

    public function show(int $id): ResponseInterface
    {
        // TODO: buscar por id
        return $this->ok(['id' => $id], 'OK');
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // TODO: reemplazo completo (PUT)
        return $this->ok(['id' => $id], 'Tenant actualizado');
    }

    public function patch(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // TODO: parcial (PATCH)
        return $this->ok(['id' => $id], 'Tenant actualizado (parcial)');
    }

    public function delete(int $id): ResponseInterface
    {
        // TODO: borrar
        return $this->ok(null, 'Tenant eliminado');
    }
}
