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
namespace Hyperf\Apidog\Annotation;

/**
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
class Query extends Param
{
    public $in = 'query';
}
