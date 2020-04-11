<?php
declare(strict_types = 1);
namespace Hyperf\Apidog\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Path extends Param
{
    public $in = 'path';
}
