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

function app_ensure_user_data_tables(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dashboard_state_user (
            user_id BIGINT PRIMARY KEY,
            funding_goal BIGINT NOT NULL DEFAULT 0,
            current_balance BIGINT NOT NULL DEFAULT 0,
            total_payouts BIGINT NOT NULL DEFAULT 0,
            total_expenses BIGINT NOT NULL DEFAULT 0,
            outstanding_challenges INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS funding_entries_user (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL,
            date DATE NOT NULL,
            amount NUMERIC(12,2) NOT NULL DEFAULT 0,
            firm TEXT NOT NULL DEFAULT '',
            action TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'ongoing',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_funding_entries_user_user_id_date ON funding_entries_user (user_id, date DESC, id DESC)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS calendar_entries_user (
            user_id BIGINT NOT NULL,
            date DATE NOT NULL,
            type TEXT NOT NULL DEFAULT 'profit',
            rr NUMERIC(10,2),
            percent_made NUMERIC(10,2),
            be NUMERIC(10,2),
            loss NUMERIC(10,2),
            win NUMERIC(10,2),
            reentry_win NUMERIC(10,2),
            reentry_loss NUMERIC(10,2),
            reentry_be NUMERIC(10,2),
            session TEXT NOT NULL DEFAULT '',
            outsession TEXT NOT NULL DEFAULT '',
            journal TEXT NOT NULL DEFAULT '',
            theory TEXT NOT NULL DEFAULT 'logical',
            ovr TEXT NOT NULL DEFAULT 'good',
            links TEXT NOT NULL DEFAULT '',
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            PRIMARY KEY (user_id, date)
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_calendar_entries_user_user_id_date ON calendar_entries_user (user_id, date)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS weekly_journal_entries_user (
            user_id BIGINT NOT NULL,
            week_start DATE NOT NULL,
            weekly_lessons TEXT NOT NULL DEFAULT '',
            overall_trading TEXT NOT NULL DEFAULT '',
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            PRIMARY KEY (user_id, week_start)
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_weekly_journal_entries_user_user_id_week_start ON weekly_journal_entries_user (user_id, week_start DESC)");
}
