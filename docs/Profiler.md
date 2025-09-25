# Profiler Documentation

The Profiler is a flexible tool for tracking performance, memory usage, and errors in PHP applications. It supports
nested actions, automatic error/exception logging, and customizable storage. Use it to analyze bottlenecks and monitor
your code in both web and CLI environments.

---

## 1. Initialization

Begin by configuring the Profiler at the start of your script or request. The identifier should describe the route,
script, or logical unit being profiled. Optionally, use a group to aggregate related profiles.

```php
use VSR\Analysis\Profiler;

$identifier = '<identifier>'; // e.g.: '/api/user', '/web/home', 'cli/script', ...

$group = null; // e.g.: 'api', 'web', 'cli', ...

$options = [
    // Start profiling immediately on construction
    PROFILER::AUTO_START => true
    
    // Automatically save profile (true = Profiler::AUTO_SAVE_ON_SHUTDOWN)
    Profiler::AUTO_SAVE => true | Profiler::AUTO_SAVE_ON_SHUTDOWN | Profiler::AUTO_SAVE_ON_DESTRUCT
    
    // Automatically save profile on script shutdown
    Profiler::AUTO_ERROR => true,

    // Alias to `$profile->setStorage($storage)`.
    Profiler::SET_STORAGE => $storage
];

$profile = new VSR\Analysis\Profiler($identifier, $group, $options);
```

---

## 2. Adding Actions (Nested Profiling)

Wrap code blocks with `start()` and `stop()` to measure specific actions or sections. You can nest actions for detailed
profiling.

```php
try {
    $action_identifier = '<entry_identifier>'; // e.g.: 'controller/method', 'model/action', ...
    $action_group = null; // e.g.: 'payment_service', 'query_database_1', ...
    $profile->start($action_identifier, $action_group); // Begin action
    // ... your code ...
} catch (Throwable $e) {
    $profile->error($e); // Log error in the current action
    // ... error handling ...
} finally {
    $extra = null; // Optional: add context info, e.g. ['user_id' => 123]
    $profile->extra($extra);
    $profile->stop(); // End action
}
```

---

## 3. Saving the Profile

If not using auto-save, call `save()` at the end of your script or request:

```php
$profile->save();
```

---

## 4. Customizing Data Before Save

You can register a callback to modify or filter the data before it is saved. Return `null` to cancel saving.

```php
$profile->onSave(function (array $data) {
    if ($data['duration'] < 0.100 && !$data['error']) {
        return null; // Cancel saving exemple
    }

    if ($data['duration'] < 0.500 && !$data['error']) {
        // Remove optional fields to save space
        foreach ([
            'referer', 'user_agent', 'query', 'body', 'raw_body', 'cookies',
            'files', 'server', 'inc_files', 'extensions', 'error', 'extra',
            'entries' // set 'entries' to null to avoid saving nested actions in JSON files
        ] as $field) {
            $data[$field] = null;
        }
    }

    return $data;
});
```

**Required fields:**

- `identifier`: string
- `group`: string|null
- `start`: float (timestamp)
- `duration`: float (seconds)
- `method`: string (HTTP method or 'CLI')
- `url`: string
- `memory_peak`: int (bytes)

**Optional fields:**

- `status`: int (HTTP status)
- `headers`: array
- `ip`: string
- `referer`: string
- `user_agent`: string
- `query`: array
- `body`: array
- `raw_body`: string
- `cookies`: array
- `files`: array
- `server`: array
- `inc_files`: array
- `extensions`: array
- `entries`: array (nested actions)
- `entries_count`: int
- `error`: array (errors/exceptions)
- `extra`: array (custom data)

---

## Troubleshooting & Tips

- Always ensure all `start()` calls are matched with `stop()`.
- Use meaningful identifiers and groups for better analysis.
- If you see no data, check that storage is configured and writable.
- Use the `onSave` callback to filter or enrich data as needed.
- For CLI scripts, method is set to 'CLI' and URL is the script path.

---

### Navigation

[← Installation & Quick Start](../README.md)&emsp;|&emsp;[Viewer Documentation →](./Viewer.md)
