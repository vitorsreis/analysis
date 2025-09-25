<?php

/**
 * This file is part of the VSR Analysis.
 */

namespace VSR\Analysis\Storage;

use Closure;
use PDO;
use RuntimeException;

class SQLite implements StorageInterface
{
    /**
     * @var PDO
     */
    protected $connection = null;

    /**
     * @var string Path to the SQLite database file.
     */
    protected $path;

    /**
     * @var string Directory to store profile entries.
     */
    protected $entriesDirectory;

    /**
     * @var bool Whether the SQLite version supports "ON CONFLICT" clause.
     */
    protected $allowOnConflict;

    /**
     * @var bool Whether the SQLite version supports "RETURNING" clause.
     */
    protected $allowReturning;

    /**
     * @param string $path Path to the SQLite database file.
     * @phpcs:ignore
     * @param string|null $entriesDirectory Directory to store profile entries, if null entries will be stored in "dirname($path)/entries"
     * @phpcs:ignore
     * @param PDO|null $connection Optional existing database connection. If null, a new connection will be created.
     */
    public function __construct($path, $entriesDirectory = null, $connection = null)
    {
        $this->path = $path;

        if ($entriesDirectory === null) {
            $entriesDirectory = dirname($path) . DIRECTORY_SEPARATOR . 'entries';
        }

        $this->entriesDirectory = $entriesDirectory;

        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('Neither pdo_sqlite extension is available.');
        }

        if ($connection instanceof PDO) {
            $this->connection = $connection;
        } elseif ($connection !== null) {
            throw new RuntimeException('Invalid connection type. Expected PDO instance.');
        }

        if (is_dir(dirname($this->path)) === false && mkdir(dirname($this->path), 0755, true) === false) {
            throw new RuntimeException('Failed to create directory: ' . dirname($this->path));
        }

        if (!is_file($this->path) && !touch($this->path)) {
            throw new RuntimeException('Failed to create SQLite database file: ' . $this->path);
        }

        if (!is_readable($this->path) || !is_writable($this->path)) {
            throw new RuntimeException('SQLite database file is not readable or writable: ' . $this->path);
        }

        if (!is_dir($this->entriesDirectory) && !mkdir($this->entriesDirectory, 0755, true)) {
            throw new RuntimeException('Failed to create entries directory: ' . $this->entriesDirectory);
        }
    }

    /**
     * Establishes a database connection if not already connected.
     *
     * @param bool $readonly Whether to open the database in read-only mode.
     *
     * @return $this
     */
    protected function connect($readonly = false)
    {
        if (!$this->connection) {
            $installed = is_file($this->path) && filesize($this->path) > 0;

            if ($readonly && $installed) {
                $flags = 1; // PDO::SQLITE_OPEN_READONLY
            } else {
                $flags = 6; // PDO::SQLITE_OPEN_READWRITE | PDO::SQLITE_OPEN_CREATE
            }

            $this->connection = new PDO("sqlite:$this->path", null, null, [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                1000 => $flags // PDO::SQLITE_ATTR_OPEN_FLAGS
            ]);

            $this->connection->exec('PRAGMA encoding = "UTF-8"');
            $this->connection->exec("PRAGMA count_changes = OFF");
            $this->connection->exec('PRAGMA journal_mode = OFF');
            $this->connection->exec('PRAGMA synchronous = OFF');
            $this->connection->exec('PRAGMA foreign_keys = OFF');
            $this->connection->exec('PRAGMA temp_store = MEMORY');
            $this->connection->exec('PRAGMA cache_size = 25600'); // 25MB

            if (!$installed) {
                $this->install();
            }
        }

        if (!isset($this->allowOnConflict) || !isset($this->allowReturning)) {
            $version = $this->connection->query("SELECT sqlite_version()")->fetchColumn();
            $this->allowOnConflict = version_compare($version, '3.24.0', '>=');
            $this->allowReturning = version_compare($version, '3.35.0', '>=');
        }

        return $this;
    }

    /**
     * Installs the necessary database schema if not already present.
     *
     * @return void
     */
    protected function install()
    {
        // phpcs:disable
        $this->connect();

        $this->beginTransaction();

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_identifier_dictionary` (
                `identifier_id` INTEGER PRIMARY KEY,
                `value` VARCHAR(100) NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_identifier_value` ON `profile_identifier_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_group_dictionary` (
                `group_id` INTEGER PRIMARY KEY,
                `value` VARCHAR(100) NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_group_value` ON `profile_group_dictionary` (`value`)");
        $this->connection->exec("INSERT OR IGNORE INTO `profile_group_dictionary` (`group_id`, `value`) VALUES ( -1, 'none' )");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_url_dictionary` (
                `url_id` INTEGER PRIMARY KEY,
                `value` TEXT NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_url_value` ON `profile_url_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_method_dictionary` (
                `method_id` INTEGER PRIMARY KEY,
                `value` VARCHAR(10) NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_method_value` ON `profile_method_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_ip_dictionary` (
                `ip_id` INTEGER PRIMARY KEY,
                `value` TEXT NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_ip_value` ON `profile_ip_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_referer_dictionary` (
                `referer_id` INTEGER PRIMARY KEY,
                `value` TEXT NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_referer_value` ON `profile_referer_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_user_agent_dictionary` (
                `user_agent_id` INTEGER PRIMARY KEY,
                `value` TEXT NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_user_agent_value` ON `profile_user_agent_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_query_field_dictionary` (
                `query_field_id` INTEGER PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `value` TEXT DEFAULT NULL,
                `count` INTEGER NOT NULL DEFAULT 1,
                UNIQUE (`name`, `value`)
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_query_field_name` ON `profile_query_field_dictionary` (`name`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_query_field_value` ON `profile_query_field_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_body_field_dictionary` (
                `body_field_id` INTEGER PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `value` TEXT DEFAULT NULL,
                `count` INTEGER NOT NULL DEFAULT 1,
                UNIQUE (`name`, `value`)
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_body_field_name` ON `profile_body_field_dictionary` (`name`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_body_field_value` ON `profile_body_field_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_raw_body_dictionary` (
                `raw_body_id` INTEGER PRIMARY KEY,
                `value` TEXT NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_raw_body_value` ON `profile_raw_body_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_cookie_dictionary` (
                `cookie_id` INTEGER PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `value` TEXT DEFAULT NULL,
                `count` INTEGER NOT NULL DEFAULT 1,
                UNIQUE (`name`, `value`)
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_cookie_name` ON `profile_cookie_dictionary` (`name`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_cookie_value` ON `profile_cookie_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_file_dictionary` (
                `files_id` INTEGER PRIMARY KEY,
                `name` TEXT NOT NULL,
                `type` TEXT NOT NULL,
                `size` INTEGER NOT NULL,
                `error` INTEGER NOT NULL,
                `count` INTEGER NOT NULL DEFAULT 1,
                UNIQUE (`name`, `type`, `size`, `error`)
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_file_name` ON `profile_file_dictionary` (`name`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_file_type` ON `profile_file_dictionary` (`type`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_file_size` ON `profile_file_dictionary` (`size`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_file_error` ON `profile_file_dictionary` (`error`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_server_dictionary` (
                `server_id` INTEGER PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `value` TEXT DEFAULT NULL,
                `count` INTEGER NOT NULL DEFAULT 1,
                UNIQUE (`name`, `value`)
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_server_name` ON `profile_server_dictionary` (`name`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_server_value` ON `profile_server_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_inc_file_dictionary` (
                `inc_file_id` INTEGER PRIMARY KEY,
                `value` TEXT NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_inc_file_value` ON `profile_inc_file_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_extension_dictionary` (
                `extension_id` INTEGER PRIMARY KEY,
                `value` TEXT NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_extension_value` ON `profile_extension_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_error_dictionary` (
                `error_id` INTEGER PRIMARY KEY,
                `severity` INTEGER NOT NULL,
                `message` TEXT NOT NULL,
                `file` TEXT NOT NULL,
                `line` INTEGER NOT NULL,
                `count` INTEGER NOT NULL DEFAULT 1,
                UNIQUE (`severity`, `message`, `file`, `line`)
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_error_severity` ON `profile_error_dictionary` (`severity`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_error_message` ON `profile_error_dictionary` (`message`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_error_file` ON `profile_error_dictionary` (`file`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_error_line` ON `profile_error_dictionary` (`line`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_extra_dictionary` (
                `extra_id` INTEGER PRIMARY KEY,
                `value` TEXT NOT NULL UNIQUE,
                `count` INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_extra_value` ON `profile_extra_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_header_dictionary` (
                `header_id` INTEGER PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `value` TEXT NOT NULL,
                `count` INTEGER NOT NULL DEFAULT 1,
                UNIQUE (`name`, `value`)
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_header_name` ON `profile_header_dictionary` (`name`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_header_value` ON `profile_header_dictionary` (`value`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile` (
                `profile_id` INTEGER PRIMARY KEY,
                `identifier_id` INTEGER NOT NULL,
                `group_id` INTEGER DEFAULT NULL,
                `start` FLOAT NOT NULL,
                `duration` FLOAT NOT NULL,
                `method_id` INTEGER NOT NULL,
                `url_id` INTEGER NOT NULL,
                `memory_peak` INTEGER NOT NULL,
                `status` INTEGER DEFAULT NULL,
                `ip_id` INTEGER DEFAULT NULL,
                `referer_id` INTEGER DEFAULT NULL,
                `user_agent_id` INTEGER DEFAULT NULL,
                `raw_body_id` INTEGER DEFAULT NULL,
                `entries_count` INTEGER NOT NULL,
                `error_count` INTEGER NOT NULL,
                FOREIGN KEY (`identifier_id`) REFERENCES `profile_identifier_dictionary` (`identifier_id`) ON DELETE CASCADE,
                FOREIGN KEY (`group_id`) REFERENCES `profile_group_dictionary` (`group_id`) ON DELETE CASCADE,
                FOREIGN KEY (`url_id`) REFERENCES `profile_url_dictionary` (`url_id`) ON DELETE CASCADE,
                FOREIGN KEY (`method_id`) REFERENCES `profile_method_dictionary` (`method_id`) ON DELETE CASCADE,
                FOREIGN KEY (`ip_id`) REFERENCES `profile_ip_dictionary` (`ip_id`) ON DELETE SET NULL,
                FOREIGN KEY (`referer_id`) REFERENCES `profile_referer_dictionary` (`referer_id`) ON DELETE SET NULL,
                FOREIGN KEY (`user_agent_id`) REFERENCES `profile_user_agent_dictionary` (`user_agent_id`) ON DELETE SET NULL,
                FOREIGN KEY (`raw_body_id`) REFERENCES `profile_raw_body_dictionary` (`raw_body_id`) ON DELETE SET NULL
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_start` ON `profile` (`start`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_duration` ON `profile` (`duration`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_memory_peak` ON `profile` (`memory_peak`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_status` ON `profile` (`status`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_entries_count` ON `profile` (`entries_count`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_error_count` ON `profile` (`error_count`)");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_header` (
                `profile_id` INTEGER NOT NULL,
                `header_id` INTEGER NOT NULL,
                PRIMARY KEY (`profile_id`, `header_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`header_id`) REFERENCES `profile_header_dictionary` (`header_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_query_field` (
                `profile_id` INTEGER NOT NULL,
                `query_field_id` INTEGER NOT NULL,
                PRIMARY KEY (`profile_id`, `query_field_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`query_field_id`) REFERENCES `profile_query_field_dictionary` (`query_field_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_body_field` (
                `profile_id` INTEGER NOT NULL,
                `body_field_id` INTEGER NOT NULL,
                PRIMARY KEY (`profile_id`, `body_field_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`body_field_id`) REFERENCES `profile_body_field_dictionary` (`body_field_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_cookie` (
                `profile_id` INTEGER NOT NULL,
                `cookie_id` INTEGER NOT NULL,
                PRIMARY KEY (`profile_id`, `cookie_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`cookie_id`) REFERENCES `profile_cookie_dictionary` (`cookie_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_file` (
                `profile_id` INTEGER NOT NULL,
                `files_id` INTEGER NOT NULL,
                PRIMARY KEY (`profile_id`, `files_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`files_id`) REFERENCES `profile_file_dictionary` (`files_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_server` (
                `profile_id` INTEGER NOT NULL,
                `server_id` INTEGER NOT NULL,
                PRIMARY KEY (`profile_id`, `server_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`server_id`) REFERENCES `profile_server_dictionary` (`server_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_inc_file` (
                `profile_id` INTEGER NOT NULL,
                `inc_file_id` INTEGER NOT NULL,
                PRIMARY KEY (`profile_id`, `inc_file_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`inc_file_id`) REFERENCES `profile_inc_file_dictionary` (`inc_file_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_extension` (
                `profile_id` INTEGER NOT NULL,
                `extension_id` INTEGER NOT NULL,
                PRIMARY KEY (`profile_id`, `extension_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`extension_id`) REFERENCES `profile_extension_dictionary` (`extension_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_error` (
                `profile_id` INTEGER NOT NULL,
                `error_id` INTEGER NOT NULL,
                `entry_parent_id` INTEGER DEFAULT NULL,
                PRIMARY KEY (`profile_id`, `error_id`, `entry_parent_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`error_id`) REFERENCES `profile_error_dictionary` (`error_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS `profile_extra` (
                `profile_id` INTEGER NOT NULL,
                `extra_id` INTEGER NOT NULL,
                `entry_parent_id` INTEGER DEFAULT NULL,
                PRIMARY KEY (`profile_id`, `extra_id`, `entry_parent_id`),
                FOREIGN KEY (`profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE CASCADE,
                FOREIGN KEY (`extra_id`) REFERENCES `profile_extra_dictionary` (`extra_id`) ON DELETE CASCADE
            )
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS profile_metrics (
                `identifier` VARCHAR(255) NOT NULL, 
                `type` VARCHAR(100) NOT NULL,
                `group` VARCHAR(100) NOT NULL,
                `count` INTEGER NOT NULL,
                `avg_duration` FLOAT NOT NULL,
                `avg_memory_peak` INTEGER NOT NULL,
                `last_profile_id` INTEGER NOT NULL,
                `last_duration` FLOAT NOT NULL,
                `last_memory_peak` INTEGER NOT NULL,
                `min_duration_profile_id` INTEGER NOT NULL,
                `min_duration` FLOAT NOT NULL,
                `min_memory_peak_profile_id` INTEGER NOT NULL,
                `min_memory_peak` INTEGER NOT NULL,
                `max_duration_profile_id` INTEGER NOT NULL,
                `max_duration` FLOAT NOT NULL,
                `max_memory_peak_profile_id` INTEGER NOT NULL,
                `max_memory_peak` INTEGER NOT NULL,
                PRIMARY KEY (`identifier`, `group`, `type`),
                FOREIGN KEY (`last_profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE SET NULL,
                FOREIGN KEY (`min_duration_profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE SET NULL,
                FOREIGN KEY (`min_memory_peak_profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE SET NULL,
                FOREIGN KEY (`max_duration_profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE SET NULL,
                FOREIGN KEY (`max_memory_peak_profile_id`) REFERENCES `profile` (`profile_id`) ON DELETE SET NULL
            )
        ");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_identifier` ON `profile_metrics` (`identifier`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_type` ON `profile_metrics` (`type`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_group` ON `profile_metrics` (`group`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_count` ON `profile_metrics` (`count`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_avg_duration` ON `profile_metrics` (`avg_duration`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_avg_memory_peak` ON `profile_metrics` (`avg_memory_peak`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_last_profile_id` ON `profile_metrics` (`last_profile_id`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_last_duration` ON `profile_metrics` (`last_duration`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_last_memory_peak` ON `profile_metrics` (`last_memory_peak`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_min_duration_profile_id` ON `profile_metrics` (`min_duration_profile_id`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_min_duration` ON `profile_metrics` (`min_duration`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_min_memory_peak_profile_id` ON `profile_metrics` (`min_memory_peak_profile_id`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_min_memory_peak` ON `profile_metrics` (`min_memory_peak`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_max_duration_profile_id` ON `profile_metrics` (`max_duration_profile_id`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_max_duration` ON `profile_metrics` (`max_duration`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_max_memory_peak_profile_id` ON `profile_metrics` (`max_memory_peak_profile_id`)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS `idx_profile_metrics_max_memory_peak` ON `profile_metrics` (`max_memory_peak`)");

        $this->commitTransaction();
    }

    /**
     * Executes a query with optional parameters.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params Optional parameters for the query.
     * @param bool $fetch Whether to fetch results (true) or just execute (false).
     *
     * @return array|bool
     */
    protected function query($sql, $params = [], $fetch = true)
    {
        $stmt = $this->connection->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . implode(' ', $this->connection->errorInfo())); // phpcs:ignore
        }

        if (!$stmt->execute($params)) {
            throw new RuntimeException('Failed to execute statement: ' . implode(' ', $stmt->errorInfo())); // phpcs:ignore
        }

        $result = $fetch ? $stmt->fetchAll(PDO::FETCH_ASSOC) : $stmt->rowCount();

        $stmt->closeCursor();

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction()
    {
        $this->connect();

        if (!$this->connection->beginTransaction()) {
            throw new RuntimeException('Failed to begin transaction.' . implode(' ', $this->connection->errorInfo())); // phpcs:ignore
        }
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction()
    {
        $this->connect();

        if (!$this->connection->commit()) {
            throw new RuntimeException('Failed to commit transaction. ' . implode(' ', $this->connection->errorInfo())); // phpcs:ignore
        }
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction()
    {
        $this->connect();

        if (!$this->connection->inTransaction()) {
            return;
        }

        if (!$this->connection->rollBack()) {
            throw new RuntimeException('Failed to rollback transaction. ' . implode(' ', $this->connection->errorInfo())); // phpcs:ignore
        }
    }

    /**
     * Inserts rows into a table with optional conflict handling and returning clause.
     *
     * @param string $table The table to insert into.
     * @param list<list<string, mixed>> $rows The rows to insert. `array[ array{ column => value, ... }, ... ]`.
     * Use callable as value to pass raw SQL, e.g. `fn() => 'NOW()'`
     * @phpcs:ignore
     * @param list<string, mixed>|false $conflict Update values on conflict `array{ column => value, ... }`, or `false` to ignore.
     * Use callable as value to pass raw SQL, e.g. `fn() => 'column = column + 1'`
     * @phpcs:ignore
     * @param string|string[]|false $returning Columns to return `"*" or "column" or array{ column, ... }` or `false` for no return
     *
     * @return array|bool The result set if returning is specified, otherwise true on success.
     */
    protected function queryInsertOnConflictUpdateReturning($table, $rows, $conflict = false, $returning = false, $conflict_columns = null)
    {
        $columns = implode(", ", array_map(static function ($column) {
            return "`$column`";
        }, array_keys($rows[0])));

        if ($conflict_columns) {
            $conflict_columns = implode(", ", array_map(static function ($column) {
                return "`$column`";
            }, $conflict_columns));
        } else {
            $conflict_columns = $columns;
        }

        $placeholders = $values = [];
        foreach ($rows as $index => $row) {
            foreach ($row as $column => $value) {
                if ($value instanceof Closure) {
                    $placeholders[$index][] = $value();
                    continue;
                }

                $key = ":{$column}_$index";
                $placeholders[$index][$column] = $key;
                $values[$key] = $value;
            }
        }

        $conflict_values = [];
        if ($conflict) {
            $conflict_placeholders = [];
            foreach ($conflict as $column => $value) {
                if ($value instanceof Closure) {
                    $conflict_placeholders[$column] = $value();
                    continue;
                }

                $key = ":{$column}_on_conflict";
                $conflict_placeholders[$key] = $value;
                $conflict_values[$key] = $value;
            }

            $conflict = implode(', ', array_map(static function ($column, $value) {
                return "`$column` = $value";
            }, array_keys($conflict_placeholders), array_values($conflict_placeholders)));
        }

        if ($returning) {
            is_string($returning) && $returning = [$returning];

            $returning = array_map(static function ($column) {
                if ($column instanceof Closure) {
                    return $column();
                }

                return $column === '*' ? '*' : "`$column`";
            }, $returning);

            $returning = implode(', ', $returning);
        }

        $placeholder_where = implode(" OR ", array_map(static function ($row) {
            return '(' . implode(" AND ", array_map(function ($column, $value) {
                    return "`$column` = $value";
                }, array_keys($row), array_values($row))) . ')';
        }, $placeholders));

        $result = 0;
        $insert_placeholders = $placeholders;
        $insert_values = $values;
        if (!$this->allowOnConflict && $conflict) {
            # SQLite 3.24.0- (emulate ON CONFLICT DO UPDATE)

            $result += $this->query(
                "UPDATE `$table` SET $conflict WHERE $placeholder_where",
                array_merge($values, $conflict_values),
                false
            );

            if ($result) {
                $existing = $this->query(
                    "SELECT $columns FROM `$table` WHERE $placeholder_where",
                    $values
                );

                $rows = static::arrayDiffMultiDimensional($rows, $existing);
                $insert_placeholders = $insert_values = [];
                foreach ($rows as $index => $row) {
                    foreach ($row as $column => $value) {
                        if ($value instanceof Closure) {
                            $insert_placeholders[$index][] = $value();
                            continue;
                        }

                        $key = ":{$column}_$index";
                        $insert_placeholders[$index][$column] = $key;
                        $insert_values[$key] = $value;
                    }
                }
            }
        }

        if (!$this->allowOnConflict) {
            # SQLite 3.24.0-

            if ($insert_values) {
                $insert_placeholder_values = implode(", ", array_map(static function ($row) {
                    return '(' . implode(", ", $row) . ')';
                }, $insert_placeholders));

                $result += $this->query(
                    "INSERT OR IGNORE INTO `$table` ($columns) VALUES $insert_placeholder_values",
                    $insert_values,
                    false
                );
            }
        } else {
            # SQLite 3.24.0+

            $placeholder_values = implode(", ", array_map(static function ($row) {
                return '(' . implode(", ", $row) . ')';
            }, $placeholders));

            $query = "INSERT INTO `$table` ($columns) VALUES $placeholder_values";

            if (!empty($conflict)) {
                $query .= " ON CONFLICT ($conflict_columns) DO UPDATE SET $conflict";
            }

            if ($this->allowReturning && $returning) {
                # SQLite 3.35.0+
                $query .= " RETURNING $returning";
            }

            $result = $this->query($query, array_merge($values, $conflict_values), !!$returning);

            if ($this->allowReturning || !$returning) {
                return $result;
            }
        }

        if ($returning) {
            # SQLite 3.35.0-
            return $this->query(
                "SELECT $returning FROM `$table` WHERE $placeholder_where",
                array_merge($values, $conflict_values)
            );
        }

        return $result;
    }

    /**
     * Escapes a value for safe insertion into the database.
     *
     * @param mixed $value The value to escape.
     *
     * @return string|null The escaped value.
     */
    protected static function escapeValue($value)
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            $value = null;
        } else {
            $value = (string)$value;
        }

        return $value;
    }

    protected static function arrayIsList(array $array)
    {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        $i = 0;
        foreach ($array as $k => $v) {
            if ($k !== $i++) {
                return false;
            }
        }

        return true;
    }

    protected static function arraySortMultiDimensional(array &$array)
    {
        $array = array_map(static function ($item) {
            if (is_array($item)) {
                if (static::arrayIsList($item)) {
                    sort($item);
                } else {
                    ksort($item);
                }
            }

            return $item;
        }, $array);

        if (static::arrayIsList($array)) {
            sort($array);
        } else {
            ksort($array);
        }
    }

    protected static function arrayUniqueMultiDimensional($array)
    {
        static::arraySortMultiDimensional($array);

        $serialized = array_map('serialize', $array);

        $unique = array_unique($serialized);

        return array_map('unserialize', $unique);
    }

    protected static function arrayDiffMultiDimensional($array1, $array2)
    {
        static::arraySortMultiDimensional($array1);
        static::arraySortMultiDimensional($array2);

        $serialized1 = array_map('serialize', $array1);
        $serialized2 = array_map('serialize', $array2);

        $diff = array_diff($serialized1, $serialized2);

        return array_map('unserialize', $diff);
    }

    /**
     * @inheritDoc
     */
    public function saveProfileInfo($data)
    {
        $this->connect();

        $count_increment = ['count' => static function () {
            return '`count` + 1';
        }];

        $identifier_id = $this->queryInsertOnConflictUpdateReturning(
            'profile_identifier_dictionary',
            [['value' => $data['identifier']]],
            $count_increment,
            'identifier_id'
        )[0]['identifier_id'];

        $group_id = -1;
        if (!empty($data['group'])) {
            $group_id = $this->queryInsertOnConflictUpdateReturning(
                'profile_group_dictionary',
                [['value' => $data['group']]],
                ['count' => $count_increment],
                'group_id'
            )[0]['group_id'];
        }

        $url_id = $this->queryInsertOnConflictUpdateReturning(
            'profile_url_dictionary',
            [['value' => $data['url']]],
            $count_increment,
            'url_id'
        )[0]['url_id'];

        $method_id = $this->queryInsertOnConflictUpdateReturning(
            'profile_method_dictionary',
            [['value' => $data['method']]],
            $count_increment,
            'method_id'
        )[0]['method_id'];

        $ip_id = null;
        if (!empty($data['ip'])) {
            $ip_id = $this->queryInsertOnConflictUpdateReturning(
                'profile_ip_dictionary',
                [['value' => $data['ip']]],
                $count_increment,
                'ip_id'
            )[0]['ip_id'];
        }

        $referer_id = null;
        if (!empty($data['referer'])) {
            $referer_id = $this->queryInsertOnConflictUpdateReturning(
                'profile_referer_dictionary',
                [['value' => $data['referer']]],
                $count_increment,
                'referer_id'
            )[0]['referer_id'];
        }

        $user_agent_id = null;
        if (!empty($data['user_agent'])) {
            $user_agent_id = $this->queryInsertOnConflictUpdateReturning(
                'profile_user_agent_dictionary',
                [['value' => $data['user_agent']]],
                $count_increment,
                'user_agent_id'
            )[0]['user_agent_id'];
        }

        $raw_body_id = null;
        if (!empty($data['raw_body'])) {
            $raw_body_id = $this->queryInsertOnConflictUpdateReturning(
                'profile_raw_body_dictionary',
                [['value' => $data['raw_body']]],
                $count_increment,
                'raw_body_id'
            )[0]['raw_body_id'];
        }

        $this->query("
            INSERT INTO `profile` (
                `identifier_id`, `group_id`, `start`, `duration`, `method_id`, `url_id`, `memory_peak`, `status`,
                `ip_id`, `referer_id`, `user_agent_id`, `raw_body_id`, `entries_count`, `error_count`
            ) VALUES (
                :identifier_id, :group_id, :start, :duration, :method_id, :url_id, :memory_peak, :status,
                :ip_id, :referer_id, :user_agent_id, :raw_body_id, :entries_count, :error_count
            )
        ", [
            ':identifier_id' => $identifier_id,
            ':group_id' => $group_id,
            ':start' => number_format($data['start'], 6, '.', ''),
            ':duration' => number_format($data['duration'], 6, '.', ''),
            ':method_id' => $method_id,
            ':memory_peak' => (int)$data['memory_peak'],
            ':status' => isset($data['status']) ? $data['status'] : null,
            ':url_id' => $url_id,
            ':ip_id' => $ip_id,
            ':referer_id' => $referer_id,
            ':user_agent_id' => $user_agent_id,
            ':raw_body_id' => $raw_body_id,
            ':entries_count' => !empty($data['entries_count']) ? $data['entries_count'] : 0,
            ':error_count' => !empty($data['error']) ? count($data['error']) : 0
        ], false);

        $profile_id = (int)$this->connection->lastInsertId();

        if (!empty($data['headers'])) {
            ($ids = array_unique(array_column($this->queryInsertOnConflictUpdateReturning(
                'profile_header_dictionary',
                array_map(static function ($name, $value) {
                    return ['name' => $name, 'value' => $value];
                }, array_keys($data['headers']), $data['headers']),
                $count_increment,
                'header_id'
            ), 'header_id')))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_header',
                array_map(static function ($id) use ($profile_id) {
                    return ['profile_id' => $profile_id, 'header_id' => $id];
                }, $ids)
            );
        }

        if (!empty($data['query'])) {
            ($ids = array_unique(array_column($this->queryInsertOnConflictUpdateReturning(
                'profile_query_field_dictionary',
                array_map(static function ($name, $value) {
                    return ['name' => $name, 'value' => static::escapeValue($value)];
                }, array_keys($data['query']), $data['query']),
                $count_increment,
                'query_field_id'
            ), 'query_field_id')))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_query_field',
                array_map(static function ($id) use ($profile_id) {
                    return ['profile_id' => $profile_id, 'query_field_id' => $id];
                }, $ids)
            );
        }

        if (!empty($data['body'])) {
            ($ids = array_unique(array_column($this->queryInsertOnConflictUpdateReturning(
                'profile_body_field_dictionary',
                array_map(static function ($name, $value) {
                    return ['name' => $name, 'value' => static::escapeValue($value)];
                }, array_keys($data['body']), $data['body']),
                $count_increment,
                'body_field_id'
            ), 'body_field_id')))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_body_field',
                array_map(static function ($id) use ($profile_id) {
                    return ['profile_id' => $profile_id, 'body_field_id' => $id];
                }, $ids)
            );
        }

        if (!empty($data['cookies'])) {
            ($ids = array_unique(array_column($this->queryInsertOnConflictUpdateReturning(
                'profile_cookie_dictionary',
                array_map(static function ($name, $value) {
                    return ['name' => $name, 'value' => static::escapeValue($value)];
                }, array_keys($data['cookies']), $data['cookies']),
                $count_increment,
                'cookie_id'
            ), 'cookie_id')))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_cookie',
                array_map(static function ($id) use ($profile_id) {
                    return ['profile_id' => $profile_id, 'cookie_id' => $id];
                }, $ids)
            );
        }

        if (!empty($data['files'])) {
            ($ids = array_unique(array_column($this->queryInsertOnConflictUpdateReturning(
                'profile_file_dictionary',
                array_filter(array_map(static function ($file) {
                    if (!isset($file['name'], $file['type'], $file['size'], $file['error'])) {
                        return null;
                    }

                    return [
                        'name' => $file['name'] ?: null,
                        'type' => $file['type'] ?: null,
                        'size' => $file['size'] ?: null,
                        'error' => $file['error'] ?: null,
                    ];
                }, $data['files'])),
                $count_increment,
                'files_id'
            ), 'files_id')))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_file',
                array_map(static function ($id) use ($profile_id) {
                    return ['profile_id' => $profile_id, 'files_id' => $id];
                }, $ids)
            );
        }

        if (!empty($data['server'])) {
            ($ids = array_unique(array_column($this->queryInsertOnConflictUpdateReturning(
                'profile_server_dictionary',
                array_map(static function ($name, $value) {
                    return ['name' => $name, 'value' => static::escapeValue($value)];
                }, array_keys($data['server']), $data['server']),
                $count_increment,
                'server_id'
            ), 'server_id')))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_server',
                array_map(static function ($id) use ($profile_id) {
                    return ['profile_id' => $profile_id, 'server_id' => $id];
                }, $ids)
            );
        }

        if (!empty($data['inc_files'])) {
            ($ids = array_unique(array_column($this->queryInsertOnConflictUpdateReturning(
                'profile_inc_file_dictionary',
                array_map(static function ($file) {
                    return ['value' => $file];
                }, $data['inc_files']),
                $count_increment,
                'inc_file_id'
            ), 'inc_file_id')))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_inc_file',
                array_map(static function ($id) use ($profile_id) {
                    return ['profile_id' => $profile_id, 'inc_file_id' => $id];
                }, $ids)
            );
        }

        if (!empty($data['extensions'])) {
            ($ids = array_unique(array_column($this->queryInsertOnConflictUpdateReturning(
                'profile_extension_dictionary',
                array_map(static function ($extension) {
                    return ['value' => $extension];
                }, $data['extensions']),
                $count_increment,
                'extension_id'
            ), 'extension_id')))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_extension',
                array_map(static function ($id) use ($profile_id) {
                    return ['profile_id' => $profile_id, 'extension_id' => $id];
                }, $ids)
            );
        }

        if (!empty($data['error'])) {
            ($values = static::arrayUniqueMultiDimensional(array_map(static function ($row) use ($profile_id, &$data) {
                $error = array_shift($data['error']);
                $entry_parent_id = isset($error['entry_parent_id']) ? $error['entry_parent_id'] : null;
                return [
                    'profile_id' => $profile_id,
                    'error_id' => $row['error_id'],
                    'entry_parent_id' => $entry_parent_id
                ];
            }, $this->queryInsertOnConflictUpdateReturning(
                'profile_error_dictionary',
                array_filter(array_map(static function ($error) {
                    if (!isset($error['severity'], $error['message'], $error['file'], $error['line'])) {
                        return null;
                    }

                    return [
                        'severity' => $error['severity'],
                        'message' => $error['message'],
                        'file' => $error['file'],
                        'line' => $error['line'],
                    ];
                }, $data['error'])),
                $count_increment,
                'error_id'
            ))))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_error',
                $values
            );
        }

        if (!empty($data['extra'])) {
            ($values = static::arrayUniqueMultiDimensional(array_map(static function ($row) use ($profile_id, &$data) {
                $extra = array_shift($data['extra']);
                return [
                    'profile_id' => $profile_id,
                    'extra_id' => $row['extra_id'],
                    'entry_parent_id' => isset($extra['entry_parent_id']) ? $extra['entry_parent_id'] : null
                ];
            }, $this->queryInsertOnConflictUpdateReturning(
                'profile_extra_dictionary',
                array_map(static function ($extra) {
                    return ['value' => static::escapeValue($extra['value'])];
                }, array_filter($data['extra'], static function ($extra) {
                    return isset($extra['value']);
                })),
                $count_increment,
                'extra_id'
            ))))

            && $this->queryInsertOnConflictUpdateReturning(
                'profile_extra',
                $values
            );
        }

        return $profile_id;
    }

    /**
     * @inheritDoc
     */
    public function saveProfileEntries($profile_id, $entries)
    {
        if (!$entries) {
            return false;
        }

        $json = json_encode($entries);
        if ($json === false) {
            return false;
        }

        $filename = $this->entriesDirectory . DIRECTORY_SEPARATOR . "$profile_id.json";

        if (extension_loaded('zlib') && $gz = gzencode($json, 9)) {
            $filename .= '.gz';
            $json = $gz;
        }

        return file_put_contents($filename, $json) !== false;
    }

    /**
     * @inheritDoc
     */
    public function saveProfileMetric($data)
    {
        // phpcs:disable
        $this->queryInsertOnConflictUpdateReturning(
            'profile_metrics',
            [[
                'identifier' => $data['identifier'],
                'type' => $data['type'],
                'group' => $data['group'],
                'count' => $data['count'],
                'avg_duration' => $data['duration'],
                'avg_memory_peak' => $data['memory_peak'],
                'last_profile_id' => $data['profile_id'],
                'last_duration' => $data['last_duration'],
                'last_memory_peak' => $data['last_memory_peak'],
                'min_duration_profile_id' => $data['profile_id'],
                'min_duration' => $data['min_duration'],
                'min_memory_peak_profile_id' => $data['profile_id'],
                'min_memory_peak' => $data['min_memory_peak'],
                'max_duration_profile_id' => $data['profile_id'],
                'max_duration' => $data['max_duration'],
                'max_memory_peak_profile_id' => $data['profile_id'],
                'max_memory_peak' => $data['max_memory_peak']
            ]],
            [
                'count' => static function () {
                    return 'profile_metrics.count + :count_0';
                },
                'avg_duration' => static function () {
                    return "(profile_metrics.avg_duration * profile_metrics.count + :avg_duration_0 * :count_0) / (profile_metrics.count + :count_0)";
                },
                'avg_memory_peak' => static function () {
                    return "(profile_metrics.avg_memory_peak * profile_metrics.count + :avg_memory_peak_0 * :count_0) / (profile_metrics.count + :count_0)";
                },
                'last_profile_id' => static function () {
                    return ':last_profile_id_0';
                },
                'last_duration' => static function () {
                    return ':last_duration_0';
                },
                'last_memory_peak' => static function () {
                    return ':last_memory_peak_0';
                },
                'min_duration_profile_id' => static function () {
                    return "CASE WHEN :min_duration_0 < profile_metrics.min_duration THEN :min_duration_profile_id_0 ELSE profile_metrics.min_duration_profile_id END";
                },
                'min_duration' => static function () {
                    return "CASE WHEN :min_duration_0 < profile_metrics.min_duration THEN :min_duration_0 ELSE profile_metrics.min_duration END";
                },
                'min_memory_peak_profile_id' => static function () {
                    return "CASE WHEN :min_memory_peak_0 < profile_metrics.min_memory_peak THEN :min_memory_peak_profile_id_0 ELSE profile_metrics.min_memory_peak_profile_id END";
                },
                'min_memory_peak' => static function () {
                    return "CASE WHEN :min_memory_peak_0 < profile_metrics.min_memory_peak THEN :min_memory_peak_0 ELSE profile_metrics.min_memory_peak END";
                },
                'max_duration_profile_id' => static function () {
                    return "CASE WHEN :max_duration_0 > profile_metrics.max_duration THEN :max_duration_profile_id_0 ELSE profile_metrics.max_duration_profile_id END";
                },
                'max_duration' => static function () {
                    return "CASE WHEN :max_duration_0 > profile_metrics.max_duration THEN :max_duration_0 ELSE profile_metrics.max_duration END";
                },
                'max_memory_peak_profile_id' => static function () {
                    return "CASE WHEN :max_memory_peak_0 > profile_metrics.max_memory_peak THEN :max_memory_peak_profile_id_0 ELSE profile_metrics.max_memory_peak_profile_id END";
                },
                'max_memory_peak' => static function () {
                    return "CASE WHEN :max_memory_peak_0 > profile_metrics.max_memory_peak THEN :max_memory_peak_0 ELSE profile_metrics.max_memory_peak END";
                },
            ],
            false,
            [
                'identifier',
                'type',
                'group'
            ]
        );
        // phpcs:enable
    }
}
