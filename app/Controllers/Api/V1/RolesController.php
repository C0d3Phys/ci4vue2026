<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class RolesController extends BaseController
{
    use ApiResponseTrait;

    public function index(): ResponseInterface
    {
        return $this->ok(['items' => []], 'OK');
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        return $this->created(['id' => 1], 'Rol creado');
    }

    public function show(int $id): ResponseInterface
    {
        return $this->ok(['id' => $id], 'OK');
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        return $this->ok(['id' => $id], 'Rol actualizado');
    }

    public function patch(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        return $this->ok(['id' => $id], 'Rol actualizado (parcial)');
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->ok(null, 'Rol eliminado');
    }
}
