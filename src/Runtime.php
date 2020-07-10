<?php
/**
 * Runtime - Main runtime object
 * File : /src/Runtime.php
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
use Stationer\Graphite\data\mysqli_;

require_once __DIR__.'/functions.php';

/**
 * Runtime class - Graphite's root invoker
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */
class Runtime {
    /** @var Runtime $instance */
    private static $instance = null;
    /** @var Profiler $Profiler */
    public $Profiler;

    /**
     * Create and return singleton instance
     *
     * @return static
     */
    public static function getInstance() {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {
        // Define the start time and start the Profiler
        defined('NOW') or define('NOW', microtime(true));
        $this->Profiler = Profiler::getInstance(NOW);
    }

    /**
     * Prepare and invoke the Controller and View
     *
     * @return void
     */
    public function main() {
        $this->init();

        $this->Profiler->mark('Controller');
        G::$C->Act();
        $this->Profiler->stop('Controller');

        $this->Profiler->mark('Clean-up');
        G::close();
        $this->Profiler->stop('Clean-up');

        $this->Profiler->mark('View');
        G::$V->preoutput();
        G::$V->output();
    }

    /**
     * Initialize all Graphite configs
     *
     * @return void
     */
    public function init() {
        $this->Profiler->mark('init');
        $this->init_core();
        $this->init_mysqli();
        $this->init_Security();
        $this->init_modules();
        G::$C = new Dispatcher(G::$G['CON']);
        G::$V = new View(G::$G['VIEW']);
        $this->Profiler->stop('init');
    }

    /**
     * Initialize all Graphite configs
     *
     * @return void
     */
    public function init_core() {
        $this->Profiler->mark(__METHOD__);

        // the root of this website
        define('SITE', $this->guessSiteRoot());
        $this->generateServerDefauts();

        $this->loadConfigs();

        G::$Factory = new Factory();
        DataBroker::setDict(G::$G['db']['ProviderDict']);
        Localizer::setLanguage(G::$G['language']);

        // controls a few things that assist dev
        define('MODE', G::$G['MODE'] ?? 'prd');
        if ('dev' == MODE) {
            error_reporting(E_ALL | E_STRICT);
        }
        if (isset(G::$G['timezone'])) {
            date_default_timezone_set(G::$G['timezone']);
        }
        define('VERSION', G::$G['VERSION']);
        define('G_DB_TABL', G::$G['db']['tabl']);
    }

    /**
     * Initialize Mysqli connection
     *
     * @return void
     */
    public function init_mysqli() {
        // if no DB host was specified, don't load DB or DB-based Security
        if ('' == G::$G['db']['host']) {
            return;
        }

        $this->Profiler->mark(__METHOD__);
        // setup DB connection or fail.
        G::$m = G::$M = new mysqli_(G::$G['db']['host'],
            G::$G['db']['user'],
            G::$G['db']['pass'],
            G::$G['db']['name'],
            null,
            null,
            G::$G['db']['tabl'],
            G::$G['db']['log'],
            G::$G['db']['sparse'] ?? false);

        if (!empty(G::$G['db']['ro'])
            && !empty(G::$G['db']['ro']['user'])
        ) {
            G::$m = new mysqli_(G::$G['db']['ro']['host'],
                G::$G['db']['ro']['user'],
                G::$G['db']['ro']['pass'],
                G::$G['db']['ro']['name'],
                null,
                null,
                G::$G['db']['tabl'],
                G::$G['db']['log'],
                G::$G['db']['ro']['sparse'] ?? G::$G['db']['sparse'] ?? false);
            if (mysqli_connect_error()) {
                G::$m = G::$M;
            } else {
                G::$m->readonly = true;
            }
        }

        // If we could not connect to database, display appropriate error
        if (!G::$M->isOpen()) {
            G::msg('Could not connect to read/write database!', 'error');
            if (!G::$m->isOpen()) {
                G::msg('Could not connect to read-only database!', 'error');
                G::$G['CON']['path'] = G::$G['CON']['controller500'].'/500';
            } else {
                G::msg('Site operating in read-only mode.', 'error');
                G::$M = G::$m;
            }
        }
        $this->Profiler->stop(__METHOD__);
    }

    /**
     * Initialize Security module, authenticate user
     *
     * @return void
     */
    public function init_Security() {
        $this->Profiler->mark(__METHOD__);
        // If DB is not connected, don't load DB-based Security
        if (!is_null(G::$M) && !G::$M->isOpen()) {
            return;
        }

        G::$S = new Security();
        if (G::$S->Login && 1 == G::$S->Login->flagChangePass
            && (!isset(G::$G['CON']['path'])
                || 'account/logout' != strtolower(trim(G::$G['CON']['path'], '/')))
        ) {
            G::msg('You must change your password before you can continue.');
            G::$G['CON']['path'] = 'Account/edit';
        }
        $this->Profiler->stop(__METHOD__);
    }

    /**
     * Load per-application includeme.php files
     *
     * @return void
     */
    public function init_modules() {
        $this->Profiler->mark(__METHOD__);

        if (isset(G::$G['includePath'])) {
            foreach (explode(';', G::$G['includePath']) as $v) {
                $s = realpath(SITE.$v.'/includeme.php');
                if (false !== strpos($s, SITE.$v) && file_exists($s)
                    && $s != __FILE__
                ) {
                    require_once $s;
                }
            }
        }
        $this->Profiler->stop(__METHOD__);
    }

    /**
     * Create default values for missing $_SERVER variables
     * This is most useful for running scripts from CLI with `php -f`
     *
     * @return void
     */
    public function generateServerDefauts() {
        if (!isset($_SERVER['SERVER_NAME'])) {
            $paths = explode('/', SITE);
            $_SERVER['SERVER_NAME'] = array_pop($paths);
        }
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = __FILE__;
        }
        if (!isset($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
        define('G_REMOTE_ADDR', $_SERVER['REMOTE_ADDR']);
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }
    }

    /**
     * Look at our current directory and guess the webroot path
     *
     * @return string Probable directory of webroot
     */
    public function guessSiteRoot() {
        $paths = explode('/', __DIR__);

        if ('/var/www/vhosts/' == substr(__DIR__, 0, 16)) {
            // Assume we're in /var/www/vhosts/[domain]
            $root = implode('/', array_slice($paths, 0, 5));
        } elseif ('/mnt/vhosts/' == substr(__DIR__, 0, 12)) {
            // Assume we're in /mnt/vhosts/[domain]
            $root = implode('/', array_slice($paths, 0, 4));
        } elseif ('/home/' == substr(__DIR__, 0, 6)) {
            // Assume we're in /home/[user]/[domain]
            $root = implode('/', array_slice($paths, 0, 4));
        } else {
            // Assume we're in a composer vendor dir, pick parent of vendor directory
            $root = dirname(substr(__DIR__, 0, strpos(__DIR__, '/stationer/graphite/')));
        }

        return $root;
    }

    /**
     * Load configs
     *
     * @return void
     */
    public function loadConfigs() {
        // Load the initial Graphite config
        require_once __DIR__.'/config.php';
        // Load the site config to get the include path
        $this->loadSiteConfigs();
        // Load module configs from that include path
        $this->loadModuleConfigs();
        // Load the site config again to support overriding module configs
        $this->loadSiteConfigs();
    }

    /**
     * Per-Domain Settings for multi-domain sites
     *  If you are not hosting a site on multiple domains, you can cautiously
     *  use this file as your only configuration file
     * We'll check for two files
     *  1. 'secrets.' which should not be in your repo, and contains credentials
     *  2. 'config.' which could be in your repo, and contains general configs
     * We'll check two places
     *  1. [webroot]/../siteConfigs/ which houses config files out of webroot
     *  2. [webroot] which is webroot
     * We'll check two versions of the current domain
     *  1. The SERVER_NAME according to $_SERVER['SERVER_NAME']
     *  2. The directory name of [webroot], applicable in most vhosting setups
     *
     * @return void
     */
    public function loadSiteConfigs() {
        $tmppath = explode('/', SITE);
        foreach (['secrets.', 'config.'] as $tmpfile) {
            foreach ([$_SERVER['SERVER_NAME'], end($tmppath)] as $tmpdomain) {
                if (file_exists(dirname(SITE).'/siteConfigs/'.$tmpfile.$tmpdomain.'.php')) {
                    include dirname(SITE).'/siteConfigs/'.$tmpfile.$tmpdomain.'.php';
                    continue 2;
                } elseif (file_exists(SITE.'/'.$tmpfile.$tmpdomain.'.php')) {
                    include SITE.'/'.$tmpfile.$tmpdomain.'.php';
                    continue 2;
                }
            }
        }
    }

    /**
     * Per-Application Default Settings
     *  Check each includePath directory for a config
     *  Each application config should limit itself to G::$G[APPNAME]
     *
     * @return void
     */
    public function loadModuleConfigs() {
        foreach (explode(';', G::$G['includePath']) as $path) {
            $path = trim($path, '/');
            if (file_exists(SITE.'/'.$path.'/config.php')) {
                include_once SITE.'/'.$path.'/config.php';
            }
        }
    }
}
