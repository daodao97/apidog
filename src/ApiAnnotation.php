<?php
declare(strict_types=1);
namespace Hyperf\Apidog;

use Doctrine\Common\Annotations\AnnotationReader;
use Hyperf\Apidog\Annotation\ApiController;
use Hyperf\Apidog\Annotation\ApiServer;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;

class ApiAnnotation
{
    public static function methodMetadata($className, $methodName)
    {
        $reflectMethod = ReflectionManager::reflectMethod($className, $methodName);
        $reader = new AnnotationReader();

        return $reader->getMethodAnnotations($reflectMethod);
    }

    public static function classMetadata($className) {
        return AnnotationCollector::list()[$className]['_c'] ?? [];
    }
}
