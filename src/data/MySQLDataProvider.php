<?php
/**
 * MySQLDataProvider - Provide data from MySQL
 * File : /src/data/MySQLDataProvider.php
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
 * MySQLDataProvider class - Runs CRUD to MySQL for PassiveRecord models
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 * @see      /src/data/mysqli_.php
 * @see      /src/data/PassiveRecord.php
 */
class MySQLDataProvider extends DataProvider {
    /**
     * Search for records of type $class according to search params $params
     * Order results by $orders and limit results by $count, $start
     *
     * @param string $class  Name of Model to search for
     * @param array  $params Values to search against
     * @param array  $orders Order(s) of results
     * @param int    $count  Number of rows to fetch
     * @param int    $start  Number of rows to skip
     *
     * @return array|bool Found records|false on failure
     */
    public function fetch($class, array $params = [], array $orders = [], $count = null, $start = 0) {
        /** @var PassiveRecord $Model */
        $Model = G::build($class);
        if (!is_a($Model, PassiveRecord::class)) {
            trigger_error('Supplied class "'.$class.'" name does not extend PassiveRecord', E_USER_ERROR);
        }

        $vars  = $Model->getFieldList();
        $where = $this->buildWhere($params, $Model);

        $keys  = array_keys($vars);
        $query = $Model->getQuery();
        if ('' == $query) {
            $query = "\nSELECT t.`".join("`, t.`", $keys)."`"
                ."\nFROM `".$Model->getTable()."` t";
        }
        $query .= $where
            ."\nGROUP BY t.`".$Model->getPkey().'`'
            .$this->_makeOrderBy($orders, array_keys($vars))
            .(is_numeric($count) && is_numeric($start)
                ? "\nLIMIT ".((int)$start).",".((int)$count)
                : "");

        $source = $Model->getSource();
        $MySql  = mysqli_::buildForSource($source);
        if (null != $MySql) {
            $result = $MySql->query($query);
        } else {
            $result = G::$m->query($query);
        }
        if (false === $result) {
            return false;
        }

        $Records = [];
        while ($row = $result->fetch_assoc()) {
            /** @var PassiveRecord $Records [$row[$Model->getPkey()]] */
            $Records[$row[$Model->getPkey()]] = new $class();
            $Records[$row[$Model->getPkey()]]->load_array($row);
        }
        $result->close();

        return $Records;
    }

    /**
     * Search for records of type $class according to search params $params
     * Order results by $orders and limit results by $count, $start
     *
     * @param string $class  Name of Model to search for
     * @param array  $params Values to search against
     *
     * @return array Found records
     */
    public function count($class, array $params = []) {
        /** @var PassiveRecord $Model */
        $Model = G::build($class);
        if (!is_a($Model, PassiveRecord::class)) {
            trigger_error('Supplied class "'.$class.'" name does not extend PassiveRecord', E_USER_ERROR);
        }

        $vars  = $Model->getFieldList();
        $where = $this->buildWhere($params, $Model);

        $query = 'SELECT COUNT(t.`'.$Model->getPkey().'`) AS `count`'
            .' FROM `'.$Model->getTable().'` t'
            .$where;

        $source = $Model->getSource();
        $MySql  = mysqli_::buildForSource($source);
        if (null != $MySql) {
            $result = $MySql->query($query);
        } else {
            $result = G::$m->query($query);
        }
        if (false === $result) {
            return false;
        }

        if ($row = $result->fetch_assoc()) {
            $count = $row['count'];
        } else {
            $count = false;
        }
        $result->close();

        return $count;
    }

    /**
     * Save data for passed model
     *
     * @param PassiveRecord $Model Model to save, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function insert(PassiveRecord &$Model) {
        $diff = $Model->getDiff();

        // If no fields were set, this is unexpected
        if (0 == count($diff)) {
            return null;
        }

        $vars   = $Model->getFieldList();
        $fields = [];
        $values = [];

        $Model->oninsert();
        // Get new diff in case oninsert altered data
        $diff = $Model->getDiff();

        foreach ($diff as $key => $val) {
            $fields[] = $key;
            if ('b' == $vars[$key]['type']) {
                $values[] = $diff[$key] ? "b'1'" : "b'0'";
            } else {
                $values[] = "'".G::$M->escape_string($diff[$key])."'";
            }
        }

        $query = 'INSERT INTO `'.$Model->getTable().'`'
            .' (`'.implode('`, `', $fields).'`)'
            ."\nVALUES (".implode(", ", $values).")";

        if (false === G::$M->query($query)) {
            return false;
        }
        if (0 != G::$M->insert_id) {
            $Model->{$Model->getPkey()} = G::$M->insert_id;
        }

        $Model->unDiff();
        $Model->onAfterInsert();

        return $Model->{$Model->getPkey()};
    }

    /**
     * Save data for passed model
     *
     * @param PassiveRecord $Model Model to save, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function insert_update(PassiveRecord &$Model) {
        $diff = $Model->getDiff();

        // If no fields were set, this is unexpected
        if (0 == count($diff)) {
            return null;
        }

        // Iff the pkey has a value, add it to the diff to ensure the UPDATE works
        if (null !== $Model->{$Model->getPkey()}) {
            $diff[$Model->getPkey()] = $Model->{$Model->getPkey()};
        }
        $vars    = $Model->getFieldList();
        $fields  = [];
        $values  = [];
        $updates = [];

        $Model->oninsert();
        // Get new diff in case oninsert altered data
        $diff = $Model->getDiff();

        foreach ($diff as $key => $val) {
            $fields[] = $key;
            if ('b' == $vars[$key]['type']) {
                $values[]  = $diff[$key] ? "b'1'" : "b'0'";
                $updates[] = "`$key` = ".($diff[$key] ? "b'1'" : "b'0'");
            } else {
                $values[]  = "'".G::$M->escape_string($diff[$key])."'";
                $updates[] = "`$key` = '".G::$M->escape_string($diff[$key])."'";
            }
        }

        $query = 'INSERT INTO `'.$Model->getTable().'`'
            .' (`'.implode('`, `', $fields).'`)'
            ."\nVALUES (".implode(", ", $values).")"
            ."\nON DUPLICATE KEY UPDATE "
            .implode(', ', $updates);

        if (false === G::$M->query($query)) {
            return false;
        }
        if (0 != G::$M->insert_id) {
            $Model->{$Model->getPkey()} = G::$M->insert_id;
        }

        $Model->unDiff();
        $Model->onAfterInsert();

        return $Model->{$Model->getPkey()};
    }

    /**
     * Save data for passed model
     *
     * @param PassiveRecord $Model Model to save, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function update(PassiveRecord &$Model) {
        // If the PKey is not set, what would we update?
        if (null === $Model->{$Model->getPkey()}) {
            return null;
        }
        $diff = $Model->getDiff();

        // If no fields were set, this is unexpected
        if (0 == count($diff)) {
            return null;
        }
        $vars   = $Model->getFieldList();
        $values = [];

        $Model->onupdate();
        // Get new diff in case onupdate altered data
        $diff = $Model->getDiff();

        foreach ($diff as $key => $val) {
            if (null === $Model->{$Model->getPkey()}) {
                $values[] = "`$key` = NULL";
            } elseif ('b' == $vars[$key]['type']) {
                $values[] = "`$key` = ".($diff[$key] ? "b'1'" : "b'0'");
            } else {
                $values[] = "`$key` = '".G::$M->escape_string($diff[$key])."'";
            }
        }

        $query = 'UPDATE `'.$Model->getTable().'` SET '
            .implode(', ', $values)
            ."\nWHERE `".$Model->getPkey()."` = '".G::$M->escape_string($Model->{$Model->getPkey()})."'";

        if (false === G::$M->query($query)) {
            return false;
        }
        $Model->unDiff();
        $Model->onAfterUpdate();

        return true;
    }

    /**
     * Delete data for passed Model
     *
     * @param PassiveRecord $Model Model to be passed
     *
     * @return mixed|null
     */
    public function delete(PassiveRecord &$Model) {
        // If the PKey is not set, what would we update?
        if (null === $Model->{$Model->getPkey()}) {
            return null;
        }

        $Model->ondelete();
        $query = 'DELETE FROM `'.$Model->getTable().'` '
            ."\nWHERE `".$Model->getPkey()."` = '".G::$M->escape_string($Model->{$Model->getPkey()})."'";

        return G::$M->query($query);
    }

    /**
     * Take an array that has fieldnames for keys
     *  and bool indicating asc/desc order for values
     *
     * @param array $orders Array of field => (= 'asc' ?)
     * @param array $valids list of valid order by values
     *
     * @return string ORDER BY clause
     */
    protected function _makeOrderBy(array $orders = [], array $valids = []) {
        if (0 == count($orders) || 0 == count($valids)) {
            return '';
        }

        foreach ($orders as $field => $asc) {
            if (false === $asc || 'desc' == strtolower($asc)) {
                $asc = 'DESC';
            } elseif (true === $asc || 'asc' == strtolower($asc)) {
                $asc = 'ASC';
            } else {
                $asc = '';
            }
            if ('rand()' == $field) {
                $orders[$field] = "RAND() ".$asc;
            } elseif (in_array($field, $valids)) {
                $orders[$field] = "`$field` ".$asc;
            }
        }

        return "\nORDER BY ".join(',', $orders);
    }

    /**
     * Builds a WHERE clause from a list of key/value pairs
     *
     * @param array         $params Key/Value pairs to add to the WHERE clause
     * @param PassiveRecord $Model  Model to be passed
     *
     * @return string
     */
    protected function buildWhere(array $params, $Model) {
        $vars   = $Model->getFieldList();
        $values = [];
        // Build search WHERE clause
        foreach ($params as $key => $val) {
            if (!isset($vars[$key])) {
                // Skip Invalid field
                continue;
            }
            // Support list of values for IN conditions
            if (is_array($val) && !in_array($vars[$key]['type'], ['a', 'j', 'o', 'b'])) {
                foreach ($val as $key2 => $val2) {
                    // Sanitize each value through the model
                    $Model->$key = $val2;
                    $val2        = $Model->$key;
                    $val[$key2]  = G::$m->escape_string($val2);
                }
                $values[] = "t.`$key` IN ('".implode("', '", $val)."')";
            } else {
                $Model->$key = $val;
                $val         = $Model->$key;
                if ('b' == $vars[$key]['type']) {
                    $values[] = "t.`$key` = ".($val ? "b'1'" : "b'0'");
                } else {
                    $values[] = "t.`$key` = '".G::$M->escape_string($val)."'";
                }
            }
        }
        $where = count($values) ? ' WHERE '.join(' AND ', $values) : '';

        return $where;
    }
}
