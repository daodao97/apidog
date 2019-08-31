<?php
declare(strict_types = 1);
namespace Hyperf\Apidog;

class ConfigProvider
{

    public function __invoke(): array
    {
        return [
            'dependencies' => [
            ],
            'commands' => [],
            'scan' => [
                'paths' => [
                    __DIR__,
                ],
            ],
        ];
    }
}
