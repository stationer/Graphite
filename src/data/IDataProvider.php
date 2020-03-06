<?php
/**
 * IDataProvider - Data Provider Interface
 * File : /src/data/IDataProvider.php
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite\data;

/**
 * IDataProvider interface -
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 * @see      /src/data/mysqli_.php
 * @see      /src/data/PassiveRecord.php
 */
interface IDataProvider {
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
     * @return array Found records
     */
    public function fetch($class, array $params = [], array $orders = [], $count = null, $start = 0);

    /**
     * Search for records of type $class according to provided primary key(s)
     *
     * @param string $class Name of Model to search for
     * @param mixed  $pkey  Value(s) of primary key to fetch
     *
     * @return ActiveRecord|array Found records
     */
    public function byPK($class, $pkey);

    /**
     * Load data for passed model
     *
     * @param PassiveRecord $Model Model to load, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function load(PassiveRecord &$Model);

    /**
     * Load data for passed model by its primary key value
     *
     * @param PassiveRecord $Model Model to load, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function select(PassiveRecord &$Model);

    /**
     * Load data for passed model by its set values
     *
     * @param PassiveRecord $Model Model to load, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function fill(PassiveRecord &$Model);

    /**
     * Save data for passed model
     *
     * @param PassiveRecord $Model Model to save, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function save(PassiveRecord &$Model);

    /**
     * Save data for passed model
     *
     * @param PassiveRecord $Model Model to save, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function insert(PassiveRecord &$Model);

    /**
     * Save data for passed model
     *
     * @param PassiveRecord $Model Model to save, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function update(PassiveRecord &$Model);

    /**
     * Delete data for passed model
     *
     * @param PassiveRecord $Model Model to delete, passed by reference
     *
     * @return bool|null True on success, False on failure, Null on invalid attempt
     */
    public function delete(PassiveRecord &$Model);
}
