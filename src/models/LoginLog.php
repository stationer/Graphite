<?php
/**
 * LoginLog - AR class for logging log-ins
 * File : /^/models/LoginLog.php
 *
 * PHP version 7.0
 *
 * @category Graphite
 * @package  Core
 * @author   LoneFry <dev@lonefry.com>
 * @license  CC BY-NC-SA http://creativecommons.org/licenses/by-nc-sa/3.0/
 * @link     http://g.lonefry.com
 */

namespace Stationer\Graphite\models;

use Stationer\Graphite\data\PassiveRecord;

/**
 * LoginLog class - AR class for logging log-ins
 *
 * @category Graphite
 * @package  Core
 * @author   LoneFry <dev@lonefry.com>
 * @license  CC BY-NC-SA http://creativecommons.org/licenses/by-nc-sa/3.0/
 * @link     http://g.lonefry.com
 * @see      /^/lib/Record.php
 * @property int    loginlog_id
 * @property int    created_uts
 * @property string updated_dts
 * @property int    login_id
 * @property string ip
 * @property string ua
 * @property int    login_uts
 */
class LoginLog extends PassiveRecord {
    /** @var string Table name, un-prefixed */
    protected static $table = G_DB_TABL.'LoginLog';
    /** @var string Primary Key */
    protected static $pkey = 'loginlog_id';
    /** @var string Select query, without WHERE clause */
    protected static $query = '';
    /** @var array Table definition as collection of fields */
    protected static $vars = [
        'loginlog_id' => ['type' => 'i', 'min' => 1, 'guard' => true],
        'created_uts' => ['type' => 'ts', 'min' => 0, 'guard' => true],
        'updated_dts' => ['type' => 'dt', 'min' => NOW, 'def' => NOW, 'guard' => true],
        'login_id'    => ['type' => 'i', 'min' => 0],
        'ip'          => ['type' => 'ip', 'def' => G_REMOTE_ADDR,
                          'ddl' => '`ip` int(10) unsigned NOT NULL DEFAULT 0'],
        'ua'          => ['type' => 's', 'max' => 255],
        'login_uts'   => ['type' => 'ts', 'min' => 0, 'def' => NOW,
                          'ddl' => '`login_uts` int(10) unsigned NOT NULL DEFAULT 0'],
    ];
}
