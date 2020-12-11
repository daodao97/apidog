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
namespace HyperfTest\Apidog;

use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class FunctionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testArrayMapRecursive()
    {
        $callback = function ($value) {
            return is_string($value) ? trim($value) : $value;
        };
        $data = array_map_recursive($callback, [
            'id' => 1,
            'name' => 'Hyperf ',
        ]);
        $this->assertSame(['id' => 1, 'name' => 'Hyperf'], $data);

        $data = array_map_recursive($callback, [
            'id' => 1,
            'data' => [
                'Hyperf',
                'Apidog ',
            ],
        ]);
        $this->assertSame(['id' => 1, 'data' => ['Hyperf', 'Apidog']], $data);
    }
}
