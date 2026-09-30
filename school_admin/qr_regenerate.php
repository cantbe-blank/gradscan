<?php
/**
 * Issues a new QR code for a graduate (POST). The old code is invalidated, so
 * use this when a code is lost, leaked, or was already scanned by mistake.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/qr_helpers.php';
gs_require_role(['school_admin'], '../login.php');

$school_id = $_SESSION['school_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: graduates.php');
    exit;
}

$graduate_id = (int)($_POST['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT graduate_id FROM graduate WHERE graduate_id = ? AND school_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $graduate_id, $school_id);
mysqli_stmt_execute($stmt);

if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
    die('Graduate not found, or does not belong to your department.');
}

gs_issue_qr($conn, $graduate_id);
gs_delete_legacy_qr_file($graduate_id);

header('Location: graduates.php?qr_reissued=' . $graduate_id);
exit;
