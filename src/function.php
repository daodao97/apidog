<?php
declare(strict_types = 1);

function array_map_recursive(callable $func, array $data) {
    $result = array();
    foreach ($data as $key => $val)
    {
        $result[$key] = is_array($val)
            ? array_map_recursive($func, $val)
            : call($func, [$val]);
    }

    return $result;
}
