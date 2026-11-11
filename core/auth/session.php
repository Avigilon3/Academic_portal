<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function is_valid_csrf_token(mixed $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    return is_string($sessionToken)
        && $sessionToken !== ''
        && is_string($token)
        && $token !== ''
        && hash_equals($sessionToken, $token);
}

function regenerate_authenticated_session(): void
{
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to secure the authenticated session.');
    }

    unset($_SESSION['csrf_token']);
}

function set_flash($k, $v) {
    $_SESSION['flash'][$k] = $v;
}

function get_flash($k) {
    if (isset($_SESSION['flash'][$k])) {
        $v = $_SESSION['flash'][$k];
        unset($_SESSION['flash'][$k]);
        return $v;
    }
    return null;
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function currentUserName(): string
{

    if (!empty($_SESSION['user']['name'])) {
        return $_SESSION['user']['name'];
    }

    $first = $_SESSION['user']['first_name'] ?? '';
    $last  = $_SESSION['user']['last_name'] ?? '';
    $full  = trim($first . ' ' . $last);
    if ($full !== '') {
        return $full;
    }

    if (!empty($_SESSION['user']['email'])) {
        return $_SESSION['user']['email'];
    }

    return 'User';
}
