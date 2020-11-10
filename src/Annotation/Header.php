<?php
declare(strict_types=1);
namespace Hyperf\Apidog\Annotation;

/**
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
class Header extends Param
{
    public $in = 'header';
}
