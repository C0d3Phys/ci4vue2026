<?php

declare(strict_types=1);

namespace App\Traits;

use CodeIgniter\HTTP\ResponseInterface;

trait ApiResponseTrait
{
    protected array $codes = [
        'ok'                => 200,
        'created'           => 201,
        'no_content'        => 204,
        'bad_request'       => 400,
        'unauthorized'      => 401,
        'forbidden'         => 403,
        'not_found'         => 404,
        'conflict'          => 409,
        'gone'              => 410,
        'unprocessable'     => 422,
        'too_many_requests' => 429,
        'server_error'      => 500,
    ];

    protected function apiRespond(
        array|string|null $data = null,
        int $httpCode = 200,
        ?string $message = null,
        array $errors = []
    ): ResponseInterface {
        // 204 No Content: sin body
        if ($httpCode === $this->codes['no_content']) {
            $response = $this->response ?? service('response');
            return $response->setStatusCode($httpCode);
        }

        $isOk = ($httpCode >= 200 && $httpCode < 300);

        $payload = [
            'status'  => $isOk ? 'success' : 'error',
            'data'    => $isOk ? ($data ?? (object)[]) : null,
            'message' => $isOk ? null : ($message ?? 'Error'),
        ];

        if (!$isOk) {
            $payload['errors'] = empty($errors) ? (object)[] : $errors;
        }

        $response = $this->response ?? service('response');
        return $response->setStatusCode($httpCode)->setJSON($payload);
    }

    public function success(array $data = []): ResponseInterface
    {
        return $this->apiRespond($data, $this->codes['ok'], null);
    }

    public function created(array $data = []): ResponseInterface
    {
        return $this->apiRespond($data, $this->codes['created'], null);
    }

    public function noContent(): ResponseInterface
    {
        $response = $this->response ?? service('response');
        return $response->setStatusCode($this->codes['no_content']);
    }

    public function badRequest(string $message = 'Solicitud inválida', array $errors = []): ResponseInterface
    {
        return $this->apiRespond(null, $this->codes['bad_request'], $message, $errors);
    }

    public function error(string $message, int $httpCode = 400, array $errors = []): ResponseInterface
    {
        return $this->apiRespond(null, $httpCode, $message, $errors);
    }

    public function notFound(string $message = 'Recurso no encontrado', array $errors = []): ResponseInterface
    {
        return $this->apiRespond(null, $this->codes['not_found'], $message, $errors);
    }

    public function unauthorized(string $message = 'No autorizado', array $errors = []): ResponseInterface
    {
        return $this->apiRespond(null, $this->codes['unauthorized'], $message, $errors);
    }

    public function forbidden(string $message = 'Prohibido', array $errors = []): ResponseInterface
    {
        return $this->apiRespond(null, $this->codes['forbidden'], $message, $errors);
    }

    public function conflict(string $message = 'Conflicto', array $errors = []): ResponseInterface
    {
        return $this->apiRespond(null, $this->codes['conflict'], $message, $errors);
    }

    public function tooManyRequests(string $message = 'Demasiadas solicitudes', array $errors = []): ResponseInterface
    {
        return $this->apiRespond(null, $this->codes['too_many_requests'], $message, $errors);
    }

    public function gone(string $message = 'El recurso ya no está disponible', array $errors = []): ResponseInterface
    {
        return $this->apiRespond(null, $this->codes['gone'], $message, $errors);
    }

    // Siempre array por campo (más simple para frontend)
    public function validationError(array $errors, string $message = 'Errores de validación'): ResponseInterface
    {
        $normalized = [];

        foreach ($errors as $field => $err) {
            if (is_string($err)) {
                $normalized[$field] = [$err];
                continue;
            }

            if (is_array($err)) {
                $list = array_values(array_filter($err, fn($v) => is_string($v) && $v !== ''));
                $normalized[$field] = $list;
                continue;
            }

            $normalized[$field] = [(string) $err];
        }

        return $this->apiRespond(null, $this->codes['unprocessable'], $message, $normalized);
    }

    public function serverError(string $message = 'Error interno del servidor', ?\Throwable $exception = null): ResponseInterface
    {
        $errors = [];

        if (ENVIRONMENT === 'development' && $exception) {
            $errors = [
                'debug' => [
                    'exception' => get_class($exception),
                    'trace'     => substr($exception->getTraceAsString(), 0, 4000),
                ],
            ];
        }

        return $this->apiRespond(null, $this->codes['server_error'], $message, $errors);
    }
}
