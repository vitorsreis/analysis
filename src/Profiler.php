<?php

/**
 * This file is part of the VSR Analysis.
 */

namespace VSR\Analysis;

use Exception;
use InvalidArgumentException;
use VSR\Analysis\Storage\StorageInterface;

/**
 * Profiler for PHP applications: tracks execution time, memory usage, errors, and custom actions.
 *
 * @package VSR\Analysis
 * @link https://github.com/vitorsreis/analysis/tree/main/docs/Profiler.md Documentation
 *
 * @psalm-type TAutoSaveHook bool|"on-shutdown"|"on-destruct"
 *
 * @psalm-type TOptions array{
 *     "auto-start"?: bool,
 *     "auto-save"?: TAutoSaveHook,
 *     "auto-error"?: bool,
 *     "storage"?: StorageInterface|null
 * }|null
 *
 * @psalm-type TEntry array{
 *     identifier: non-empty-string,
 *     group: string|null,
 *     entry_parent_id?: int,
 *     start: float,
 *     duration: float,
 *     memory_peak: int
 * }
 *
 * @psalm-type TErrorEntry array{
 *     entry_parent_id: int,
 *     severity: int,
 *     message: string,
 *     file: string,
 *     line: int
 * }
 *
 * @psalm-type TExtraEntry array{
 *     entry_parent_id: int,
 *     value: mixed
 * }
 *
 * @psalm-type TData array{
 *     // required fields
 *     identifier: non-empty-string,
 *     group: string|null,
 *     start: float,
 *     duration: float,
 *     method: string,
 *     url: string,
 *     memory_peak: int,
 *
 *     // optional fields
 *     status?: int,
 *     headers?: array<string, string>,
 *
 *     ip?: string,
 *     referer?: string,
 *     user_agent?: string,
 *     query?: array,
 *     body?: array,
 *     raw_body?: string,
 *     cookies?: array,
 *     files?: array,
 *     server?: array,
 *     inc_files?: list<string>,
 *     extensions?: list<string>,
 *
 *     entries?: list<TEntry>,
 *     entries_count?: int,
 *
 *     error?: list<TErrorEntry>,
 *     extra?: list<TExtraEntry>
 * }
 *
 * @psalm-type TSaveCallback callable(TData $data): TData
 *
 * @psalm-type TErrorCallback callable(\Throwable $e): void
 *
 * @phpcs:disable PSR12.Properties.ConstantVisibility.NotFound
 */
class Profiler
{
    /**
     * Option to automatically start the profile on construction. Default is `true`.
     * - `true`: Automatically start the profile.
     * - `false`: Do not start the profile automatically.
     */
    const AUTO_START = 'auto-start';

    /**
     * Option to automatically save the profile.
     *
     * Default is `false`.
     * - `true`: Enable autosave with `Profiler::AUTO_SAVE_ON_SHUTDOWN`.
     * - `Profiler::AUTO_SAVE_ON_SHUTDOWN`: Register a shutdown function to save the profile when the script ends.
     * - `Profiler::AUTO_SAVE_ON_DESTRUCT`: Save the profile when the object is destructed.
     * - `false`: Do not enable auto save.
     */
    const AUTO_SAVE = 'auto-save';

    /**
     * Value for `AUTO_SAVE` to register a shutdown function to save the profile when the script ends.
     */
    const AUTO_SAVE_ON_SHUTDOWN = 'on-shutdown';

    /**
     * Value for `AUTO_SAVE` to save the profile when the object is destructed.
     */
    const AUTO_SAVE_ON_DESTRUCT = 'on-destruct';

    /**
     * Option to automatically record errors and exceptions in the profile. Default is `false`.
     * - `true`: Enable automatic error and exception recording.
     * - `false`: Do not enable auto error.
     */
    const AUTO_ERROR = 'auto-error';

    /**
     * Alias to `$profile->setStorage($storage)`.
     */
    const SET_STORAGE = 'storage';

    /**
     * @var non-empty-string $identifier The metrics are grouped based on this identifier.
     */
    protected $identifier;

    /**
     * @var string|null $group Optional group name for the entry.
     */
    protected $group;

    /**
     * @var TOptions $options
     */
    protected $options = [];

    /**
     * @var StorageInterface $storage The storage adaptor
     */
    protected $storage = null;

    /**
     * @var list<TEntry> $entries List of profile entries.
     */
    protected $entries = [];

    /**
     * @var int $entries_count Total number of profile entries.
     */
    protected $entries_count = 0;

    /**
     * @var list<int> $entry_parent_id Stack of parent profile IDs.
     */
    protected $entry_parent_id = [];

    /**
     * @var list<TErrorEntry> $error Errors
     */
    protected $error = [];

    /**
     * @var list<TExtraEntry> $extra Extra data
     */
    protected $extra = [];

    /**
     * @var TSaveCallback|null $onSaveCallback Callback to modify data before saving or cancel saving.
     */
    protected $onSaveCallback = null;

    /**
     * @param non-empty-string $identifier The metrics are grouped based on this identifier.
     *
     * @param string $group Optional group name for the profile.
     *
     * @param TOptions $options Configuration options.
     *
     * - `Profiler::AUTO_START`: Automatically start the profile on construction. Default is `true`.
     *   - `true`: Automatically start the profile.
     *   - `false`: Do not start the profile automatically.
     *
     * - `Profiler::AUTO_SAVE`: Automatically save the profile. Default is `false`.
     *   - `true`: Enable autosave with `Profiler::AUTO_SAVE_ON_SHUTDOWN`.
     *   - `Profiler::AUTO_SAVE_ON_SHUTDOWN`: Register a shutdown function to save the profile when the script ends.
     *   - `Profiler::AUTO_SAVE_ON_DESTRUCT`: Save the profile when the object is destructed.
     *   - `false`: Do not enable auto save.
     *
     * - `Profiler::AUTO_ERROR`: Automatically record errors and exceptions in the profile. Default is `false`.
     *   - `true`: Enable automatic error and exception recording.
     *   - `false`: Do not enable auto error.
     *
     * - `Profiler::SET_STORAGE`: Alias to `$profile->setStorage($storage)`.
     */
    public function __construct($identifier, $group = null, $options = null)
    {
        if (!is_string($identifier) || empty($identifier)) {
            throw new InvalidArgumentException('Profile identifier must be a non-empty string.');
        }

        $this->identifier = $identifier;

        if ($group !== null && !is_string($group)) {
            throw new InvalidArgumentException('Entry group must be a string or null.');
        }

        $this->group = $group;


        if (!is_array($options)) {
            throw new InvalidArgumentException('Hooks must be a boolean or an array.');
        }

        $this->options[self::AUTO_SAVE] = isset($options[self::AUTO_SAVE]) ? $options[self::AUTO_SAVE] : false;
        switch ($this->options[self::AUTO_SAVE]) {
            case true:
                $this->options[self::AUTO_SAVE] = self::AUTO_SAVE_ON_SHUTDOWN;
                break;
            case self::AUTO_SAVE_ON_SHUTDOWN:
            case self::AUTO_SAVE_ON_DESTRUCT:
            case false: // Allowed values
                break;
            default:
                throw new InvalidArgumentException('Invalid value for auto-save hook. Allowed values are true, false, "' . self::AUTO_SAVE_ON_SHUTDOWN . '", "' . self::AUTO_SAVE_ON_DESTRUCT . '".'); // phpcs:ignore
        }

        $this->options[self::AUTO_ERROR] = !empty($options[self::AUTO_ERROR]);

        $this->hookShutdown();

        $this->hookErrors();

        if (isset($options[self::SET_STORAGE])) {
            $this->setStorage($options[self::SET_STORAGE]);
        }

        if (!empty($this->options[self::AUTO_START])) {
            $this->start($this->identifier);
        }
    }

    public function __destruct()
    {
        if ($this->options['auto-save'] === 'on-destruct') {
            @$this->save();
        }
    }

    /**
     * Hook into PHP shutdown to automatically save the profile or capture fatal errors.
     */
    protected function hookShutdown()
    {
        if ($this->options['auto-save'] !== 'on-shutdown' && !$this->options['auto-error']) {
            return;
        }

        register_shutdown_function(function () {
            if ($this->options['auto-error']) {
                $error = error_get_last();
                if ($error !== null) {
                    $this->error($error['type'], $error['message'], $error['file'], $error['line']);
                }
            }

            if ($this->options['auto-save'] === 'on-shutdown') {
                @$this->save();
            }
        });
    }

    /**
     * Hook into PHP error and exception handling to automatically record them.
     */
    protected function hookErrors()
    {
        if (!$this->options['auto-error']) {
            return;
        }

        set_error_handler(function ($severity, $message, $file, $line) {
            /**
             * @var int $severity
             * @var string $message
             * @var string $file
             * @var int $line
             */

            if (error_reporting() === 0 || !(error_reporting() & $severity)) {
                // Silenced error with @ operator or this error code is not included in error_reporting
                return false; // Continue with normal error handling
            }

            $this->error($severity, $message, $file, $line);

            return false; // Continue with normal error handling
        });

        set_exception_handler(function ($e) {
            $this->error($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        });
    }

    /**
     * Set the storage adaptor.
     *
     * @param StorageInterface $storage The storage implementation to use.
     * @return static
     * @throws InvalidArgumentException If the storage does not implement StorageInterface.
     */
    public function setStorage($storage)
    {
        if (!($storage instanceof StorageInterface)) {
            throw new InvalidArgumentException('Adaptor must implement ' . StorageInterface::class . '.');
        }

        $this->storage = $storage;
        return $this;
    }

    /**
     * Start a new profiling entry (action).
     *
     * @param non-empty-string $identifier Unique identifier for the entry (e.g., 'controller/action').
     * @param string|null $group Optional group name for the entry.
     * @return static
     * @throws InvalidArgumentException If identifier is not a non-empty string or group is not string/null.
     */
    public function start($identifier, $group = null)
    {
        if (!is_string($identifier) || empty($identifier)) {
            throw new InvalidArgumentException('Entry identifier must be a non-empty string.');
        }

        if ($group !== null && !is_string($group)) {
            throw new InvalidArgumentException('Entry group must be a string or null.');
        }

        $index = $this->entries_count++;

        $this->entries[$index] = [
            'identifier' => $identifier,
            'group' => $group,
            'entry_parent_id' => $this->getCurrentEntryIndex(),
            'start' => microtime(true),
            'duration' => null
        ];

        $this->entry_parent_id[] = $index;

        return $this;
    }

    /**
     * Stop the current profiling entry.
     *
     * @param mixed $extra Optional extra data to attach to the entry.
     * @return static
     */
    public function stop($extra = null)
    {
        if (empty($this->entry_parent_id)) {
            return $this;
        }

        if ($extra !== null) {
            $this->extra($extra);
        }

        $index = array_pop($this->entry_parent_id);

        $this->entries[$index]['duration'] = microtime(true) - $this->entries[$index]['start'];
        $this->entries[$index]['memory_peak'] = memory_get_peak_usage();

        return $this;
    }

    /**
     * Attach extra data to the current entry.
     *
     * @param mixed $extra The extra data to attach (e.g., array with context info).
     * @return static
     */
    public function extra($extra)
    {
        $this->extra[] = [
            'entry_parent_id' => $this->getCurrentEntryIndex(),
            'value' => $extra
        ];

        return $this;
    }

    /**
     * Record an error or exception in the current entry.
     *
     * @param \Throwable|int $e Exception or error severity code.
     * @param string|null $message Error message (if not using Throwable).
     * @param string|null $file File where the error occurred.
     * @param int|null $line Line number where the error occurred.
     *
     * @return static
     *
     * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
     */
    public function error($e, $message = null, $file = null, $line = null)
    {
        if ($e instanceof Exception || $e instanceof \Throwable) {
            $message = $e->getMessage();
            $file = $e->getFile();
            $line = $e->getLine();
            $severity = $e->getCode();
        } else {
            $severity = (int)$e;
        }

        $this->error[] = [
            'entry_parent_id' => $this->getCurrentEntryIndex(),
            'severity' => $severity,
            'message' => (string)$message,
            'file' => (string)$file,
            'line' => (int)$line
        ];

        return $this;
    }

    /**
     * Get the index of the current entry.
     *
     * @return int The index of the current entry, or -1 if there is no current entry.
     */
    protected function getCurrentEntryIndex()
    {
        return empty($this->entry_parent_id) ? -1 : end($this->entry_parent_id);
    }

    /**
     * Set a callback to modify or cancel data before saving.
     *
     * @param callable $callback Function that receives the data array and returns modified data or null to cancel.
     * @return static
     * @throws InvalidArgumentException If the callback is not callable.
     */
    public function onSave($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('The onSave callback must be callable.');
        }

        $this->onSaveCallback = $callback;

        return $this;
    }

    /**
     * Save the profile data to the configured storage.
     *
     * @param bool $cleanup Whether to clean up the profile data after saving (default: true).
     * @return void
     * @throws \Throwable If saving fails or storage is not set.
     */
    public function save($cleanup = true)
    {
        if ($this->storage === null) {
            throw new InvalidArgumentException('No storage adaptor set. Use setStorage() to set one.');
        }

        if (empty($this->entries)) {
            return; // Nothing to save
        }

        // Ensure all entries are stopped
        while (!empty($this->entry_parent_id)) {
            $this->stop();
        }

        $data = $this->getData();

        if ($cleanup) {
            // Clean up data to free memory
            $this->entries = [];
            $this->entries_count = 0;
            $this->entry_parent_id = [];
            $this->error = [];
            $this->extra = [];
        }

        if (is_callable($this->onSaveCallback)) {
            $result = call_user_func($this->onSaveCallback, $data);

            if (!is_array($result)) {
                return; // Saving was cancelled by the callback
            }

            $data = $result;
        }

        $data = $this->normalizeQueryData($data);

        $this->processAndSaveData($data);
    }

    /**
     * Gather all data to be saved.
     *
     * @return TData The data to be saved.
     */
    protected function getData()
    {
        $url = getcwd() . basename(array_shift($_SERVER['argv']) ?: '');
        $url .= $_SERVER['argv'] ? ' ' . implode(' ', $_SERVER['argv']) : '';

        $data = [
            'identifier' => $this->identifier,
            'group' => $this->group,
            'start' => $this->entries[0]['start'],
            'duration' => $this->entries[0]['duration'],
            'method' => 'CLI',
            'url' => $url,
            'memory_peak' => memory_get_peak_usage(),

            'status' => null,
            'headers' => null,

            'ip' => null,
            'referer' => null,
            'user_agent' => null,
            'query' => null,
            'body' => null,
            'raw_body' => null,
            'cookies' => null,
            'files' => null,
            'server' => array_filter($_SERVER ?: [], static function ($key) {
                return !in_array($key, [
                    'HTTP_CF_CONNECTING_IP',
                    'HTTP_TRUE_CLIENT_IP',
                    'HTTP_X_CLIENT_IP',
                    'HTTP_CLIENT_IP',
                    'HTTP_X_FORWARDED_FOR',
                    'HTTP_X_FORWARDED',
                    'HTTP_FORWARDED_FOR',
                    'HTTP_FORWARDED',
                    'HTTP_X_CLUSTER_CLIENT_IP',
                    'HTTP_X_REAL_IP',
                    'REMOTE_ADDR',
                    'HTTPS',
                    'HTTP_HOST',
                    'HTTP_CONNECTION',
                    'HTTP_CACHE_CONTROL',
                    'HTTP_UPGRADE_INSECURE_REQUESTS',
                    'HTTP_ACCEPT',
                    'HTTP_ACCEPT_ENCODING',
                    'HTTP_ACCEPT_LANGUAGE',
                    'HTTP_REFERER',
                    'HTTP_USER_AGENT',
                    'HTTP_COOKIE',
                    'REQUEST_METHOD',
                    'REQUEST_URI',
                    'QUERY_STRING',
                    'PHP_SELF',
                    'argv',
                    'argc',
                    'SERVER_SOFTWARE',
                    'SERVER_NAME',
                    'SERVER_ADDR',
                    'SERVER_PORT',
                    'REMOTE_ADDR',
                    'REMOTE_PORT',
                    'SCRIPT_FILENAME',
                    'SCRIPT_NAME',
                    'REQUEST_TIME_FLOAT',
                    'REQUEST_TIME',
                    'argv',
                    'argc'
                ], true);
            }, ARRAY_FILTER_USE_KEY) ?: null,
            'inc_files' => get_included_files() ?: null,
            'extensions' => get_loaded_extensions() ?: null,

            'entries' => $this->entries ?: null,
            'entries_count' => $this->entries_count,

            'error' => $this->error ?: null,
            'extra' => $this->extra ?: null
        ];

        if ('cli' !== php_sapi_name()) {
            $data['status'] = http_response_code();
            $data['headers'] = headers_list();

            $data['method'] = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
            $data['url'] = $this->getURL();
            $data['ip'] = $this->getIP();
            $data['referer'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $data['query'] = $_GET;
            $data['body'] = $_POST;
            $data['raw_body'] = file_get_contents('php://input');
            $data['cookies'] = $_COOKIE;
            $data['files'] = $_FILES;
        }

        return $data;
    }

    /**
     * Get the full URL of the current request.
     *
     * @return string The full URL of the current request, or an empty string if not available.
     */
    private function getURL()
    {
        $port = isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : '';

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $url = "$_SERVER[HTTP_X_FORWARDED_PROTO]";
        } elseif ($port === 443 || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) { // phpcs:ignore
            $url = 'https';
        } else {
            $url = 'http';
        }

        $url .= '://';

        if (isset($_SERVER['HTTP_HOST'])) {
            $url .= $_SERVER['HTTP_HOST'];
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $url .= $_SERVER['SERVER_NAME'];
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $url .= $_SERVER['SERVER_ADDR'];
        } else {
            $url .= 'localhost';
        }

        if ($port && $port !== 80 && $port !== 443 && strpos($url, ':') === false) {
            $url .= ':' . $port;
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            $url .= $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['PHP_SELF'])) {
            $url .= $_SERVER['PHP_SELF'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $url .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        return rtrim($url, '/?&');
    }

    /**
     * Get the IP address of the client.
     *
     * @return string The IP address of the client, or an empty string if not available.
     */
    private function getIP()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',        // Cloudflare
            'HTTP_TRUE_CLIENT_IP',          // Akamai / Cloudflare Enterprise
            'HTTP_X_CLIENT_IP',             // Alguns proxies / balanceadores
            'HTTP_CLIENT_IP',               // Alternativo, menos confiável
            'HTTP_X_FORWARDED_FOR',         // Padrão para proxies em cadeia
            'HTTP_X_FORWARDED',             // Alguns servidores antigos
            'HTTP_FORWARDED_FOR',           // Padrão RFC 7239
            'HTTP_FORWARDED',               // RFC 7239
            'HTTP_X_CLUSTER_CLIENT_IP',     // AWS Elastic Load Balancer
            'HTTP_X_REAL_IP',               // Nginx proxy
            'REMOTE_ADDR'                    // Fallback final
        ];

        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            $ip = $_SERVER[$header];

            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip);
                $ip = trim($ip[0]);
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return '';
    }

    /**
     * Validate and normalize the data before saving.
     *
     * @param TData $data The data to normalize.
     *
     * @return TData The normalized data.
     */
    private function normalizeQueryData($data)
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Data must be an array.');
        }

        foreach ($data as $field => &$value) {
            switch ($field) {
                case 'identifier':
                    if (!is_string($value) || empty($value)) {
                        throw new InvalidArgumentException('Data identifier must be a non-empty string.');
                    }
                    break;

                case 'start':
                case 'duration':
                    if (!is_float($value) && !is_int($value)) {
                        throw new InvalidArgumentException("Data $field must be a float.");
                    }

                    $value = (float)$value;
                    break;

                case 'method':
                case 'url':
                    if (!is_string($value)) {
                        throw new InvalidArgumentException("Data $field must be a string.");
                    }
                    break;

                case 'memory_peak':
                    if (!is_int($value)) {
                        throw new InvalidArgumentException("Data $field must be an integer.");
                    }

                    $value = max(0, $value);
                    break;

                case 'status':
                case 'entries_count':
                    if (!is_int($value)) {
                        $value = null;
                    }
                    break;

                case 'headers':
                case 'query':
                case 'body':
                case 'cookies':
                case 'files':
                case 'server':
                case 'inc_files':
                case 'extensions':
                case 'error':
                case 'extra':
                    if (!$value || json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) === false) {
                        $value = null;
                    }
                    break;

                case 'group':
                case 'ip':
                case 'referer':
                case 'user_agent':
                case 'raw_body':
                    if (!is_string($value)) {
                        $value = null;
                    }
                    break;

                case 'entries':
                    if ($value !== null && !is_array($value)) {
                        throw new InvalidArgumentException("Data entries must be an array or null.");
                    }

                    if (empty($value)) {
                        $value = null;
                        break;
                    }

                    foreach ($value as $index => &$entry) {
                        // phpcs:disable
                        if (!is_array($entry)) {
                            throw new InvalidArgumentException("Data entry at index $index must be an array.");
                        }

                        if (empty($entry['identifier']) || !is_string($entry['identifier'])) {
                            throw new InvalidArgumentException("Data entry at index $index must have a non-empty string identifier.");
                        }

                        if (empty($entry['group'])) {
                            $entry['group'] = null;
                        } elseif (!is_string($entry['group'])) {
                            throw new InvalidArgumentException("Data entry at index $index must have a string or null group.");
                        }

                        if (!isset($entry['entry_parent_id'])) {
                            $entry['entry_parent_id'] = -1;
                        } elseif (!is_int($entry['entry_parent_id'])) {
                            throw new InvalidArgumentException("Data entry at index $index must have an integer entry_parent_id.");
                        }

                        if (!isset($entry['start']) || (!is_float($entry['start']) && !is_int($entry['start']))) {
                            throw new InvalidArgumentException("Data entry at index $index must have a float start time.");
                        }
                        $entry['start'] = (float)$entry['start'];

                        if (!isset($entry['duration']) || (!is_float($entry['duration']) && !is_int($entry['duration']))) {
                            throw new InvalidArgumentException("Data entry at index $index must have a float duration.");
                        }
                        $entry['duration'] = (float)$entry['duration'];

                        if (!isset($entry['memory_peak']) || !is_int($entry['memory_peak'])) {
                            throw new InvalidArgumentException("Data entry at index $index must have an integer memory_peak.");
                        }
                        $entry['memory_peak'] = max(0, $entry['memory_peak']);
                        // phpcs:enable
                    }
                    break;
            }

            unset($value); // Destroy reference
        }

        return $data;
    }

    /**
     * Process and save the data using the adaptor.
     *
     * @param TData $data The data to process and save.
     *
     * @return void
     * @throws \Throwable On failure
     * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
     */
    private function processAndSaveData($data)
    {
        $entries = $data['entries'];
        unset($data['entries']);

        try {
            $hits = array_reduce($entries ?: [], static function ($carry, $item) {
                static $entry_id = 0;

                $index = "$item[identifier]|" . ($item['group'] ?: '');

                if (!isset($carry[$item['identifier']])) {
                    $carry[$index] = [
                        'identifier' => $item['identifier'],
                        'group' => $item['group'],
                        'count' => 0,
                        'duration' => 0,
                        'memory_peak' => 0,
                        'last_duration' => 0,
                        'last_memory_peak' => 0,
                        'min_duration' => $item['duration'],
                        'min_memory_peak' => $item['memory_peak'],
                        'max_duration' => $item['duration'],
                        'max_memory_peak' => $item['memory_peak'],
                    ];
                }

                $carry[$index]['count']++;

                $carry[$index]['duration'] += $item['duration'];

                $carry[$index]['memory_peak'] += $item['memory_peak'];

                $carry[$index]['min_duration'] = min(
                    $carry[$index]['min_duration'],
                    $item['duration']
                );

                $carry[$index]['min_memory_peak'] = min(
                    $carry[$index]['min_memory_peak'],
                    $item['memory_peak']
                );

                $carry[$index]['max_duration'] = max(
                    $carry[$index]['max_duration'],
                    $item['duration']
                );

                $carry[$index]['max_memory_peak'] = max(
                    $carry[$index]['max_memory_peak'],
                    $item['memory_peak']
                );

                $carry[$index]['last_duration'] = $item['duration'];

                $carry[$index]['last_memory_peak'] = $item['memory_peak'];

                $entry_id++;

                return $carry;
            }, []);

            $this->storage->beginTransaction();

            $profile_id = $this->storage->saveProfileInfo($data);

            $this->storage->saveProfileEntries($profile_id, $entries);

            $this->storage->saveProfileMetric([
                'identifier' => (string)$data['identifier'],
                'type' => 'profile',
                'group' => (string)$this->group,
                'duration' => (float)$data['duration'],
                'memory_peak' => (int)$data['memory_peak'],
                'count' => 1,
                'profile_id' => $profile_id,
                'last_duration' => (float)$data['duration'],
                'last_memory_peak' => (int)$data['memory_peak'],
                'min_duration' => (float)$data['duration'],
                'min_memory_peak' => (int)$data['memory_peak'],
                'max_duration' => (float)$data['duration'],
                'max_memory_peak' => (int)$data['memory_peak']
            ]);

            foreach ($hits as $hit) {
                $this->storage->saveProfileMetric([
                    'identifier' => (string)$hit['identifier'],
                    'type' => "entry",
                    'group' => (string)$hit['group'],
                    'duration' => (float)($hit['duration'] / $hit['count']),
                    'memory_peak' => (int)($hit['memory_peak'] / $hit['count']),
                    'count' => (int)$hit['count'],
                    'profile_id' => $profile_id,
                    'last_duration' => (float)$hit['last_duration'],
                    'last_memory_peak' => (int)$hit['last_memory_peak'],
                    'min_duration' => (float)$hit['min_duration'],
                    'min_memory_peak' => (int)$hit['min_memory_peak'],
                    'max_duration' => (float)$hit['max_duration'],
                    'max_memory_peak' => (int)$hit['max_memory_peak']
                ]);
            }

            $this->storage->commitTransaction();
        } catch (Exception $e) {
            $this->storage->rollBackTransaction();
            throw $e;
        } catch (\Throwable $e) {
            $this->storage->rollBackTransaction();
            throw $e;
        }
    }
}
