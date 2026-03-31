<?php
$configLocalPath = dirname(__DIR__) . '/config.local.php';
if (is_file($configLocalPath)) {
    require_once $configLocalPath;
}

if (!function_exists('app_env')) {
    function app_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string) $value;
    }
}

if (!function_exists('app_project_root')) {
    function app_project_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('app_start_session')) {
    function app_start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('job_portal_session');
        session_start();
    }
}

if (!function_exists('app_redirect')) {
    function app_redirect(string $path, int $status = 302): void
    {
        header('Location: ' . $path, true, $status);
        exit();
    }
}

if (!function_exists('app_request_is_secure')) {
    function app_request_is_secure(): bool
    {
        $proto = strtolower(trim((string) explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        if ($proto !== '') {
            return $proto === 'https';
        }
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        $configured = trim(app_env('APP_BASE_PATH', ''));
        if ($configured !== '') {
            return '/' . trim($configured, '/');
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = str_replace('\\', '/', dirname($scriptName));
        if (str_ends_with($dir, '/admin')) {
            $dir = dirname($dir);
        }
        if ($dir === '\\' || $dir === '.') {
            return '';
        }
        return rtrim($dir, '/');
    }
}

if (!function_exists('app_base_url')) {
    function app_base_url(): string
    {
        $configured = trim(app_env('APP_BASE_URL', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = app_request_is_secure() ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . app_base_path();
    }
}

if (!function_exists('app_db_config')) {
    function app_db_config(): array
    {
        return [
            'host' => app_env('DB_HOST', '127.0.0.1'),
            'port' => (int) app_env('DB_PORT', '3306'),
            'name' => app_env('DB_NAME', 'job_application_portal'),
            'user' => app_env('DB_USER', 'root'),
            'pass' => app_env('DB_PASS', ''),
        ];
    }
}

if (!function_exists('app_db')) {
    function app_db(): mysqli
    {
        static $conn = null;
        if ($conn instanceof mysqli) {
            return $conn;
        }

        $db = app_db_config();
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], $db['port']);
        if ($conn->connect_errno) {
            http_response_code(500);
            exit('Database connection failed. Check config.local.php or environment variables.');
        }

        $conn->set_charset('utf8mb4');
        return $conn;
    }
}

if (!function_exists('fetch_rows')) {
    function fetch_rows($result): array
    {
        if (!$result instanceof mysqli_result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('has_column')) {
    function has_column(mysqli $conn, string $table, string $column): bool
    {
        $table = preg_replace('/[^A-Za-z0-9_]+/', '', $table) ?: '';
        $column = preg_replace('/[^A-Za-z0-9_]+/', '', $column) ?: '';
        if ($table === '' || $column === '') {
            return false;
        }

        $sql = sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $conn->real_escape_string($table), $conn->real_escape_string($column));
        $result = $conn->query($sql);
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('ensure_index')) {
    function ensure_index(mysqli $conn, string $table, string $indexName, string $definition): void
    {
        $table = preg_replace('/[^A-Za-z0-9_]+/', '', $table) ?: '';
        $indexName = preg_replace('/[^A-Za-z0-9_]+/', '', $indexName) ?: '';
        if ($table === '' || $indexName === '') {
            return;
        }

        $check = $conn->query("SHOW INDEX FROM `{$table}` WHERE Key_name='{$indexName}'");
        if ($check instanceof mysqli_result && $check->num_rows > 0) {
            return;
        }

        $conn->query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$definition}");
    }
}

if (!function_exists('uploads_dir')) {
    function uploads_dir(): string
    {
        $dir = app_project_root() . '/uploads/jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }
}

if (!function_exists('resolve_upload_file_path')) {
    function resolve_upload_file_path(string $fileName): ?string
    {
        $fileName = basename(trim($fileName));
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return null;
        }

        $path = uploads_dir() . '/' . $fileName;
        return is_file($path) ? $path : $path;
    }
}

if (!function_exists('detect_mime_type')) {
    function detect_mime_type(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return null;
    }
}