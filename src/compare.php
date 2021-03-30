<?php

namespace lolql;

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $len = strlen($needle);
        if ($haystack < $len) {
            return false;
        }
        $offset = -1 * $len;
        return strpos($haystack, $needle, $offset) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('is_assoc')) {
    function is_assoc(array $arr) {
        if ([] === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

/*
title == "hello"
title == subtitle
authors._ref == "a19"
*/
function cmp_eq($l, $r) {
    dbg('cmp +++ ', $l, $r);

    if ($l['t'] == 'k' && is_array($l['v'])) {
        return array_search($r['v'][0], $l['v']);
    }
    if ($l['t'] == 'k') {
        return ($l['v'] == $r['v'][0]);
    }
    if ($r['t'] == 'k' && is_array($r['v'])) {
        return array_search($l['v'][0], $r['v']);
    }
    if ($r['t'] == 'k') {
        return ($l['v'][0] == $r['v']);
    }
    return ($l['v'][0] == $r['v'][0]);
}

/*
title matches "world"
title matches "world*"
title matches "*world"
*/
function cmp_matches($l, $r) {
    if ($r['t'] != 'v') {
        return false;
    }
    $val = $r['c'][0];
    // arrays as name not supported for now
    if ($val[0] == '*') {
        return str_ends_with($l['v'], ltrim($val, '*'));
    }

    if ($val[-1] == '*') {
        return str_starts_with($l['v'], rtrim($val, '*'));
    }

    return str_contains($l['v'], $val);
}

/*
title in ["Aliens", "Interstellar", "Passengers"]
"yolo" in tags
*/
function cmp_in($l, $r) {
    if ($l['t'] == 'k') {
        $haystack = $l['v'];
        $needle = $r['v'][0];
    } else {
        $haystack = $r['v'];
        $needle = $r['v'][0];
    }
    return in_array($needle, $haystack);
}
