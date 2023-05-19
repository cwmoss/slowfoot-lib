<?php
/*

lolql - lovely query language

make queries easy & keep it simple

*/

namespace lolql;

require_once __DIR__ . '/compare.php';

// TODO: slice/ limit
function query($ds, $query, $params = []) {
    $query = is_string($query) ? parse($query, $params) : $query;

    // TODO: params aus dem parsing herausnehmen und zum evaluierungszeitpunkt einfügen
    $rs = eval_cond($ds, $query['q']);

    if ($query['order']) {
        usort($rs, $query['order']);
    }

    return $rs;
}
// TODO: params aus dem parsing herausnehmen und zum evaluierungszeitpunkt einfügen
function parse($string, $params = []) {
    $string = normalize($string);
    $string = replace_params($string, $params);

    dbg('[lolql] parse lolql query', $string);
    if (!$string) {
        // no string, no data
        return [];
    }
    $parts = parse_parentheses($string);
    $parts = array_reduce(array_chunk($parts, 2), function ($res, $kv) {
        $res[trim($kv[0])] = $kv[1];
        return $res;
    }, []);
    $qk = array_key_first($parts);
    // print_r($parts);
    // print_r(parse_condition($parts[$qk][1][0]));
    // \dbg('first key', $qk, '😂');
    $q = array_map_recursive(fn ($it) => parse_condition($it), $parts[$qk]);
    //array_walk_recursive($parts[$qk], function (&$val, $idx) {
    //    $val = parse_condition($val);
    //});
    //print_r($parts[$qk]);

    //print_r($q);
    if (!($qk == '*' || $qk == '😂' || $qk == '❤️')) {
        array_unshift(
            $q,
            [
                'l' => ['t' => 'k', 'c' => ['_type']],
                'o' => '==',
                'r' => ['t' => 'v', 'c' => [$qk]],
                'x' => '&&'
            ]
        );
    }
    $order = build_order_fun($parts['order'][0]);
    $limit = parse_limit($parts['limit'][0] ?? "");
    return [
        'q' => $q,
        'order' => $order[0],
        'order_raw' => $order[1],
        'limit' => $limit,
        'limit_raw' => $parts['limit']
    ];
}

function eval_cond($db, $query) {
    $evaluator = get_evaluator($query);

    return array_filter($db, function ($item) use ($query, $evaluator) {
        // dbg('item-compare...', $item['_id'], $item['title']);
        [$ok, $next] = $evaluator($query, $item);
        return $ok;
    });
}

function eval_cond_as_sql_function($query) {
    $evaluator = get_evaluator($query);
    return function ($json_col) use ($query, $evaluator) {
        $item = json_decode($json_col, true);
        #print_r($item);
        #return true;
        [$ok, $dummy] = $evaluator($query, $item);
        return $ok;
    };
}

function get_evaluator($query) {
    #print_r($query);
    $evaluator = function ($query, $item, $level = 0) use (&$evaluator) {
        // dbg('level... ', $level);
        $ok = false;
        foreach ($query as $q) {
            if (!is_assoc($q)) {
                //print "\n\nhuhu\n\n";
                //\dbg('.. klammer', $q);
                [$ok, $next] = $evaluator($q, $item, $level + 1);
            } else {
                $ok = evaluate_single($q['l'], $q['r'], $q['o'], $item);
                $next = $q['x'];
            }

            //   dbg('eval result', $ok, $next);
            if (!$ok && $next == '&&') {
                return [false, $next];
            }
            if ($ok && $next == '||') {
                return [true, $next];
            }
        }
        return [$ok, null];
    };
    return $evaluator;
}

function evaluate($cond, $data) {
    foreach ($cond as $k => $v) {
        $ok = evaluate_single($k, $v, $data);
        if (!$ok) {
            return false;
        }
    }
    return true;
}
function evaluate_single($l, $r, $op, $data) {
    if ($l['t'] == 'k') {
        $l['v'] = get_value($l['c'], $data);
    } else {
        $l['v'] = get_literal($l['c']);
    }
    if ($r['t'] == 'k') {
        $r['v'] = get_value($r['c'], $data);
    } else {
        $r['v'] = get_literal($r['c']);
    }

    $ops = ['==' => 'eq', 'in' => 'in', '!=' => 'ne', '>' => 'gt', '<' => 'lt', '<=' => 'lte', '>=' => 'gte', 'matches' => 'matches'];
    $ops_m = $ops[$op] ?? null;
    if (!$ops_m) {
        return false;
    }

    $cmp = __NAMESPACE__ . '\\' . 'cmp_' . $ops_m;
    if (!function_exists($cmp)) {
        return false;
    }

    return $cmp($l, $r);
}

function get_value($keys, $data) {
    $current = array_shift($keys);

    // nested?
    if ($keys) {
        return get_value($keys, $data[$current]);
    }

    if (!$data) {
        return null;
    }

    if (!is_assoc($data)) {
        return array_column($data, $current);
    } else {
        return $data[$current];
    }
}

function get_literal($data) {
    return $data;
}

function build_order_fun($order) {
    $orders = parse_order($order);
    if (!$order) {
        return null;
    }
    $os = [];
    foreach ($orders as $k => $o) {
        //$key = $dir = $cmp = null;
        // keys must start with 0, 1, 2...
        list($key, $dir, $cmp) = array_merge($o) + [1 => "", 2 => ""];
        //print "key, $key";
        if ($dir && ($dir != 'asc' && $dir != 'desc')) {
            $cmp = $dir;
            $dir = 'asc';
        } elseif (!$dir) {
            $dir = 'asc';
        }
        $os[] = [
            'k' => $key,
            'd' => $dir,
            'c' => $cmp
        ];
    }
    $coll = collator_create('de_DE');
    return [
        function ($a, $b) use ($os, $coll) {
            foreach ($os as $order) {
                //$cmp = 'strnatcasecmp';
                $cmp = 'collator_compare';
                $r = $cmp($coll, $a[$order['k']], $b[$order['k']]);
                if ($r) {
                    return $order['d'] == 'desc' ? (-1 * $r) : $r;
                }
            }
            return 0;
            //return strnatcmp($a[$key], $b[$key]);
        },
        $os
    ];
}

function parse_order($order) {
    if (!$order) {
        return [];
    }
    return array_map('\lolql\words', explode(',', $order));
}
function parse_limit($limit) {
    $l = ['limit' => null, 'offset' => 0];
    if (!$limit) {
        return $l;
    }
    $limit_offset = words($limit);
    $l['limit'] = $limit_offset[0] ?? null;
    $l['offset'] = $limit_offset[1] ?? 0;
    return $l;
}
function words($string) {
    return array_filter(explode(' ', $string), 'trim');
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

function normalize($string) {
    return join(' ', array_filter(
        explode("\n", $string),
        fn ($line) => trim($line)[0] != '#'
    ));
}

function replace_params($q, $params = []) {
    foreach ($params as $k => $v) {
        $q = \str_replace('$' . $k, '"' . $v . '"', $q);
    }
    return $q;
}
function parse_condition($string) {
    $t = token_get_all('<?' . $string . '?>');
    $t = compact_tokens($t);
    //print_r($t);
    $t = combine_tokens($t);
    //print_r($t);
    return $t;
}
/*
l left
r right
t type (k key, v value)
c content
o operator
x next logical operator (&& ||)
*/
function combine_tokens($tokens) {
    $start = ['l' => ['t' => null, 'c' => []], 'o' => null, 'r' => ['t' => null, 'c' => []], 'x' => null];
    $buffer = $start;
    $lr = 'l';
    $res = [];
    foreach ($tokens as $item) {
        if ($item == '&&' || $item == '||') {
            $buffer['x'] = $item;
            $res[] = $buffer;
            $buffer = $start;
            $lr = 'l';
            continue;
        }
        if (in_array($item, ['==', 'in', '!=', '>', '<', '<=', '>=', 'matches'])) {
            $buffer['o'] = $item;
            $lr = 'r';
        } elseif ($item[0] == '"') {
            $buffer[$lr]['c'][] = trim($item, '"');
            if (!$buffer[$lr]['t']) {
                $buffer[$lr]['t'] = 'v';
            }
        } elseif (!in_array($item, ['[', ']', '.', ','])) {
            $buffer[$lr]['c'][] = $item;
            if (!$buffer[$lr]['t']) {
                $buffer[$lr]['t'] = 'k';
            }
        }
    }
    if ($buffer && $buffer['o']) {
        $res[] = $buffer;
    }
    return $res;
}

function compact_tokens($t) {
    $t = array_map(function ($tok) {
        if (is_array($tok)) {
            return $tok[1] == '<?' || $tok[1] == '?>' ? '' : $tok[1];
        }
        return $tok;
    }, $t);
    $t = array_filter($t, 'trim');
    return $t;
}

function array_map_recursive($fn, $arr) {
    return array_map(function ($item) use ($fn) {
        return is_array($item) ? array_map($fn, $item) : $fn($item);
    }, $arr);
}
