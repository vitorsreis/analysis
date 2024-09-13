<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

namespace VSR\Extend\Analysis\Server;

use InvalidArgumentException;
use VSR\Extend\Analysis\Contract\AbstractServer;

class Top extends AbstractServer
{
    private static $pattern = "
        ~
            top\s+-\s+(?<hour>[\d:]+)\s+up\s+(?:(?<up_days>\d+)\s+days?,\s+)?(?:(?<up_hour>[\d:]+),\s+)?(?:(?<up_min>\d+)\s+min,\s+)?(?<users>\d+)\s+user(?:s)?(?:,\s+load\s+average:\s+(?<la1>[\d.]+),\s+(?<la2>[\d.]+),\s+(?<la3>[\d.]+))?.*
            (?:Threads|Tasks):\s+(?<thr_total>\d+)\s+total,\s+(?<thr_running>\d+)\s+running,\s+(?<thr_sleeping>\d+)\s+sleeping,\s+(?<thr_stopped>\d+)\s+stopped,\s+(?<thr_zombie>\d+)\s+zombie\s+
            %Cpu\(s\):\s+(?<cpu>[\d.]+)\s+us(?:,\s+(?<cpu_sy>[\d.]+)\s+sy,\s+(?<cpu_ni>[\d.]+)\s+ni,\s+(?<cpu_id>[\d.]+)\s+id,\s+(?<cpu_wa>[\d.]+)\s+wa,\s+(?<cpu_hi>[\d.]+)\s+hi,\s+(?<cpu_si>[\d.]+)\s+si,\s+(?<cpu_st>[\d.]+)\s+st)?.*
            [MK]iB\s+Mem\s+:\s+(?<mem_total>[\d.]+)\s+total,\s+(?<mem_free>[\d.]+)\s+free,\s+(?<mem_used>[\d.]+)\s+used(?:,\s+(?<mem_cache>[\d.]+)\s+buff\s+)?.*
            [MK]iB\s+Swap:\s+(?<swa_total>[\d.]+)\s+total,\s+(?<swa_free>[\d.]+)\s+free,\s+(?<swa_used>[\d.]+)\s+used(?:\.\s+(?<swa_cache>[\d.]+)\s+avail)?.*
        ~xs
    ";

    public static function execute($input = null)
    {
        if (null === $input) {
            throw new InvalidArgumentException('Input is required');
        }

        if (!preg_match(static::$pattern, $input, $matches)) {
            return false;
        }

        $uptime = 0;
        isset($matches['up_days']) && $uptime += intval($matches['up_days']) * 24 * 60;
        isset($matches['up_hour']) && $uptime += intval($matches['up_hour']) * 60;
        isset($matches['up_min']) && $uptime += intval($matches['up_min']);

        $disk_total = (float)number_format(disk_total_space('/') / 1024 / 1024 / 1024, 3, '.', '');
        $disk_free = (float)number_format(disk_free_space('/') / 1024 / 1024 / 1024, 3, '.', '');
        $disk_used = (float)number_format($disk_total - $disk_free, 3, '.', '');

        return static::process([
            'uptime' => $uptime,
            'cpu' => (float)$matches['cpu'],
            'thr_total' => (int)$matches['thr_total'],
            'thr_running' => (int)$matches['thr_running'],
            'thr_sleeping' => (int)$matches['thr_sleeping'],
            'thr_stopped' => (int)$matches['thr_stopped'],
            'thr_zombie' => (int)$matches['thr_zombie'],
            'mem_total' => (float)$matches['mem_total'],
            'mem_free' => (float)$matches['mem_free'],
            'mem_used' => (float)$matches['mem_used'],
            'mem_cache' => (float)$matches['mem_cache'],
            'swa_total' => (float)$matches['swa_total'],
            'swa_free' => (float)$matches['swa_free'],
            'swa_used' => (float)$matches['swa_used'],
            'swa_cache' => (float)$matches['swa_cache'],
            'disk_total' => $disk_total,
            'disk_free' => $disk_free,
            'disk_used' => $disk_used
        ]);
    }
}
