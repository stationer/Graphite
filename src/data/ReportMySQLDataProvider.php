<?php
/**
 * ReportMySQLDataProvider - Provide report data from MySQL
 * File : /src/data/ReportMySQLDataProvider.php
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
 * ReportMySQLDataProvider class - Fetches reports for PassiveReport models
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 * @see      /src/data/mysqli_.php
 */
abstract class ReportMySQLDataProvider extends MySQLDataProvider {
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
     * @return array|bool Found records, false on error
     */
    public function fetch($class, array $params = [], array $orders = [], $count = null, $start = 0) {
        /** @var PassiveReport $Model */
        $Model = G::build($class);
        if (!is_a($Model, PassiveReport::class)) {
            trigger_error('Supplied class name does not extend PassiveReport', E_USER_ERROR);
        }

        // Sanitize $params through Model
        $Model->setAll($params);

        $vars   = $Model->getParamList();
        $params = $Model->getAll();
        $params = array_filter($params, function ($val) {
            return !is_null($val);
        });

        $query = [];

        foreach ($params as $key => $val) {
            // Support list of values for OR conditions
            if (is_array($val) && !in_array($vars[$key]['type'], ['a', 'j', 'o', 'b'])) {
                foreach ($val as $key2 => $val2) {
                    // Sanitize each value through the model
                    $Model->$key = $val2;
                    $val[$key2]  = sprintf($vars[$key]['sql'], G::$m->escape_string($Model->$key));
                }
                $query[] = "((".implode(") OR (", $val)."))";
            } elseif ('a' === $vars[$key]['type']) {
                $arr = unserialize($Model->$key);

                foreach ($arr as $kk => $vv) {
                    $arr[$kk] = G::$m->escape_string($vv);
                }
                $query[] = sprintf($vars[$key]['sql'], "'".implode("', '", $arr)."'");
            } elseif ('b' == $vars[$key]['type']) {
                $query[] = sprintf($vars[$key]['sql'], (int)$val);
            } else {
                $query[] = sprintf($vars[$key]['sql'], G::$m->escape_string($val));
            }
        }

        if (count($query) == 0) {
            $query = sprintf($this->getQueryForReport($class), '1');
        } else {
            $query = sprintf($this->getQueryForReport($class), implode(' AND ', $query));
        }

        $query .= $this->_makeOrderBy($Model->getOrders($orders));

        if (null == $count) {
            $count = $Model->getCount();
            $start = $Model->getStart();
        }
        if (is_numeric($count) && is_numeric($start)) {
            // add limits also
            $query .= ' LIMIT '.$start.', '.$count;
        }

        $result = G::$m->query($query);

        if (false === $result) {
            return false;
        }
        $data = [];
        $row  = $result->fetch_assoc();
        while ($row) {
            $data[] = $row;
            $row    = $result->fetch_assoc();
        }
        $result->close();
        $Model->setData($data);
        $Model->onload();

        return $Model->toArray();
    }

    /**
     * Search for records of type $class according to provided primary key(s)
     *
     * @param string $class Name of Model to search for
     * @param mixed  $pkey  Value(s) of primary key to fetch
     *
     * @return array|bool Found records, false on failure
     */
    public function byPK($class, $pkey) {
        return false;
    }

    /**
     * Save data does not apply to reports
     *
     * @param PassiveRecord $Model Model to save, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function insert(PassiveRecord &$Model) {
        return false;
    }

    /**
     * Save data does not apply to reports
     *
     * @param PassiveRecord $Model Model to save, passed by reference
     *
     * @return bool false
     */
    public function update(PassiveRecord &$Model) {
        return false;
    }

    /**
     * Gets the Query for the report
     *
     * @param string $class Name of Report
     *
     * @return mixed
     */
    abstract public function getQueryForReport($class);
}
