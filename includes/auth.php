<?php
/**
 * Authentication and authorisation helpers.
 * Passwords are checked with password_verify() against bcrypt hashes in users.password_hash.
 */

/** Currently signed-in user from the session, or null. */
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

/** Staff desk role: true for staff OR admin (legacy helper). */
function is_staff(): bool
{
    $role = user_role();
    return $role === 'staff' || $role === 'admin';
}

function is_admin(): bool
{
    return user_role() === 'admin';
}

/** Store a safe subset of the user row in the session after a successful login. */
function login_user(array $user): void
{
    // New session id after login helps prevent session-fixation attacks.
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

/** Simple brute-force guard: lock login attempts for 60 seconds after 5 failures. */
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

/**
 * Look up user by email and verify the password hash.
 * Uses a prepared statement so the email cannot inject SQL.
 */
function authenticate(mysqli $db, string $email, string $password): ?array
{
    $user = db_one($db, 'SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1', 's', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}
