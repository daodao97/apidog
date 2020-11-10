<?php

namespace Hyperf\Apidog\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"ALL"})
 */
class ApiDefinition extends AbstractAnnotation
{
    public $name;

    public $properties;
}
