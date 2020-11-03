<?php
declare(strict_types=1);
namespace Hyperf\Apidog\Annotation;

/**
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
class Query extends Param
{
    public $in = 'query';
}
