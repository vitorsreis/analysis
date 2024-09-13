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

    usleep(100000 * (rand(0, 12)));

    # Example of use
    $request->start('aaa');
    usleep(100000 * (rand(0, 12)));
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    $request->stop();

    $request->start('bbb');
    usleep(100000 * (rand(0, 12)));
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    $request->stop();

    # Example of use multi level
    $request->start("bbb-lvl-1");
    usleep(100000 * (rand(0, 12)));
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    $request->start("bbb-lvl-2");
    usleep(100000 * (rand(0, 12)));
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    $request->start("bbb-lvl-3");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->start("bbb-lvl-4");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->start("bbb-lvl-5");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->stop(); // bbb-lvl-5
    $request->start("bbb-lvl-5");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->stop(); // bbb-lvl-5
    $request->start("bbb-lvl-5");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->stop(); // bbb-lvl-5
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    $request->stop(); // bbb-lvl-5
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->stop(); // bbb-lvl-4
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->stop(); // bbb-lvl-3
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->stop(); // bbb-lvl-2
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->stop(); // bbb-lvl-1

    $request->start("ccc-lvl-1");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->start("ccc-lvl-2");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->start("ccc-lvl-3");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->start("ccc-lvl-4");

    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->start("ccc-lvl-5");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));
    $request->stop(); // ccc-lvl-5
    $request->start("ccc-lvl-5");
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));

    $request->stop(); // ccc-lvl-5
    $request->stop(); // ccc-lvl-4
    $request->stop(); // ccc-lvl-3
    $request->stop(); // ccc-lvl-2
    $request->stop(); // ccc-lvl-1
    rand(0, 10) === 5 && $request->error(new Exception("Error test", 1));
    usleep(100000 * (rand(0, 12)));

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
