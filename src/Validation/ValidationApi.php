<?php

namespace Hyperf\Apidog\Validation;

use Hyperf\Apidog\Annotation\Body;
use Hyperf\Apidog\Annotation\FormData;
use Hyperf\Apidog\Annotation\Header;
use Hyperf\Apidog\Annotation\Query;
use Hyperf\Apidog\ApiAnnotation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Psr\Http\Message\ServerRequestInterface;

class ValidationApi
{
    public $validation;

    public function __construct()
    {
        $this->validation = make(Validation::class);
    }

    public function validated($controller, $action)
    {
        $container = ApplicationContext::getContainer();
        $controllerInstance = $container->get($controller);
        $request = $container->get(ServerRequestInterface::class);
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
            return true;
        }

        $config = make(ConfigInterface::class);
        $error_code = $config->get('apidog.error_code', -1);
        $field_error_code = $config->get('apidog.field_error_code', 'code');
        $field_error_message = $config->get('apidog.field_error_message', 'message');

        if ($header_rules) {
            $headers = $request->getHeaders();
            $headers = array_map(function ($item) {
                return $item[0];
            }, $headers);
            [
                $data,
                $error,
            ] = $this->check($header_rules, $headers, $controllerInstance);
            if ($data === null) {
                return [
                    $field_error_code => $error_code,
                    $field_error_message => implode(PHP_EOL, $error),
                ];
            }
        }

        if ($query_rules) {
            [
                $data,
                $error,
            ] = $this->check($query_rules, $request->getQueryParams(), $controllerInstance);
            if ($data === null) {
                return [
                    $field_error_code => $error_code,
                    $field_error_message => implode(PHP_EOL, $error),
                ];
            }
            Context::set(ServerRequestInterface::class, $request->withQueryParams($data));
        }

        if ($body_rules) {
            [
                $data,
                $error,
            ] = $this->check($body_rules, (array)json_decode($request->getBody()->getContents(), true), $controllerInstance);
            if ($data === null) {
                return [
                    $field_error_code => $error_code,
                    $field_error_message => implode(PHP_EOL, $error),
                ];
            }
            Context::set(ServerRequestInterface::class, $request->withBody(new SwooleStream(json_encode($data))));
        }

        if ($form_data_rules) {
            [
                $data,
                $error,
            ] = $this->check($form_data_rules, array_merge($request->getUploadedFiles(),$request->getParsedBody()), $controllerInstance);
            if ($data === null) {
                return [
                    $field_error_code => $error_code,
                    $field_error_message => implode(PHP_EOL, $error),
                ];
            }
            Context::set(ServerRequestInterface::class, $request->withParsedBody($data));
        }

        isset($data) && Context::set('validator.data', $data);

        return true;
    }

    public function check($rules, $data, $controllerInstance)
    {
        [
            $data,
            $error,
        ] = $this->validation->check($rules, $data, $controllerInstance);
        return [$data, $error];
    }
}
