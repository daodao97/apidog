<?php

declare(strict_types = 1);
namespace Hyperf\Apidog;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'commands' => [],
            'dependencies' => [
                \Hyperf\Apidog\Validation\ValidationInterface::class => \Hyperf\Apidog\Validation\ApiParamsValidation::class,
                \Hyperf\HttpServer\Router\DispatcherFactory::class => \Hyperf\Apidog\DispatcherFactory::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for swagger.',
                    'source' => __DIR__ . '/../publish/swagger.php',
                    'destination' => BASE_PATH . '/config/autoload/swagger.php',
                ],
            ],
        ];
    }
}
