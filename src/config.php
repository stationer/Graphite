<?php
/**
 * website core configuration file
 * File : /src/config.php
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite;

use Stationer\Graphite\data\DataModel;

// Tue, 15 Nov 1994 12:45:26 GMT
const DATETIME_HTTP = 'D, d M Y H:i:s T';
// August 1, 2017
const DATE_HUMAN = "M j, Y";
// 11:07 am
const TIME_HUMAN = "g:i a";
// 2018-04-22 11:07 am
const DATETIME_HUMAN = "M j, Y g:i a";
// 2018-04-22
const DATE_ISO = "Y-m-d";
// 15:32:34
const TIME_ISO = 'H:i:s';

/** **************************************************************************
 * General settings
 ****************************************************************************/
G::$G['startTime'] = NOW;

G::$G['timezone'] = 'America/New_York';
G::$G['siteEmail'] = 'apache@localhost';
G::$G['contactFormSubject'] = 'Contact form message: ';
G::$G['MODE'] = 'prd';
// Application Version, can be overwritten by any app config and used to
// prevent caching
G::$G['VERSION'] = date('Y.m.d');
// includePath relative to SITE
// each class will append it's own sub directory to each path
G::$G['includePath'] = '';
G::$G['language'] = 'en_us';
G::$G['namespaces'] = [
    '',
    '\\',
    '\\Stationer\\Graphite\\',
    '\\Stationer\\Graphite\\data\\',
    '\\Stationer\\Graphite\\models\\',
];

// enable the installer -- reverse this when installed
G::$G['installer'] = true;

// International characters that should be substituted for php's filter_var
G::$G[DataModel::class]['InternationalCharacters'] = [
    'À', 'à', 'Â', 'â', 'Ä', 'ä', 'Æ', 'æ', 'Ç', 'ç', 'È', 'è', 'É', 'é', 'Ê',
    'ê', 'Ë', 'ë', 'Î', 'î', 'Ï', 'ï', 'Ô', 'ô', 'Ù', 'ù', 'Û', 'û', 'Ü', 'ü',
];
/** **************************************************************************
 * /General settings
 ****************************************************************************/


/** **************************************************************************
 * Database settings
 ****************************************************************************/
G::$G['db'] = array(
    'host' => '',
    'user' => '',
    'pass' => '',
    'name' => '',
    'tabl' => '',
    'log'  => false
);
G::$G['db']['ro'] = array(
    'host' => '',
    'user' => '',
    'pass' => '',
    'name' => ''
);

G::$G['db']['slowQueryThreshold'] = 50;

G::$G['db']['ProviderDict'] = array(
    \Stationer\Graphite\data\DataModel::class => \Stationer\Graphite\data\MySQLDataProvider::class,
    \Stationer\Graphite\data\Report::class    => \Stationer\Graphite\data\ReportDataProvider::class,
);

G::$G['db']['guard'] = true;
/** **************************************************************************
 * /Database settings
 ****************************************************************************/


/** **************************************************************************
 * Settings for Security
 ****************************************************************************/
// A blank encyption key prevents Record::encypt() and ::decrypt()
G::$G['SEC']['encryptionKey'] = '';

// Classes to use to produce and test password hash
G::$G['SEC']['hash_class'] = array(
    PBKDF2PasswordHasher::class,
    SHA1PasswordHasher::class,
);

// parameters for the PBKDF2 hashword generation method
G::$G['SEC']['PBKDF2'] = array(
    'algo'       => 'sha256',
    'iterations' => 1024,
    'salt_len'   => 32,
    'hash_len'   => 32,
    'sections'   => array(
        'algo', 'iterations', 'salt', 'PBKDF2',
    ),
);

// password policies for use by Security::validate_password()
// Security::validate_password() should be called when users change passwords
G::$G['SEC']['passwords'] = array(
    // valid passwords must match these regular expressions
    // If they do not match, the error is returned
    'require' => array(
        array('/^.{6,}$/',
            'Password must be at least six characters long.'),
    ),

    // valid passwords must NOT match these regular expressions
    // If they do match, the error and $matches array are passed to vsprintf
    'deny' => array(
        array('/password|12345/',
            'Password must not contain "%s".'),
    ),

    // whether to enforce policies in the admin forms
    'enforce_in_admin' => !true,
);
// Examples of useful patterns
/*
G::$G['SEC']['passwords']['require'][] = array(
    '/^(?=.*\d)(?=.*[a-zA-Z]).{8,}$/',
    'Password must be at least eight characters long and contain digits and letters.'
    );
 G::$G['SEC']['passwords']['require'][] = array(
    '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}$/',
    'Password must be at least eight characters long and contain digits, lower and upper letters, and symbols.'
    );
 */

/** **************************************************************************
 * Settings for Security
 ****************************************************************************/


/** **************************************************************************
 * Settings for the Dispatcher
 ****************************************************************************/
// Defaults
G::$G['CON']['controller'] = 'Installer';
G::$G['CON']['controller404'] = 'Default';
G::$G['CON']['controller500'] = 'Default';

// Passed Values
if (isset($_GET['_controller'])) {
    G::$G['CON']['controller'] = $_GET['_controller'];
}
if (isset($_GET['_action'])) {
    G::$G['CON']['action'] = $_GET['_action'];
}
if (isset($_GET['_params'])) {
    G::$G['CON']['params'] = $_GET['_params'];
}
if (isset($_GET['_argv'])) {
    G::$G['CON']['argv'] = $_GET['_argv'];
}
if (isset($_SERVER['PATH_INFO'])) {
    G::$G['CON']['path'] = $_SERVER['PATH_INFO'];
} elseif (isset($_POST['_path'])) {
    G::$G['CON']['path'] = $_POST['_path'];
} elseif (isset($_GET['_path'])) {
    G::$G['CON']['path'] = $_GET['_path'];
}
/** **************************************************************************
 * /Settings for the Dispatcher
 ****************************************************************************/


/** **************************************************************************
 * Settings for the View
 ****************************************************************************/

// display vars
G::$G['VIEW']['_siteName'] = 'Graphite Site';
G::$G['VIEW']['_siteURL'] = 'https://'.$_SERVER['SERVER_NAME'];
G::$G['VIEW']['_loginURL'] = '/Account/login';
G::$G['VIEW']['_logoutURL'] = '/Account/Logout';
G::$G['VIEW']['_header'] = 'header.php';
G::$G['VIEW']['_footer'] = 'footer.php';
G::$G['VIEW']['_debug'] = 'footer.debug.php';

G::$G['VIEW']['_meta'] = array(
    "description" => "Graphite MVC framework",
    "keywords"    => "Graphite,MVC,framework",
    "generator"   => "Graphite MVC Framework",
);
G::$G['VIEW']['_script'] = array(
    // '/path/to/script.js',
);
G::$G['VIEW']['_link'] = array(
    array('rel' => 'shortcut icon','type' => 'image/vnd.microsoft.icon','href' => '/favicon.ico'),
);
G::$G['VIEW']['_siteClass'] = '';

// login redirection vars
G::$G['VIEW']['_URI'] = isset($_POST['_URI']) ? $_POST['_URI'] : $_SERVER['REQUEST_URI'];
G::$G['VIEW']['_Lbl'] = isset($_POST['_Lbl']) ? $_POST['_Lbl'] : 'to the page you requested';

/** **************************************************************************
 * /Settings for the View
 ****************************************************************************/
