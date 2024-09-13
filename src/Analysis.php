<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

namespace VSR\Extend;

use RuntimeException;
use VSR\Extend\Analysis\Contract\AbstractDriver;

class Analysis
{
    /**
     * @var AbstractDriver
     */
    private static $model;

    /**
     * @return AbstractDriver
     */
    public static function getDriver()
    {
        if (!isset(static::$model)) {
            throw new RuntimeException('Driver not defined');
        }
        return static::$model;
    }

    public static function setDriver(AbstractDriver $model)
    {
        static::$model = $model;
    }
}
