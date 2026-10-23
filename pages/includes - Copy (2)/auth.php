<?php

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

function is_staff(): bool
{
    $role = user_role();
    return $role === 'staff' || $role === 'admin';
}

function is_admin(): bool
{
    return user_role() === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('error', 'Please sign in to continue.');
        redirect(portal_login('customer'));
    }
}

function require_staff(): void
{
    require_staff_portal();
}

function require_admin(): void
{
    require_admin_portal();
}

function login_is_locked(): bool
{
    $until = $_SESSION['login_lock_until'] ?? 0;
    return is_int($until) && $until > time();
}

function record_login_failure(): void
{
    $fails = (int) ($_SESSION['login_fails'] ?? 0) + 1;
    $_SESSION['login_fails'] = $fails;
    if ($fails >= 5) {
        $_SESSION['login_lock_until'] = time() + 60;
    }
}

function authenticate(mysqli $db, string $email, string $password): ?array
{
    $user = db_one($db, 'SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1', 's', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}
