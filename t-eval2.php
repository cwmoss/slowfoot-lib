<?php

require 'src/util.php';
require_once 'src/lolql.php';
use function lolql\parse;
use function lolql\eval_cond;

$db = [
    ['_id' => 'a1', 'title' => 'hey', 'status' => 'draft', 'authors' => [['_ref' => '2'], ['_ref' => '4']]],
    ['_id' => 'a2', 'title' => 'hello', 'status' => 'published'],
    ['_id' => 'a3', 'title' => 'world', 'status' => 'published'],
    ['_id' => 'a4', 'title' => 'hello world', 'status' => 'published', '_type' => 'article'],
    ['_id' => 'a5', 'title' => 'world is caos', 'status' => 'draft', '_type' => 'article'],
    ['_id' => 'a6', 'title' => 'yourworld is caos', 'status' => 'published'],
];

$tests = [
    // '*(title == "hello")',
    // '*(title matches "world*" || status == "draft") order(name)',
    //  '*(authors._ref == "4")',
    '*(title matches "hello" || (status == "draft" && _id == "a5"))',
    '*(title[] matches "hello" || (pub.status == $status && _id == 55) || date(publ) > now()) order(name age)',
    'article()',
    'article( status=="draft" )'
];

foreach ($tests as $t) {
    print "\n\n$t\n";
    //$t = parse($t);
    //$t = parse_parentheses($t);
    //print_r($t);
    //break;
    $t = parse2($t);
    print_r($t);
    dbg('parsed..', $t);
    //print_r($t);
    print_r(eval_cond($db, $t['q']));
    break;
}

function parse2($t) {
    $t = token_get_all('<?' . $t . '?>');
    print_r($t);
    $t = lolql\compact_tokens($t);
    print_r($t);
    $t = stack_token($t);
    return $t;
}
function stack_token($tokens, $level = 1, $mode = 'func') {
    $expr = ['&&', '||', ''];
    $res = [];
    $buffer = [];
    $recent = '';
    while ($t = array_shift($tokens)) {
        if ($t == '(') {
            $mode = in_array($recent, $expr) ? 'expr' : 'func';

            [$cont, $tokens] = stack_token($tokens, $level + 1, $mode);

            if ($mode == 'func') {
                $func = ['t' => 'f', 'n' => $recent, 'b' => $cont];
                $buffer[count($buffer) - 1] = $func;
                $res[] = $buffer;
            } else {
                $res[] = $buffer;
                $res[] = $cont;
            }
            $buffer = [];
        } elseif ($t == ')') {
            $res[] = $buffer;
            return [$res, $tokens];
        } else {
            $buffer[] = $t;
            $recent = $t;
        }
    }
    return $res;
}
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
