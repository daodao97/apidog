<?php
declare(strict_types=1);

function array_map_recursive(callable $func, array $data)
{
    $result = array();
    foreach ($data as $key => $val) {
        $result[$key] = is_array($val)
            ? array_map_recursive($func, $val)
            : call($func, [$val]);
    }

    return $result;
}

if (!function_exists('array_sort_by_value_length')) {
    function array_sort_by_value_length($arr, $sort_order = SORT_DESC)
    {
        $keys = array_map('strlen', $arr);
        array_multisort($keys, $sort_order, $arr);
        return $arr;
    }
}

if (!function_exists('array_sort_by_key_length')) {
    function array_sort_by_key_length($arr, $sort_order = SORT_DESC)
    {
        $keys = array_map('strlen', array_keys($arr));
        array_multisort($keys, $sort_order, $arr);
        return $arr;
    }
}
