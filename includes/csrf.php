<?php
/**
 * Cross-Site Request Forgery (CSRF) protection for HTML forms.
 * Every POST must include the hidden _csrf field; init.php runs csrf_verify().
 */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Hidden input to embed in forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Reject POST requests that do not carry a matching token (timing-safe compare). */
function csrf_verify(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        echo 'Invalid security token. Please go back and try again.';
        exit;
    }
}
