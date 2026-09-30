<?php
/**
 * GradScan QR code helpers.
 *
 * A graduate's QR code is just their qr_code.qr_token. Images are rendered on
 * request from the token (SVG for screens and print, PNG for downloads), so
 * they're sharp at any size and no image files need to be kept in sync.
 *
 * A graduate normally has one active code. Reissuing a code invalidates the
 * old one (scanning it then logs "invalidated") and keeps it for history.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** Scalable SVG markup (no XML header, so it can be inlined in HTML). */
function gs_qr_svg(string $token): string
{
    $options = new QROptions([
        'outputInterface' => QRMarkupSVG::class,
        'outputBase64'    => false,
        'svgAddXmlHeader' => false,
        'eccLevel'        => EccLevel::M,   // tolerates glare and scuffs on phone screens and prints
        'addQuietzone'    => true,
        'quietzoneSize'   => 4,
        'connectPaths'    => true,
        'drawLightModules'=> false,
    ]);

    // Fill the container it's placed in; the viewBox keeps it square
    return preg_replace('/<svg /', '<svg width="100%" height="100%" role="img" aria-label="QR code" ', (new QRCode($options))->render($token), 1);
}

/** PNG bytes with a white border; $scale is pixels per module. */
function gs_qr_png(string $token, int $scale = 12): string
{
    $options = new QROptions([
        'outputInterface' => QRGdImagePNG::class,
        'outputBase64'    => false,
        'eccLevel'        => EccLevel::M,
        'addQuietzone'    => true,
        'quietzoneSize'   => 4,
        'scale'           => max(1, min(40, $scale)),
    ]);

    return (new QRCode($options))->render($token);
}

/**
 * The graduate's current QR code row (the newest one), or null if they have
 * never been issued one.
 *
 * @return array{qr_id:int, qr_token:string, qr_status:string, issued_at:string, used_at:?string}|null
 */
function gs_current_qr(mysqli $conn, int $graduate_id): ?array
{
    $stmt = mysqli_prepare($conn, "SELECT qr_id, qr_token, qr_status, issued_at, used_at FROM qr_code WHERE graduate_id = ? ORDER BY qr_id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $graduate_id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
}

/**
 * Issues a new QR code for a graduate. Any active code they had is marked
 * invalidated first, so a lost or leaked code stops working.
 * Returns the new token.
 */
function gs_issue_qr(mysqli $conn, int $graduate_id): string
{
    $token = bin2hex(random_bytes(16));

    mysqli_begin_transaction($conn);
    try {
        $invalidate = mysqli_prepare($conn, "UPDATE qr_code SET qr_status = 'invalidated' WHERE graduate_id = ? AND qr_status = 'active'");
        mysqli_stmt_bind_param($invalidate, 'i', $graduate_id);
        mysqli_stmt_execute($invalidate);

        $insert = mysqli_prepare($conn, "INSERT INTO qr_code (graduate_id, qr_token, qr_status) VALUES (?, ?, 'active')");
        mysqli_stmt_bind_param($insert, 'is', $graduate_id, $token);
        mysqli_stmt_execute($insert);

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }

    return $token;
}

/** Removes the PNG an older version of GradScan saved for this graduate, if any. */
function gs_delete_legacy_qr_file(int $graduate_id): void
{
    $file = dirname(__DIR__) . '/qrcodes/qr_' . $graduate_id . '.png';
    if (is_file($file)) {
        unlink($file);
    }
}
