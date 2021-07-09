<?php
/**
 * Revision - For recording Revision history
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite\models;

use Stationer\Graphite\data\PassiveRecord;

/**
 * Class Revision
 *
 * @package Stationer\Graphite
 * @author  Andrew Leach
 *
 * @property int    $revision_id
 * @property string $created_uts
 * @property int    $updated_dts
 * @property string $revisedModel
 * @property int    $revised_id
 * @property int    $editor_id
 * @property string $changes
 *
 * @property string $loginname
 */
class Revision extends PassiveRecord {
    protected static $table = G_DB_TABL.'Revision';
    protected static $pkey = 'revision_id';
    protected static $query = '';
    protected static $vars = [
        'revision_id' => ['type' => 'i', 'min' => 0, 'guard' => true],
        'created_uts' => ['type' => 'ts', 'min' => 0, 'guard' => true],
        'updated_dts' => ['type' => 'dt', 'def' => NOW, 'guard' => true],

        'revisedModel' => ['type' => 's', 'min' => 0, 'max' => 255],
        'revised_id'   => ['type' => 'i', 'min' => 0],
        'editor_id'    => ['type' => 'i', 'min' => 0],
        'changes'      => ['type' => 'o', 'strict' => true, 'max' => 655350],
    ];

    protected $loginname;

    public function __construct($a = null, bool $b = null) {
        static::$query = "
SELECT t.`".join("`, t.`", array_keys(static::$vars))."`, l.`loginname`
FROM `".$this->getTable()."` t
    LEFT JOIN `".Login::getTable()."` l ON t.`editor_id` = l.`login_id`
";

        parent::__construct($a, $b);
    }

    /**
     * Sets the login name after load
     *
     * @param array $row the row
     *
     * @return array
     */
    public function onload(array $row = []) {
        if (isset($row['loginname'])) {
            $this->loginname = $row['loginname'];
            unset($row['loginname']);
        }

        return $row;
    }

    /**
     * Returns the login name
     *
     * @return string
     */
    public function loginname() {
        return $this->loginname;
    }
}
