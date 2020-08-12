<?php
declare(strict_types=1);
namespace Hyperf\Apidog;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;

/**
 * @Command
 */
class UICommand extends HyperfCommand
{
    protected $name = 'apidog:ui';

    protected $coroutine = false;

    public function handle()
    {
        $dir = __DIR__;
        $root = realpath($dir . '/../ui');
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
        $swagger_file = $config->get('apidog.output_file');
        $ui = 'default';
        $command = $this;
        $host = '127.0.0.1';
        $port = 9527;

        $http = new \Swoole\Http\Server($host, $port);
        $http->set([
            'document_root' => $root . '/' . $ui,
            'enable_static_handler' => true,
            'http_index_files' => ['index.html', 'doc.html'],
        ]);

        $http->on("start", function ($server) use ($root, $swagger_file, $ui, $command, $host, $port) {
            $command->output->success('Apidog Swagger UI is started at http://127.0.0.1:9527');
            system(sprintf("cp %s %s", $swagger_file, $root . '/' . $ui . '/swagger.json'));
            $command->output->text('I will open it in browser after 1 seconds');
            \Swoole\Timer::after(1000, function () use ($host, $port) {
                // TODO win下
                system(sprintf('open http://%s:%s', $host, $port));
            });
        });

        $http->on("request", function ($request, $response) {
            $response->header("Content-Type", "text/plain");
            $response->end("This is apidog server.\n");
        });
        $http->start();
    }
}

