<?php

declare(strict_types=1);

namespace App\Controllers\Api\Base;

use CodeIgniter\HTTP\ResponseInterface;

abstract class ApiRestController extends ApiBaseController
{
    public function index(): ResponseInterface
    {
        return $this->error('Not implemented', 501);
    }

    public function show($id = null): ResponseInterface
    {
        return $this->error('Not implemented', 501);
    }

    public function create(): ResponseInterface
    {
        return $this->error('Not implemented', 501);
    }

    public function update($id = null): ResponseInterface
    {
        return $this->error('Not implemented', 501);
    }

    public function delete($id = null): ResponseInterface
    {
        return $this->error('Not implemented', 501);
    }
}
