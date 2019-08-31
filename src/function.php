<?php
declare(strict_types = 1);

function array_get_node($key, $arr = [], $default = null)
{
    $path = explode('.', $key);
    foreach ($path as $key) {
        $key = trim($key);
        if (empty($arr) || !isset($arr[$key])) {
            return $default;
        }
        $arr = $arr[$key];
    }

    return $arr;
}

function controllerNameToPath($className)
{
    $path = strtolower($className);
    $path = str_replace('\\', '/', $path);
    $path = str_replace('app/controller', '', $path);
    $path = str_replace('controller', '', $path);
    return $path;
}
