<?php

/**
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

namespace VSR\Extend\Analysis\Driver;

use Exception;
use PDO;
use RuntimeException;
use VSR\Extend\Analysis\Contract\AbstractDriver;

class Standard extends AbstractDriver
{
    /**
     * @var PDO
     */
    protected $instance;

    protected $directory;

    protected $lastId = false;

    protected $affected = [];

    protected $rows = [];

    protected $onConflictSupport = false;

    /**
     * @param string $directory Directory
     */
    public function __construct($directory)
    {
        if (extension_loaded('pdo_sqlite') === false) {
            throw new RuntimeException('PDO SQLite extension is not loaded');
        }

        $this->directory = $directory;
        $this->makeDirectory();

        $this->instance = new PDO("sqlite:$this->directory/analysis.sqlite");
        $this->onConflictSupport = version_compare(
            $this->instance->query('select sqlite_version()')->fetch()[0],
            '3.24.0',
            '>='
        );
    }

    public function __destruct()
    {
        $this->instance = null;
        unset(
            $this->instance,
            $this->lastId,
            $this->affected
        );
    }

    private function makeDirectory()
    {
        if (!is_dir("$this->directory/profiles")) {
            mkdir("$this->directory/profiles", 0644, true);
        }
    }

    private function query($command, ...$values)
    {
        $this->lastId = false;
        $this->affected = [];
        $this->rows = [];

        try {
            $statement = $this->instance->prepare($command);
            if (!$statement) {
                throw new Exception($this->instance->errorInfo()[2], $this->instance->errorInfo()[1]);
            }

            if (!$values) {
                $values = [[]];
            }

            # Escape values
            foreach ($values as $i => $fields) {
                foreach ($fields as $j => $value) {
                    if (is_array($value) || is_object($value)) {
                        $values[$i][$j] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
                    }
                }
            }

            !$this->instance->inTransaction() && $this->instance->beginTransaction();
            foreach ($values as $fields) {
                if (!$statement->execute($fields)) {
                    throw new Exception($statement->errorInfo()[2], $statement->errorInfo()[1]);
                }
                $this->lastId = $this->instance->lastInsertId();
                if ($statement->rowCount()) {
                    if (isset($fields['key']) && isset($fields['type']) && isset($fields['ref'])) {
                        $id = [$fields['key'], $fields['type'], $fields['ref']];
                    } elseif (isset($fields['key'])) {
                        $id = $fields['key'];
                    } else {
                        $id = $this->lastId;
                    }
                    $this->affected[] = $id;
                    unset($id);
                }
                $this->rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            !$this->instance->inTransaction() || $this->instance->commit();
        } catch (Exception $th) {
            $this->instance->inTransaction() && $this->instance->rollBack();
            $this->lastId = false;
            $this->affected = false;
            $this->rows = false;
            throw $th;
        }
    }

    public function install()
    {
        $this->query("
            CREATE TABLE IF NOT EXISTS `hits` (
                `key` varchar(255) NOT NULL,
                `type` varchar(255) NOT NULL,
                `ref` integer DEFAULT NULL,
                `count` bigint DEFAULT NULL,
                `value` float DEFAULT NULL,
                `create_at` integer DEFAULT CURRENT_TIMESTAMP,
                `update_at` integer DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`,`type`,`ref`)
            )
        ");
        $this->query("
            CREATE TABLE IF NOT EXISTS `requests` (
                `id` integer PRIMARY KEY AUTOINCREMENT,
                `key` varchar(255) NOT NULL,
                `start` float NOT NULL,
                `end` float NOT NULL,
                `duration` float NOT NULL,
                `http_code` int(3) DEFAULT NULL,
                `method` varchar(10) DEFAULT NULL,
                `url` varchar(255) DEFAULT NULL,
                `ip` varchar(100) DEFAULT NULL,
                `referer` varchar(255) DEFAULT NULL,
                `useragent` varchar(255) DEFAULT NULL,
                `get` json DEFAULT NULL,
                `post` json DEFAULT NULL,
                `raw_post` longtext DEFAULT NULL,
                `files` json DEFAULT NULL,
                `cookies` json DEFAULT NULL,
                `server` json DEFAULT NULL,
                `headers` json DEFAULT NULL,
                `inc_files` json DEFAULT NULL,
                `memory` bigint DEFAULT NULL,
                `memory_peak` bigint DEFAULT NULL,
                `error` json DEFAULT NULL,
                `error_count` integer DEFAULT false,
                `extra` json DEFAULT NULL,
                `profile_count` integer NOT NULL
            )
        ");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-key` ON `requests` (`key`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-start` ON `requests` (`start`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-end` ON `requests` (`end`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-duration` ON `requests` (`duration`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-http_code` ON `requests` (`http_code`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-method` ON `requests` (`method`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-url` ON `requests` (`url`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-referer` ON `requests` (`referer`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-ip` ON `requests` (`ip`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-useragent` ON `requests` (`useragent`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-memory` ON `requests` (`memory`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-memory_peak` ON `requests` (`memory_peak`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests-profile_count` ON `requests` (`profile_count`)");

        $this->query("
            CREATE TABLE IF NOT EXISTS `requests_avg` (
                `key` varchar(255) PRIMARY KEY,
                `avg` float DEFAULT NULL,
                `min` float DEFAULT NULL,
                `min_id` integer DEFAULT NULL,
                `max` float DEFAULT NULL,
                `max_id` integer DEFAULT NULL,
                `last` float DEFAULT NULL,
                `last_id` integer DEFAULT NULL,
                `hits` bigint DEFAULT NULL,
                `create_at` integer DEFAULT CURRENT_TIMESTAMP,
                `update_at` integer DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests_avg-avg` ON `requests_avg` (`avg`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests_avg-min` ON `requests_avg` (`min`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests_avg-max` ON `requests_avg` (`max`)");
        $this->query("CREATE INDEX IF NOT EXISTS `idx_requests_avg-hits` ON `requests_avg` (`hits`)");
    }

    private function queryHit(...$values)
    {
        if ($this->onConflictSupport) {
            $this->query("
                INSERT INTO `hits`
                    (`key`, `type`, `ref`, `count`, `value`)
                VALUES
                    (:key, :type, :ref, 1, :value)
                ON CONFLICT (`key`, `type`, `ref`) DO UPDATE SET
                    `count` = `count` + 1,
                    `value` = CASE
                        WHEN `type` = '' AND `ref` = '' THEN :value
                        ELSE ((`value` * `count`) + :value) / (`count` + 1)
                    END,
                    `update_at` = CURRENT_TIMESTAMP
            ", ...$values);
        } else {
            $this->query("
                UPDATE `hits` SET
                    `count` = `count` + 1,
                    `value` = CASE
                        WHEN `type` = '' AND `ref` = '' THEN :value
                        ELSE ((`value` * `count`) + :value) / (`count` + 1)
                    END,
                    `update_at` = CURRENT_TIMESTAMP
                WHERE
                    `key` = :key
                    AND `type` = :type
                    AND `ref` = :ref
            ", ...$values);

            $values = array_filter($values, function ($i) {
                return !in_array([$i['key'], $i['type'], $i['ref']], $this->affected, true);
            });

            if ($values) {
                $this->query("
                    INSERT OR IGNORE INTO `hits`
                        (`key`, `type`, `ref`, `count`, `value`)
                    VALUES
                        (:key, :type, :ref, 1, :value)
                ", ...$values);
            }
        }
        unset($values);
    }

    private function queryAvg($collection, ...$values)
    {
        if ($this->onConflictSupport) {
            $this->query("
                INSERT INTO `$collection`
                    (`key`, `hits`, `avg`, `min`, `min_id`, `max`, `max_id`, `last`, `last_id`)
                VALUES
                    (:key, 1, :duration, :duration, :id, :duration, :id, :duration, :id)
                ON CONFLICT (`key`) DO UPDATE SET
                    `hits` = `hits` + 1,
                    `avg` = ((`avg` * `hits`) + :duration) / (`hits` + 1),
                    `min_id` = CASE WHEN `min` > :duration THEN :id ELSE `min_id` END,
                    `min` = CASE WHEN `min` > :duration THEN :duration ELSE `min` END,
                    `max_id` = CASE WHEN `max` < :duration THEN :id ELSE `max_id` END,
                    `max` = CASE WHEN `max` < :duration THEN :duration ELSE `max` END,
                    `last_id` = :id,
                    `last` = :duration,
                    `update_at` = CURRENT_TIMESTAMP
            ", ...$values);
        } else {
            $this->query("
                UPDATE `$collection` SET
                    `hits` = `hits` + 1,
                    `avg` = ((`avg` * `hits`) + :duration) / (`hits` + 1),
                    `min_id` = CASE WHEN `min` > :duration THEN :id ELSE `min_id` END,
                    `min` = CASE WHEN `min` > :duration THEN :duration ELSE `min` END,
                    `max_id` = CASE WHEN `max` < :duration THEN :id ELSE `max_id` END,
                    `max` = CASE WHEN `max` < :duration THEN :duration ELSE `max` END,
                    `last_id` = :id,
                    `last` = :duration,
                    `update_at` = CURRENT_TIMESTAMP
                WHERE
                    `key` = :key
            ", ...$values);

            $values = array_filter($values, function ($i) {
                return !in_array($i['key'], $this->affected, true);
            });

            if ($values) {
                $this->query("
                    INSERT OR IGNORE INTO `$collection`
                        (`key`, `hits`, `avg`, `min`, `min_id`, `max`, `max_id`, `last`, `last_id`)
                    VALUES
                        (:key, 1, :duration, :duration, :id, :duration, :id, :duration, :id)
                ", ...$values);
            }
        }
    }

    private function queryInsert($collection, ...$values)
    {
        $c = $v = [];
        foreach (current($values) as $k => $i) {
            $c[] = "`$k`";
            $v[] = ":$k";
        }

        $this->query("
            INSERT INTO `$collection` (
                " . implode(',' . PHP_EOL, $c) . "
            ) VALUES (
                " . implode(',' . PHP_EOL, $v) . "
            )
        ", ...$values);

        unset($c, $v, $collection, $values);

        return $this->lastId;
    }

    public function processRequest($request)
    {
        // Requests duration avg and hits
        $this->queryHit(...$this->hitGroup(
            self::HIT_AVG | self::HIT_ALL,
            ['key' => 'req', 'value' => $request['duration']]
        ));

        $request['error_count'] = $request['error'] ? count($request['error']) : 0;
        $profile = $request['profile'];
        unset($request['profile']);

        $id = $this->queryInsert('requests', $request);
        $request_key = $request['key'];
        $request_duration = $request['duration'];
        unset($request);

        // Request key duration avg and hits
        $this->queryAvg(
            'requests_avg',
            ['id' => $id, 'key' => $request_key, 'duration' => $request_duration]
        );

        foreach ($profile as $index => $item) {
            $profile[$index]['index'] = $index;
            $profile[$index]['id'] = $id;
            $profile[$index]['extra'] = $profile[$index]['extra']
                ? (json_encode($profile[$index]['extra'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null)
                : null;
            $profile[$index]['error'] = $profile[$index]['error']
                ? (json_encode($profile[$index]['error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null)
                : null;
        }
        unset($index, $item);

        if ($profile) {
            $this->makeDirectory();
            file_put_contents(
                "$this->directory/profiles/$id.json.gz",
                gzencode(json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            );
        }

        unset($profile);
    }

    public function processServer($data)
    {
        $this->queryHit(...$this->hitGroup(
            self::HIT_RAW,
            ['key' => 'uptime', 'value' => $data['uptime']],
            ['key' => 'cpu', 'value' => $data['cpu']],
            ['key' => 'thr_total', 'value' => $data['thr_total']],
            ['key' => 'thr_running', 'value' => $data['thr_running']],
            ['key' => 'thr_sleeping', 'value' => $data['thr_sleeping']],
            ['key' => 'thr_stopped', 'value' => $data['thr_stopped']],
            ['key' => 'thr_zombie', 'value' => $data['thr_zombie']],
            ['key' => 'mem_total', 'value' => $data['mem_total']],
            ['key' => 'mem_free', 'value' => $data['mem_free']],
            ['key' => 'mem_used', 'value' => $data['mem_used']],
            ['key' => 'mem_cache', 'value' => $data['mem_cache']],
            ['key' => 'swa_total', 'value' => $data['swa_total']],
            ['key' => 'swa_free', 'value' => $data['swa_free']],
            ['key' => 'swa_used', 'value' => $data['swa_used']],
            ['key' => 'swa_cache', 'value' => $data['swa_cache']],
            ['key' => 'disk_total', 'value' => $data['disk_total']],
            ['key' => 'disk_free', 'value' => $data['disk_free']],
            ['key' => 'disk_used', 'value' => $data['disk_used']]
        ));

        $this->queryHit(...$this->hitGroup(
            self::HIT_ALL,
            ['key' => 'cpu', 'value' => $data['cpu']],
            ['key' => 'mem_used', 'value' => $data['mem_used']],
            ['key' => 'disk_used', 'value' => $data['disk_used']]
        ));

        unset($data);
    }

    public function getDatabaseSize()
    {
        $filesize = is_file("$this->directory/analysis.sqlite")
            ? filesize("$this->directory/analysis.sqlite")
            : 0;

        if ($io = @popen('/usr/bin/du -sb ' . realpath("$this->directory/.."), 'r')) {
            $size = $filesize + intval(fgets($io, 80));
            pclose($io);
        }

        return [
            'file' => empty($filesize) ? 0 : $filesize / 1024 / 1024,
            'full' => empty($size) ? 0 : $size / 1024 / 1024
        ];
    }

    public function getViewerData($options)
    {
        $json = [
            'server' => [
                'current' => [
                    'uptime' => 0,
                    'cpu' => 0.0,
                    'thr_total' => 0,
                    'thr_running' => 0,
                    'thr_sleeping' => 0,
                    'thr_stopped' => 0,
                    'thr_zombie' => 0,
                    'mem_total' => 0.0,
                    'mem_free' => 0.0,
                    'mem_used' => 0.0,
                    'mem_cache' => 0.0,
                    'swa_total' => 0.0,
                    'swa_free' => 0.0,
                    'swa_used' => 0.0,
                    'swa_cache' => 0.0,
                    'disk_total' => 0.0,
                    'disk_free' => 0.0,
                    'disk_used' => 0.0,
                    'db_size' => $this->getDatabaseSize()
                ],
                'chart' => [
                    # '00:00' => ['cpu' => 0.0, 'mem' => 0.0, 'swa' => 0.0, 'disk' => 0.0]
                ]
            ],
            'request' => [
                'current' => [
                    'avg' => 0.0,
                    'total' => 0,
                    'per' => [
                        'second' => 0,
                        'minute' => 0,
                        'hour' => 0,
                        'day' => 0,
                    ]
                ],
                'chart' => [
                    # '00:00' => 0
                ],
                'dt' => [
                    'reqs' => [
                        'recordsTotal' => 0,
                        'recordsFiltered' => 0,
                        'data' => [
                            /*[
                                'id' => 0,
                                'key' => '',
                                'start' => 0.0,
                                'end' => 0.0,
                                'duration' => 0.0,
                                'memory' => 0.0,
                                'profile_count' => 0,
                                'http_code' => 0,
                                'method' => '',
                                'uri' => '',
                                'error_count' => false
                            ]*/
                        ]
                    ],
                    'keys' => [
                        'recordsTotal' => 0,
                        'recordsFiltered' => 0,
                        'data' => [
                            /*[
                                'key' => '',
                                'count' => 0,
                                'avg' => 0.0,
                                'min' => 0.0,
                                'max' => 0.0,
                                'last' => 0.0
                            ]*/
                        ]
                    ],
                ]
            ]
        ];

        $chartGroupBy = !empty($options['chartGroupBy']) ? $options['chartGroupBy'] : null;
        $chartLength = !empty($options['chartLength']) ? $options['chartLength'] : 100;
        $dtReqsStart = !empty($options['dtReqsStart']) ? $options['dtReqsStart'] : 0;
        $dtReqsLength = !empty($options['dtReqsLength']) ? $options['dtReqsLength'] : 10;
        $dtReqsOrder = !empty($options['dtReqsOrder']) ? $options['dtReqsOrder'] : 'start';
        $dtReqsDir = !empty($options['dtReqsDir']) ? $options['dtReqsDir'] : 'desc';
        $dtKeysStart = !empty($options['dtKeysStart']) ? $options['dtKeysStart'] : 0;
        $dtKeysLength = !empty($options['dtKeysLength']) ? $options['dtKeysLength'] : 10;
        $dtKeysOrder = !empty($options['dtKeysOrder']) ? $options['dtKeysOrder'] : 'avg';
        $dtKeysDir = !empty($options['dtKeysDir']) ? $options['dtKeysDir'] : 'desc';
        $wheres = !empty($options['wheres']) ? json_decode($options['wheres'], true) ?: [] : [];

        # Get server.current.*, request.current.*
        $this->query("
            SELECT
                *
            FROM
                `hits`
            WHERE
                (`type` = '' AND `ref` = '')
                OR (`key` = 'req' AND `type` = 'avg' AND `ref` = '')
            ORDER BY
                `ref` DESC
        ");
        foreach ($this->rows as $i) {
            if ($i['key'] === 'req') {
                $time = strtotime($i['update_at']) - strtotime($i['create_at']) ?: 1;
                $json['request']['current']['avg'] = round($i['value'], 3);
                $json['request']['current']['total'] = $i['count'];
                $json['request']['current']['per']['second'] = ceil($i['count'] / $time);
                $json['request']['current']['per']['minute'] = ceil($i['count'] / ($time / 60));
                $json['request']['current']['per']['hour'] = ceil($i['count'] / ($time / 3600));
                $json['request']['current']['per']['day'] = ceil($i['count'] / ($time / 86400));
                continue;
            }

            if (isset($json['server']['current'][$i['key']])) {
                $json['server']['current'][$i['key']] = round($i['value'] ?: 0, 1);
            }
        }

        # Get server.chart.*, request.chart.*
        if ($chartGroupBy) {
            $this->query("
                SELECT
                    `ref` AS `ref`,
                    GROUP_CONCAT('{\"key\":\"'||`key`||'\",\"count\":'||`count`||',\"value\":'||`value`||'}') AS `json`
                FROM
                    `hits`
                WHERE
                    `type` = :type
                GROUP BY
                    `ref`
                ORDER BY
                    `ref` DESC
                LIMIT
                    :length
            ", [
                'type' => $chartGroupBy,
                'length' => $chartLength
            ]);

            foreach ($this->rows as $i) {
                foreach (json_decode('[' . $i['json'] . ']', true) as $j) {
                    !isset($json['server']['chart'][$i['ref']])
                    && $json['server']['chart'][$i['ref']] = ['cpu' => 0.0, 'mem_used' => 0.0, 'disk_used' => 0.0];

                    !isset($json['request']['chart'][$i['ref']])
                    && $json['request']['chart'][$i['ref']] = 0;

                    if ($j['key'] === 'req') {
                        $json['request']['chart'][$i['ref']] = round($j['count'] ?: 0);
                        continue;
                    }

                    $json['server']['chart'][$i['ref']][$j['key']] = round($j['value'] ?: 0, 1);
                }
            }

            ksort($json['server']['chart']);
            ksort($json['request']['chart']);
        }

        # Get request.dt.reqs.*
        $where_reqs = [];
        foreach ($wheres as $i) {
            if (
                in_array($i[0], [
                    'request_key',
                    'date',
                    'duration',
                    'memory',
                    'profile_count',
                    'http_code',
                    'method',
                    'url',
                    'has_error',
                    'extra'
                ])
            ) {
                if ($i[0] === 'request_key') {
                    $i[0] = 'key';
                }
                if (is_numeric($i[2]) || in_array(strtoupper($i[2]), ['NULL', 'TRUE', 'FALSE'])) {
                    $where_reqs[] = "`$i[0]` $i[1] " . strtoupper($i[2]) . " $i[3] ";
                } else {
                    $where_reqs[] = "`$i[0]` $i[1] {$this->instance->quote($i[2])} $i[3] ";
                }
            }
        }
        $where_reqs = implode('', $where_reqs);
        $where_reqs = substr($where_reqs, 0, $wheres ? -2 - strlen($i[3]) : -5);
        $where_reqs = $where_reqs ?: '1';

        $this->query("
            SELECT
                `id`,
                `key`,
                `start`,
                `duration`,
                `memory`,
                `profile_count`,
                `http_code`,
                `method`,
                `url`,
                `error_count`
            FROM
                `requests`
            WHERE
                $where_reqs
            ORDER BY
                `$dtReqsOrder` $dtReqsDir
            LIMIT
                :start, :length
        ", [
            'start' => $dtReqsStart,
            'length' => $dtReqsLength
        ]);

        if ($this->rows) {
            foreach ($this->rows as $i) {
                $i['start'] = intval($i['start']);
                $i['duration'] = round($i['duration'], 3);
                $i['memory'] = round($i['memory'], 3);
                $i['profile_count'] = intval($i['profile_count']);
                $i['http_code'] = intval($i['http_code']);
                $i['error_count'] = intval($i['error_count']);
                $i['url'] = strlen($i['url']) > 100 ? substr($i['url'], 0, 97) . '...' : $i['url'];
                $json['request']['dt']['reqs']['data'][] = $i;
            }

            $this->query("
                SELECT
                    COUNT(*) AS `count`
                FROM
                    `requests`
            ");
            $json['request']['dt']['reqs']['recordsTotal'] = $this->rows[0]['count'];

            $this->query("
                SELECT
                    COUNT(*) AS `count`
                FROM
                    `requests`
                WHERE
                    $where_reqs
            ");
            $json['request']['dt']['reqs']['recordsFiltered'] = $this->rows[0]['count'];
        }

        # Get request.dt.keys.*
        $wheres_keys = [];
        foreach ($wheres as $i) {
            if (in_array($i[0], ['request_key', 'hits', 'avg', 'min', 'max', 'last'])) {
                if ($i[0] === 'request_key') {
                    $i[0] = 'key';
                }
                if (is_numeric($i[2]) || in_array(strtoupper($i[2]), ['NULL', 'TRUE', 'FALSE'])) {
                    $wheres_keys[] = "`$i[0]` $i[1] " . strtoupper($i[2]) . " $i[3] ";
                } else {
                    $wheres_keys[] = "`$i[0]` $i[1] {$this->instance->quote($i[2])} $i[3] ";
                }
            }
        }
        $wheres_keys = $wheres_keys ?: ['1=1 AND '];
        $wheres_keys = implode('', $wheres_keys);
        $wheres_keys = substr($wheres_keys, 0, $wheres ? -2 - strlen($i[3]) : -5);

        $this->query("
            SELECT
                `key`,
                `hits`,
                `avg`,
                `min`,
                `min_id`,
                `max`,
                `max_id`,
                `last`,
                `last_id`
            FROM
                `requests_avg`
            WHERE
                $wheres_keys
            ORDER BY
                `$dtKeysOrder` $dtKeysDir
            LIMIT
                :start, :length
        ", [
            'start' => $dtKeysStart,
            'length' => $dtKeysLength
        ]);
        if ($this->rows) {
            foreach ($this->rows as $i) {
                $i['avg'] = round($i['avg'], 5);
                $i['min'] = round($i['min'], 5);
                $i['max'] = round($i['max'], 5);
                $i['last'] = round($i['last'], 5);
                $json['request']['dt']['keys']['data'][] = $i;
            }

            $this->query("
                SELECT
                    COUNT(*) AS `count`
                FROM
                    `requests_avg`
            ");
            $json['request']['dt']['keys']['recordsTotal'] = $this->rows[0]['count'];

            $this->query("
                SELECT
                    COUNT(*) AS `count`
                FROM
                    `requests_avg`
                WHERE
                    $wheres_keys
            ");
            $json['request']['dt']['keys']['recordsFiltered'] = $this->rows[0]['count'];
        }
        return $json;
    }

    public function getRequestData($id)
    {
        if (!$id) {
            return false;
        }

        $this->query("
            SELECT
                *
            FROM
                `requests`
            WHERE
                `id` = :id
        ", [
            'id' => $id
        ]);
        if (!$this->rows) {
            return false;
        }

        $json = $this->rows[0];
        $json['duration'] = round($json['duration'], 3);
        $json['http_code'] = intval($json['http_code']);
        $json['get'] = $json['get'] ? json_decode($json['get'], true) : null;
        $json['post'] = $json['post'] ? json_decode($json['post'], true) : null;
        $json['raw_post'] = $json['raw_post'] ?: null;
        $json['files'] = $json['files'] ? json_decode($json['files'], true) : null;
        $json['cookies'] = $json['cookies'] ? json_decode($json['cookies'], true) : null;
        $json['server'] = $json['server'] ? json_decode($json['server'], true) : null;
        $json['headers'] = $json['headers'] ? json_decode($json['headers'], true) : null;
        $json['inc_files'] = $json['inc_files'] ? json_decode($json['inc_files'], true) : null;
        $json['memory'] = round($json['memory'], 3);
        $json['memory_peak'] = round($json['memory_peak'], 3);
        $json['error'] = $json['error'] ? json_decode($json['error'], true) : null;
        $json['error_count'] = intval($json['error_count']);
        $json['extra'] = $json['extra'] ? json_decode($json['extra'], true) : null;
        $json['profile_count'] = intval($json['profile_count']);
        $json['profile'] = [];

        if (empty($json['get'])) {
            parse_str(parse_url($json['url'], PHP_URL_QUERY) ?: '', $json['get']);
        }

        if (is_file("$this->directory/profiles/$id.json.gz")) {
            $profile = json_decode(gzdecode(file_get_contents("$this->directory/profiles/$id.json.gz")), true);
            foreach ($profile as $i) {
                $i['duration'] = round($i['duration'], 3);
                $i['memory'] = !empty($i['memory']) ? round($i['memory'], 3) : null;
                $i['memory_peak'] = !empty($i['memory_peak']) ? round($i['memory_peak'], 3) : null;
                $i['error'] = !empty($i['error']) ? json_decode($i['error'], true) : null;
                $i['extra'] = !empty($i['extra']) ? json_decode($i['extra'], true) : null;
                $json['profile'][] = $i;
            }
            unset($profile);
        }

        $json['key_info'] = [];
        $this->query("
            SELECT
                *
            FROM
                `requests_avg`
            WHERE
                `key` = :key
        ", [
            'key' => $json['key']
        ]);
        if ($this->rows) {
            $json['key_info'] = $this->rows[0];
            $json['key_info']['avg'] = round($json['key_info']['avg'], 5);
            $json['key_info']['min'] = round($json['key_info']['min'], 5);
            $json['key_info']['max'] = round($json['key_info']['max'], 5);
            $json['key_info']['last'] = round($json['key_info']['last'], 5);
        }

        return $json;
    }
}
