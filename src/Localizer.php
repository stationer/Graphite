<?php
/**
 * Localizer
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   Cris Bettis <apt142@gmail.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite;

/**
 * Localizer
 *
 * Interface for translating values into a local accepted unit/language.
 *
 * @package  Stationer\Graphite
 * @author   Cris Bettis <apt142@gmail.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */
class Localizer {
    /** @var string Current language to do includes off of. */
    private static $language = 'en_us';

    /** @var array  Array of Language keys to reference. */
    private static $langKeys = [];

    /**
     * Sets the language to be used from here on out.
     *
     * @param string $lang Language to start using.
     *
     * @return void
     */
    public static function setLanguage($lang) {
        self::$language = $lang;
        self::$langKeys = [];
        self::loadLib('Global');
    }

    /**
     * Imports the requested language library
     *
     * @param string $libName Library name to import
     *
     * @return void
     */
    public static function loadLib($libName) {
        $dirs = array_reverse(explode(';', G::$G['includePath']));
        foreach ($dirs as $dir) {
            $filename = SITE.$dir.'/lang/'.self::$language.'/'.$libName.'.php';
            if (file_exists($filename)) {
                $newKeys        = include $filename;
                self::$langKeys = array_merge($newKeys, self::$langKeys);
            }
        }
    }

    /**
     * Translates a key into a language specific string.
     * This function can take additional parameters to fill in specific details.
     *
     * @param string $langKey Language Key to look up.
     *
     * @return string
     */
    public static function translate($langKey) {
        $args = func_get_args();
        array_shift($args);

        if (isset(self::$langKeys[$langKey])) {
            $rawCopy = self::$langKeys[$langKey];
        } else {
            $rawCopy = $langKey;
        }

        return vsprintf($rawCopy, $args);
    }
}
