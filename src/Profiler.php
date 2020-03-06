<?php
/**
 * Profiler - Stores runtime checkpoint metrics
 * File : /src/Profiler.php
 *
 * PHP version 7.0
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */

namespace Stationer\Graphite;

/**
 * Profiler class - Stores runtime checkpoint metrics
 *
 * @package  Stationer\Graphite
 * @author   Tyler Uebele
 * @license  MIT https://github.com/stationer/Graphite/blob/master/LICENSE
 * @link     https://github.com/stationer/Graphite
 */
class Profiler {
    /** @var Profiler $instance */
    private static $instance = null;

    protected $checkpoints = [];

    const DEFAULT_LABEL = '_Profiler';

    /**
     * Create and return singleton instance
     *
     * @param int $startTime Time for initial default mark
     *
     * @return Profiler
     */
    public static function getInstance($startTime = null) {
        if (null === self::$instance) {
            self::$instance = new static($startTime);
        }

        return self::$instance;
    }

    /**
     * Private constructor to prevent instantiation
     *
     * @param int $startTime Time for initial default mark
     */
    private function __construct($startTime = null) {
        // Start default label
        $this->mark(self::DEFAULT_LABEL);

        if (null !== $startTime) {
            $this->checkpoints[''][1]['time'] = $startTime;
        }
    }

    /**
     * Traces the code
     *
     * @return string
     */
    protected function trace() {
        // __FILE__:__LINE__(__METHOD__)
        $trace = debug_backtrace();
        for ($i = 0; $i < count($trace); $i++) {
            if (!isset($trace[$i + 1]) || !isset($trace[$i + 1]['class']) || get_class() != $trace[$i + 1]['class']) {
                break;
            }
        }
        $location = $trace[$i]['file'].':'.$trace[$i]['line']
            .'('.((isset($trace[$i + 1]['class']) ? $trace[$i + 1]['class'].$trace[$i + 1]['type'] : '')
                .(isset($trace[$i + 1]['function']) ? $trace[$i + 1]['function'] : '')).')';

        return $location;
    }

    /**
     * @param string $label Label to mark
     *
     * @return array Mark
     */
    public function mark($label = self::DEFAULT_LABEL) {
        $time     = microtime(true);
        $memory   = memory_get_usage();
        $location = $this->trace();

        if (!isset($this->checkpoints[$label])) {
            // Create tracking label
            $this->checkpoints[$label] = [0 => ['timer' => 0]];
            $duration                  = 0;
        } else {
            // Calculate time since last label mark
            $duration = $time - $this->checkpoints[$label][count($this->checkpoints[$label]) - 1]['time'];
        }

        // Add new label mark
        $mark                        = compact('time', 'memory', 'location', 'duration');
        $this->checkpoints[$label][] = $mark;

        return $mark;
    }

    /**
     * Marks a label, then adds its duration to the mark's total time
     *
     * @param string $label Label to mark
     *
     * @return array Mark
     */
    public function stop($label = self::DEFAULT_LABEL) {
        $mark                                  = $this->mark($label);
        $this->checkpoints[$label][0]['timer'] += $mark['duration'];

        return $mark;
    }

    /**
     * @return array Log of checkpoints
     */
    public function get() {
        // Mark default label
        $this->mark(self::DEFAULT_LABEL);

        return $this->checkpoints;
    }
}
