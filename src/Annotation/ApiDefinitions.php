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

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class ApiDefinitions extends AbstractAnnotation
{
    /**
     * @var array
     */
    public $definitions;

    public function __construct($value = null)
    {
        $this->bindMainProperty('definitions', $value);
    }
}
