# hyperf apidog 

## Api Watch Dog
一个 [Hyperf](https://github.com/hyperf-cloud/hyperf) 框架的 Api 参数校验及 swagger 文档生成扩展

1.  根据注解自动进行Api参数的校验, 业务代码更纯粹.
2.  根据注解自动生成Swagger文档, 让接口文档维护更省心.

## 安装

```
composer install hyperf/apidog
```

## 配置

```php
// config/autoload/middlewares.php 定义使用中间件
<?php
declare(strict_types=1);

return [
    'http' => [
        Hyperf\Apidog\Middleware\ValidationMiddleware::class,
    ],
];

// config/autoload/swagger.php  swagger 基础信息
<?php
declare(strict_types=1);

return [
    'output_file' => BASE_PATH . '/public/swagger.json',
    'swagger' => '2.0',
    'info' => [
        'description' => 'hyperf swagger api desc',
        'version' => '1.0.0',
        'title' => 'HYPERF API DOC',
    ],
    'host' => 'apidog.com',
    'schemes' => ['http']
];

// config/dependencies.php  重写 DispathcerFactory 依赖
<?php
declare(strict_types=1);

return [
    'dependencies' => [
        Hyperf\HttpServer\Router\DispatcherFactory::class => Hyperf\Apidog\DispathcerFactory::class
    ],
];

```

## 使用

```php
<?php
declare(strict_types = 1);
namespace App\Controller;

use Hyperf\Apidog\Annotation\ApiController;
use Hyperf\Apidog\Annotation\ApiResponse;
use Hyperf\Apidog\Annotation\Body;
use Hyperf\Apidog\Annotation\DeleteApi;
use Hyperf\Apidog\Annotation\FormData;
use Hyperf\Apidog\Annotation\GetApi;
use Hyperf\Apidog\Annotation\Header;
use Hyperf\Apidog\Annotation\PostApi;
use Hyperf\Apidog\Annotation\Query;

/**
 * @ApiController(tag="用户管理", description="用户的新增/修改/删除接口")
 */
class UserController extends Controller
{

    /**
     * @PostApi(path="/user", description="添加一个用户")
     * @Header(key="token|接口访问凭证", rule="required|cb_checkToken")
     * @FormData(key="name|名称", rule="required|trim|max_width[10]|min_width[2]")
     * @FormData(key="age|年龄", rule="int|enum[0,1]")
     * @ApiResponse(code="-1", description="参数错误")
     * @ApiResponse(code="0", description="创建成功", schema={"id":1})
     */
    public function add()
    {
        return [
            'code' => 0,
            'id' => 1
        ];
    }

    /**
     * @DeleteApi(path="/user", description="删除用户")
     * @Body(rules={
     *     "id|用户id":"require|int|gt[0]"
     * })
     * @ApiResponse(code="-1", description="参数错误")
     * @ApiResponse(code="0", description="删除成功", schema={"id":1})
     */
    public function delete()
    {
        return [
            'code' => 0,
            'id' => 1
        ];
    }

    /**
     * @GetApi(path="/user", description="获取用户详情")
     * @Query(key="id", rule="required|int|gt[0]")
     * @ApiResponse(code="-1", description="参数错误")
     * @ApiResponse(code="0", schema={"id":1,"name":"张三","age":1})
     */
    public function get()
    {
        return [
            'code' => 0,
            'id' => 1,
            'name' => '张三',
            'age' => 1
        ];
    }
}
```

## Swagger展示
![swagger](http://tva1.sinaimg.cn/large/007X8olVly1g6j91o6xroj31k10u079l.jpg)
