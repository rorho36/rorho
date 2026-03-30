<?php

function auth_start_session() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function auth_login_user($user_id, $email) {
    auth_start_session();
    $_SESSION['user'] = [
        'id' => (int)$user_id,
        'email' => (string)$email,
    ];
}

function auth_current_user() {
    auth_start_session();
    return $_SESSION['user'] ?? null;
}

function auth_require_login() {
    auth_start_session();
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function auth_logout_user() {
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

