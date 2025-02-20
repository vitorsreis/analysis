<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

namespace VSR\Extend\Analysis;

use Exception;
use Throwable;
use VSR\Extend\Analysis;

class Request
{
    protected $key;

    protected $parent_id = [];

    protected $profile = [];

    protected $profileCount = 0;

    protected $error = [];

    protected $extra = [];

    /**
     * @var callable
     */
    protected $beforeSave;

    /**
     * @param string $key Request key
     * @param array $options Options
     */
    public function __construct($key, $options = ['autoSave' => true, 'autoError' => true])
    {
        $this->key = $key;
        $this->start('profile');
        !empty($options['autoError']) && set_error_handler([$this, 'error'], E_ALL);
        !empty($options['autoError']) && set_exception_handler([$this, 'error']);
        !empty($options['autoSave']) && register_shutdown_function([$this, 'save']);
    }

    /**
     * Profile start, level up
     * @param string $key Profile key
     * @return static
     */
    public function start($key)
    {
        $index = count($this->profile);
        $parent_id = !$this->parent_id ? -1 : end($this->parent_id);

        $this->profile[$index] = [
            'parent_id' => $parent_id,
            'key' => $key,
            'start' => microtime(true),
            'end' => null,
            'duration' => null,
            'extra' => null,
            'error' => []
        ];
        $this->profileCount++;
        $this->parent_id[] = $index;

        return $this;
    }

    /**
     * Profile stop, level down
     * @param mixed $extra Extra info about profile
     * @return static
     */
    public function stop($extra = null)
    {
        if (empty($this->parent_id)) {
            return $this;
        }

        $index = array_pop($this->parent_id);
        $this->profile[$index]['extra'] = $extra;
        $this->profile[$index]['end'] = $end = microtime(true);
        $this->profile[$index]['duration'] = $end - $this->profile[$index]['start'];

        if (!$this->parent_id) {
            $this->parent_id[] = $index; # Prevent empty
        }

        return $this;
    }

    /**
     * Add error to current activity
     * @param int|Exception $error
     * @param string $message
     * @param string $file
     * @param int $line
     * @return $this
     */
    public function error($error = null, $message = null, $file = null, $line = null)
    {
        if (is_object($error)) {
            $e = $error;
            $error = $e->getCode();
            $message = $e->getMessage();
            $file = $e->getFile();
            $line = $e->getLine();
        }

        $error = [
            'code' => $error,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ];

        $this->error[] = $error;
        $this->profile[count($this->profile) - 1]['error'][] = $error;
        return $this;
    }

    /**
     * Extra info about request
     * @param mixed $extra
     * @return $this
     */
    public function extra($extra)
    {
        $this->extra[] = $extra;
        return $this;
    }

    /**
     * @param callable $callback Return false to cancel save, or return data to save
     * @return $this
     */
    public function onBeforeSave($callback)
    {
        $this->beforeSave = $callback;
        return $this;
    }

    /**
     * @return int|false Request ID
     */
    public function save()
    {
        # Stop all
        do {
            $this->stop();
        } while (count($this->parent_id) > 1);

        $request = [
            'key' => $this->key,
            'start' => $this->profile[0]['start'],
            'end' => $this->profile[0]['end'],
            'duration' => $this->profile[0]['end'] - $this->profile[0]['start'],

            'http_code' => !empty(http_response_code()) ? http_response_code() : null,
            'method' => !empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null,
            'url' => rtrim((!empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''), '/') ?: null,
            'ip' => $this->getIP(),
            'referer' => !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
            'useragent' => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,

            'get' => $_GET ?: null,
            'post' => $_POST ?: null,
            'raw_post' => file_get_contents('php://input') ?: null,
            'files' => $_FILES ?: null,
            'cookies' => $_COOKIE ?: null,
            'server' => $_SERVER ?: null,
            'headers' => headers_list() ?: null,
            'inc_files' => get_included_files() ?: null,

            'memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 3),
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 3),
            'error' => $this->error ?: null,
            'extra' => $this->extra ?: null,

            'profile' => $this->profile,
            'profile_count' => $this->profileCount
        ];

        try {
            $request = isset($this->beforeSave) && is_callable($this->beforeSave)
                ? call_user_func($this->beforeSave, $request)
                : $request;
        } catch (Exception $e) {
            $this->error($e);
        } catch (Throwable $e) {
            $this->error($e);
        }

        try {
            if (!$request) {
                return false;
            }

            # Save request
            return Analysis::getDriver()->processRequest($request);
        } finally {
            unset($request);
        }
    }

    private function getIP()
    {
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return null;
    }
}
