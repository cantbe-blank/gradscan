<?php
/**
 * App-wide settings shared by every page. Required by config/config.php
 * right after the database connection is made.
 *
 * Timestamps are created by MySQL (NOW(), DEFAULT current_timestamp()), so the
 * connection's time zone is set to match PHP's. That keeps stored and displayed
 * times in ceremony-local time no matter how PHP or MySQL are configured.
 */

const APP_TIMEZONE = 'Asia/Manila';

/** Default minimum seconds each graduate stays on the audience display. */
const GS_DEFAULT_DISPLAY_SECONDS = 5;

date_default_timezone_set(APP_TIMEZONE);

if (isset($conn) && $conn) {
    // e.g. "+08:00"; an offset works even when MySQL has no time zone tables loaded
    mysqli_query($conn, "SET time_zone = '" . (new DateTime())->format('P') . "'");
}

/**
 * Formats a DB timestamp ("2026-09-28 19:26:21") for display, e.g.
 * "Sep 28, 2026 7:26:21 PM". Returns $empty for NULL/blank values.
 */
function gs_format_datetime(?string $value, string $format = 'M j, Y g:i:s A', string $empty = '—'): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000')) {
        return $empty;
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value) ?: new DateTime($value);
    return $dt->format($format);
}

/** Formats a number of seconds as "45s", "3m 05s" or "1h 02m". */
function gs_format_duration(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '—';
    }
    if ($seconds < 60) {
        return $seconds . 's';
    }
    if ($seconds < 3600) {
        return intdiv($seconds, 60) . 'm ' . sprintf('%02ds', $seconds % 60);
    }
    return intdiv($seconds, 3600) . 'h ' . sprintf('%02dm', intdiv($seconds % 3600, 60));
}

/**
 * Stops the request unless the logged-in user has one of $roles.
 * $loginPath is the relative path from the page to login.php.
 */
function gs_require_role(array $roles, string $loginPath): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: ' . $loginPath);
        exit;
    }
}
