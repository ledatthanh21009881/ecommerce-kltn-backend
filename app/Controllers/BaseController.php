<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BaseController
{
    protected $request;
    protected $response;
    protected $container;

    public function __construct($container = null)
    {
        $this->container = $container;
    }

    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;
        return $this;
    }

    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
        return $this;
    }

    protected function json($data, $statusCode = 200)
    {
        $this->response->getBody()->write(json_encode($data));
        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    protected function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode,
            'data' => $data
        ], $statusCode);
    }

    protected function error($message = 'Error', $statusCode = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return $this->json($response, $statusCode);
    }
}
