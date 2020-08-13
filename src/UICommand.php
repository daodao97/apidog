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
        $servers = $config->get('server.servers');
        $ui = 'default';
        $command = $this;
        $host = '127.0.0.1';
        $port = 9939;

        $http = new \Swoole\Http\Server($host, $port);
        $http->set([
            'document_root' => $root . '/' . $ui,
            'enable_static_handler' => true,
            'http_index_files' => ['index.html', 'doc.html'],
        ]);

        $http->on("start", function ($server) use ($root, $swagger_file, $ui, $command, $host, $port, $servers) {
            $command->output->success(sprintf('Apidog Swagger UI is started at http://%s:%s', $host, $port));
            $command->output->text('I will open it in browser after 1 seconds');

            foreach ($servers as $index => $server) {
                $copy_file = str_replace('{server}', $server['name'], $swagger_file);
                $copy_json = sprintf("cp %s %s", $copy_file, $root . '/' . $ui);
                system($copy_json);
                \Swoole\Timer::tick(1000, function () use ($copy_json) {
                    system($copy_json);
                });
                if ($index === 0) {
                    $index_html = $root . '/' . $ui;
                    $html = file_get_contents($index_html . '/index_tpl.html');
                    $path_info = explode('/', $copy_file);
                    $html = str_replace('{swagger-json-url}', sprintf('http://%s:%s/%s', $host, $port, end($path_info)), $html);
                    file_put_contents($index_html . '/index.html', $html);
                }
            }

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

