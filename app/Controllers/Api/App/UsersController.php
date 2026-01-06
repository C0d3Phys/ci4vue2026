<?php

declare(strict_types=1);

namespace App\Controllers\Api\App;

use App\Controllers\Api\Base\ApiRestController;
use CodeIgniter\HTTP\ResponseInterface;

class UsersController extends ApiRestController
{
    public function index(): ResponseInterface
    {
        return $this->success([
            ['id' => 1, 'name' => 'Cesar'],
        ]);
    }

    public function create(): ResponseInterface
    {
        return $this->created(['id' => 123]);
    }
}
