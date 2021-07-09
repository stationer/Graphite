<?php
/**
 * RevisionWorkflow - Helper for logging and recalling revisions
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite;

use Stationer\Graphite\data\DataBroker;
use Stationer\Graphite\models\Revision;

/**
 * Runtime class - Graphite's root invoker
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */
class RevisionWorkflow {
    /** @var DataBroker $DB */
    protected $DB;

    /**
     * RevisionWorkflow constructor.
     *
     * @param DataBroker|null $DB
     */
    public function __construct(DataBroker $DB = null) {
        $this->DB = $DB ?? G::build(DataBroker::class);
    }

    /**
     * Record a revision
     *
     * @param string $model Table name, or any way to group revisions
     * @param int    $key   id of record revised
     * @param mixed  $data  Data to log
     *
     * @return bool|null
     */
    public function log(string $model, int $key, $data) {
        /** @var Revision $Revision */
        $Revision = G::build(Revision::class);
        $Revision->revisedModel = $model;
        $Revision->revised_id = $key;
        $Revision->changes = $data;
        $Revision->editor_id = G::$S->Login->login_id ?? 0;

        return $this->DB->save($Revision);
    }

    /**
     * Return the recent 100 changes for specified model
     *
     * @param string $model Table name, or any way to group revisions
     * @param int    $key   id of record revised
     *
     * @return array
     */
    public function get(string $model, int $key) {
        $results = $this->DB->fetch(Revision::class,
            ['revisedModel' => $model, 'revised_id' => $key],
            ['created_uts' => false], 100);
        return $results;
    }
}
