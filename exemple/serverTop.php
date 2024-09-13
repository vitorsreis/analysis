<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

# Use the following command to monitor the server:
# | top -b -H -n1 -p0
#
# MODE 1 (recommended)
# | 1. Create file "<directory>/cron.sh" with the following content:
# |    #!/bin/bash
# |    DIR="$(cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd)"
# |    for ((i = 1; i <= 60; i++)); do
# |      top -b -H -n1 -p0 | php "$DIR/serverWithTop.php"
# |      sleep 1
# |    done
# | 2. Add cronjob:
# |    * * * * * /bin/bash <directory>/cron.sh
# | 3. Capture input data:
# |    $input = file_get_contents('php://stdin');
#
# MODE 2
# | 1. Add cronjob:
# |    * * * * * top -b -H -n1 -p0 | php <directory>/serverWithTop.php
# | 2. Capture input data:
# |    $input = shell_exec('top -b -H -n1 -p0');
# |    exec('top -b -H -n1 -p0', $input);

require_once __DIR__ . '/config.php';

use VSR\Extend\Analysis;

echo "Start add 1000 server reports:" . PHP_EOL;

$start = microtime(true);
$avgCount = 0;
$avgTime = 0;
for ($i = 0; $i < 1000; $i++) {
    $now = time() - strtotime("2023-01-01 00:00:00");
    $hour = date('H:i:s', $now);
    $uptime_days = floor($now / 86400);
    $uptime_hours = floor(($now % 86400) / 3600);
    $cpu = rand(0, 1000) / 10;
    $thr_total = rand(0, 1000);
    $thr_running = rand(0, 1000 - $thr_total);
    $thr_sleeping = rand(0, 1000 - $thr_total - $thr_running);
    $thr_stopped = rand(0, 1000 - $thr_total - $thr_running - $thr_sleeping);
    $thr_zombie = rand(0, 1000 - $thr_total - $thr_running - $thr_sleeping - $thr_stopped);
    $mem_total = 16 * 1024;
    $mem_free = rand(0, $mem_total);
    $mem_used = rand(0, $mem_total - $mem_free);
    $mem_cache = rand(0, $mem_total - $mem_free - $mem_used);
    $swa_total = 3 * 1024;
    $swa_free = rand(0, $swa_total);
    $swa_used = rand(0, $swa_total - $swa_free);
    $swa_cache = rand(0, $swa_total - $swa_free - $swa_used);

    $input = "
top - $hour up $uptime_days days, $uptime_hours min,  1 users,  load average: 0.00, 0.01, 0.00
Threads: $thr_total total, $thr_running running, $thr_sleeping sleeping, $thr_stopped stopped, $thr_zombie zombie
%Cpu(s): $cpu us,  0.0 sy,  0.0 ni,100.0 id,  0.0 wa,  0.0 hi,  0.0 si,  0.0 st
MiB Mem : $mem_total total, $mem_free free, $mem_used used, $mem_cache buff/cache
MiB Swap: $swa_total total, $swa_free free, $swa_used used. $swa_cache avail Mem 

    PID USER      PR  NI    VIRT    RES    SHR S  %CPU  %MEM     TIME+ COMMAND
4105821 d5whub    20   0   52152   3876   3448 R   0.0   0.1   0:00.00 top
";

    $start_save_avg = microtime(true);
    Analysis\Server\Top::execute($input);
    $time_save_avg = microtime(true) - $start_save_avg;

    $avgTime = (($avgTime * $avgCount) + $time_save_avg) / ($avgCount + 1);
    $avgCount++;

    unset($request, $drive);
    echo "- " . $i . ', time: ' . (microtime(true) - $start_save_avg) . PHP_EOL;

    echo "Waiting 1 second";
    for ($w = 0; $w < 3; $w++) {
        usleep(1000000 / 3);
        echo ".";
    }
    echo "\r";
}

echo "OK" . PHP_EOL;
echo '- Memory: ' . (memory_get_peak_usage() / 1024 / 1024) . PHP_EOL;
echo '- Avg time: ' . $avgTime . PHP_EOL;
echo '- Total time: ' . (microtime(true) - $start) . PHP_EOL;
