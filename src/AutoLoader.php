<?php
/**
 * AutoLoader
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   Cris Bettis <apt142@gmail.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 *
 * @see      http://jes.st/2011/phpunit-bootstrap-and-autoloading-classes/
 */

namespace Stationer\Graphite;

/**
 * AutoLoader
 *
 * Caches a directory list and then uses that list to auto include files as
 * necessary.
 *
 * @package  Stationer\Graphite
 * @author   Cris Bettis <apt142@gmail.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */
class AutoLoader {
    /** @var array Registry of known class names */
    protected static $classNames = [];

    /** @var \RedisUtility */
    protected static $Cache;

    /**
     * Fetch the current class map
     *
     * @return array
     */
    public static function getClassMap() {
        return static::$classNames;
    }

    /**
     * Index the file path based on the file name minus the .php extension.
     *
     * The intent is to cache a list of file/location pairs so it doesn't need
     * to search the directory repeatedly.
     *
     * @param bool $rebuild If true, don't use existing registry file
     *
     * @return void
     */
    public static function registerDirectory($rebuild = false) {
        // Attempt to load cached class registry from file or redis
        if (!$rebuild) {
            $output = static::getRegistryCache();
        }

        if (isset($output) && is_array($output)) {
            static::$classNames = $output;
        } else {
            static::$classNames = [];
            // Grab the items in reverse so the first in the array overwrites at
            // the end if there is a conflict.
            $dirs = array_reverse(explode(';', G::$G['includePath']));
            foreach ($dirs as $dir) {
                $output = static::getDirListing($dir);
                foreach ($output as $file) {
                    static::addFile($file, true);
                }
            }
            // Rebuild dependency data
            static::rebuildDependencyTree();
            // Attempt to save cached class registry
            static::setRegistryCache();
        }
    }

    /**
     * Loop over the class map and fill in dependency data for extenders and implementers
     *
     * @return void
     */
    protected static function rebuildDependencyTree() {
        foreach (static::$classNames as $class => $classDetails) {
            if (!empty($classDetails['extends'])) {
                static::$classNames[ltrim($classDetails['extends'], '\\')]['extenders'][] = $class;
            }
            if (!empty($classDetails['implements'])) {
                foreach ($classDetails['implements'] as $implement) {
                    static::$classNames[ltrim($implement, '\\')]['implementers'][] = $class;
                }
            }
        }
        foreach (static::$classNames as $class => &$classDetails) {
            if (!empty($classDetails['extenders'])) {
                $classDetails['extenders'] = array_unique($classDetails['extenders']);
            }
            if (!empty($classDetails['implementers'])) {
                $classDetails['implementers'] = array_unique($classDetails['implementers']);
            }
        }
    }

    /**
     * Scan directory for classes to include
     *
     * @param string $dir The directory to scan
     *
     * @return array List of class files found
     */
    public static function getDirListing($dir) {
        // Clean up path and prepare to prepend it to each result
        $dir = realpath(SITE.$dir).DIRECTORY_SEPARATOR;
        // Any missing paths will translate as root
        if ('/' == $dir) {
            return [];
        }
        $output = [];
        foreach (scandir($dir) as $path) {
            // Only scan directories expected to have classes
            if (!in_array($path, ['controllers', 'lib', 'models', 'reports'])) {
                continue;
            }
            $output = array_merge($output, self::findPhpFiles($dir.$path.DIRECTORY_SEPARATOR));
        }

        return $output;
    }

    /**
     * Recursive wrapper to scandir that returns absolute paths of php files.
     *
     * @param string $dir The directory to scan
     *
     * @return array List of class files found
     */
    public static function findPhpFiles($dir) {
        // Clean up path and prepare to prepend it to each result
        $dir = realpath($dir).DIRECTORY_SEPARATOR;
        // If directory doesn't exist, fail
        if ('/' == $dir) {
            trigger_error("Specified path $dir not found.  Did you forget to prepend your webroot?");

            return [];
        }
        // convert return values of scandir() to full paths
        $files  = array_map(function ($val) use ($dir) {
            return $dir.$val;
        }, scandir($dir));
        $output = [];
        while (!empty($files)) {
            $file = array_shift($files);
            if (in_array(basename($file), ['.', '..']) || '.' == basename($file)[0]) {
                continue;
            }
            if ('.' !== $file[0] && '/.' !== substr($file, 0, 2) && is_dir($file) && is_readable($file)) {
                // Add full paths of subdirectories to the list of paths to scan
                $files = array_merge($files, array_map(function ($val) use ($file) {
                    return $file.DIRECTORY_SEPARATOR.$val;
                }, scandir($file)));
            } elseif ('.php' == substr($file, -4)) {
                // Add php files to the list of paths to return
                $output[] = $file;
            }
        }

        return $output;
    }

    /**
     * Adds a path after the initial init.
     *
     * @param string $path      Path to include
     * @param bool   $overwrite Optional. Flags to overwrite existing.
     *
     * @return void
     */
    public static function addDirectory($path, $overwrite = false) {
        $output = self::findPhpFiles($path);
        foreach ($output as $file) {
            static::addFile($file, $overwrite);
        }
        // Rebuild dependency data
        static::rebuildDependencyTree();
    }

    /**
     * Adds a single file after the initial init.
     *
     * @param string $file      File to include
     * @param bool   $overwrite Optional. Flags to overwrite existing.
     *
     * @return void
     */
    public static function addFile($file, $overwrite = false) {
        $newClasses = static::findClasses($file);
        foreach ($newClasses as $class) {
            // If it is already registered don't overwrite
            if (true == $overwrite || !isset(static::$classNames[$class['name']])) {
                static::$classNames[$class['name']] = [
                    'name'         => $class['name'],
                    'basename'     => $class['basename'],
                    'path'         => str_replace(SITE, '', $file),
                    'extends'      => $class['extends'],
                    'implements'   => $class['implements'],
                    'extenders'    => [],
                    'implementers' => [],
                ];
            }
        }
    }

    /**
     * Gets the path of the classname passed into it.
     *
     * @param string $className Name of class to fetch
     *
     * @return null
     */
    public static function getClass($className) {
        if (isset(static::$classNames[$className])) {
            return static::$classNames[$className];
        }

        return null;
    }

    /**
     * Removes a registered class.
     *
     * @param string $className Class name to remove
     *
     * @return void
     */
    public static function removeClass($className) {
        if (isset(static::$classNames[$className])) {
            unset(static::$classNames[$className]);
        }
    }

    /**
     * Locates a class and loads it.
     *
     * @param string $className Class you are trying to load.
     *
     * @return void
     */
    public static function loadClass($className) {
        // If the classname is registered, load it.
        if (isset(static::$classNames[$className])
            && is_array(static::$classNames[$className])
            && isset(static::$classNames[$className]['path'])
            && file_exists(SITE.static::$classNames[$className]['path'])
        ) {
            requireFileOnce(SITE.static::$classNames[$className]['path']);

            return;
        }
        // If the classname is registered, load it.
        if (isset(static::$classNames[$className])
            && is_string(static::$classNames[$className])
            && file_exists(static::$classNames[$className])
        ) {
            requireFileOnce(static::$classNames[$className]);

            return;
        }
    }

    /**
     * Adds a class and file to the lookup table
     *
     * @param string $className Class you are trying to load.
     * @param string $path      File containing class
     *
     * @return void
     */
    public static function addClass($className, $path) {
        static::$classNames[$className] = $path;
    }

    /**
     * Generate a key that is distinct to the current VHost/Server pair
     *
     * @return string Key name for AutoLoad Cache
     */
    private static function getCacheKey() {
        return static::class.'_'.gethostname().'_'.$_SERVER['SERVER_NAME'];
    }

    /**
     * Attempt to load cached class registry
     *
     * @TODO: Move RedisUtility into Graphite
     *
     * @return array|null
     */
    private static function getRegistryCache() {
        if (file_exists(SITE.'/classMap.php')) {
            $output = include SITE.'/classMap.php';
            if (is_array($output)) {
                return $output;
            }
        }

        /** @var \RedisUtility $Cache */
        try {
            if (!class_exists(\RedisUtility::class)) {
                throw new \Exception();
            }
            self::$Cache = new \RedisUtility();
            if ($Cache->exists(static::getCacheKey())) {
                $output = self::$Cache->get(static::getCacheKey());
            }
        } catch (\Exception $e) {
            self::$Cache = null;
        } catch (\Error $e) {
            self::$Cache = null;
        }

        return $output ?? null;
    }

    /**
     * Stub for storing registry cache
     *
     * @return void
     */
    private static function setRegistryCache() {
        // Attempt to save cached class registry, if the $Cache is available
        self::$Cache && self::$Cache->set(static::getCacheKey(), static::$classNames, 3600);
    }

    /**
     * Checks whether the specified classname is in a known namespace
     *
     * @param string $className The fully qualified classname to check
     *
     * @return bool True if the namespace is registered in G::$G['namespaces']
     */
    public static function isNamespaceKnown(string $className) {
        // Test if class is global
        if (false === strpos($className, '\\')) {
            return true;
        }

        foreach (G::$G['namespaces'] as $namespace) {
            if ('' != trim($namespace, '\\') && 0 === strpos(trim($className, '\\'), trim($namespace, '\\'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze a file and find classes and dependencies.
     * Code derived from composer's class map generator.
     *
     * @param string $path File to analyze
     *
     * @return array Analysis of Class(es) in specified file.
     */
    private static function findClasses(string $path) {
        // Use @ here instead of Silencer to actively suppress 'unhelpful' output
        // @link https://github.com/composer/composer/pull/4886
        $contents = @php_strip_whitespace($path);
        if (!$contents) {
            if (!file_exists($path)) {
                $message = 'File at "%s" does not exist, check your classmap definitions';
            } elseif (!is_readable($path)) {
                $message = 'File at "%s" is not readable, check its permissions';
            } elseif ('' === trim(file_get_contents($path))) {
                // The input file was really empty and thus contains no classes
                return [];
            } else {
                $message = 'File at "%s" could not be parsed as PHP, it may be binary or corrupted';
            }
            $error = error_get_last();
            if (isset($error['message'])) {
                $message .= PHP_EOL.'The following message may be helpful:'.PHP_EOL.$error['message'];
            }
            throw new \RuntimeException(sprintf($message, $path));
        }

        // return early if there is no chance of matching anything in this file
        if (!preg_match('{\b(?:class|interface|trait)\s}i', $contents)) {
            return [];
        }

        // strip heredocs/nowdocs
        $contents = preg_replace('{<<<[ \t]*([\'"]?)(\w+)\\1(?:\r\n|\n|\r)(?:.*?)(?:\r\n|\n|\r)(?:\s*)\\2(?=\s+|[;,.)])}s',
            'null', $contents);
        // strip strings
        $contents = preg_replace('{"[^"\\\\]*+(\\\\.[^"\\\\]*+)*+"|\'[^\'\\\\]*+(\\\\.[^\'\\\\]*+)*+\'}s', 'null',
            $contents);
        // strip leading non-php code if needed
        if (substr($contents, 0, 2) !== '<?') {
            $contents = preg_replace('{^.+?<\?}s', '<?', $contents, 1, $replacements);
            if ($replacements === 0) {
                return [];
            }
        }
        // strip non-php blocks in the file
        $contents = preg_replace('{\?>(?:[^<]++|<(?!\?))*+<\?}s', '?><?', $contents);
        // strip trailing non-php code if needed
        $pos = strrpos($contents, '?>');
        if (false !== $pos && false === strpos(substr($contents, $pos), '<?')) {
            $contents = substr($contents, 0, $pos);
        }
        // strip comments if short open tags are in the file
        if (preg_match('{(<\?)(?!(php|hh))}i', $contents)) {
            $contents = preg_replace('{//.* | /\*(?:[^*]++|\*(?!/))*\*/}x', '', $contents);
        }
        preg_match_all('{
            (?:
                 \b(?<![\$:>])(?P<type>class|interface|trait) \s++ (?P<name>[a-zA-Z_\x7f-\xff:][a-zA-Z0-9_\x7f-\xff:\-]*+)
                 (?:\s+extends\s+(?P<extends>[\\\\a-zA-Z_\x7f-\xff:][\\\\a-zA-Z0-9_\x7f-\xff:\-]*+))?
                 (?:\s+implements\s+(?:(?P<implements>[\\\\a-zA-Z_\x7f-\xff:][\\\\a-zA-Z0-9_\x7f-\xff:\-]*+)(?:,\s*)?)+)?
               | \b(?<![\$:>])(?P<ns>namespace) \s++ (?P<nsname>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\s*+\\\\\s*+[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+)? \s*+ [\{;]
               | \b(?<![\$:>])(?P<use>use) \s++ (?P<usename>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\s*+\\\\\s*+[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+) (?: \s++ as \s++ (?P<usealias>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))? \s*+;
            )
        }ix', $contents, $matches);

        $classes   = [];
        $namespace = '';
        $uses      = [];

        for ($i = 0, $len = count($matches['type']); $i < $len; $i++) {
            if (!empty($matches['ns'][$i])) {
                $namespace = str_replace([' ', "\t", "\r", "\n"], '', $matches['nsname'][$i]).'\\';
            } elseif (!empty($matches['use'][$i])) {
                $use             = str_replace([' ', "\t", "\r", "\n"], '', $matches['usename'][$i]);
                $usealias        = !empty($matches['usealias'][$i])
                    ? $matches['usealias'][$i]
                    : substr($matches['usename'][$i], strrpos($matches['usename'][$i], '\\') + 1);
                $uses[$usealias] = $use;
            } else {
                $name = trim($matches['name'][$i]);
                // skip anon classes extending/implementing
                if ($name === 'extends' || $name === 'implements') {
                    continue;
                }
                // If the extended class does not have an explicit namespace, infer it
                if (!empty($matches['extends'][$i])) {
                    $extends = $matches['extends'][$i];
                    if ('\\' !== $extends[0]) {
                        if (isset($uses[$extends])) {
                            $extends = $uses[$extends];
                        } else {
                            $extends = ltrim($namespace.$extends, '\\');
                        }
                    }
                    $extends = trim($extends);
                } else {
                    $extends = '';
                }
                if (!empty($matches['implements'][$i])) {
                    $implements = empty($matches['implements'][$i])
                        ? []
                        : array_map('trim', explode(',', $matches['implements'][$i]));
                    foreach ($implements as &$implement) {
                        if ('\\' !== $implement[0]) {
                            if (isset($uses[$implement])) {
                                $implement = $uses[$implement];
                            } else {
                                $implement = ltrim($namespace.$implement, '\\');
                            }
                        }
                        $implement = trim($implement);
                    }
                } else {
                    $implements = [];
                }

                $classes[] = [
                    'name'       => ltrim($namespace.$name, '\\'),
                    'basename'   => $name,
                    'extends'    => $extends,
                    'implements' => $implements,
                    'uses'       => $uses,
                    'matches'    => array_map('array_filter', $matches),
                ];
            }
        }

        return $classes;
    }

    /**
     * Return names of classes that extend the given class, one level.
     *
     * @param string $className Fully qualified class name to seek
     *
     * @return mixed|null
     */
    public static function getExtendersOf(string $className) {
        if (isset(static::$classNames[$className]['extenders'])) {
            return static::$classNames[$className]['extenders'];
        }

        return null;
    }

    /**
     * Return instances of classes that extend the given class, one level.
     *
     * @param string $className Fully qualified interface name to seek
     *
     * @return mixed|null
     */
    public static function instantiateExtendersOf(string $className, ...$args) {
        $classes = static::getExtendersOf($className);
        if (empty($classes)) {
            return null;
        }
        $return = [];
        foreach ($classes as $class) {
            if (static::canInstantiate($class)) {
                $return[$class] = G::build($class, ...$args);
            }
        }
    }

    /**
     * Return names of classes that implement the given interface, one level.
     *
     * @param string $className Fully qualified interface name to seek
     *
     * @return mixed|null
     */
    public static function getImplementersOf(string $className) {
        if (isset(static::$classNames[$className]['implementers'])) {
            return static::$classNames[$className]['implementers'];
        }

        return null;
    }

    /**
     * Return instances of classes that implement the given interface, one level.
     *
     * @param string $className Fully qualified interface name to seek
     *
     * @return mixed|null
     */
    public static function instantiateImplementersOf(string $className, ...$args) {
        $classes = static::getImplementersOf($className);
        if (empty($classes)) {
            return null;
        }
        $return = [];
        foreach ($classes as $class) {
            if (static::canInstantiate($class)) {
                $return[$class] = G::build($class, ...$args);
            }
        }

        return $return;
    }

    /**
     * Determines whether a specified class can be instantiated
     *
     * @param string $className Class name to check
     *
     * @return bool
     */
    public static function canInstantiate(string $className) {
        static::loadClass($className);
        if (!class_exists($className)) {
            return false;
        }
        $class = new \ReflectionClass($className);
        if ($class->isAbstract()) {
            return false;
        }

        return true;
    }
}

/**
 * Load a file within an empty scope
 *
 * @param string $path Filesystem path to require
 *
 * @return mixed
 */
function requireFileOnce($path) {
    return require_once $path;
}
