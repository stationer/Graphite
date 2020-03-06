<?php
/**
 * Session - core Session Data Wrapper
 * File : /src/Session.php
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite;

/**
 * Session class - accessing persistent session data
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 *
 * Session methods supported via __call() which are not explicitly wrapped herein
 * @method abort
 * @method cache_expire
 * @method cache_limiter
 * @method commit
 * @method create_id
 * @method decode
 * @method destroy
 * @method encode
 * @method gc
 * @method get_cookie_params
 * @method id
 * @method module_name
 * @method name
 * @method register_shutdown
 * @method reset
 * @method save_path
 * @method set_cookie_params
 * @method set_save_handler
 * @method status
 * @method unset
 */
class Session {
    /** @var Session $instance */
    private static $instance = null;

    /** @var bool $hash Hash of last $_SESSION state */
    private $hash = false;

    /** @var bool $open Indicates whether session is open */
    private $open = false;

    /** @var string $session_id PHP's Session ID for re-opening */
    private $session_id = null;

    /** @var bool Whether to run in CLI mode */
    private $CLI_Mode = false;

    /** @var array Store the initial session array so we can patch on re-open */
    private $initialSessionArray = null;

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {
        // OnBDC-913: Prevent use of sessions from CLI
        $this->CLI_Mode = 'cli' == php_sapi_name();
    }

    /**
     * Create and return singleton instance
     *
     * @return Session
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Store a value to the Session
     *
     * @param string $key Session key to set
     * @param mixed  $val Session value to set
     *
     * @return mixed Requested value
     */
    public function set($key, $val) {
        $_SESSION[$key] = $val;

        return $val;
    }

    /**
     * Retrieve a value from the Session
     *
     * @param string $key Session key to get
     *
     * @return mixed Value in session for requested key
     */
    public function get($key) {
        if (!array_key_exists($key, $_SESSION)) {
            $trace = debug_backtrace();
            trigger_error('Undefined property via '.__METHOD__.': '
                .$key.' in '.$trace[1]['file'].' on line '.$trace[1]['line'],
                E_USER_NOTICE);

            return null;
        }

        return $_SESSION[$key];
    }

    /**
     * Verify a value in the Session
     *
     * @param string $key value to verify exists
     *
     * @return bool
     */
    public function exists($key) {
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove a value from the Session
     *
     * @param string $key Value to destroy
     *
     * @return void
     */
    public function drop($key) {
        unset($_SESSION[$key]);
    }

    /**
     * Set dirty bit false and call session_start
     *
     * @return bool pass through value from session_start()
     */
    public function start() {
        // Prevent use of sessions from CLI
        if ($this->CLI_Mode) {
            $_SESSION = $_SESSION ?? [];

            return $this->open = true;
        }
        // Cannot change session id when session is active
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent() && null != $this->session_id) {
            session_id($this->session_id);
        }
        // Cannot start session when headers already sent
        if (true !== $this->open && !headers_sent()) {
            // If we already have a session, preserve its data for the re-open
            if (isset($_SESSION)) {
                $temp       = $_SESSION;
                $this->open = session_start();
                // Assign $_SESSION to the merged difference between itself and newer versions
                $_SESSION   = array_patch($this->initialSessionArray, $_SESSION, $temp);
            } else {
                $this->open = session_start();
                ksort($_SESSION);
                $this->hash = md5(json_encode($_SESSION));
                // Save the initial values of the newly created $_SESSION
                $this->initialSessionArray = $_SESSION;
            }
            $this->session_id = session_id();
        }

        return $this->open;
    }

    /**
     * If @_SESSION changed, and we're not open, open to save changed values first
     *
     * @return void
     */
    public function write_close() {
        // Prevent use of sessions from CLI
        if ($this->CLI_Mode) {
            $this->open = false;

            return;
        }
        // Sort and compare current session state to last known state
        ksort($_SESSION);
        $state = md5(json_encode($_SESSION));
        if ($state != $this->hash && true !== $this->open) {
            // Make sure we have an open session
            $this->start();
        }
        $this->open = false;
        // Store current session state
        $this->hash = $state;
        session_write_close();
    }

    /**
     * call session_regenerate_id() and store new ID
     *
     * @return bool
     */
    public function regenerate_id() {
        if ($this->CLI_Mode) {
            return false;
        }
        // Make sure we have an open session
        $this->start();
        $return = session_regenerate_id();
        $this->session_id = session_id();

        return $return;
    }

    /**
     * Call a PHP session_* function
     *
     * @param string $func Partial name of PHP session_* function to call
     * @param array  $argv Arguments to pass PHP session_* function
     *
     * @return mixed Return value of PHP session_* function
     */
    public function __call($func, $argv) {
        if ($this->CLI_Mode) {
            return false;
        }
        if (function_exists('session_'.$func)) {
            // Make sure we have an open session
            $this->start();

            return call_user_func_array('session_'.$func, $argv);
        }
        $trace = debug_backtrace();
        trigger_error('Undefined property via '.__METHOD__.': '
            .$func.' in '.$trace[1]['file'].' on line '.$trace[1]['line'],
            E_USER_NOTICE);

        return false;
    }

    /**
     * Kill a session:
     *  1. Unset its data
     *  2. Destroy it locally
     *  3. Destroy its cookie
     *
     * @return void
     */
    public function kill() {
        // Prevent use of sessions from CLI
        if ($this->CLI_Mode) {
            $this->open = false;
            $_SESSION   = [];

            return;
        }
        $this->start();
        $this->unset();
        // Trying to destroy uninitialized session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->destroy();
        }
        $this->write_close();
        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            // Set a blank cookie expiring at a time arbitrarily in the past
            setcookie(session_name(), '', NOW / 2, $params["path"],
                $params["domain"], $params["secure"], $params["httponly"]);
        }
    }
}
