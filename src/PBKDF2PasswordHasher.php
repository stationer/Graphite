<?php
/**
 * PBKDF2PasswordHasher - PBKDF2 Password Hashing plugin
 * File : /src/PBKDF2PasswordHasher.php
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
 * PBKDF2PasswordHasher class - PBKDF2 Password Hashing plugin
 *
 * @package  Stationer\Graphite
 * @author   LoneFry <dev@lonefry.com>
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 * @see      /src/PasswordHasher.php
 */
class PBKDF2PasswordHasher implements IPasswordHasher {
    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {
    }

    /**
     * Create a hashword using the PBKDF2 and assemble its parameters
     *
     * @param string $password password to hash
     *
     * @return string hashed password for storage
     */
    public static function hash_password($password) {
        /** @var $algo */
        /** @var $iterations */
        /** @var $salt_len */
        /** @var $hash_len */
        /** @var $sections */
        extract(G::$G['SEC']['PBKDF2']);
        if (function_exists('mcrypt_create_iv')) {
            $salt = mcrypt_create_iv($salt_len, MCRYPT_DEV_URANDOM);
        } else {
            $salt = openssl_random_pseudo_bytes($salt_len);
        }
        $salt = base64_encode($salt);
        $hash = self::PBKDF2($algo, $password, $salt, $iterations, $hash_len);
        $hash = $algo.':'.$iterations.':'.$salt.':'.base64_encode($hash);

        return $hash;
    }

    /**
     * Test a password against a recalled PBKDF2
     *
     * @param string $password password to test
     * @param string $hash     PBKDF2 from database
     *
     * @return bool true if password passes, false if not
     */
    public static function test_password($password, $hash) {
        $sections = array_flip(G::$G['SEC']['PBKDF2']['sections']);
        $parts    = explode(":", $hash);
        if (count($sections) > count($parts)) {
            return false;
        }
        $pbkdf2 = base64_decode($parts[$sections['PBKDF2']]);
        $test   = self::PBKDF2($parts[$sections['algo']], $password,
            $parts[$sections['salt']], (int)$parts[$sections['iterations']],
            strlen($pbkdf2));

        return $pbkdf2 == $test;
    }

    /**
     * Test a hash is PBKDF2
     *
     * @param string $hash PBKDF2 from database
     *
     * @return bool true if argument passes as PBKDF2 string, false if not
     */
    public static function is_hash($hash) {
        $sections = G::$G['SEC']['PBKDF2']['sections'];
        $parts    = explode(":", $hash);

        return count($sections) == count($parts);
    }

    /**
     * Create a derived key using the Password-Based Key Derivation Function 2
     *
     * @param string $algo        hashing algorithm to use
     * @param string $password    password to hash
     * @param string $salt        salt to add to hash
     * @param int    $iterations  number of times to hash
     * @param int    $hash_length desired length of derived key in octets
     *
     * @return string hashed password for storage
     * @throws Exception
     *
     */
    public static function PBKDF2($algo, $password, $salt, $iterations, $hash_length) {
        $algo = strtolower($algo);
        if (!in_array($algo, hash_algos(), true)) {
            throw new Exception('Hash algorithm not supported: '.$algo);
        }
        if (0 >= $iterations) {
            throw new Exception('PBKDF2() Must perform at least 1 iteration!');
        }
        if (0 >= $hash_length) {
            throw new Exception('PBKDF2() Hash length must be at least 1!');
        }

        $blocks      = ceil($hash_length / strlen(hash($algo, '', true)));
        $derived_key = "";
        for ($i = 1; $i <= $blocks; $i++) {
            $prev  = $salt.pack("N", $i);
            $block = $prev = hash_hmac($algo, $prev, $password, true);
            for ($j = 1; $j < $iterations; $j++) {
                $block ^= ($prev = hash_hmac($algo, $prev, $password, true));
            }
            $derived_key .= $block;
        }

        return substr($derived_key, 0, $hash_length);
    }
}
