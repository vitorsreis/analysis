# Analysis and Profile of Requests/Server for PHP

[![Latest Stable Version](https://img.shields.io/packagist/v/vitorsreis/extend-analysis?style=flat-square&label=stable&color=2E9DD3)](https://packagist.org/packages/vitorsreis/extend-analysis)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/vitorsreis/extend-analysis/php?style=flat-square&color=777BB3)](https://packagist.org/packages/vitorsreis/extend-analysis)
[![License](https://img.shields.io/packagist/l/vitorsreis/extend-analysis?style=flat-square&color=418677)](https://github.com/vitorsreis/extend-analysis/blob/master/LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/vitorsreis/extend-analysis?style=flat-square&color=0476B7)](https://packagist.org/packages/vitorsreis/extend-analysis)
[![Repo Stars](https://img.shields.io/github/stars/vitorsreis/extend-analysis?style=social)](https://github.com/vitorsreis/extend-analysis)

Simple and powerful monitor of requests/server with interactive and realtime dashboard for a simple analysis and manual
profile.
Unit tests have passed on versions: ```5.6```, ```7.4```, ```8.1```, ```8.2``` and  ```8.3```

---

## Install

```bash
composer require vitorsreis/extend-analysis
```

## Request Monitor

#### • Simple start usage

```php
use VSR\Extend\Analysis;

# Create driver
$driver = new Analysis\Driver\Standard(__DIR__);

# Set driver
Analysis::setDriver($driver);

# @param bool $autoSave [optional] Save automatically on shutdown event
#                       default: true, if false, you need call $requestProfile->save() manually
global $profile;
$profile = new Analysis\Request();
```

#### • Adding action to profile tree

Is recommended to use try/catch/finally for capture errors, however Error\Exception is captured automatically.
Use at strategic points in the code to better build your tree, e.g. caller middleware, model proxy, ...

```php
global $profile;
try {
    $profile->start(/* profile_name */); # up level, start action monitor
    // your code
} catch (Throwable $e) {
    $profile->error($e); # register error in current level
    // your code
} finally {
    $extra = ...; // [optional] extra info about action
    $profile->stop($extra || null); # down level, end action monitor
}
```

#### • Adding extra info about request

```php
$requestProfile->extra(...);
```

#### • Capture before save

You can capture the request before save and cancel it if necessary or remove some data.
Use "return false" to cancel save.

```php
global $requestProfile;
$requestProfile->onBeforeSave(function (array $request) {
    # Toleration of 1000 actions, 300ms of duration and not error
    if ($request['profile_count'] < 1000 && $request['duration'] < .300 && !$request['error']) {
        # Remove debug fields to save space in database
        $fields = [
            'referer',
            'useragent',
            'get',
            'post',
            'raw_post',
            'files',
            'cookies',
            'server',
            'headers', 
            'inc_files',
            'error',
            'extra',
            'profile'
        ];
        foreach ($fields as $field) {
            $request[$field] = null;
        }
    }
    return $request;
});
```

# Server Monitor
Exemple of usage in [examples/serverTop.php](examples/serverTop.php)
