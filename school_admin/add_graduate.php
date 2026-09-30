<?php
session_start();

require_once __DIR__ . '/../config/config.php';
gs_require_role(['school_admin'], '../login.php');

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/qr_helpers.php';
$school_id = $_SESSION['school_id'];

$errors = [];
$success = false;

$importResults = null; // set only when the Excel import path runs

$honorOptions = [
    'none' => 'No Honors',
    'cum laude' => 'Cum Laude',
    'magna cum laude' => 'Magna Cum Laude',
    'summa cum laude' => 'Summa Cum Laude',
];

/**
 * Inserts one graduate row + generates its QR code.
 * Shared by both the manual-entry form and the Excel import loop
 * so the QR generation logic only lives in one place.
 *
 * @return int the new graduate_id
 */
function insertGraduateAndGenerateQr(
    mysqli $conn,
    int $school_id,
    string $student_id,
    string $first_name,
    string $middle_name,
    string $last_name,
    string $suffix,
    string $course,
    string $address,
    string $major,
    string $honors,
    string $graduation_year
): int {
    $insertStmt = mysqli_prepare($conn, "INSERT INTO graduate (school_id, student_id, first_name, middle_name, last_name, suffix, course, address, major, honors, graduation_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param(
        $insertStmt,
        'i' . str_repeat('s', 10),
        $school_id,
        $student_id,
        $first_name,
        $middle_name,
        $last_name,
        $suffix,
        $course,
        $address,
        $major,
        $honors,
        $graduation_year
    );
    mysqli_stmt_execute($insertStmt);
    $graduate_id = mysqli_insert_id($conn);

    // --- Issue the graduate's QR code (images are rendered on request, see qr.php) ---
    gs_issue_qr($conn, $graduate_id);

    return $graduate_id;
}

function studentIdExists(mysqli $conn, string $student_id): bool
{
    $checkStmt = mysqli_prepare($conn, "SELECT graduate_id FROM graduate WHERE student_id = ?");
    mysqli_stmt_bind_param($checkStmt, 's', $student_id);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);
    return mysqli_stmt_num_rows($checkStmt) > 0;
}

/**
 * Saves one extracted photo from the uploaded ZIP into photos/ for the given
 * graduate and updates their `photo` column. Returns true on success.
 */
function savePhotoFromZip(mysqli $conn, ZipArchive $zip, int $zipIndex, int $graduate_id, string $originalFilename): bool
{
    $photoDir = __DIR__ . '/../photos/';
    if (!is_dir($photoDir)) {
        mkdir($photoDir, 0755, true);
    }

    $photoData = $zip->getFromIndex($zipIndex);
    if ($photoData === false) {
        return false;
    }

    $extension = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'jpg';
    $photoFilename = 'photo_' . $graduate_id . '.' . $extension;
    $photoRelPath = 'photos/' . $photoFilename;

    file_put_contents($photoDir . $photoFilename, $photoData);

    $photoUpdateStmt = mysqli_prepare($conn, "UPDATE graduate SET photo = ? WHERE graduate_id = ?");
    mysqli_stmt_bind_param($photoUpdateStmt, 'si', $photoRelPath, $graduate_id);
    mysqli_stmt_execute($photoUpdateStmt);

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =========================================================
    // Path 1: Bulk import from an uploaded Excel file
    // =========================================================
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'excel_import') {

        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please choose a valid Excel file to import.';
        } else {
            $allowedExt = ['xlsx', 'xls'];
            $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'Only .xlsx or .xls files are supported.';
            } else {
                try {
                    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray(null, true, true, false);

                    // Expected column order (row 1 = header, skipped):
                    // Student ID | First Name | Middle Name | Last Name | Suffix | Course | Major | Address | Honors | Graduation Year | Photo Filename
                    $imported = 0;
                    $skipped = [];
                    $photoNotes = [];
                    $seenInBatch = [];

                    // --- Optional: open the photo ZIP and index it by filename (case-insensitive) ---
                    $photoZip = null;
                    $photoMap = [];

                    if (isset($_FILES['photo_zip']) && $_FILES['photo_zip']['error'] === UPLOAD_ERR_OK) {
                        $photoZip = new ZipArchive();
                        if ($photoZip->open($_FILES['photo_zip']['tmp_name']) === true) {
                            for ($z = 0; $z < $photoZip->numFiles; $z++) {
                                $entryName = $photoZip->getNameIndex($z);
                                $base = basename($entryName);

                                // Skip folder entries, __MACOSX junk, and hidden files
                                if (str_ends_with($entryName, '/') || str_contains($entryName, '__MACOSX') || $base === '' || $base[0] === '.') {
                                    continue;
                                }

                                $photoMap[strtolower($base)] = $z;
                            }
                        } else {
                            $photoZip = null;
                            $errors[] = 'The photo ZIP file could not be opened — graduates were imported without photos. Add photos individually via Edit.';
                        }
                    }

                    foreach ($rows as $i => $row) {
                        if ($i === 0) {
                            continue; // header row
                        }

                        $student_id      = trim((string)($row[0] ?? ''));
                        $first_name      = trim((string)($row[1] ?? ''));
                        $middle_name     = trim((string)($row[2] ?? ''));
                        $last_name       = trim((string)($row[3] ?? ''));
                        $suffix          = trim((string)($row[4] ?? ''));
                        $course          = trim((string)($row[5] ?? ''));
                        $major           = trim((string)($row[6] ?? ''));
                        $address         = trim((string)($row[7] ?? ''));
                        $honors          = trim((string)($row[8] ?? 'none'));
                        $graduation_year = trim((string)($row[9] ?? ''));
                        $photo_filename  = trim((string)($row[10] ?? ''));

                        // Skip fully blank rows silently (e.g. trailing empty rows in the sheet)
                        if ($student_id === '' && $first_name === '' && $last_name === '') {
                            continue;
                        }

                        $rowLabel = 'Row ' . ($i + 1) . ($student_id !== '' ? " ({$student_id})" : '');

                        if ($student_id === '' || $first_name === '' || $last_name === '' || $course === '' || $address === '' || $graduation_year === '') {
                            $skipped[] = "$rowLabel — missing a required field.";
                            continue;
                        }

                        if (!array_key_exists($honors, $honorOptions)) {
                            $honors = 'none';
                        }

                        if (isset($seenInBatch[$student_id])) {
                            $skipped[] = "$rowLabel — duplicate Student ID within this file.";
                            continue;
                        }

                        if (studentIdExists($conn, $student_id)) {
                            $skipped[] = "$rowLabel — Student ID already exists in the system.";
                            continue;
                        }

                        $graduate_id = insertGraduateAndGenerateQr(
                            $conn,
                            $school_id,
                            $student_id,
                            $first_name,
                            $middle_name,
                            $last_name,
                            $suffix,
                            $course,
                            $address,
                            $major,
                            $honors,
                            $graduation_year
                        );

                        // --- Match this row's photo, if a filename was given ---
                        if ($photo_filename !== '') {
                            $lookupKey = strtolower(basename($photo_filename));

                            if ($photoZip !== null && isset($photoMap[$lookupKey])) {
                                $saved = savePhotoFromZip($conn, $photoZip, $photoMap[$lookupKey], $graduate_id, $photo_filename);
                                if (!$saved) {
                                    $photoNotes[] = "$rowLabel — photo \"$photo_filename\" could not be read from the ZIP.";
                                }
                            } elseif ($photoZip === null) {
                                $photoNotes[] = "$rowLabel — photo filename given but no ZIP was uploaded.";
                            } else {
                                $photoNotes[] = "$rowLabel — \"$photo_filename\" not found in the ZIP.";
                            }
                        }

                        $seenInBatch[$student_id] = true;
                        $imported++;
                    }

                    if ($photoZip !== null) {
                        $photoZip->close();
                    }

                    $importResults = [
                        'imported'   => $imported,
                        'skipped'    => $skipped,
                        'photoNotes' => $photoNotes,
                    ];

                    if ($imported > 0) {
                        $success = true;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Could not read the uploaded file. Make sure it matches the template format.';
                }
            }
        }

    // =========================================================
    // Path 2: Manual single-graduate entry (unchanged behavior)
    // =========================================================
    } else {
        $student_id  = trim($_POST['student_id']);
        $first_name  = trim($_POST['first_name']);
        $middle_name = trim($_POST['middle_name']);
        $last_name   = trim($_POST['last_name']);
        $suffix      = trim($_POST['suffix']);
        $course      = trim($_POST['course']);
        $major       = trim($_POST['major']);
        $address     = trim($_POST['address']);
        $honors      = $_POST['honors'];
        $graduation_year = trim($_POST['graduation_year']);

        if ($student_id === '' || $first_name === '' || $last_name === '' || $course === '' || $address === '' || $graduation_year === '') {
            $errors[] = 'Please fill out all required fields.';
        }

        if (empty($errors) && studentIdExists($conn, $student_id)) {
            $errors[] = 'A graduate with this Student ID already exists.';
        }

        if (empty($errors)) {
            $graduate_id = insertGraduateAndGenerateQr(
                $conn,
                $school_id,
                $student_id,
                $first_name,
                $middle_name,
                $last_name,
                $suffix,
                $course,
                $address,
                $major,
                $honors,
                $graduation_year
            );

            // --- Handle photo upload (manual entry only — imports skip photos) ---
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $photoDir = __DIR__ . '/../photos/';
                if (!is_dir($photoDir)) {
                    mkdir($photoDir, 0755, true);
                }

                $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photoFilename = 'photo_' . $graduate_id . '.' . $extension;
                $photoPath = 'photos/' . $photoFilename;

                move_uploaded_file($_FILES['photo']['tmp_name'], $photoDir . $photoFilename);

                $photoUpdateStmt = mysqli_prepare($conn, "UPDATE graduate SET photo = ? WHERE graduate_id = ?");
                mysqli_stmt_bind_param($photoUpdateStmt, 'si', $photoPath, $graduate_id);
                mysqli_stmt_execute($photoUpdateStmt);
            }

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Tailwind -->
    <link rel="stylesheet" href="../frontend/dist/output.css">

    <title>GradScan | Add Graduate</title>
</head>
<body class="min-h-screen bg-slate-100 font-poppins text-ascot-text">
<div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside class="flex w-64 flex-shrink-0 flex-col bg-ascot-dark">
        <a href="dashboard.php" class="flex items-center gap-3 px-6 py-6">
            <img src="../photos/ascot_logo.png" alt="ASCOT Logo" class="h-10 w-10 rounded-full bg-white object-contain p-1">
            <div>
                <p class="text-lg font-bold text-white">GradScan</p>
                <p class="text-xs font-medium text-green-200">School Admin</p>
            </div>
        </a>

        <nav class="mt-4 flex flex-col gap-1 px-3">
            <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-green-300">Management</p>

            <a href="graduates.php" class="flex items-center gap-3 rounded-lg border border-white/20 bg-white/10 px-3 py-2.5 text-sm font-medium text-white">
                <span>Graduate Management</span>
            </a>
            <a href="layouts.php" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-green-100 transition hover:bg-white/10">
                <span>Layout Management</span>
            </a>
            <a href="scan_history.php" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-green-100 transition hover:bg-white/10">
                <span>Scan History</span>
            </a>
        </nav>
    </aside>

    <!-- Main -->
    <div class="flex flex-1 flex-col">

        <!-- Top bar -->
        <header class="flex items-center justify-between border-b border-gray-200 bg-white px-8 py-4">
            <div>
                <h1 class="text-xl font-bold text-ascot-dark">Add Graduate</h1>
                <p class="text-sm text-gray-500">Add a single graduate, or import a batch from Excel.</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($_SESSION['role']) ?></p>
                </div>
                <a href="../logout.php" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Logout
                </a>
            </div>
        </header>

        <main class="flex-1 px-8 py-8">

            <?php if ($success): ?>
                <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <?php if ($importResults !== null): ?>
                        <p class="font-semibold"><?= $importResults['imported'] ?> graduate(s) imported successfully. QR codes generated.</p>
                        <?php if (!empty($importResults['skipped'])): ?>
                            <p class="mt-2 font-semibold text-amber-700"><?= count($importResults['skipped']) ?> row(s) skipped:</p>
                            <ul class="mt-1 list-inside list-disc space-y-0.5 text-amber-700">
                                <?php foreach ($importResults['skipped'] as $reason): ?>
                                    <li><?= htmlspecialchars($reason) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($importResults['photoNotes'])): ?>
                            <p class="mt-2 font-semibold text-amber-700">Photo issues (graduate was still added):</p>
                            <ul class="mt-1 list-inside list-disc space-y-0.5 text-amber-700">
                                <?php foreach ($importResults['photoNotes'] as $note): ?>
                                    <li><?= htmlspecialchars($note) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        Graduate successfully added! QR code generated.
                    <?php endif; ?>
                    <a href="graduates.php" class="ml-1 font-semibold underline">Back to Graduate Management</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-0.5">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($importResults !== null && !empty($importResults['skipped'])): ?>
                        <p class="mt-2 font-semibold">Rows skipped:</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            <?php foreach ($importResults['skipped'] as $reason): ?>
                                <li><?= htmlspecialchars($reason) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="mb-6 flex gap-2 border-b border-gray-200">
                <button type="button" id="tabBtnManual" onclick="switchTab('manual')"
                    class="border-b-2 border-ascot-green px-4 py-2.5 text-sm font-semibold text-ascot-dark">
                    Manual Entry
                </button>
                <button type="button" id="tabBtnImport" onclick="switchTab('import')"
                    class="border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-gray-500 hover:text-ascot-dark">
                    Import from Excel
                </button>
            </div>

            <!-- Manual Entry Panel -->
            <div id="panelManual" class="rounded-2xl bg-white p-8 shadow-sm">
                <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Student ID</label>
                        <input type="text" name="student_id" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Graduation Year</label>
                        <input type="number" name="graduation_year" value="<?= htmlspecialchars($_POST['graduation_year'] ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Suffix</label>
                        <input type="text" name="suffix" value="<?= htmlspecialchars($_POST['suffix'] ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Course</label>
                        <input type="text" name="course" value="<?= htmlspecialchars($_POST['course'] ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Major</label>
                        <input type="text" name="major" value="<?= htmlspecialchars($_POST['major'] ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Address</label>
                        <textarea name="address" rows="3" placeholder="Enter graduate's address"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Honors</label>
                        <select name="honors"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm outline-none transition focus:border-ascot-green focus:ring-2 focus:ring-green-100">
                            <?php foreach ($honorOptions as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Photo</label>
                        <input type="file" name="photo" accept="image/*"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-ascot-dark hover:file:bg-green-100">
                    </div>

                    <div class="mt-2 flex gap-3 md:col-span-2">
                        <button type="submit" onclick="this.disabled=true; this.form.submit();"
                            class="rounded-lg bg-ascot-green px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ascot-dark">
                            Save Graduate
                        </button>
                        <a href="graduates.php"
                            class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Import from Excel Panel -->
            <div id="panelImport" class="hidden rounded-2xl bg-white p-8 shadow-sm">
                <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <p class="font-semibold">Expected column order (first row is treated as the header and skipped):</p>
                    <p class="mt-1">Student ID · First Name · Middle Name · Last Name · Suffix · Course · Major · Address · Honors · Graduation Year · Photo Filename</p>
                    <p class="mt-1">Honors must be one of: <code>none</code>, <code>cum laude</code>, <code>magna cum laude</code>, <code>summa cum laude</code> (defaults to <code>none</code> if left blank or invalid).</p>
                    <p class="mt-1"><strong>Photos (optional):</strong> put every photo file in one ZIP, matching the exact name you wrote in each row's "Photo Filename" column (e.g. <code>23-01-2405.jpg</code>). Upload the ZIP alongside the Excel file below. Rows with no filename, or a filename that isn't found in the ZIP, are still imported — just without a photo.</p>
                </div>

                <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-5">
                    <input type="hidden" name="form_type" value="excel_import">

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Excel File (.xlsx or .xls)</label>
                        <input type="file" name="excel_file" accept=".xlsx,.xls" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-ascot-dark hover:file:bg-green-100">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Photo ZIP <span class="font-normal text-gray-400">(optional)</span></label>
                        <input type="file" name="photo_zip" accept=".zip"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-ascot-dark hover:file:bg-green-100">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" onclick="this.disabled=true; this.form.submit();"
                            class="rounded-lg bg-ascot-green px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ascot-dark">
                            Import Graduates
                        </button>
                        <a href="graduates.php"
                            class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        </main>
    </div>
</div>

<script>
    function switchTab(tab) {
        const manualPanel = document.getElementById('panelManual');
        const importPanel = document.getElementById('panelImport');
        const manualBtn = document.getElementById('tabBtnManual');
        const importBtn = document.getElementById('tabBtnImport');

        const activeBtnClass = ['border-ascot-green', 'text-ascot-dark'];
        const inactiveBtnClass = ['border-transparent', 'text-gray-500'];

        if (tab === 'manual') {
            manualPanel.classList.remove('hidden');
            importPanel.classList.add('hidden');
            manualBtn.classList.add(...activeBtnClass);
            manualBtn.classList.remove(...inactiveBtnClass);
            importBtn.classList.add(...inactiveBtnClass);
            importBtn.classList.remove(...activeBtnClass);
        } else {
            importPanel.classList.remove('hidden');
            manualPanel.classList.add('hidden');
            importBtn.classList.add(...activeBtnClass);
            importBtn.classList.remove(...inactiveBtnClass);
            manualBtn.classList.add(...inactiveBtnClass);
            manualBtn.classList.remove(...activeBtnClass);
        }
    }

    <?php if ($importResults !== null): ?>
        // Land back on the Import tab after a bulk-import submission
        document.addEventListener('DOMContentLoaded', () => switchTab('import'));
    <?php endif; ?>
</script>
</body>
</html>
