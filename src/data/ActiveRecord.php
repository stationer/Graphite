<?php
/**
 * ActiveRecord - core database active record class file
 * File : /src/data/ActiveRecord.php
 *
 * PHP version 7.1
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite\data;

use Stationer\Graphite\G;

/**
 * Record class - used as a base class for Active Record Model classes
 *  an example extension is at bottom of file
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 * @see      /src/data/mysqli_.php
 * @see      /src/data/DataModel.php
 */
abstract class ActiveRecord extends PassiveRecord {
    /** @var array Instance DB values of vars defined in $vars */
    protected $DBvals = [];

    /** @var string $table Name of table, defined in subclasses */
    /* protected static $table; */

    /** @var string $pkey Primary key, defined in subclasses */
    /* protected static $pkey; */

    /** @var array $vars List of fields in table, defined in subclasses */
    /* protected static $vars = array(); */

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
        // Set the query that would be used by load()
        if ('' == static::$query) {
            $keys          = array_keys(static::$vars);
            static::$query = 'SELECT t.`'.join('`, t.`', $keys).'` FROM `'.static::$table.'` t';
        }

        parent::__construct($a, $b);
    }

    /**
     * Flush Diff
     *
     * @return void
     */
    public function flushDiff() {
        // initialize the values arrays with null values as some tests depend
        foreach (static::$vars as $key => $value) {
            $this->DBvals[$key] = null;
        }
    }

    /**
     * Load object from database
     *  if pkey is not set, assume fill(), else select()
     *
     * @return mixed Array of unregistered values on success, false on failure
     */
    public function load() {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        if (null === $this->vals[static::$pkey]) {
            return G::build(DataBroker::class)->fill($this);
        }

        return G::build(DataBroker::class)->select($this);
    }

    /**
     * SELECT the record from the database using static::$query
     * use sprintf() to embed the registered pkey
     * returns values selected that are not registered variables, typ. array()
     *
     * @return mixed Array of unregistered values on success, false on failure
     */
    public function select() {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        // Fail if pkey has no value
        if (null === $this->vals[static::$pkey]) {
            return false;
        }

        // embed pkey value into instance SELECT query, then run
        $query = static::$query." WHERE t.`".static::$pkey."` = '%d'";
        $query = sprintf($query, $this->vals[static::$pkey]);
        if (false === $result = G::$m->query($query)) {
            return false;
        }
        if (0 == $result->num_rows) {
            $result->close();

            return false;
        }
        $row = $result->fetch_assoc();
        $result->close();

        // data from DB should be filtered with setall to ensure specific types
        $this->setAll($row);
        foreach (static::$vars as $k => $v) {
            $this->DBvals[$k] = $this->vals[$k];
            unset($row[$k]);
        }
        $this->onload($row);

        return $row;
    }

    /**
     * SELECT the record from the database using static::$query
     * add all set values to the WHERE clause, otherwise like load()
     *
     * @return mixed Array of unregistered values on success, false on failure
     */
    public function fill() {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        // embed pkey value into instance SELECT query, then run
        $query = '';
        foreach (static::$vars as $k => $v) {
            if (null !== $this->vals[$k]) {
                if ('b' == static::$vars[$k]['type']) {
                    $query .= " AND t.`$k` = ".($this->vals[$k] ? '1' : '0');
                } else {
                    $query .= " AND t.`$k` = '".G::$m->escape_string($this->vals[$k])."'";
                }
            }
        }

        // if no fields were set, return false
        if ('' == $query) {
            return null;
        }

        $query = static::$query." WHERE ".substr($query, 4)
            .' GROUP BY `'.static::$pkey.'`'
            .' LIMIT 1';
        if (false === $result = G::$m->query($query)) {
            return false;
        }
        if (0 == $result->num_rows) {
            $result->close();

            return false;
        }
        $row = $result->fetch_assoc();
        $result->close();

        // data from DB should be filtered with setall to ensure specific types
        $this->setAll($row);
        foreach (static::$vars as $k => $v) {
            $this->DBvals[$k] = $this->vals[$k];
            unset($row[$k]);
        }
        $this->onload($row);

        return $row;
    }

    /**
     * SELECT all the records from the database using static::$query
     * add all set values to the WHERE clause, returns collection
     *
     * @param int    $count LIMIT - number of rows to SELECT
     * @param int    $start OFFSET - number of rows to skip
     * @param string $order ORDER BY - column to sort query by
     * @param bool   $desc  DESC/ASC - true for DESC ordering
     *
     * @return array Collection of objects found in search
     */
    public function search($count = null, $start = 0, $order = null, $desc = false) {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        // embed pkey value into instance SELECT query, then run
        $query = '';
        foreach (static::$vars as $k => $v) {
            if (null !== $this->vals[$k]) {
                if ('b' == static::$vars[$k]['type']) {
                    $query .= " AND t.`$k` = ".($this->vals[$k] ? '1' : '0');
                } else {
                    $query .= " AND t.`$k` = '".G::$m->escape_string($this->vals[$k])."'";
                }
            }
        }

        // if no fields were set, return false
        if ('' == $query && $count == null) {
            return null;
        }

        return self::search_where(" WHERE 1 ".$query, $count, $start, $order, $desc);
    }

    /**
     * SELECT all the records from the database using static::$query
     * add passed WHERE clause, returns collection
     *
     * @param string $where Custom WHERE clause
     * @param int    $count LIMIT - number of rows to SELECT
     * @param int    $start OFFSET - number of rows to skip
     * @param string $order ORDER BY - column to sort query by
     * @param bool   $desc  DESC/ASC - true for DESC ordering
     *
     * @return array Collection of objects found in search
     */
    protected static function search_where($where = "WHERE 1", $count = null, $start = 0, $order = null, $desc = false
    ) {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        // if the static properties haven't been initialized, do it by invoking the constructor
        if ('' == static::$query) {
            new static();
        }
        $query = static::$query.' '.$where
            .' GROUP BY `'.static::$pkey.'`'
            .(null !== $order && array_key_exists($order, static::$vars)
                ? ' ORDER BY t.`'.$order.'` '.($desc ? 'desc' : 'asc')
                : '')
            .('rand()' == $order ? ' ORDER BY RAND() '.($desc ? 'desc' : 'asc') : '')
            .(is_numeric($count) && is_numeric($start)
                ? ' LIMIT '.((int)$start).','.((int)$count)
                : '');
        $result = G::$m->query($query);
        if (false === $result) {
            return false;
        }
        $a = [];
        while ($row = $result->fetch_assoc()) {
            $a[$row[static::$pkey]] = new static();
            $a[$row[static::$pkey]]->load_array($row);
        }
        $result->close();

        return $a;
    }

    /**
     * SELECT $count of the records from the database using static::$query
     *
     * @param int    $count LIMIT - number of rows to SELECT
     * @param int    $start OFFSET - number of rows to skip
     * @param string $order ORDER BY - column to sort query by
     * @param bool   $desc  DESC/ASC - true for DESC ordering
     *
     * @return array Collection of objects found in search
     */
    public static function some($count = null, $start = 0, $order = null, $desc = false) {
        return self::search_where(" WHERE 1 ", $count, $start, $order, $desc);
    }

    /**
     * SELECT all the records from the database using static::$query
     *
     * @param string $order ORDER BY - column to sort query by
     * @param bool   $desc  DESC/ASC - true for DESC ordering
     *
     * @return array Collection of objects found in search
     */
    public static function all($order = null, $desc = false) {
        return static::some(null, 0, $order, $desc);
    }

    /**
     * SELECT all the records from the database using static::$query
     * add passed list of ids, returns collection
     *
     * @param array  $ids   Array of numeric ids to SELECT records for
     * @param int    $count LIMIT - number of rows to SELECT
     * @param int    $start OFFSET - number of rows to skip
     * @param string $order ORDER BY - column to sort query by
     * @param bool   $desc  DESC/ASC - true for DESC ordering
     *
     * @return array Collection of objects found in search
     */
    public static function search_ids($ids = [], $count = null, $start = 0, $order = null, $desc = false) {
        if (!is_array($ids)) {
            return false;
        }
        $a = [];
        foreach ($ids as $k => $v) {
            if (is_numeric($v)) {
                $a[] = $v;
            }
        }
        if (1 > count($a)) {
            return [];
        }
        $where = "WHERE t.`".static::$pkey."` IN (".implode(',', $a).")";

        return static::search_where($where, $count, $start, $order, $desc);
    }

    /**
     * SELECT the record from the database with the specified pkey value
     *
     * @param int $id Numeric id to SELECT record for
     *
     * @return object Object for specified ID
     * @see ActiveRecord::byPK
     *
     * @deprecated
     */
    public static function byId($id) {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        $R = new static($id);
        G::build(DataBroker::class)->load($R);

        return $R;
    }

    /**
     * SELECT the record from the database with the specified pkey value
     *
     * @param int $val Numeric id to SELECT record for
     *
     * @return bool|ActiveRecord False on failure or Record object for specified PKey
     */
    public static function byPK($val) {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        $R        = new static();
        $pkey     = static::$pkey;
        $R->$pkey = $val;
        if (false === G::build(DataBroker::class)->select($R)) {
            return false;
        }

        return $R;
    }

    /**
     * Commit object to database
     *  if pkey is not set, assume INSERT query, else UPDATE
     *
     * @return mixed Value returned by delegated method
     */
    public function save() {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        if (null === $this->vals[static::$pkey]) {
            return G::build(DataBroker::class)->insert($this);
        }

        return G::build(DataBroker::class)->update($this);
    }

    /**
     * Build INSERT query for set values, run and store insert_id
     * set value detection based on DBval, null for new (unloaded) records
     * $save flag set if any field changed, typically pkey set for insert()
     *
     * returns new pkey value (insert_id)
     * (uses MySQL specific INSERT ... SET ... syntax)
     *
     * @return mixed New primary key value of inserted row, or false on failure
     */
    public function insert() {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        $query = 'INSERT INTO `'.static::$table.'` SET ';
        // if hasDiff returns false, no fields were set, this is unexpected
        if (false === $this->hasDiff()) {
            return null;
        }
        $this->oninsert();
        foreach (array_keys($this->getDiff()) as $field) {
            if ('b' == static::$vars[$field]['type']) {
                $query .= " `$field` = ".($this->vals[$field] ? '1' : '0').',';
            } else {
                $query .= " `$field` = '".G::$M->escape_string($this->vals[$field])."',";
            }
        }

        $query = substr($query, 0, -1);
        if (false === G::$M->query($query)) {
            return false;
        }
        if (0 != G::$M->insert_id) {
            $this->vals[static::$pkey] = G::$M->insert_id;
        }

        // Subsequent to successful DB commit, update DBvals
        foreach (array_keys(static::$vars) as $field) {
            $this->DBvals[$field] = $this->vals[$field];
        }

        return $this->vals[static::$pkey];
    }

    /**
     * Build INSERT query for set values, run and store insert_id
     * set value detection based on DBval, null for new (unloaded) records
     * $save flag set if any field changed, typically pkey set for insert()
     *
     * returns new pkey value (insert_id)
     * (uses MySQL specific INSERT ... SET ... syntax)
     *
     * @return mixed New primary key value of inserted row, or false on failure
     */
    public function insert_update() {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        $query = 'INSERT INTO `'.static::$table.'` SET';
        // if hasDiff returns false, no fields were set, this is unexpected
        if (false === $this->hasDiff()) {
            return null;
        }
        $this->oninsert();
        $fields                      = '';
        $this->DBvals[static::$pkey] = null;
        foreach (array_keys($this->getDiff()) as $field) {
            if ('b' == static::$vars[$field]['type']) {
                $fields .= " `$field` = ".($this->vals[$field] ? '1' : '0').',';
            } else {
                $fields .= " `$field` = '".G::$M->escape_string($this->vals[$field])."',";
            }
        }

        $fields = substr($fields, 0, -1);
        $query  .= $fields.' ON DUPLICATE KEY UPDATE'.$fields;
        if (false === G::$M->query($query)) {
            return false;
        }
        if (0 != G::$M->insert_id) {
            $this->vals[static::$pkey] = G::$M->insert_id;
        }

        // Subsequent to successful DB commit, update DBvals
        foreach (array_keys(static::$vars) as $field) {
            $this->DBvals[$field] = $this->vals[$field];
        }

        return $this->vals[static::$pkey];
    }

    /**
     * Build UPDATE query for changed values, run
     * set value detection based on DBval, set in load()
     * $save flag set if any field changed
     *
     * @return bool True on success, false on failure, null on abort
     */
    public function update() {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        $query = 'UPDATE `'.static::$table.'` SET ';
        // if hasDiff returns false, no fields were set, this is unexpected
        if (false === $this->hasDiff()) {
            return null;
        }
        $this->onupdate();
        foreach (array_keys($this->getDiff()) as $field) {
            if (null === $this->vals[$field]) {
                $query .= '`'.$field."` = NULL,";
            } elseif ('b' == static::$vars[$field]['type']) {
                $query .= '`'.$field.'` = '.($this->vals[$field] ? '1' : '0').',';
            } else {
                $query .= '`'.$field."` = '".G::$M->escape_string($this->vals[$field])."',";
            }
        }

        $query = substr($query, 0, -1)
            ." WHERE `".static::$pkey."` = '".G::$M->escape_string($this->vals[static::$pkey])."'";
        if (false === G::$M->query($query)) {
            return false;
        }
        if (1 < G::$M->affected_rows) {
            trigger_error('PKEY UPDATE affected more than one row!!! '.$query);
        }

        // Subsequent to successful DB commit, update DBvals
        foreach (array_keys(static::$vars) as $field) {
            $this->DBvals[$field] = $this->vals[$field];
        }

        return true;
    }

    /**
     * Delete a record
     *
     * @return bool True on success, false on failure
     */
    public function delete() {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        // Fail if pkey has no value
        if (null === $this->vals[static::$pkey]) {
            return false;
        }
        $this->ondelete();
        $query = 'DELETE FROM `'.static::$table.'` '
            ." WHERE `".static::$pkey."` = '".G::$M->escape_string($this->vals[static::$pkey])."' LIMIT 1";
        if (false === G::$M->query($query)) {
            return false;
        }

        return true;
    }

    /**
     * Drop table from database
     *
     * @return bool
     */
    public static function drop() {
        $query = "DROP TABLE IF EXISTS `".static::$table."`";

        return G::$M->query($query);
    }

    /**
     * Create table in database
     *
     * @param bool $returnQuery If true, return query instead of running it
     *
     * @return mixed
     */
    public static function create($returnQuery = false) {
        $query = "CREATE TABLE IF NOT EXISTS `".static::$table."` (";
        foreach (static::$vars as $field => $config) {
            if (!isset($config['ddl'])) {
                $config['ddl'] = static::deriveDDL($field);
            }
            $query .= $config['ddl'].', ';
        }
        $query .= 'PRIMARY KEY(`'.static::$pkey.'`))';

        if ($returnQuery) {
            return $query;
        }

        return G::$M->query($query);
    }

    /**
     * Get DESCRIBE data from mysql server
     *
     * @return array|bool
     */
    public static function describe() {
        $query = "DESCRIBE `".static::$table."`";

        return G::$m->queryToArray($query);
    }

    /**
     *
     *
     * @return array
     */
    public static function verifyStructure() {
        $describe = static::describe();
        $config   = static::$vars;
        $changes  = [];
        if (!empty($describe)) {
            foreach ($describe as $col) {
                $back_ddl  = '`'.$col['Field'].'` '
                    .($col['Type'])
                    .('NO' == $col['Null'] ? ' NOT NULL' : '')
                    .('' != $col['Default'] ? ' DEFAULT '.$col['Default'] : '')
                    .('' != $col['Extra'] ? ' '.strtoupper($col['Extra']) : '')
                    .('MUL' == $col['Key'] ? ', KEY (`'.$col['Field'].'`)' : '')
                    .('UNI' == $col['Key'] ? ' UNIQUE KEY' : '');
                $front_ddl = static::getDDL($col['Field']);
                if ($back_ddl != $front_ddl) {
                    if (false === $front_ddl) {
                        $alter = 'DROP `'.$col['Field'].'`';
                    } else {
                        $alter = 'CHANGE `'.$col['Field'].'` '.$front_ddl;
                    }
                    $changes[$col['Field']] = compact('back_ddl', 'front_ddl', 'alter');
                }
                unset($config[$col['Field']]);
                unset($describe[$col['Field']]);
            }
        }
        foreach ($config as $field => $col) {
            $front_ddl       = static::getDDL($field);
            $back_ddl        = false;
            $alter           = 'ADD '.$front_ddl;
            $changes[$field] = compact('back_ddl', 'front_ddl', 'alter');
        }

        return $changes;
    }

    /**
     * Encrypt a string using openSSL
     *
     * @param string $plaintext String to encrypt
     *
     * @return string
     */
    public function encrypt($plaintext) {
        if (empty(G::$G['SEC']['encryptionKey'])) {
            trigger_error('Encryption key not set!', E_USER_ERROR);
        }

        $cipher            = G::$G['SEC']['encryptionCipher'];
        $iv_size           = openssl_cipher_iv_length(G::$G['SEC']['encryptionCipher']);
        $iv                = openssl_random_pseudo_bytes($iv_size);
        $ciphertext        = openssl_encrypt($plaintext, $cipher, G::$G['SEC']['encryptionKey'], $options = 0, $iv);
        $ciphertext_base64 = base64_encode($iv).':'.base64_encode($ciphertext);

        return $ciphertext_base64;
    }

    /**
     * Decrypt a string
     *
     * @param string $ciphertext String to decrypt
     *
     * @return string
     */
    public function decrypt($ciphertext) {
        if (empty(G::$G['SEC']['encryptionKey'])) {
            trigger_error('Encryption key not set!', E_USER_ERROR);
        }
        $cipher = G::$G['SEC']['encryptionCipher'];
        $split  = explode(':', $ciphertext);
        if (count($split) !== 2) {
            return false;
        }
        [$iv, $ciphertext] = $split;
        $iv                 = base64_decode($iv);
        $ciphertext         = base64_decode($ciphertext);
        $original_plaintext = openssl_decrypt($ciphertext, $cipher, G::$G['SEC']['encryptionKey'], $options = 0, $iv);

        return $original_plaintext;
    }

    /**
     * SELECT COUNT() all the records from the database using static::$query
     * add all set values to the WHERE clause, returns collection
     *
     * @param string $where Custom WHERE clause
     *
     * @return array Collection of objects found in search
     */
    public function count($where = "WHERE 1") {
        trigger_error('Call to deprecated method: '.__METHOD__, E_USER_DEPRECATED);
        // embed pkey value into instance SELECT query, then run
        $query = '';
        foreach (static::$vars as $k => $v) {
            if (null !== $this->vals[$k]) {
                if ('b' == static::$vars[$k]['type']) {
                    $query .= " AND t.`$k` = ".($this->vals[$k] ? '1' : '0');
                } else {
                    $query .= " AND t.`$k` = '".G::$m->escape_string($this->vals[$k])."'";
                }
            }
        }

        // if no fields were set, return false
        if ('' == $query) {
            return null;
        }

        $query = "SELECT COUNT(`".static::$pkey."`) FROM `".static::$table."` t ".$where." ".$query;
        if (false === $result = G::$m->query($query)) {
            return false;
        }
        // We should find exactly one value
        $count = $result->fetch_array()[0];
        $result->close();

        return $count;
    }
}
