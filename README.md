# VSR Analysis

[![Latest Stable Version](https://img.shields.io/packagist/v/vitorsreis/analysis?style=flat-square&label=stable&color=2E9DD3)](https://packagist.org/packages/vitorsreis/analysis)
[![PHP Version Required](https://img.shields.io/packagist/dependency-v/vitorsreis/analysis/php?style=flat-square&color=777BB3)](https://packagist.org/packages/vitorsreis/analysis)
[![License](https://img.shields.io/packagist/l/vitorsreis/analysis?style=flat-square&color=418677)](https://github.com/vitorsreis/analysis/blob/main/LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/vitorsreis/analysis?style=flat-square&color=0476B7)](https://packagist.org/packages/vitorsreis/analysis)
[![Repo Stars](https://img.shields.io/github/stars/vitorsreis/analysis?style=social)](https://github.com/vitorsreis/analysis)

Simple and powerful monitor/profiler for PHP applications, featuring an interactive and real-time dashboard. Easily
track performance, errors, and resource usage for your scripts and APIs.

# TODO ADD BANNER IMAGE

---

## Features

- Real-time interactive dashboard for profiling and monitoring
- Supports PHP 5.6+
- Tracks execution time, memory usage, errors, and custom actions
- Pluggable storage (SQLite + JSON by default)
- Easy integration with any PHP project (web or CLI)
- Automatic error and exception logging
- Extensible and customizable

---

## Requirements

- PHP >= 5.6
- PDO SQLite extension enabled
- Composer for installation

---

## Installation

```bash
composer require vitorsreis/analysis
```

---

## Quick Start

###### Profile example (e.g., index.php)

```php
require_once 'vendor/autoload.php';

$storage = new VSR\Analysis\Storage\SQLite(__DIR__ . '/storage/db.sqlite');

$profiler = new VSR\Analysis\Profiler('route-or-script');
$profiler->setStorage($storage);

$profiler->start('controller/action');
// ... code ...
$profiler->stop();

$profiler->save();
```

###### View example (e.g., viewer.php)

```php
require_once 'vendor/autoload.php';

$storage = new VSR\Analysis\Storage\SQLite(__DIR__ . '/storage/db.sqlite');

$viewer = new VSR\Analysis\Viewer();
$viewer->setStorage($storage);
$viewer->execute();
```

---

### Navigation

- [Profiler Documentation](docs/Profiler.md)
- [Viewer Documentation](docs/Viewer.md)
- [Server Documentation](docs/Server.md)
- [Contributing Guidelines](CONTRIBUTING.md)
- [License (MIT)](LICENSE)
- [Support / Issues](https://github.com/vitorsreis/analysis/issues)
