<?php

function stack_token($tokens, $level = 1) {
    $res = [];
    $bufferL = [];
    $buffer = [];
    while ($t = array_shift($tokens)) {
        if ($t == '(' || ($t == '[' && $level == 1)) {
            $res[] = $buffer;
            $buffer = [];
            [$cont, $tokens] = stack_token($tokens, $level + 1);
            $res[] = $cont;
        } elseif ($t == ')' || ($t == ']' && $level == 2)) {
            $res[] = $buffer;
            if ($level > 1) {
                return [$res, $tokens];
            } else {
                $buffer = [];
            }
        } else {
            $buffer[] = $t;
        }
    }
    return $res;
}

function xxarray_map_recursive($callback, $array) {
    $func = function ($item) use (&$func, &$callback) {
        return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
    };

    return array_map($func, $array);
}

function xxxbuild_sorter($key) {
    return function ($a, $b) use ($key) {
        return strnatcmp($a[$key], $b[$key]);
    };
}

function xxquery($ds, $filter) {
    if (is_string($filter)) {
        $filter = ['_type' => $filter];
    }
    $rs = array_filter($ds, function ($row) use ($filter) {
        return evaluate($filter, $row);
    });

    if ($filter['_type'] == 'artist') {
        $skey = 'firstname';
    }
    $s_fn = build_order_fun('firstname, familyname desc');

    if ($s_fn) {
        // dbg('... query sorting..');
        // usort($rs, build_sorter($skey));
        usort($rs, $s_fn);
    }
    return $rs;
}

function xxeval_cond($db, $query) {
    $evaluator = function ($query, $item, $level = 0) use (&$evaluator) {
        // dbg('level... ', $level);
        foreach ($query as $q) {
            if (!is_assoc($q)) {
                //print "\n\nhuhu\n\n";
                //\dbg('.. klammer', $q);
                $ok = $evaluator($q, $item, $level + 1);
            } else {
                $ok = evaluate_single($q['l'], $q['r'], $q['o'], $item);
            }

            // dbg('eval result', $ok, $q['x']);
            if (!$ok && $q['x'] == '&&') {
                return false;
            }
            if ($ok && $q['x'] == '||') {
                return true;
            }
        }
        return $ok;
    };

    return array_filter($db, function ($item) use ($query, $evaluator) {
        // dbg('item-compare...', $item['_id'], $item['title']);
        return $evaluator($query, $item);
    });

    /*
    return array_filter($db, function ($item) use ($query) {
        foreach ($query as $q) {
            $ok = evaluate_single($q['l'], $q['r'], $q['o'], $item);
            dbg('eval result', $ok);
            if (!$ok && $q['x'] == '&&') {
                return false;
            }
            if ($ok && $q['x'] == '||') {
                return true;
            }
        }
        return $ok;
    });
    */
}

/**
     * Parse a string into an array.
     *
*/
// https://stackoverflow.com/questions/196520/php-best-way-to-extract-text-within-parenthesis
// https://stackoverflow.com/questions/2650414/php-curly-braces-into-array

// @rodneyrehm
// http://stackoverflow.com/a/7917979/99923

function parse_parentheses($string) {
    if ($string[0] == '(') {
        // killer outer parens, as they're unnecessary
        $string = substr($string, 1, -1);
    }

    $buffer_start = null;
    $position = null;
    $current = [];
    $stack = [];

    $push = function (&$current, $string, &$buffer_start, $position) {
        if ($buffer_start === null) {
            return;
        }
        $buffer = substr($string, $buffer_start, $position - $buffer_start);
        $buffer_start = null;
        $current[] = $buffer;
    };

    for ($position = 0; $position < strlen($string); $position++) {
        switch ($string[$position]) {
            case '(':
                $push($current, $string, $buffer_start, $position);
                // push current scope to the stack an begin a new scope
                array_push($stack, $current);
                $current = [];
                break;

            case ')':
                $push($current, $string, $buffer_start, $position);
                // save current scope
                $t = $current;
                // get the last scope from stack
                $current = array_pop($stack);
                // add just saved scope to current scope
                $current[] = $t;
                break;
           /*
            case ' ':
                // make each word its own token
                $this->push();
                break;
            */
            default:
                // remember the offset to do a string capture later
                // could've also done $buffer .= $string[$position]
                // but that would just be wasting resources…
                if ($buffer_start === null) {
                    $buffer_start = $position;
                }
        }
    }
    // catch any trailing text
    if ($buffer_start < $position) {
        $push($current, $string, $buffer_start, $position);
    }
    return $current;
}
