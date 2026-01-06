<?php

declare(strict_types=1);

namespace App\Controllers\Api\Base;

use App\Traits\ApiResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class ApiBaseController extends Controller
{
    use ApiResponseTrait;

    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        // API-only: nada de session, nada de email, nada de helpers web por defecto.
        // Si quieres, aquí puedes forzar JSON header para todas las respuestas:
        $this->response->setHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
