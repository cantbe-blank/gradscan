<?php
session_start();
require_once __DIR__ . '/../config/config.php';

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

// --- Step: Retrieve Graduate ID (look up the QR by its token) ---
$qrStmt = mysqli_prepare($conn, "SELECT qr_id, graduate_id, qr_status FROM qr_code WHERE qr_token = ?");
mysqli_stmt_bind_param($qrStmt, 's', $token);
mysqli_stmt_execute($qrStmt);
$qrResult = mysqli_stmt_get_result($qrStmt);
$qr = mysqli_fetch_assoc($qrResult);

// --- Step: Valid Graduate QR? (No: token not recognized at all) ---
if (!$qr) {
    $logStmt = mysqli_prepare($conn, "INSERT INTO scan_log (qr_id, operator_id, scan_status) VALUES (NULL, ?, 'not_found')");
    mysqli_stmt_bind_param($logStmt, 'i', $operator_id);
    mysqli_stmt_execute($logStmt);

    echo json_encode(['error' => 'QR code not recognized.']);
    exit;
}

// --- Branch on the QR's current status ---
if ($qr['qr_status'] !== 'active') {
    // already used, expired, or previously invalidated
    $logStmt = mysqli_prepare($conn, "INSERT INTO scan_log (qr_id, operator_id, scan_status) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($logStmt, 'iis', $qr['qr_id'], $operator_id, $qr['qr_status']);
    mysqli_stmt_execute($logStmt);

    echo json_encode(['error' => 'This QR code is ' . $qr['qr_status'] . ' and cannot be used again.']);
    exit;
}

// --- Step: Search Graduate / Graduate Found? ---
$gradStmt = mysqli_prepare($conn, "SELECT * FROM graduate WHERE graduate_id = ?");
mysqli_stmt_bind_param($gradStmt, 'i', $qr['graduate_id']);
mysqli_stmt_execute($gradStmt);
$gradResult = mysqli_stmt_get_result($gradStmt);
$graduate = mysqli_fetch_assoc($gradResult);

if (!$graduate) {
    // shouldn't normally happen (FK constraints protect this), but handled defensively
    $logStmt = mysqli_prepare($conn, "INSERT INTO scan_log (qr_id, operator_id, scan_status) VALUES (?, ?, 'not_found')");
    mysqli_stmt_bind_param($logStmt, 'ii', $qr['qr_id'], $operator_id);
    mysqli_stmt_execute($logStmt);

    echo json_encode(['error' => 'Graduate record not found.']);
    exit;
}

// --- Step: Retrieve School Layout ---
$layoutStmt = mysqli_prepare($conn, "SELECT layout_config FROM layout WHERE school_id = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($layoutStmt, 'i', $graduate['school_id']);
mysqli_stmt_execute($layoutStmt);
$layoutResult = mysqli_stmt_get_result($layoutStmt);
$layoutRow = mysqli_fetch_assoc($layoutResult);
$layoutConfig = $layoutRow ? json_decode($layoutRow['layout_config'], true) : null;

// --- Step: mark QR as used (one-time trigger, per earlier decision) ---
$updateQrStmt = mysqli_prepare($conn, "UPDATE qr_code SET qr_status = 'used', used_at = NOW() WHERE qr_id = ?");
mysqli_stmt_bind_param($updateQrStmt, 'i', $qr['qr_id']);
mysqli_stmt_execute($updateQrStmt);

// --- Step: Record Scan History ---
$logStmt = mysqli_prepare($conn, "INSERT INTO scan_log (qr_id, operator_id, scan_status) VALUES (?, ?, 'success')");
mysqli_stmt_bind_param($logStmt, 'ii', $qr['qr_id'], $operator_id);
mysqli_stmt_execute($logStmt);

// --- Step: Generate Display (send graduate + layout data back to the frontend) ---
echo json_encode([
    'success' => true,
    'graduate' => $graduate,
    'layout' => $layoutConfig,
]);
