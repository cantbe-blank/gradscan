<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/layout_helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized.']);
    exit;
}

$operator_id = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if ($token === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No token provided.']);
    exit;
}

/**
 * Records one scan attempt. School and graduation year are copied onto the
 * log row so history survives the graduate being deleted later.
 * Returns the scan's timestamp as stored by the database.
 */
function log_scan(mysqli $conn, ?int $qr_id, int $operator_id, string $status, ?int $school_id = null, ?int $year = null): string
{
    $stmt = mysqli_prepare($conn, "INSERT INTO scan_log (qr_id, operator_id, school_id, graduation_year, scan_status) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iiiis', $qr_id, $operator_id, $school_id, $year, $status);
    mysqli_stmt_execute($stmt);

    $scanId = mysqli_insert_id($conn);
    return mysqli_fetch_row(mysqli_query($conn, "SELECT scanned_at FROM scan_log WHERE scan_id = " . (int)$scanId))[0];
}

// --- Step: Retrieve Graduate ID (look up the QR by its token) ---
$qrStmt = mysqli_prepare($conn, "
    SELECT qr_code.qr_id, qr_code.graduate_id, qr_code.qr_status,
           qr_code.expires_at IS NOT NULL AND qr_code.expires_at <= NOW() AS is_expired,
           graduate.school_id, graduate.graduation_year
    FROM qr_code
    LEFT JOIN graduate ON graduate.graduate_id = qr_code.graduate_id
    WHERE qr_code.qr_token = ?
");
mysqli_stmt_bind_param($qrStmt, 's', $token);
mysqli_stmt_execute($qrStmt);
$qr = mysqli_fetch_assoc(mysqli_stmt_get_result($qrStmt));

// --- Step: Valid Graduate QR? (No: token not recognized at all) ---
if (!$qr) {
    log_scan($conn, null, $operator_id, 'not_found');

    echo json_encode(['error' => 'QR code not recognized.']);
    exit;
}

$qr_id = (int)$qr['qr_id'];
$school_id = $qr['school_id'] !== null ? (int)$qr['school_id'] : null;
$year = $qr['graduation_year'] !== null ? (int)$qr['graduation_year'] : null;

// --- Past its expiry date: mark it expired so later scans say so directly ---
if ($qr['qr_status'] === 'active' && $qr['is_expired']) {
    mysqli_query($conn, "UPDATE qr_code SET qr_status = 'expired' WHERE qr_id = $qr_id AND qr_status = 'active'");
    $qr['qr_status'] = 'expired';
}

// --- Branch on the QR's current status ---
if ($qr['qr_status'] !== 'active') {
    // already used, expired, or previously invalidated
    log_scan($conn, $qr_id, $operator_id, $qr['qr_status'], $school_id, $year);

    echo json_encode(['error' => 'This QR code is ' . $qr['qr_status'] . ' and cannot be used again.']);
    exit;
}

// --- Step: Search Graduate / Graduate Found? ---
$gradStmt = mysqli_prepare($conn, "SELECT * FROM graduate WHERE graduate_id = ?");
mysqli_stmt_bind_param($gradStmt, 'i', $qr['graduate_id']);
mysqli_stmt_execute($gradStmt);
$graduate = mysqli_fetch_assoc(mysqli_stmt_get_result($gradStmt));

if (!$graduate) {
    // shouldn't normally happen (FK constraints protect this), but handled defensively
    log_scan($conn, $qr_id, $operator_id, 'not_found');

    echo json_encode(['error' => 'Graduate record not found.']);
    exit;
}

// --- Step: mark QR as used (one-time trigger) ---
// The status condition makes this atomic: if two scanners submit the same code
// at once, only one UPDATE changes the row and the other is reported as used.
$updateQrStmt = mysqli_prepare($conn, "
    UPDATE qr_code SET qr_status = 'used', used_at = NOW()
    WHERE qr_id = ? AND qr_status = 'active' AND (expires_at IS NULL OR expires_at > NOW())
");
mysqli_stmt_bind_param($updateQrStmt, 'i', $qr_id);
mysqli_stmt_execute($updateQrStmt);

if (mysqli_stmt_affected_rows($updateQrStmt) !== 1) {
    log_scan($conn, $qr_id, $operator_id, 'used', $school_id, $year);

    echo json_encode(['error' => 'This QR code was just scanned and cannot be used again.']);
    exit;
}

// --- Step: Record Scan History ---
$scannedAt = log_scan($conn, $qr_id, $operator_id, 'success', $school_id, $year);

// --- Step: Retrieve School Layout ---
$layoutStmt = mysqli_prepare($conn, "SELECT layout_config FROM layout WHERE school_id = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($layoutStmt, 'i', $graduate['school_id']);
mysqli_stmt_execute($layoutStmt);
$layoutRow = mysqli_fetch_assoc(mysqli_stmt_get_result($layoutStmt));
$layoutConfig = $layoutRow ? json_decode($layoutRow['layout_config'], true) : null;

// --- Step: Generate Display (send graduate + layout data back to the frontend) ---
echo json_encode([
    'success'         => true,
    'graduate'        => $graduate,
    'layout'          => $layoutConfig,
    'display_html'    => gs_render_layout_canvas($layoutConfig, $graduate, '../'),
    'display_seconds' => gs_normalize_layout_config($layoutConfig)['display_seconds'],
    'scanned_at'      => $scannedAt,
    'scanned_at_text' => gs_format_datetime($scannedAt, 'g:i:s A'),
]);
