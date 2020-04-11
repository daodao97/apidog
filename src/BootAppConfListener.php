<?php
namespace Hyperf\Apidog;

use Hyperf\Apidog\Swagger\SwaggerJson;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;

class BootAppConfListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event)
    {
        $container = ApplicationContext::getContainer();
        $logger = $container->get(LoggerFactory::class)->get('apidog');
        $config = $container->get(ConfigInterface::class);
        if (!$config->get('apidog.enable')) {
            $logger->debug('apidog not enable');
            return;
        }
        $enable = $config->get('apidog.output_file');
        if (!$enable) {
            $logger->error('/config/autoload/apidog.php need set output_file');
            return;
        }
        $router = $container->get(DispatcherFactory::class)->getRouter('http');
        $data = $router->getData();
        $swagger = new SwaggerJson();

        $ignore = $config->get('apidog.ignore', function ($controller, $action) { return false;});

        array_walk_recursive($data, function ($item) use ($swagger, $ignore) {
            if ($item instanceof Handler) {
                [$controller, $action] = $this->prepareHandler($item->callback);
                (!$ignore($controller, $action)) && $swagger->addPath($controller, $action);
            }
        });

        $swagger->save();
    }

    protected function prepareHandler($handler): array
    {
        if (is_string($handler)) {
            if (strpos($handler, '@') !== false) {
                return explode('@', $handler);
            }
            return explode('::', $handler);
        }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            return $handler;
        }
        throw new \RuntimeException('Handler not exist.');
    }
}
