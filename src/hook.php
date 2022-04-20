<?php
/*

hook::load($custdir."/hooks.php");
account::$order = hook::invoke('account_order', 'accountname');
*/

class hook {

    public static $h = [];
    public static $f = [];

    public static function add($name, $fun){
        if (!array_key_exists($name, self::$h)) {
            self::$h[$name] = array();
        }

        self::$h[$name][] = $fun;
    }
    public static function add_filter($name, $fun){
        if (!array_key_exists($name, self::$f)) {
            self::$f[$name] = [];
        }

        self::$f[$name][] = $fun;
    }
    public static function invoke($action, $default = null) {
        #print_r(self::$h);
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        # print_r(self::$h);
        if (!array_key_exists($action, self::$h)) {
            return $default;
        }
        $res = [];
        foreach (self::$h[$action] as $meth) {
            // return $meth();
            $res[] = call_user_func_array($meth, $args);
        }
        #var_dump($res);
        return $res;
    }

    public static function invoke_filter($action, $start = null) {
        #print $action;
        #print_r(self::$f);
        $args = func_get_args();
        array_shift($args);
   
        # print_r(self::$h);
        if (!array_key_exists($action, self::$f)) {
            return $start;
        }
        
        foreach (self::$f[$action] as $meth) {
            // return $meth();
            $start = call_user_func_array($meth, $args);
        }
        #var_dump($res);
        return $start;
    }
}
