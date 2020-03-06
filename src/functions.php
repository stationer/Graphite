<?php
/**
 * Useful functions to use
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

/**
 * Fetch raw HTTP request headers
 * Cache the result for successive calls to avoid manifest issues with successive calls.
 *
 * @return string Full representation of HTTP request headers
 */
function php_getRawInputHeader() {
    static $output = '';
    if ('' == $output && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            $output .= $k.': '.$v."\n";
        }
    }

    return $output;
}

/**
 * Fetch raw HTTP request body
 * Cache the result for successive calls to avoid manifest issues with successive calls.
 *
 * @return string Full representation of HTTP request body
 */
function php_getRawInputBody() {
    static $output = '';
    if ('' == $output) {
        $output = file_get_contents('php://input', null, null, 0);
    }

    return $output;
}

/**
 * Fetch raw HTTP request
 *
 * @return string Full representation of HTTP request headers and body
 */
function php_getRawInput() {
    return php_getRawInputHeader()."\n".php_getRawInputBody();
}

/**
 * Updates a variable in a url
 *
 * @param string $url      URL to add the variable to
 * @param string $variable Variable in the query string to alter
 * @param mixed  $value    Value to set the query string to
 *
 * @return string
 */
function updateQueryString($url, $variable, $value) {
    $baseUrl = $url;
    $query   = [];

    if (strpos($url, '?') !== false) {
        $parts       = explode('?', $url, 2);
        $baseUrl     = reset($parts);
        $queryString = end($parts);

        parse_str($queryString, $query);
    }

    $query[$variable] = $value;

    return $baseUrl.'?'.http_build_query($query);
}

/**
 * A shorthand for the frequent
 *   `isset($var) ? $var : '';`
 * statements that salt our codebase.  Used as
 *   `ifset($var)`
 *
 * @param mixed $test    Value to test and return if set.
 * @param mixed $default Value to return if $test is empty
 *
 * @return mixed
 */
function ifset(&$test, $default = null) {
    return isset($test) ? $test : $default;
}


/**
 * Compares an array (supports multidimensional) to a other versions and merges differences
 *
 * @param array $base       Base array before changes were made
 * @param array ...$patches Other arrays to compare with
 *
 * @return array $result Array containing the merged differences
 */
function array_patch(array $base, array ...$patches) {
    // Initialize result array to base array
    $result = [] + $base;

    // Loop over patch arrays and patch the result with each
    foreach ($patches as $patch) {
        // Merge things result which are absent from base
        foreach ($patch as $key => $value) {
            if (!isset($base[$key])) {
                $result[$key] = $patch[$key];
            }
        }

        foreach ($base as $key => $value) {
            // remove things from result which are missing from patch
            if (!isset($patch[$key])) {
                unset($result[$key]);
                continue;
            }
            // Skip items which are unchanged
            // soft equals to ignore types and allow unordered array compare
            if ($patch[$key] == $base[$key]) {
                continue;
            }
            // Merge things into result which are different from base in patch
            // If both values are arrays, use recusion
            if (is_array($patch[$key]) && is_array($base[$key])) {
                $result[$key] = array_patch($base[$key], $patch[$key]);
                continue;
            }
            // If either value is scalar, merge the patch value into the result
            $result[$key] = $patch[$key];
        }
    }

    return $result;
}

/**
 * Determine if the specified array contains all the specified keys
 *
 * @param array $keys   Keys to seek
 * @param array $search Array to seek in
 *
 * @return bool
 */
function array_keys_exist($keys, $search) {
    // If we were passed a single key, use existing function
    if (!is_array($keys)) {
        return array_key_exists($keys, $search);
    }
    // If there are no keys in $search that are not in $keys
    // We have all the keys
    return [] == array_diff($keys, array_keys($search));
}


/**
 * Searches through array to find positive integer values
 *
 * @param array $data Array of data being processed
 *
 * @return array Array of integers
 */
function array_filter_ids(array $data) {
    // Array to be returned
    $newData = [];

    // Validates that each id is an integer
    foreach ($data as $id) {
        // Verifies that $val is an integer or a string representation of one
        if (is_numeric($id) && filter_var($id, FILTER_VALIDATE_INT)) {
            // Validate that the integer is positive
            $id = (int)$id;
            if ($id >= 0) {
                $newData[] = $id;
            }
        }
    }

    return $newData;
}

/**
 * Dumps an array in a single line for brief output
 *
 * @param mixed $a Array to dump
 *
 * @return string Array values
 */
function array_value_dump($a) {
    if (is_array($a)) {
        return '['.implode(', ', array_map(__FUNCTION__, $a)).']';
    } elseif (is_string($a)) {
        return "'".$a."'";
    } elseif (is_bool($a)) {
        return ($a ? 'true' : 'false');
    } elseif (is_null($a)) {
        return 'null';
    } else {
        return $a;
    }
}

/**
 * Helper for brevity in templates - echo html escaped string
 *
 * @param string $s String to output
 *
 * @return void
 */
function html($s) {
    echo htmlspecialchars($s, ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE);
}

/**
 * Capture the output of var_dump() and return it as a string
 *
 * @param mixed $s value to dump
 *
 * @return string
 */
function ob_var_dump($s) {
    ob_start();
    // @codingStandardsIgnoreStart
    var_dump($s);

    // @codingStandardsIgnoreEnd

    return ob_get_clean();
}

/**
 * Emit invocation info, and passed value
 *
 * @param mixed $value     Value to var_dump
 * @param int   $openDepth How deep to open the details
 *
 * @return void
 */
function croak($value = null, $openDepth = 99) {
    $debug = debug_backtrace();
    echo '<details class="G__croak"'.(0 < $openDepth ? ' open="open"' : '').'>'
        .'<summary class="G__croak_info"><b>'.__METHOD__.'()</b> called'
        .(isset($debug[1])
            ? ' in <b>'.(isset($debug[1]['class'])
                ? $debug[1]['class'].$debug[1]['type']
                : ''
            ).$debug[1]['function'].'()</b>'
            : '')
        .' at <b>'.$debug[0]['file'].':'.$debug[0]['line'].'</b></summary>'
        .'<hr><pre class="G__croak_value">';
    croak_sub($value, $openDepth - 1);
    echo '</pre></details>';
}

/**
 * Helper for croak
 * Sets arrays into <details>/<summary> tags, recursively
 *
 * @param mixed $value     Value to dump out
 * @param int   $openDepth How deep to open the details
 */
function croak_sub($value, $openDepth = 99) {
    if (is_array($value)) {
        // For arrays, replicate the output of var_dump, but add <details> collapsing and recursion
        echo '<details', (0 < $openDepth ? ' open="open"' : ''), '>'
        , '<summary>array(', count($value), ') {</summary><div style="padding-left: 2em;">';
        foreach ($value as $key => $val) {
            if (!is_int($key)) {
                $key = '"'.$key.'"';
            }
            echo '[', $key, "]=>\n";
            croak_sub($val, $openDepth - 1);
        }
        echo "</div></details>}\n";
    } elseif (is_object($value)) {
        // For objects, capture var_dump output and use the first line as the <summary> for a <details>
        [$key, $val] = explode("\n", ob_var_dump($value), 2);
        echo '<details', (0 < $openDepth ? ' open=\"open\"' : ''), '><summary>', $key, '</summary><div>'
        , htmlspecialchars(rtrim(rtrim($val), '}'))
        , "</div></details>}\n";
    } else {
        // For everything else, capture var_dump output and escape it with htmlspecialchars()
        echo htmlspecialchars(ob_var_dump($value));
    }
}

/**
 * Convert array to CSV
 *
 * @param array $array   Array of arrays to convert to CSV
 * @param array $headers Array of headers, if blank, assume first arrays are associative
 *
 * @return mixed
 */
function array_to_csv(array $array, array $headers = null) {
    $headers = $headers ?? array_keys((array)reset($array));

    ob_start();
    $file = fopen("php://output", 'w');
    fputcsv($file, $headers);
    foreach ($array as $row) {
        if (!is_array($row) && is_object($row)) {
            if (method_exists($row, 'getAll')) {
                $row = $row->getAll();
            } elseif (method_exists($row, 'toArray')) {
                $row = $row->toArray();
            } else {
                $row = (array)$row;
            }
        }
        $row = array_values($row);
        fputcsv($file, $row);
    }
    fclose($file);

    return ob_get_clean();
}

/**
 * Set header() if headers not already sent (avoid the warning)
 *
 * @param string $thisHeader         Header string
 * @param bool   $replace            If true, should replace a previous similar header
 * @param int    $http_response_code Force the HTTP response code
 *
 * @return void
 */
function header_if_not_sent($thisHeader, bool $replace = true, int $http_response_code = null) {
    headers_sent() || header($thisHeader, $replace, $http_response_code);
}
