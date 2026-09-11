<?php
/**
 * Shared view helpers: escaping, URLs, flash messages, and input filters.
 */

/** Escape output for HTML to prevent XSS. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Project URL prefix (e.g. /pharmacy) so links work under XAMPP htdocs. */
function app_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($docRoot !== '' && str_starts_with($projectRoot, rtrim($docRoot, '/'))) {
        $base = substr($projectRoot, strlen(rtrim($docRoot, '/')));
    } else {
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        if (str_ends_with($script, '/admin') || str_ends_with($script, '/staff') || str_ends_with($script, '/pages') || str_ends_with($script, '/api')) {
            $script = dirname($script);
        }
        $base = $script === '/' ? '' : $script;
    }

    $base = rtrim($base, '/');
    return $base;
}

/** Build an absolute path under the project base (e.g. url('pages/login.php')). */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = app_base();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** One-time message shown after redirect (success / error / info). */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function money(float|string $amount): string
{
    return 'RM ' . number_format((float) $amount, 2);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post_int(string $key, int $default = 0): int
{
    return filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT) ?: $default;
}

function get_int(string $key, int $default = 0): int
{
    return filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT) ?: $default;
}

function current_path(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

function nav_active(string $needle): string
{
    $path = current_path();
    if ($needle === 'index.php') {
        $isHome = str_ends_with($path, '/index.php') || str_ends_with($path, '/') || preg_match('#/pharmacy/?$#', $path);
        $otherPortal = str_contains($path, '/admin/') || str_contains($path, '/staff/');
        return ($isHome && !$otherPortal) ? ' is-active' : '';
    }
    return str_contains($path, $needle) ? ' is-active' : '';
}
