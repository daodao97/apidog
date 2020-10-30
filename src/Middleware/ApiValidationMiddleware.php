<?php
declare(strict_types=1);

namespace Hyperf\Apidog\Middleware;

use FastRoute\Dispatcher;
use Hyperf\Apidog\Validation\ValidationApi;
use Hyperf\Config\Annotation\Value;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\CoreMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Apidog\Exception\ApiDocException;

class ApiValidationMiddleware extends CoreMiddleware
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;

    protected $validationApi;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request, ValidationApi $validation)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
        $this->validationApi = $validation;
        parent::__construct($container, 'http');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $routes = $this->dispatcher->dispatch($request->getMethod(), $uri->getPath());
        if ($routes[0] !== Dispatcher::FOUND) {
            return $handler->handle($request);
        }

        // do not check Closure
        if ($routes[1]->callback instanceof \Closure) {
            return $handler->handle($request);
        }

        [$controller, $action] = $this->prepareHandler($routes[1]->callback);

        $result = $this->validationApi->validated($controller, $action);
        if ($result !== true) {
            $config = $this->container->get(ConfigInterface::class);
            $exceptionEnable = $config->get('apidog.exception_enable', false);
            if ($exceptionEnable) {
                $fieldErrorMessage = $config->get('apidog.field_error_message', 'message');
                throw new ApiDocException($fieldErrorMessage);
            }
            $httpStatusCode = $config->get('apidog.http_status_code', 400);
            return $this->response->json($result)->withStatus($httpStatusCode);
        }

        return $handler->handle($request);
    }
}
