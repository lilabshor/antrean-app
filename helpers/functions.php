<?php

declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        session_set_cookie_params([
            "lifetime" => 0,
            "path" => "/",
            "domain" => "",
            "secure" => $is_https,
            "httponly" => true,
            "samesite" => "lax"
        ]);
        session_start();
    }
}

function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $csrf_token): bool
{
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || empty($csrf_token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $csrf_token);
}

function json_response(array $data, int $status_code = 200): void
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function get_available_time_slots(): array
{
    return [
        "08:00 - 09:00",
        "09:00 - 10:00",
        "10:00 - 11:00",
        "11:00 - 12:00",
        "13:00 - 14:00",
        "14:00 - 15:00",
        "15:00 - 16:00",
    ];
}

function format_indo_date(string $date_str): string
{
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    $timestamp = strtotime($date_str);
    if (!$timestamp) return $date_str;

    $d = date("j", $timestamp);
    $m = (int)date("n", $timestamp);
    $y = date("Y", $timestamp);

    return $d . " " . $bulan[$m] . " " . $y;
}
