<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Traits\ApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

final class AuditController extends BaseController
{
    use ApiResponseTrait;

    public function index(): ResponseInterface
    {
        // filtros típicos via query:
        // ?action=auth.login&entity=users&user_id=1&branch_id=2&from=2026-01-01&to=2026-01-31&page=1&per_page=50
        $q = $this->request->getGet();
        return $this->ok([
            'filters' => $q,
            'items'   => [],
        ], 'OK');
    }

    public function show(int $id): ResponseInterface
    {
        // TODO: buscar por id
        return $this->ok(['id' => $id], 'OK');
    }
}
