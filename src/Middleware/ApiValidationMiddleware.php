<?php
declare(strict_types=1);

namespace Hyperf\Apidog\Middleware;

use FastRoute\Dispatcher;
use Hyperf\Apidog\Annotation\Body;
use Hyperf\Apidog\Annotation\FormData;
use Hyperf\Apidog\Annotation\Header;
use Hyperf\Apidog\Annotation\Query;
use Hyperf\Apidog\ApiAnnotation;
use Hyperf\Apidog\Validation\Validation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\CoreMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\Context;

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

    protected $logger;

    protected $validation;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request, LoggerFactory $logger, Validation $validation)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
        $this->logger = $logger->get('validation');
        $this->validation = $validation;
        parent::__construct($container, 'http');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $routes = $this->dispatcher->dispatch($request->getMethod(), $uri->getPath());
        if ($routes[0] !== Dispatcher::FOUND) {
            return $handler->handle($request);
        }

        [$controller, $action] = $this->prepareHandler($routes[1]->callback);

        $controllerInstance = $this->container->get($controller);
        $annotations = ApiAnnotation::methodMetadata($controller, $action);
        $header_rules = [];
        $query_rules = [];
        $body_rules = [];
        $form_data_rules = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Header) {
                $header_rules[$annotation->key] = $annotation->rule;
            }
            if ($annotation instanceof Query) {
                $query_rules[$annotation->key] = $annotation->rule;
            }
            if ($annotation instanceof Body) {
                $body_rules = $annotation->rules;
            }
            if ($annotation instanceof FormData) {
                $form_data_rules[$annotation->key] = $annotation->rule;
            }
        }

        if (!array_filter(compact('header_rules', 'query_rules', 'body_rules', 'form_data_rules'))) {
            return $handler->handle($request);
        }

        $error_code = $this->container->get(ConfigInterface::class)->get('apidoc.error_code', -1);
        $http_status_code = $this->container->get(ConfigInterface::class)->get('apidoc.http_status_code', 200);
        $field_error_code = $this->container->get(ConfigInterface::class)->get('apidoc.field_error_code', 'code');
        $field_error_message = $this->container->get(ConfigInterface::class)->get('apidoc.field_error_message', 'message');

        if ($header_rules) {
            $headers = $request->getHeaders();
            $headers = array_map(function ($item) {
                return $item[0];
            }, $headers);
            [$data, $error] = $this->check($header_rules, $headers, $controllerInstance);
            if ($data === null) {
                return $this->response->raw(json_encode([
                    $field_error_code => $error_code,
                    $field_error_message => implode(PHP_EOL, $error)
                ], JSON_UNESCAPED_UNICODE))->withStatus($http_status_code);
            }
        }

        if ($query_rules) {
            [$data, $error] = $this->check($query_rules, $request->getQueryParams(), $controllerInstance);
            if ($data === null) {
                return $this->response->raw(json_encode([
                    $field_error_code => $error_code,
                    $field_error_message => implode(PHP_EOL, $error)
                ], JSON_UNESCAPED_UNICODE))->withStatus($http_status_code);
            }
            Context::set(ServerRequestInterface::class, $request->withQueryParams($data));
        }

        if ($body_rules) {
            [$data, $error] = $this->check($body_rules, (array)json_decode($request->getBody()->getContents(), true), $controllerInstance);
            if ($data === null) {
                return $this->response->raw(json_encode([
                    $field_error_code => $error_code,
                    $field_error_message => implode(PHP_EOL, $error)
                ], JSON_UNESCAPED_UNICODE))->withStatus($http_status_code);
            }
            Context::set(ServerRequestInterface::class, $request->withBody(new SwooleStream(json_encode($data))));
        }

        if ($form_data_rules) {
            [$data, $error] = $this->check($form_data_rules, $request->getParsedBody(), $controllerInstance);
            if ($data === null) {
                return $this->response->raw(json_encode([
                    $field_error_code => $error_code,
                    $field_error_message => implode(PHP_EOL, $error)
                ], JSON_UNESCAPED_UNICODE))->withStatus($http_status_code);
            }
            Context::set(ServerRequestInterface::class, $request->withParsedBody($data));
        }

        isset($data) && Context::set('validator.data', $data);
        return $handler->handle($request);
    }

    public function check($rules, $data, $controllerInstance)
    {
        [$data, $error] = $this->validation->check($rules, $data, $controllerInstance);
        return [$data, $error];
    }
}
