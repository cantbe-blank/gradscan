<?php
/**
 * Serves a graduate's current QR code as an image.
 *
 *   qr.php?id=12              SVG (for screens and printing; scales to any size)
 *   qr.php?id=12&format=png   PNG (high resolution, white border)
 *   &download=1               sends it as a file named after the student ID
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/qr_helpers.php';
gs_require_role(['school_admin'], '../login.php');

$school_id = $_SESSION['school_id'];
$graduate_id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT graduate_id, student_id FROM graduate WHERE graduate_id = ? AND school_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $graduate_id, $school_id);
mysqli_stmt_execute($stmt);
$graduate = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$qr = $graduate ? gs_current_qr($conn, $graduate_id) : null;

if (!$qr) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'No QR code for this graduate.';
    exit;
}

// Tokens never change once issued, but a reissue creates a new row, so let
// the browser revalidate instead of caching an old code
header('Cache-Control: private, no-cache');

$filenameBase = 'QR_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $graduate['student_id']);

if (($_GET['format'] ?? 'svg') === 'png') {
    header('Content-Type: image/png');
    if (!empty($_GET['download'])) {
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.png"');
    }
    echo gs_qr_png($qr['qr_token'], 16);
    exit;
}

header('Content-Type: image/svg+xml');
if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.svg"');
}
echo gs_qr_svg($qr['qr_token']);
