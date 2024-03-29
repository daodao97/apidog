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
namespace Hyperf\Apidog\Validation;

class ValidationCustomRule
{
    /**
     * @param mixed $attribute 属性
     * @param mixed $value 属性值
     *
     * @return bool|string 校验错误则返回错误信息, 正确则返回 true
     */
    public function test($attribute, $value)
    {
        return '自定义错误';
    }

    public function crontab($attribute, $value)
    {
        if (! preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($value))) {
            if (! preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($value))) {
                return '不是合法的crontab配置';
            }
        }
        return true;
    }

    public function class_exist($attribute, $value)
    {
        if (! class_exists((string) $value)) {
            return '类名不存在';
        }
        return true;
    }

    public function number_concat_ws_comma($attribute, $value)
    {
        if (! preg_match('/^\\d+(,\\d+)*$/', $value)) {
            return '不是英文逗号分隔的字符串';
        }

        return true;
    }
}
