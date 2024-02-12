<?php

namespace slowfoot;

use ErrorException;
use Throwable;
use xorcapp;
use XorcRuntimeException;
use Xorc_Controller;

class error_handler {

    public function __construct(public string $error_logfile = "php://stderr") {
        $this->install();
    }

    public function install() {
        if (\PHP_SAPI == 'cli') {
            \set_exception_handler($this->cli_exception_handler(...));
        } else {
            \set_exception_handler($this->exception_handler(...));
        }
        \set_error_handler($this->error_handler(...));
        \register_shutdown_function($this->fatal_handler(...));
    }

    public function restore() {
        \restore_error_handler();
        \restore_exception_handler();
    }

    public function cli_exception_handler(Throwable $e) {
        // $code = ($e instanceof fail) ? $e->appcode->value : $e->getCode();
        $code = $e->getCode();
        print "\n😢 error\n   > " . $e->getMessage() . "\n>> " . self::jTraceEx($e) . "\n";
        exit($code ?: 1);
    }

    public function api_exception_handler($e) {
        // $code = ($e instanceof fail) ? $e->appcode->http_code() : 500;
        $code = 500;
        $trace = self::jTraceEx($e);
        header('HTTP/1.1 ' . $code);
        header('Content-Type: application/json');
        print json_encode(['exception' => $trace], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    public function error_handler($fehlercode, $fehlertext, $fehlerdatei, $fehlerzeile) {
        #var_dump(error_reporting());
        #var_dump(\E_ALL);

        #$message = $fehlercode . ": $fehlertext in $fehlerdatei line $fehlerzeile";
        #print $message;
        #error_log(date("Y-m-d H:i:s") . " " . $message . "\n", 3, $this->error_logfile);

        if (!(error_reporting() & $fehlercode)) {
            // Dieser Fehlercode ist nicht in error_reporting enthalten
            # return true;
            return false;
        }

        $message = self::code($fehlercode) . ": $fehlertext in $fehlerdatei line $fehlerzeile";
        // error_log($message);
        match ($fehlercode) {
            E_ERROR, E_PARSE, E_CORE_ERROR,
            E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR =>
            throw new ErrorException($fehlertext, 0, $fehlercode, $fehlerdatei, $fehlerzeile),
            default => error_log(date("Y-m-d H:i:s") . " " . $message . "\n", 3, $this->error_logfile)
        };

        return true;
    }

    function exception_handler($e) {
        $trace = self::jTraceEx($e);
        $this->render_exception_page($e, $trace);
        error_log(date("Y-m-d H:i:s") . " " . $trace . "\n", 3, $this->error_logfile);
    }

    public function fatal_handler() {
        $error = error_get_last();
        $fatal_errors = [
            E_ERROR,
            E_USER_ERROR,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_PARSE,
            E_RECOVERABLE_ERROR
        ];

        if ($error === NULL || !in_array($error['type'], $fatal_errors)) {
            // Clean shutdown. Nothing to do.
            return;
        }
        // $trace = (new \Error)->getTraceAsString();
        $trace = "";
        $message = "FATAL#: " . self::code($error['type']) . ": {$error['message']} in {$error['file']} line {$error['line']} $trace";
        error_log(date("Y-m-d H:i:s") . " " . $message . $trace . "\n", 3, $this->error_logfile);

        print $this->render_exception_page($message, $trace);
    }

    /**
        für die 404 fälle, wo auch kein controller geladen werden kann 
        brauchen wir ein geeignetes theme

        default: [  "layout" => "",
                    "header" => "404",
                    "view" => "/errors/default.html",
                ]
     */
    function render_exception_page($e, $trace) {
        $htmlpage = __DIR__ . '/../resources/exception.html';
        include_once($htmlpage);
    }

    static public function install_boot_exception_handler() {
        $htmlpage = __DIR__ . '/../resources/exception.html';
        $hdl = fn ($e) => $trace = self::jTraceEx($e) and include_once($htmlpage);
        \set_exception_handler($hdl);
    }

    static public function jTraceEx($e, $seen = null) {
        $starter = $seen ? 'Caused by: ' : '';
        $result = array();
        if (!$seen) $seen = array();
        $trace  = $e->getTrace();
        $prev   = $e->getPrevious();
        $result[] = sprintf('%s%s: %s', $starter, get_class($e), $e->getMessage());
        $file = $e->getFile();
        $line = $e->getLine();
        while (true) {
            $current = "$file:$line";
            if (is_array($seen) && in_array($current, $seen)) {
                $result[] = sprintf(' ... %d more', count($trace) + 1);
                break;
            }
            $result[] = sprintf(
                ' at %s%s%s(%s%s%s)',
                count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',
                count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',
                count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',
                $line === null ? $file : basename($file),
                $line === null ? '' : ':',
                $line === null ? '' : $line
            );
            if (is_array($seen))
                $seen[] = "$file:$line";
            if (!count($trace))
                break;
            $file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
            $line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
            array_shift($trace);
        }
        $result = join("\n", $result);
        if ($prev)
            $result  .= "\n" . self::jTraceEx($prev, $seen);

        return $result;
    }

    static public function code($code) {
        $codes = [
            E_ERROR => "E_ERROR",
            E_WARNING => "E_WARNING",
            E_PARSE => "E_PARSE",
            E_NOTICE => "E_NOTICE",
            E_CORE_ERROR => "E_CORE_ERROR",
            E_CORE_WARNING => "E_CORE_WARNING",
            E_COMPILE_ERROR => "E_COMPILE_ERROR",
            E_COMPILE_WARNING => "E_COMPILE_WARNING",
            E_USER_ERROR => "E_USER_ERROR",
            E_USER_WARNING => "E_USER_WARNING",
            E_USER_NOTICE => "E_USER_NOTICE",
            E_STRICT => "E_STRICT",
            E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
            E_DEPRECATED => "E_DEPRECATED",
            E_USER_DEPRECATED => "E_USER_DEPRECATED",
            E_ALL => "E_ALL"
        ];
        return $codes[$code] ?? ('UNKNOWN_' . $code);
    }
}
