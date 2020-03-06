<?php
/**
 * G - static class for scoping core Graphite objects & functions
 * File : /src/G.php
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite;

use Stationer\Graphite\data\mysqli_;

/**
 * G class - static class for scoping core Graphite objects & functions
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */
final class G {
    /** @var mysqli_ mysqli_ object */
    public static $M;
    /** @var mysqli_ mysqli_ object  with read-only connection */
    public static $m;
    /** @var View View object */
    public static $V;
    /** @var Dispatcher (Controller) Dispatcher object */
    public static $C;
    /** @var Security Security / Session object */
    public static $S;
    /** @var array Graphite configuration array */
    public static $G = [];

    /** @var Factory Object */
    public static $Factory;

    /** @var null Placeholder for Logging object */
    public static $Log;

    /** @var array Stores messages to be displayed to the user */
    private static $_msg = [];

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {

    }

    /**
     * Shortcut for a factory build method call.
     *
     * @return mixed
     */
    public static function build() {
        $args = func_get_args();
        if (empty($args)) {
            trigger_error(__METHOD__.' called with no parameters', E_USER_ERROR);
        }
        // Properly handle Singletons
        if (method_exists($args[0], 'getInstance')) {
            $className = array_shift($args);

            return $className::getInstance($args);
        }

        return call_user_func_array(
            [self::$Factory, "build"],
            $args
        );
    }

    /**
     * Log messages for output later
     *
     * @param string $s the message
     *                  pass null to return the messages
     *                  pass true to return the messages and clear the log
     * @param string $c class, arbitrary, used at will by template on output
     *
     * @return mixed
     */
    public static function msg($s = null, $c = '') {
        if (null === $s) {
            return self::$_msg;
        }
        if (true === $s) {
            $msg        = self::$_msg;
            self::$_msg = [];

            return $msg;
        }
        self::$_msg[] = [$s, $c];
    }

    /**
     * Log messages for output later
     *
     * @return mixed
     */
    public static function storeMsg() {
        $hash = '';
        if (isset($_SESSION)) {
            $hash                        = substr(md5(NOW), 0, 6);
            $_SESSION['msgStore'][$hash] = self::$_msg;
        }

        return $hash;
    }

    /**
     * Loads previously stored messages into the running message log
     *
     * @param string $hash Hash of the session items to fetch
     *
     * @return array
     */
    public static function loadMsg($hash) {
        $messages = [];
        if (!empty($_SESSION['msgStore'][$hash])) {
            $messages = $_SESSION['msgStore'][$hash];
            unset($_SESSION['msgStore'][$hash]);
            // Array Append
            self::$_msg += $messages;
        }

        return $messages;
    }

    /**
     * Replace special characters with their common counterparts
     *
     * @param string $s the string to alter
     *
     * @return string
     */
    public static function normalize_special_characters($s) {
        // ‘single’ and “double” quot’s yeah.
        $s = str_replace([
            '“',  // left side double smart quote
            '”',  // right side double smart quote
            '‘',  // left side single smart quote
            '’',  // right side single smart quote
            '…',  // ellipsis
            '—',  // em dash
            '–',
        ], // en dash
            ['"', '"', "'", "'", "...", "-", "-"],
            $s);

        return $s;
    }

    /**
     * Emit invocation info, and passed value
     *
     * @param mixed $v value to var_dump
     *
     * @return void
     * @deprecated
     *
     */
    public static function croak($v = null) {
        $debug = debug_backtrace();
        $from  = ' in '.($debug[1]['class'] ?? '').($debug[1]['type'] ?? '').($debug[1]['function'] ?? '').'()';
        trigger_error("Call to deprecated method ".__METHOD__.$from, E_USER_DEPRECATED);
        \croak($v);
    }

    /**
     * Return a newline delimited call stack
     *
     * @return string call stack
     */
    public static function trace() {
        // get PHP's trace
        $d = debug_backtrace();
        // build printable trace
        $s = '';
        for ($i = 0; $i < count($d); $i++) {
            $s .= "\n";
            // If the called function is in a class, indicate it
            if (isset($d[$i]['class'])) {
                // If the called function is in a subclass, indicate it also
                if (isset($d[$i]['object']) && get_class($d[$i]['object']) != $d[$i]['class']) {
                    $s .= '['.get_class($d[$i]['object']).']';
                }
                $s .= $d[$i]['class'].$d[$i]['type'];
            }
            // Indicate the called function
            if (isset($d[$i]['function'])) {
                $s .= $d[$i]['function'].'() called';
            }
            // Indicate the file and line of the current call
            if (isset($d[$i]['file']) && isset($d[$i]['line'])) {
                $s .= ' at '.$d[$i]['file'].':'.$d[$i]['line'].";";
            }
        }

        return $s;
    }

    /**
     * Close Security and mysqli objects in proper order
     * This should be called before PHP cleanup to close things in order
     * register_shutdown_function() is one way to do this.
     *
     * @return void
     */
    public static function close() {
        if (self::$S) {
            self::$S->close();
        }
        if (self::$M) {
            self::$M->close();
        }
        if (self::$m) {
            self::$m->close();
        }
    }

    /**
     * Provide short alias for Localizer::translate
     *
     * @return mixed Pass-through return value of Localizer::translate
     */
    public static function _() {
        return call_user_func_array([Localizer::class, 'translate'], func_get_args());
    }
}

// register G::close() to be called at shutdown
register_shutdown_function('\Stationer\Graphite\G::close');
