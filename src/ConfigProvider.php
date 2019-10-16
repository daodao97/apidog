<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Apidog;

use Hyperf\Cache\CacheListenerCollector;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\Apidog\Command\GenCommand;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'commands' => [
                GenCommand::class,
            ],
            'dependencies' => [
                \Hyperf\Apidog\Validation\ValidationInterface::class => \Hyperf\Apidog\Validation\ApiParamsValidation::class,
                \Hyperf\HttpServer\Router\DispatcherFactory::class => \Hyperf\Apidog\DispathcerFactory::class,
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
