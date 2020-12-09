<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Apidog;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'commands' => [
                UICommand::class,
            ],
            'dependencies' => [
                \Hyperf\HttpServer\Router\DispatcherFactory::class => DispatcherFactory::class,
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
