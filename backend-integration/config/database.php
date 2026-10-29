<?php
/**
 * Database Configuration
 * Loads local credentials from the project-root .env file. Process environment
 * variables take precedence so deployment secrets can be injected externally.
 */

$projectRoot = dirname(__DIR__, 2);
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';

if (is_file($envFile)) {
    if (!is_readable($envFile)) {
        throw new RuntimeException('The project .env file exists but cannot be read');
    }

    $envLines = file($envFile, FILE_IGNORE_NEW_LINES);
    if ($envLines === false) {
        throw new RuntimeException('Unable to read the project .env file');
    }

    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            throw new RuntimeException('Invalid .env entry; expected KEY=value');
        }

        $name = $matches[1];
        $value = trim($matches[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

$databasePort = filter_var(getenv('DB_PORT') ?: '3307', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535]
]);
if ($databasePort === false) {
    throw new RuntimeException('DB_PORT must be a valid TCP port number');
}

$GLOBALS['HRMS_DATABASE_CONFIG'] = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => $databasePort,
    'dbname' => getenv('DB_NAME') ?: 'db_employee',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') === false ? '' : getenv('DB_PASS'),
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

function getDatabaseConnection(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = $GLOBALS['HRMS_DATABASE_CONFIG'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['charset']
    );

    $connection = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $config['options']
    );

    return $connection;
}

function getAdminDatabaseConnection(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = $GLOBALS['HRMS_DATABASE_CONFIG'];
    $host = getenv('ADMIN_DB_HOST') ?: $config['host'];
    $port = filter_var(getenv('ADMIN_DB_PORT') ?: (string) $config['port'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535]
    ]);
    if ($port === false) {
        throw new RuntimeException('ADMIN_DB_PORT must be a valid TCP port number');
    }

    $database = getenv('ADMIN_DB_NAME') ?: 'careerportal';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new RuntimeException('ADMIN_DB_NAME must be a valid database name');
    }

    $connection = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $config['charset']),
        getenv('ADMIN_DB_USER') === false ? $config['username'] : getenv('ADMIN_DB_USER'),
        getenv('ADMIN_DB_PASS') === false ? $config['password'] : getenv('ADMIN_DB_PASS'),
        $config['options']
    );

    return $connection;
}