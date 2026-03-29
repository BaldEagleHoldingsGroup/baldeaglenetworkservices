<?php
declare(strict_types=1);

if (!function_exists('ensure_session_started')) {
    function csrf_ttl(): int
    {
        return 3600;
    }

    function ensure_session_started(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = ben_scheme() === 'https';
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) csrf_ttl());
        session_set_cookie_params([
            'lifetime' => csrf_ttl(),
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    function clear_csrf_token(): void
    {
        ensure_session_started();
        unset($_SESSION['_csrf_token'], $_SESSION['_csrf_token_issued_at']);
    }

    function rotate_csrf_token(): string
    {
        ensure_session_started();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token_issued_at'] = time();

        return $_SESSION['_csrf_token'];
    }

    function csrf_token_is_fresh(): bool
    {
        $issuedAt = $_SESSION['_csrf_token_issued_at'] ?? null;
        if (!is_int($issuedAt)) {
            return false;
        }

        return (time() - $issuedAt) <= csrf_ttl();
    }

    function csrf_token(): string
    {
        ensure_session_started();

        if (
            empty($_SESSION['_csrf_token']) ||
            !is_string($_SESSION['_csrf_token']) ||
            !csrf_token_is_fresh()
        ) {
            return rotate_csrf_token();
        }

        return $_SESSION['_csrf_token'];
    }

    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    function verify_csrf_token(?string $token): bool
    {
        ensure_session_started();

        if (
            !isset($_SESSION['_csrf_token']) ||
            !is_string($_SESSION['_csrf_token']) ||
            $token === null ||
            !csrf_token_is_fresh()
        ) {
            clear_csrf_token();
            return false;
        }

        if (!hash_equals($_SESSION['_csrf_token'], $token)) {
            clear_csrf_token();
            return false;
        }

        return true;
    }
}
