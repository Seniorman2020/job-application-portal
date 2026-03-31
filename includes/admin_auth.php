<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/bootstrap.php';

function portal_admin_email(): string
{
    return trim(app_env('PORTAL_ADMIN_EMAIL', 'admin@example.com'));
}

function portal_admin_password(): string
{
    return app_env('PORTAL_ADMIN_PASSWORD', 'ChangeMe123!');
}

function portal_admin_password_hash(): string
{
    return trim(app_env('PORTAL_ADMIN_PASSWORD_HASH', ''));
}

function portal_demo_autologin_enabled(): bool
{
    return in_array(strtolower(trim(app_env('PORTAL_DEMO_AUTOLOGIN', '0'))), ['1', 'true', 'yes', 'on'], true);
}

function portal_admin_is_logged_in(): bool
{
    if (empty($_SESSION['portal_admin_logged_in']) && portal_demo_autologin_enabled()) {
        $_SESSION['portal_admin_logged_in'] = true;
        $_SESSION['portal_admin_email'] = portal_admin_email();
    }

    return !empty($_SESSION['portal_admin_logged_in']);
}

function portal_admin_login_attempt(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    if ($email !== strtolower(portal_admin_email())) {
        return false;
    }

    $passwordHash = portal_admin_password_hash();
    $valid = $passwordHash !== ''
        ? password_verify($password, $passwordHash)
        : hash_equals(portal_admin_password(), $password);

    if (!$valid) {
        return false;
    }

    $_SESSION['portal_admin_logged_in'] = true;
    $_SESSION['portal_admin_email'] = portal_admin_email();
    return true;
}

function portal_admin_logout(): void
{
    unset($_SESSION['portal_admin_logged_in'], $_SESSION['portal_admin_email']);
}

function require_admin_auth(): void
{
    if (!portal_admin_is_logged_in()) {
        app_redirect('login.php');
    }
}
