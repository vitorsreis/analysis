<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

# Constants
const DIR_ROOT = __DIR__;
const DIR_STORAGE = DIR_ROOT . '/storage';

# Create storage directory
if (!is_dir(DIR_STORAGE)) {
    mkdir(DIR_STORAGE, 0777, true);
}

# Autoload
require_once __DIR__ . '/../vendor/autoload.php';

use VSR\Extend\Analysis;

# Set driver
$drive = new Analysis\Driver\Standard(DIR_STORAGE);
$drive->install();

Analysis::setDriver($drive);
