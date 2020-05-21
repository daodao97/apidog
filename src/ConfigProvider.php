<?php
declare(strict_types=1);
namespace Hyperf\Apidog;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'commands' => [],
            'dependencies' => [
                \Hyperf\HttpServer\Router\DispatcherFactory::class => DispatcherFactory::class
            ],
            'listeners' => [
                BootAppConfListener::class,
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
                    'description' => 'The config for apidog.',
                    'source' => __DIR__ . '/../publish/apidog.php',
                    'destination' => BASE_PATH . '/config/autoload/apidog.php',
                ],
            ],
        ];
    }
}
