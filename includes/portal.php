<?php
/**
 * Three separate sites (customer / staff / admin) share one database.
 * Each site has its own session cookie. Login pages only accept the matching role.
 */

/** Detect which site the current script belongs to from its URL path. */
function current_portal(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, '/admin/')) {
        return 'admin';
    }
    if (str_contains($script, '/staff/')) {
        return 'staff';
    }
    return 'customer';
}

/** PHP session cookie name — one per site so logins do not overwrite each other. */
function portal_session_name(?string $portal = null): string
{
    $prefix = defined('SESSION_NAME') ? SESSION_NAME : 'pharmacy';
    return match ($portal ?? current_portal()) {
        'admin' => $prefix . '_admin',
        'staff' => $prefix . '_staff',
        default => $prefix . '_customer',
    };
}

/** Cookie path limited to that site (admin cookie is not sent to the storefront). */
function portal_session_path(?string $portal = null): string
{
    $base = app_base();
    $root = $base === '' ? '/' : $base . '/';
    return match ($portal ?? current_portal()) {
        'admin' => rtrim($root, '/') . '/admin/',
        'staff' => rtrim($root, '/') . '/staff/',
        default => $root,
    };
}

/** Home page path for a role after successful sign-in. */
function portal_home(?string $role = null): string
{
    $role = $role ?? user_role();
    return match ($role) {
        'admin' => 'admin/index.php',
        'staff' => 'staff/index.php',
        default => 'index.php',
    };
}

/** Login page path for a role. */
function portal_login(?string $role = null): string
{
    $role = $role ?? user_role();
    return match ($role) {
        'admin' => 'admin/login.php',
        'staff' => 'staff/login.php',
        default => 'pages/login.php',
    };
}

/** If already signed in, send the user to their own portal home. */
function redirect_signed_in_user(): void
{
    if (!is_logged_in()) {
        return;
    }
    redirect(portal_home());
}

/** Customer pages only — staff/admin sessions are bounced to their own site. */
function require_storefront(): void
{
    $role = user_role();
    if ($role === 'staff' || $role === 'admin') {
        flash_set('info', 'You are signed into the ' . $role . ' site.');
        redirect(portal_home($role));
    }
}

/** Staff counter pages — admin and customers are not allowed here. */
function require_staff_portal(): void
{
    if (user_role() === 'staff') {
        return;
    }
    if (user_role() === 'admin') {
        flash_set('error', 'Administrators use the admin site.');
        redirect('admin/index.php');
    }
    flash_set('error', 'Sign in on the staff site.');
    redirect('staff/login.php');
}

/** Admin management pages — staff/customers are not allowed here. */
function require_admin_portal(): void
{
    if (is_admin()) {
        return;
    }
    if (user_role() === 'staff') {
        flash_set('error', 'Counter staff use the staff site.');
        redirect('staff/index.php');
    }
    flash_set('error', 'Sign in on the administrator site.');
    redirect('admin/login.php');
}

/** Staff counter tools that admins may also use (e.g. Rx approvals). */
function require_pharmacy_ops(): void
{
    $role = user_role();
    if ($role === 'staff' || $role === 'admin') {
        return;
    }
    flash_set('error', 'Sign in as staff or administrator.');
    redirect(current_portal() === 'admin' ? 'admin/login.php' : 'staff/login.php');
}

/**
 * Path prefix for shared ops pages (Rx / pickups) under the current portal.
 * Keeps admin sessions on /admin/ and staff sessions on /staff/.
 */
function ops_path(string $page): string
{
    $prefix = current_portal() === 'admin' ? 'admin' : 'staff';
    return $prefix . '/' . ltrim($page, '/');
}

/** Sidebar include for shared ops pages. */
function ops_nav_file(): string
{
    return current_portal() === 'admin' ? 'admin_nav' : 'staff_nav';
}

/**
 * Shared login POST handler for all three portals.
 * Password may be correct, but role must still match this site.
 * @return string Error message, or redirects on success
 */
function attempt_portal_login(string $expectedRole): string
{
    if (login_is_locked()) {
        return 'Too many failed attempts. Wait a minute and try again.';
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = authenticate(db(), $email, $password);

    if (!$user || $user['role'] !== $expectedRole) {
        record_login_failure();
        return 'Those details are not valid for this site.';
    }

    login_user($user);
    flash_set('success', 'Welcome back, ' . $user['name'] . '.');
    redirect(portal_home($expectedRole));
}
