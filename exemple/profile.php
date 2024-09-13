<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

require_once __DIR__ . '/config.php';

use VSR\Extend\Analysis;

echo "Start add 1000 requests:" . PHP_EOL;

$start = microtime(true);
$avgCount = 0;
$avgTime = 0;
for ($i = 0; $i < 1000; $i++) {
    $request = new Analysis\Request("test-" . ($i % 25), false);
    $_SERVER['REQUEST_METHOD'] = $i % 2 ? 'GET' : 'POST';
    $_SERVER['REQUEST_URI'] = '/test-' . ($i % 25);

    # Example of use
    for ($j = 1; $j <= 100 * ($i % 10); $j++) {
        $request->start('aaa');
        $request->stop();

        $request->start('bbb');
        $request->start('ccc-1');
        $request->stop();
        $request->start('ccc-2');
        $request->stop();
        $request->stop();
    }

    $start_save_avg = microtime(true);
    $request->save();
    $time_save_avg = microtime(true) - $start_save_avg;

    $avgTime = (($avgTime * $avgCount) + $time_save_avg) / ($avgCount + 1);
    $avgCount++;

    unset($request, $drive);
    echo "- " . $i . ', time: ' . $time_save_avg . PHP_EOL;
}

echo 'End' . PHP_EOL;
echo '- Memory: ' . (memory_get_peak_usage() / 1024 / 1024) . PHP_EOL;
echo '- Avg time: ' . $avgTime . PHP_EOL;
echo '- Total time: ' . (microtime(true) - $start) . PHP_EOL;
