<?php

function app_db_connect() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $database_url = getenv('DATABASE_URL');
    if (!$database_url) {
        return null;
    }

    $parts = parse_url($database_url);
    if ($parts === false || !isset($parts['host'], $parts['path'])) {
        throw new RuntimeException('Invalid DATABASE_URL format.');
    }

    $host = $parts['host'];
    $port = isset($parts['port']) ? (int)$parts['port'] : 5432;
    $user = isset($parts['user']) ? rawurldecode($parts['user']) : '';
    $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
    $dbname = ltrim((string)$parts['path'], '/');
    $sslmode = 'require';

    if (isset($parts['query'])) {
        parse_str($parts['query'], $query_params);
        if (!empty($query_params['sslmode'])) {
            $sslmode = (string)$query_params['sslmode'];
        }
    }

    $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';sslmode=' . $sslmode;

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function app_require_db() {
    $pdo = app_db_connect();
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    http_response_code(500);
    echo 'DATABASE_URL is missing. Add it in your Render Environment settings.';
    exit;
}

