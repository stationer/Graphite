<?php
/**
 * Password Hashing
 * File : /src/PasswordHasher.php
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite;

/**
 * IPasswordHasher interface - Interface for Password Hashing plugins
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */
interface IPasswordHasher {
    /**
     * Create a hashword for storage from provided password
     *
     * @param string $password password to hash
     *
     * @return string hashed password for storage
     */
    public static function hash_password($password);

    /**
     * Test a password against provided hash
     *
     * @param string $password password to test
     * @param string $hash     test against this
     *
     * @return string hashed password for storage
     */
    public static function test_password($password, $hash);

    /**
     * Test a hash is supported hash
     *
     * @param string $hash mystery hash from database
     *
     * @return bool true if argument passes any type, false if not
     */
    public static function is_hash($hash);
}

/**
 * PasswordHasher class - Wrapper for Password Hashing plugins
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */
class PasswordHasher implements IPasswordHasher {
    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {
    }

    /**
     * Create a hashword using the configured function
     *
     * @param string $password password to hash
     *
     * @return string hashed password for storage
     */
    public static function hash_password($password) {
        // if no hasher is configured, or it is not defined, or it is this class
        // just use a simple sha1 hash
        if (!is_array(G::$G['SEC']['hash_class'])
            || !isset(G::$G['SEC']['hash_class'][0])
            || !class_exists(G::$G['SEC']['hash_class'][0])
            || !in_array(IPasswordHasher::class,
                class_implements(G::$G['SEC']['hash_class'][0]))
            || get_class() == G::$G['SEC']['hash_class'][0]
        ) {
            G::$G['SEC']['hash_class'][0] = SHA1PasswordHasher::class;
        }
        $class = G::$G['SEC']['hash_class'][0];

        return $class::hash_password($password);
    }

    /**
     * Test a password using the configured functions
     * to preserve backwards compatability in systems that change hasher
     *
     * @param string $password password to test
     * @param string $hash     test against this
     *
     * @return string hashed password for storage
     */
    public static function test_password($password, $hash) {
        // if no hasher is configured, fail
        if (!is_array(G::$G['SEC']['hash_class'])) {
            return false;
        }
        foreach (G::$G['SEC']['hash_class'] as $class) {
            if (class_exists(G::$G['SEC']['hash_class'][0])
                && in_array(IPasswordHasher::class,
                    class_implements(G::$G['SEC']['hash_class'][0]))
                && get_class() != G::$G['SEC']['hash_class'][0]
                && $class::test_password($password, $hash)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test a hash is supported hash
     *
     * @param string $hash mystery hash from database
     *
     * @return bool true if argument passes any type, false if not
     */
    public static function is_hash($hash) {
        // if no hasher is configured, fail
        if (!is_array(G::$G['SEC']['hash_class'])) {
            return false;
        }
        foreach (G::$G['SEC']['hash_class'] as $class) {
            if (class_exists(G::$G['SEC']['hash_class'][0])
                && in_array(IPasswordHasher::class,
                    class_implements(G::$G['SEC']['hash_class'][0]))
                && get_class() != G::$G['SEC']['hash_class'][0]
                && $class::is_hash($hash)
            ) {
                return true;
            }
        }

        return false;
    }
}
