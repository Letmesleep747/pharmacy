<?php

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

function portal_home(?string $role = null): string
{
    $role = $role ?? user_role();
    return match ($role) {
        'admin' => 'admin/index.php',
        'staff' => 'staff/index.php',
        default => 'index.php',
    };
}

function portal_login(?string $role = null): string
{
    $role = $role ?? user_role();
    return match ($role) {
        'admin' => 'admin/login.php',
        'staff' => 'staff/login.php',
        default => 'pages/login.php',
    };
}

function redirect_signed_in_user(): void
{
    if (!is_logged_in()) {
        return;
    }
    redirect(portal_home());
}

function require_storefront(): void
{
    $role = user_role();
    if ($role === 'staff' || $role === 'admin') {
        flash_set('info', 'You are signed into the ' . $role . ' site.');
        redirect(portal_home($role));
    }
}

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
