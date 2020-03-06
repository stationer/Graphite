<?php
/**
 * PassiveRecord - core database record class
 * File : /src/data/PassiveRecord.php
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite\data;

use Stationer\Graphite\G;

/**
 * PassiveRecord class - used as a base class for Record Model classes
 *  for use with a DataProvider
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 * @see      /src/data/mysqli_.php
 * @see      /src/data/DataModel.php
 * @property string  $table
 * @property string  $pkey
 * @property array[] $vars
 */
abstract class PassiveRecord extends DataModel implements \JsonSerializable {
    /** @var array Instance DB values of vars defined in $vars */
    protected $DBvals = [];

    // Should be defined in subclasses
    // protected static $table;// name of table
    // protected static $pkey;// name of primary key column
    // protected static $vars = array();// record definition

    /**
     * Constructor accepts four prototypes:
     *  Record(true) will create an instance with default values
     *  Record(int) will create an instance with pkey set to int
     *  Record(array()) will create an instance with supplied values
     *  record(array(),true) will create a record with supplied values
     *
     * @param bool|int|array $a pkey value|set defaults|set values
     * @param bool           $b set defaults
     *
     * @throws \Exception
     */
    public function __construct($a = null, $b = null) {
        // Ensure that a pkey is defined in subclasses
        if (!isset(static::$pkey) || !isset(static::$vars[static::$pkey])) {
            throw new \Exception('Record class defined with no pkey, or pkey not registered');
        }
        if (!isset(static::$table)) {
            throw new \Exception('Record class defined with no table');
        }

        // initialize the values arrays with null values as some tests depend
        foreach (static::$vars as $k => $v) {
            $this->DBvals[$k] = $this->vals[$k] = null;
        }

        // This fakes constructor overriding
        if (true === $a) {
            $this->defaults();
        } elseif (is_numeric($a)) {
            $this->setAll([static::$pkey => $a]);
        } else {
            if (true === $b) {
                $this->defaults();
            }
            if (is_array($a)) {
                $this->setAll($a);
            }
        }
    }

    /**
     * Return the pkey, which is a protected static var
     *
     * @return string Model's primary key
     */
    public static function getPkey() {
        return static::$pkey;
    }

    /**
     * Return the query, which is a protected static var
     *
     * @return string Model's SELECT query
     */
    public static function getQuery() {
        if ('' == static::$query) {
            $keys          = array_keys(static::$vars);
            static::$query = 'SELECT t.`'.join('`, t.`', $keys).'` FROM `'.static::$table.'` t';
        }

        return static::$query;
    }

    /**
     * Return the table, which is a protected static var
     *
     * @param string $joiner  Request a joiner table by specifying which table
     *                        to join with
     *
     * @return string Model's table name
     */
    public static function getTable($joiner = null) {
        // If no joiner is specified, we just want the table name
        if (null == $joiner) {
            return static::$table;
        }

        // If a known joiner is specified, return it
        if (isset(static::$joiners) && isset(static::$joiners[$joiner])) {
            return static::$joiners[$joiner];
        }

        // If a plausible joiner is specified, derive it
        if (preg_match('/^[\w\d]+$/i', $joiner)) {
            return static::$table.'_'.$joiner;
        }

        // An invalid joiner was requested, that's an error
        trigger_error('Requested invalid joiner table');

        return null;
    }

    /**
     * Return the model field list
     *
     * @return array Vars array representing table schema
     */
    public static function getFieldList() {
        return static::$vars;
    }

    /**
     * Return array of values changed since last DB load/save
     *
     * @return array Changed values
     */
    public function getDiff() {
        $diff = [];
        foreach (static::$vars as $k => $v) {
            if ($this->vals[$k] !== $this->DBvals[$k]) {
                $diff[$k] = $this->vals[$k];
            }
        }

        return $diff;
    }

    /**
     * Return whether record was altered
     *
     * @return bool True if altered, False if not
     */
    public function hasDiff() {
        foreach (static::$vars as $k => $v) {
            if ($this->vals[$k] !== $this->DBvals[$k]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets DBvals to match current vals
     *
     * @param array $keys Which fields to unDiff, defaults to all
     *
     * @return mixed Array of unregistered values on success, false on failure
     */
    public function unDiff(array $keys = null) {
        if (null === $keys) {
            $keys = array_keys(static::$vars);
        }
        foreach ($keys as $key) {
            $this->DBvals[$key] = $this->vals[$key];
        }
    }

    /**
     * Override this function to perform custom actions AFTER load
     *
     * @param array $row Unregistered values selected in load()
     *
     * @return void
     */
    public function onload(array $row = []) {
    }

    /**
     * Override this function to perform custom actions BEFORE insert
     * This will not be called if insert() does not attempt commit to DB
     *
     * @return void
     */
    public function oninsert() {
        if (isset(static::$vars['created_uts'])
            && 'ts' == static::$vars['created_uts']['type']
            && 0 == $this->vals['created_uts']
        ) {
            $this->vals['created_uts'] = NOW;
        }
    }

    /**
     * Override this function to perform custom actions BEFORE insert
     * This will not be called if insert() does not attempt commit to DB
     *
     * @return void
     */
    public function onAfterInsert() {
    }

    /**
     * Override this function to perform custom actions BEFORE update
     * This will not be called if update() does not attempt commit to DB
     *
     * @return void
     */
    public function onupdate() {
    }

    /**
     * Override this function to perform custom actions BEFORE update
     * This will not be called if update() does not attempt commit to DB
     *
     * @return void
     */
    public function onAfterUpdate() {
    }

    /**
     * Override this function to perform custom actions BEFORE delete
     * This will not be called if update() does not attempt commit to DB
     *
     * @return void
     */
    public function ondelete() {
    }

    /**
     * "Load" object from array, sets DBvals as if loaded from database
     *  if pkey is not passed, fail
     *
     * @param array $row values
     *
     * @return mixed Array of unregistered values on success, false on failure
     */
    public function load_array(array $row = []) {
        if (!isset($row[static::$pkey]) || null === $row[static::$pkey]) {
            return false;
        }
        $row = $this->setAll($row, false);
        foreach (static::$vars as $k => $v) {
            $this->DBvals[$k] = $this->vals[$k];
        }
        $this->onload($row);

        return $row;
    }

    /**
     * Produce meaningful array representation of Model
     *
     * @return array
     */
    public function toArray() {
        return $this->getAll();
    }

    /**
     * Instruct json_encode to only encode the array cast
     *
     * @return array json_encode'd array of values
     */
    public function jsonSerialize() {
        return $this->toArray();
    }

    /**
     * Generate DDL to Drop table in database
     *
     * @return mixed
     */
    public static function drop() {
        $query = "DROP TABLE IF EXISTS `".static::$table."`;";

        return $query;
    }

    /**
     * Generate DDL to Create table in database
     *
     * @return mixed
     */
    public static function create() {
        $query = "CREATE TABLE IF NOT EXISTS `".static::$table."` (\n";
        foreach (static::$vars as $field => $config) {
            $query .= '    '.static::getDDL($field).",\n";
        }
        if (!empty(static::$keys)) {
            foreach (static::$keys as $key) {
                if (!is_array($key)) {
                    $key = [$key];
                }
                foreach ($key as $i => $field) {
                    if ('`' != $field[0]) {
                        $key[$i] = "`$field`";
                    }
                }
                $query .= "    KEY (".implode(',', $key)."),\n";
            }
        }
        foreach (['updated_dts', 'recordChanged'] as $key) {
            if (!empty(static::$vars[$key]) && !in_array($key, static::$keys ?? [])) {
                $query .= "    KEY (`$key`),\n";
            }
        }
        if (!empty(static::$ukeys)) {
            foreach (static::$ukeys as $key) {
                if (!is_array($key)) {
                    $key = [$key];
                }
                foreach ($key as $i => $field) {
                    if ('`' != $field[0]) {
                        $key[$i] = "`$field`";
                    }
                }
                $query .= "    UNIQUE KEY (".implode(',', $key)."),\n";
            }
        }
        $query .= "    PRIMARY KEY(`".static::$pkey."`)\n);";

        return $query;
    }

    /**
     * Get or derive DDL for specified field
     *
     * @param string $field Specified field
     *
     * @return bool|string
     */
    public static function getDDL($field) {
        if (isset(static::$vars[$field]['ddl'])) {
            return static::$vars[$field]['ddl'];
        }

        return static::deriveDDL($field);
    }

    /**
     * Derive DDL for a field as configured in self::$vars
     *
     * @param string $field Name of field to derive DDL for
     *
     * @return bool|string
     */
    public static function deriveDDL($field) {
        if (!isset(static::$vars[$field])) {
            return false;
        }
        $config = static::$vars[$field];
        switch ($config['type']) {
            case 'f':
                // float
                $config['ddl'] = '`'.$field.'` float NOT NULL';
                if (isset($config['def']) && is_numeric($config['def'])) {
                    $config['ddl'] .= ' DEFAULT '.$config['def'];
                }
                break;
            case 'b':
                // boolean stored as bit
                $config['ddl'] = '`'.$field.'` bit(1) NOT NULL';
                if (isset($config['def'])) {
                    $config['ddl'] .= ' DEFAULT '.($config['def'] ? "b'1'" : "b'0'");
                } else {
                    $config['ddl'] .= " DEFAULT b'0'";
                }
                break;
            case 'ip':
                // IP address stored as int
                $config['ddl'] = '`'.$field.'` int(10) unsigned NOT NULL';
                if (isset($config['def'])) {
                    if (!is_numeric($config['def'])) {
                        $config['ddl'] .= ' DEFAULT '.ip2long($config['def']);
                    } else {
                        $config['ddl'] .= ' DEFAULT '.$config['def'];
                    }
                } else {
                    $config['ddl'] .= ' DEFAULT 0';
                }
                break;
            case 'em':
                // email address
            case 'o':
                // serialize()'d variables
            case 'j':
                // json_encoded()'d variables
            case 'a':
                // serialized arrays
            case 's':
                // string
                if (!isset($config['max']) || !is_numeric($config['max']) || 16777215 < $config['max']) {
                    $config['ddl'] = '`'.$field.'` longtext NOT NULL';
                } elseif (65535 < $config['max']) {
                    $config['ddl'] = '`'.$field.'` mediumtext NOT NULL';
                } elseif (255 < $config['max']) {
                    $config['ddl'] = '`'.$field.'` text NOT NULL';
                } else {
                    $config['ddl'] = '`'.$field.'` varchar('.((int)$config['max']).') NOT NULL';
                }
                if (isset($config['def'])) {
                    $def           = ($config['def'] == '' ? '' : G::$M->escape_string($config['def']));
                    $config['ddl'] .= " DEFAULT '$def'";
                }
                break;
            case 'ts':
                // int based timestamps
                // convert date min/max values to ints and fall through
                if (isset($config['min']) && !is_numeric($config['min'])) {
                    $config['min'] = strtotime($config['min']);
                }
                if (isset($config['max']) && !is_numeric($config['max'])) {
                    $config['max'] = strtotime($config['max']);
                }
                if (isset($config['def']) && !is_numeric($config['def'])) {
                    $config['def'] = strtotime($config['def']);
                }
            // fall through
            case 'i':
                // integers
                if (isset($config['min']) && is_numeric($config['min']) && 0 <= $config['min']) {
                    if (!isset($config['max']) || !is_numeric($config['max'])) {
                        $config['ddl'] = '`'.$field.'` int(10) unsigned NOT NULL';
                    } elseif (4294967295 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` bigint(20) unsigned NOT NULL';
                    } elseif (16777215 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` int(10) unsigned NOT NULL';
                    } elseif (65535 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` mediumint(7) unsigned NOT NULL';
                    } elseif (255 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` smallint(5) unsigned NOT NULL';
                    } elseif (0 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` tinyint(3) unsigned NOT NULL';
                    }
                } else {
                    if (!isset($config['max']) || !is_numeric($config['max'])) {
                        $config['ddl'] = '`'.$field.'` int(11) NOT NULL';
                    } elseif (2147483647 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` bigint(20) NOT NULL';
                    } elseif (8388607 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` int(11) NOT NULL';
                    } elseif (32767 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` mediumint(8) NOT NULL';
                    } elseif (127 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` smallint(6) NOT NULL';
                    } elseif (0 < $config['max']) {
                        $config['ddl'] = '`'.$field.'` tinyint(4) NOT NULL';
                    }
                }
                if (isset($config['def']) && is_numeric($config['def'])) {
                    $config['ddl'] .= ' DEFAULT '.((int)$config['def']);
                } elseif ($field != static::$pkey) {
                    $config['ddl'] .= ' DEFAULT 0';
                }

                // If the PRIMARY KEY is an INT type, assume AUTO_INCREMENT
                // This can be overridden with an explicit DDL
                if ($field == static::$pkey) {
                    $config['ddl'] .= ' AUTO_INCREMENT';
                }
                break;
            case 'e':
                // enums
                $config['ddl'] = '`'.$field.'` enum(';
                foreach ($config['values'] as $v) {
                    $config['ddl'] .= "'".G::$M->escape_string($v)."',";
                }
                $config['ddl'] = substr($config['ddl'], 0, -1).') NOT NULL';
                if (isset($config['def'])) {
                    $def           = ($config['def'] == '' ? '' : G::$M->escape_string($config['def']));
                    $config['ddl'] .= " DEFAULT '$def'";
                }
                break;
            case 'dt':
                // datetimes and mysql timestamps
                // A column called 'recordChanged' is assumed to be a MySQL timestamp
                if (in_array($field, ['recordChanged', 'updated_dts'])) {
                    $config['ddl'] = '`'.$field.'` timestamp NOT NULL'
                        .' DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
                    break;
                }

                if ('_dts' == substr($field, -4)) {
                    $config['ddl'] = '`'.$field.'` timestamp NOT NULL';
                } else {
                    $config['ddl'] = '`'.$field.'` datetime NOT NULL';
                }
                if (isset($config['def'])) {
                    // This supports more flexible defaults, like '5 days ago'
                    if (!is_numeric($config['def'])) {
                        $config['def'] = strtotime($config['def']);
                    }
                    $config['ddl'] .= " DEFAULT '".date('Y-m-d H:i:s', $config['def'])."'";
                }
                break;
            default:
                trigger_error('Unknown field type "'.$config['type'].'" in Record::create()');

                return false;
        }

        return $config['ddl'];
    }

}
