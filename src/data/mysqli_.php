<?php
/**
 * mysqli_ - mysqli query-logging wrapper
 * File : /src/data/mysqli_.php
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite\data;

use \mysqli;
use Stationer\Graphite\G;
use Stationer\Graphite\Profiler;

/**
 * mysqli_ class - extend mysqli to add query logging
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 *
 * @property int    $affected_rows
 * @property int    $connect_errno
 * @property string $connect_error
 * @property int    $errno
 * @property array  $error_list
 * @property string $error
 * @property int    $field_count
 * @property string $client_info
 * @property int    $client_version
 * @property string $host_info
 * @property string $protocol_version
 * @property string $server_info
 * @property int    $server_version
 * @property string $info
 * @property mixed  $insert_id
 * @property string $sqlstate
 * @property int    $thread_id
 * @property int    $warning_count
 *
 * @method autocommit
 * @method begin_transaction
 * @method change_user
 * @method character_set_name
 * @method commit
 * @method debug
 * @method dump_debug_info
 * @method get_charset
 * @method get_connection_stats
 * @method get_warnings
 * @method init
 * @method kill
 * @method more_results
 * @method multi_query
 * @method next_result
 * @method options
 * @method ping
 * @method poll
 * @method prepare
 * @method query
 * @method real_connect
 * @method real_escape_string
 * @method real_query
 * @method reap_async_query
 * @method refresh
 * @method release_savepoint
 * @method rollback
 * @method rpl_query_type
 * @method savepoint
 * @method select_db
 * @method send_query
 * @method set_charset
 * @method set_local_infile_default
 * @method set_local_infile_handler
 * @method ssl_set
 * @method stat
 * @method stmt_init
 * @method store_result
 * @method thread_safe
 * @method use_result
 * @method string escape_string(string $s)
 */
class mysqli_ {
    /** @var array Log of queries, run times, errors */
    private static $_aQueries = [[0]];

    /** @var string Common prefix used by app tables, for reference */
    private static $_tabl = '';

    /** @var bool Whether to log */
    private static $_log = false;

    /** @var array Keep connection params, in case need to reconnect */
    private $_connectionParam;

    /** @var mysqli The instance of MySQL */
    private $_mysqli;

    /** @var bool Whether connection succeeded */
    private $_inner_is_open = false;

    /** @var bool Whether to pretend we're open */
    private $_outer_is_open = false;

    /** @var bool Whether to close inner connection between calls */
    private $_sparse = false;

    /** @var array Store inner properties before closing inner connection */
    private $_inner_properties = [];

    /** @var bool Whether connection has readonly credentials */
    public $readonly = false;

    /**
     * mysqli_ constructor
     *
     * @param string $host   pass through to mysqli - hostname of DB server
     * @param string $user   pass through to mysqli - DB username
     * @param string $pass   pass through to mysqli - DB password
     * @param string $db     pass through to mysqli - DB name
     * @param string $port   pass through to mysqli
     * @param string $sock   pass through to mysqli
     * @param string $tabl   table prefix
     * @param bool   $log    whether to enable query logging
     * @param bool   $sparse whether to close inner connection between calls
     */
    public function __construct($host, $user, $pass, $db, $port = null,
                                $sock = null, $tabl = '', $log = false, $sparse = false
    ) {
        $this->_connectionParam = [$host, $user, $pass, $db, $port, $sock];
        $this->_sparse          = $sparse;
        $this->_outer_is_open   = true;
        $this->_inner_open();
        // If the initial open worked, set our pretense and close the inner connection
        if ($this->_inner_is_open) {
            self::$_tabl = $this->_mysqli->escape_string($tabl);
            $this->_inner_close();
        } else {
            $this->_outer_is_open = false;
        }
        self::$_log = $log;
    }

    /**
     * Destructor that closes connection
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Pass unknown function calls to mysqli (because, this class used to extend mysqli)
     * Re-open the inner connection prior to delegating and close it after
     *
     * @param string $name      Called method
     * @param array  $arguments Passed arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments) {
        // If we're in read only mode and the connection is not open, just fail quietly
        if (true === $this->readonly && false === $this->_outer_is_open) {
            return false;
        }

        $this->_inner_open();
        if ('query' == $name) {
            $result = $this->_inner_query(...$arguments);
        } elseif ('queryToArray' == $name) {
            $result = $this->_queryToArray(...$arguments);
        } else {
            $result = $this->_mysqli->{$name}(...$arguments);
        }
        $this->_inner_close();

        return $result;
    }

    /**
     * Store the values of our inner mysqli object
     *
     * @return void
     */
    protected function _inner_properties() {
        $properties = [
            'affected_rows', 'connect_errno', 'connect_error', 'errno', 'error_list', 'error', 'field_count',
            'client_info', 'client_version', 'host_info', 'protocol_version', 'server_info', 'server_version', 'info',
            'insert_id', 'sqlstate', 'thread_id', 'warning_count',
        ];
        foreach ($properties as $property) {
            $this->_inner_properties[$property] = $this->_mysqli->$property;
        }
    }

    /**
     * Close the inner connection but maintain the pretense we are open
     *
     * @param bool $force Close connection even if we're not in sparse mode
     *
     * @return void
     */
    protected function _inner_close(bool $force = false) {
        if ($this->_inner_is_open && ($this->_sparse || $force)) {
            $this->_inner_properties();
            $this->_mysqli->close();
            $this->_inner_is_open = false;
        }
    }

    /**
     * If we are supporting the pretentious open, open the inner connection
     *
     * @return bool
     */
    protected function _inner_open() {
        if ($this->_outer_is_open && !$this->_inner_is_open) {
            $this->_mysqli = new mysqli(...$this->_connectionParam);
            if (!mysqli_connect_error()) {
                $this->_inner_is_open = true;
            }
        }

        return $this->_inner_is_open;
    }

    /**
     * Prevents double closing
     *
     * @return void
     */
    public function close() {
        if ($this->_inner_is_open) {
            $this->_mysqli->close();
            $this->_inner_is_open = false;
        }
        $this->_outer_is_open = false;
    }

    /**
     * Returns true if a connection is open
     *
     * @return bool
     */
    public function isOpen() {
        return $this->_outer_is_open;
    }

    /**
     * Wrapper for mysqli::query() that logs queries
     *
     * @param string $query      Query to run
     * @param int    $resultMode http://php.net/manual/en/mysqli.query.php
     *
     * @return mixed Passes return value from mysqli::query()
     */
    private function _inner_query($query, $resultMode = \MYSQLI_STORE_RESULT) {
        if (false === $this->_inner_is_open) {
            trigger_error('Refusing to run a query against a closed connection.');

            return false;
        }
        // If we're flagged readonly, just don't bother with DML
        // THIS IS NOT A SECURITY FEATURE, DO NOT RELY ON IT FOR SECURITY
        $skipQuery = $this->readonly
            && !in_array(strtolower(substr(ltrim($query), 0, 6)), ['select', 'explai', 'descri', 'show t']);
        if (!self::$_log) {
            return $skipQuery ? false : $this->_query_and_handle_errors($query, $resultMode);
        }

        // get the last few functions on the call stack
        $trace = debug_backtrace();
        // assemble call stack
        $stack = $trace[0]['file'].':'.$trace[0]['line'];
        if (isset($trace[1])) {
            $stack .= ' - '.(
                isset($trace[1]['class'])
                    ? (isset($trace[1]['object'])
                        ? get_class($trace[1]['object'])
                        : $trace[1]['class']
                    ).$trace[1]['type']
                    : ''
                ).$trace[1]['function'];
        }
        // query as sent to database
        $query_stacked = '/* '.$this->_mysqli->escape_string(substr($stack, strrpos($stack, '/'))).' */ '.$query;

        if ($skipQuery) {
            $result = false;
            $time   = '-';
        } else {
            // Start Profiler for 'query'
            Profiler::getInstance()->mark(__METHOD__);
            // start time
            $time = microtime(true);
            // Call mysqli's query() method, with call stack in comment
            $result = $this->_query_and_handle_errors($query_stacked, $resultMode);
            // [0][0] totals the time of all queries
            self::$_aQueries[0][0] += $time = microtime(true) - $time;
            // Pause Profiler for 'query'
            Profiler::getInstance()->stop(__METHOD__);
        }
        // finish assembling the call stack
        for ($i = 2; $i < count($trace); $i++) {
            $stack .= ' - '.(
                isset($trace[$i]['class'])
                    ? (isset($trace[$i]['object'])
                        ? get_class($trace[$i]['object'])
                        : $trace[$i]['class']
                    ).$trace[$i]['type']
                    : ''
                ).$trace[$i]['function'];
        }
        // trigger_error for slow queries
        if (is_numeric($time) && G::$G['db']['slowQueryThreshold'] < $time) {
            trigger_error('Slow Query ('.$time.'): '.$query);
            trigger_error('Slow Query @'.$stack);
        }
        // assemble log: query time, query, call stack, rows affected/selected
        $log = [
            'time'       => $time,
            'error'      => '',
            'errno'      => '',
            'stack'      => $stack,
            'rows'       => $result == false ? 0 : $this->_mysqli->affected_rows,
            '$host_info' => $this->_mysqli->host_info,
            'query'      => $query,
        ];
        // if there was an error, log that too
        if ($this->_mysqli->errno) {
            $log['error'] = $this->_mysqli->error;
            $log['errno'] = $this->_mysqli->errno;
            // report error on PHP error log
            // unless it's a read-only mode error, we don't care about those.
            if (self::$_log >= 2
                && !(1290 == $this->_mysqli->errno
                    && 'The MySQL server is running with the --read-only' == substr($this->_mysqli->error, 0, 48))
            ) {
                // @codingStandardsIgnoreStart
                trigger_error(print_r($log, 1));
                // @codingStandardsIgnoreEnd
            }
        }
        // append to log
        self::$_aQueries[] = $log;

        // return result as normal
        return $result;
    }

    /**
     * Run a query, and handle any errors we can handle
     *
     * @param string $query      Query to run
     * @param int    $resultmode http://php.net/manual/en/mysqli.query.php
     *
     * @return mixed Passes return value from mysqli::query()
     */
    private function _query_and_handle_errors($query, $resultmode = \MYSQLI_STORE_RESULT) {
        $result = $this->_mysqli->query($query, $resultmode);

        // Handle "MySQL server has gone away" by reopening the inner connection
        if (2006 == $this->_mysqli->errno) {
            $this->_inner_close(true);
            $this->_inner_open();
            if ($this->_inner_is_open) {
                $result = $this->_mysqli->query($query, $resultmode);
            }
        }

        return $result;
    }

    /**
     * Wrapper for mysqli::query() that returns an array of rows
     *
     * @param string $query    Query to run
     * @param string $keyField Name of field to index returned array by.
     *
     * @return array|bool Array of rows returned by query|false on error
     */
    private function _queryToArray($query, $keyField = null) {
        // If query fails, return false
        if (false === $result = $this->_inner_query($query)) {
            return false;
        }

        // If query returns no rows, return empty array
        if (0 == $this->_mysqli->affected_rows) {
            $result->close();

            return [];
        }

        // We have rows, fetch them all into a new array to return
        $data = [];
        // Get the first row to verify the keyField
        $row = $result->fetch_assoc();
        if (null !== $keyField && !isset($row[$keyField])) {
            trigger_error('Invalid keyField specified in '.__METHOD__.', falling back to numeric indexing');
            $keyField = null;
        }
        if (null !== $keyField) {
            do {
                $data[$row[$keyField]] = $row;
            } while ($row = $result->fetch_assoc());
        } else {
            do {
                $data[] = $row;
            } while ($row = $result->fetch_assoc());
        }
        $result->close();

        return $data;
    }

    /**
     * Return logged queries
     *
     * @return array query log
     */
    public function getQueries() {
        return self::$_aQueries;
    }

    /**
     * Return logged queries
     *
     * @return array query log
     */
    public function getLastQuery() {
        return end(self::$_aQueries);
    }

    /**
     * Getter for read-only properties
     *
     * @param string $k property to get
     *
     * @return mixed requested property value
     */
    public function __get($k) {
        switch ($k) {
            case 'tabl':
                return self::$_tabl;
            case 'table':
                return self::$_tabl;
            case 'log':
                return self::$_log;
            case 'open':
                return $this->_outer_is_open;
            case 'inner_open':
                return $this->_inner_is_open;
            case 'outer_open':
                return $this->_outer_is_open;
            default:
                if ($this->_inner_is_open) {
                    if (isset($this->_mysqli->{$k})) {
                        return $this->_mysqli->{$k};
                    }
                } else {
                    if (isset($this->_inner_properties[$k])) {
                        return $this->_inner_properties[$k];
                    }
                }
                $d = debug_backtrace();
                trigger_error('Undefined property via __get(): '.$k.' in '.$d[0]['file'].' on line '.$d[0]['line'],
                    E_USER_NOTICE);

                return null;
        }
    }

    /**
     * Build and return a MySQL connection object for the specified source
     *
     * @param string $source name of source in the configs
     *
     * @return mysqli_|null
     */
    public static function buildForSource($source) {
        if ($source !== 'default'
            && isset(G::$G['db'][$source]['host'])
            && isset(G::$G['db'][$source]['user'])
            && isset(G::$G['db'][$source]['pass'])
            && isset(G::$G['db'][$source]['name'])
        ) {
            $MySql = G::build(
                self::class,
                G::$G['db'][$source]['host'],
                G::$G['db'][$source]['user'],
                G::$G['db'][$source]['pass'],
                G::$G['db'][$source]['name'],
                null,
                null,
                G::$G['db']['tabl'],
                G::$G['db']['log'],
                G::$G['db'][$source]['sparse'] ?? G::$G['db']['sparse'] ?? false
            );
            if ($MySql->isOpen()) {
                return $MySql;
            }
            trigger_error('Falling back to primary on secondary db query.');
        }

        // Fail
        return null;
    }
}
