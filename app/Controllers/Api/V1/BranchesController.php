<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class BranchesController extends BaseController
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
        // TODO: validar tenant_id del token, insertar branch
        return $this->created(['id' => 1], 'Sucursal creada');
    }

    public function show(int $id): ResponseInterface
    {
        // TODO: validar tenant
        return $this->ok(['id' => $id], 'OK');
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        return $this->ok(['id' => $id], 'Sucursal actualizada');
    }

    public function patch(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        return $this->ok(['id' => $id], 'Sucursal actualizada (parcial)');
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->ok(null, 'Sucursal eliminada');
    }

    public function indexByTenant(int $tenantId): ResponseInterface
    {
        // TODO: superadmin/admin tenant
        return $this->ok(['tenant_id' => $tenantId, 'items' => []], 'OK');
    }

    public function createForTenant(int $tenantId): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        // TODO: forzar tenant_id = $tenantId
        return $this->created(['id' => 1, 'tenant_id' => $tenantId], 'Sucursal creada');
    }
}
