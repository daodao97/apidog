<?php
declare(strict_types=1);
namespace Hyperf\Apidog;

use Hyperf\Apidog\Annotation\ApiController;
use Hyperf\Apidog\Annotation\ApiVersion;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\HttpServer\Router\DispatcherFactory as HyperfDispatcherFactory;
use Hyperf\Utils\Str;

class DispatcherFactory extends HyperfDispatcherFactory
{
    protected function handleController(string $className, Controller $annotation, array $methodMetadata, array $middlewares = []): void
    {
        if (!$methodMetadata) {
            return;
        }
        $router = $this->getRouter($annotation->server);

        /** @var ApiVersion $version */
        $version = AnnotationCollector::list()[$className]['_c'][ApiVersion::class] ?? null;
        foreach ($methodMetadata as $methodName => $values) {
            $methodMiddlewares = $middlewares;
            // Handle method level middlewares.
            if (isset($values)) {
                $methodMiddlewares = array_merge($methodMiddlewares, $this->handleMiddleware($values));
                $methodMiddlewares = array_unique($methodMiddlewares);
            }

            foreach ($values as $mapping) {
                if (!($mapping instanceof Mapping)) {
                    continue;
                }
                if (!isset($mapping->methods)) {
                    continue;
                }

                $tokens = [$version ? $version->version : null, $annotation->prefix, $mapping->path];
                $tokens = array_map(function ($item) {
                    return Str::replaceFirst('/', '', $item);
                }, array_filter($tokens));
                $path = '/' . implode('/', $tokens);

                $router->addRoute($mapping->methods, $path, [$className, $methodName], [
                    'middleware' => $methodMiddlewares,
                ]);
            }
        }
    }

    protected function initAnnotationRoute(array $collector): void
    {
        foreach ($collector as $className => $metadata) {
            if (isset($metadata['_c'][ApiController::class])) {
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->handleController($className, $metadata['_c'][ApiController::class], $metadata['_m'] ?? [], $middlewares);
            }
        }
        parent::initAnnotationRoute($collector);
    }
}
