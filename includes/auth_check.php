<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isAdmin(): bool
{
    return ($_SESSION['role'] ?? null) === 'admin';
}

function isKaryawan(): bool
{
    return ($_SESSION['role'] ?? null) === 'karyawan';
}

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: ' . appUrl('auth/login.php'));
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();

    if (!isAdmin()) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

function requireKaryawan(): void
{
    requireLogin();

    if (!isKaryawan()) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

function appUrl(string $path = ''): string
{
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(dirname(__DIR__));

    if ($documentRoot === false || $projectRoot === false
        || stripos($projectRoot, $documentRoot) !== 0) {
        $baseUrl = '';
    } else {
        $baseUrl = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            substr($projectRoot, strlen($documentRoot))
        );
        $baseUrl = rtrim($baseUrl, '/');
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
