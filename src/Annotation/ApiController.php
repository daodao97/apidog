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

use Attribute;
use Hyperf\HttpServer\Annotation\Controller;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiController extends Controller
{
    public $tag;

    public string $prefix = '';

    public string $server = 'http';

    public string $description = '';
}
