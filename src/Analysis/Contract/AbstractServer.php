<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

namespace VSR\Extend\Analysis\Contract;

use VSR\Extend\Analysis;

abstract class AbstractServer
{
    abstract public static function execute();

    /**
     * @param array{
     *     uptime:int,
     *     cpu:float,
     *     thr_total:int,
     *     thr_running:int,
     *     thr_sleeping:int,
     *     thr_stopped:int,
     *     thr_zombie:int,
     *     mem_total:float,
     *     mem_free:float,
     *     mem_used:float,
     *     mem_cache:float,
     *     swa_total:float,
     *     swa_free:float,
     *     swa_used:float,
     *     swa_cache:float,
     *     disk_total:float,
     *     disk_free:float,
     *     disk_used:float
     * }|false $normalized
     */
    protected static function process($normalized)
    {
        if (!$normalized) {
            return false;
        }

        $valid = false;
        $data = [];

        $keys = [
            'uptime',
            'cpu',
            'thr_total',
            'thr_running',
            'thr_sleeping',
            'thr_stopped',
            'thr_zombie',
            'mem_total',
            'mem_free',
            'mem_used',
            'mem_cache',
            'swa_total',
            'swa_free',
            'swa_used',
            'swa_cache',
            'disk_total',
            'disk_free',
            'disk_used'
        ];
        foreach ($keys as $key) {
            $data[$key] = !empty($normalized[$key]) ? $normalized[$key] : null;
            $valid = $valid || null !== $data[$key];
        }
        unset($keys, $normalized);

        if (!$valid) {
            return false;
        }

        return Analysis::getDriver()->processServer($data);
    }
}
