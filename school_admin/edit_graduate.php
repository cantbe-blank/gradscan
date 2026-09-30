<?php
require_once __DIR__ . '/../config/config.php';
session_start();

require_once __DIR__ . '/../config/config.php';
gs_require_role(['school_admin'], '../login.php');

$school_id = $_SESSION['school_id'];

// --- Step: Search Graduate (by ID from the Edit link) ---
$graduate_id = $_GET['id'] ?? null;

if (!$graduate_id) {
    die('No graduate specified.');
}

$errors = [];
$success = false;

// --- Step: Graduate Found? ---
$stmt = mysqli_prepare($conn, "SELECT * FROM graduate WHERE graduate_id = ? AND school_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $graduate_id, $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$graduate = mysqli_fetch_assoc($result);

if (!$graduate) {
    // --- Step: Error ---
    die('Graduate not found, or does not belong to your department.');
}

$honorOptions = [
    'none' => 'No Honors',
    'cum laude' => 'Cum Laude',
    'magna cum laude' => 'Magna Cum Laude',
    'summa cum laude' => 'Summa Cum Laude',
];

// --- Step: Edit Info / Save Changes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Duplicate check, excluding this graduate's own current row
    if (empty($errors)) {
        $checkStmt = mysqli_prepare($conn, "SELECT graduate_id FROM graduate WHERE student_id = ? AND graduate_id != ?");
        mysqli_stmt_bind_param($checkStmt, 'si', $student_id, $graduate_id);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors[] = 'Another graduate already uses this Student ID.';
        }
    }

    // --- Step: Update DB ---
    if (empty($errors)) {
        $updateStmt = mysqli_prepare($conn, "UPDATE graduate SET student_id = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, course = ?, address = ?, major = ?, honors = ?, graduation_year = ? WHERE graduate_id = ?");
        mysqli_stmt_bind_param(
            $updateStmt,
            'sssssssssii',
            $student_id,
            $first_name,
            $middle_name,
            $last_name,
            $suffix,
            $course,
            $address,
            $major,
            $honors,
            $graduation_year,
            $graduate_id
        );
        mysqli_stmt_execute($updateStmt);

        // --- Handle photo replacement (only if a new file was chosen) ---
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

            $graduate['photo'] = $photoPath; // update what's shown on this page
        }

        $success = true;

        // refresh $graduate with the new values for display
        $graduate['student_id'] = $student_id;
        $graduate['first_name'] = $first_name;
        $graduate['middle_name'] = $middle_name;
        $graduate['last_name'] = $last_name;
        $graduate['suffix'] = $suffix;
        $graduate['course'] = $course;
        $graduate['address'] = $address;
        $graduate['major'] = $major;
        $graduate['honors'] = $honors;
        $graduate['graduation_year'] = $graduation_year;
    }
}

$pageTitle = 'GradScan | Edit Graduate';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

?>

<div class="gs-main">

    <!-- Top Navigation -->
    <header class="gs-topbar">

        <div>
            <h2 class="gs-topbar-title">Edit Graduate</h2>
            <p class="gs-topbar-subtitle">Update a graduate's record</p>
        </div>

        <div class="flex items-center gap-5">

            <div class="text-right">
                <p class="gs-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
                <p class="gs-user-role"><?= htmlspecialchars($_SESSION['role']) ?></p>
            </div>

            <a href="../logout.php" class="gs-button-danger">Logout</a>

        </div>

    </header>


    <!-- Main Content -->
    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title">Edit Graduate</h1>
            <p class="gs-page-description">Update <?= htmlspecialchars($graduate['first_name'] . ' ' . $graduate['last_name']) ?>'s record.</p>
        </section>

        <?php if ($success): ?>
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                Changes saved successfully.
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
            </div>
        <?php endif; ?>

        <div class="gs-card p-8">
            <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 md:grid-cols-2">

                <div>
                    <label class="gs-label">Student ID</label>
                    <input type="text" name="student_id" class="gs-input" value="<?= htmlspecialchars($graduate['student_id']) ?>">
                </div>

                <div>
                    <label class="gs-label">Graduation Year</label>
                    <input type="number" name="graduation_year" class="gs-input" value="<?= htmlspecialchars($graduate['graduation_year']) ?>">
                </div>

                <div>
                    <label class="gs-label">First Name</label>
                    <input type="text" name="first_name" class="gs-input" value="<?= htmlspecialchars($graduate['first_name']) ?>">
                </div>

                <div>
                    <label class="gs-label">Middle Name</label>
                    <input type="text" name="middle_name" class="gs-input" value="<?= htmlspecialchars($graduate['middle_name']) ?>">
                </div>

                <div>
                    <label class="gs-label">Last Name</label>
                    <input type="text" name="last_name" class="gs-input" value="<?= htmlspecialchars($graduate['last_name']) ?>">
                </div>

                <div>
                    <label class="gs-label">Suffix</label>
                    <input type="text" name="suffix" class="gs-input" value="<?= htmlspecialchars($graduate['suffix']) ?>">
                </div>

                <div>
                    <label class="gs-label">Course</label>
                    <input type="text" name="course" class="gs-input" value="<?= htmlspecialchars($graduate['course']) ?>">
                </div>

                <div>
                    <label class="gs-label">Major</label>
                    <input type="text" name="major" class="gs-input" value="<?= htmlspecialchars($graduate['major']) ?>">
                </div>

                <div class="md:col-span-2">
                    <label class="gs-label">Address</label>
                    <textarea name="address" rows="3" class="gs-input"><?= htmlspecialchars($graduate['address']) ?></textarea>
                </div>

                <div>
                    <label class="gs-label">Honors</label>
                    <select name="honors" class="gs-input">
                        <?php foreach ($honorOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $graduate['honors'] === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="gs-label">Photo</label>

                    <?php if (!empty($graduate['photo'])): ?>
                        <div class="mb-3 flex items-center gap-3">
                            <img
                                src="../<?= htmlspecialchars($graduate['photo']) ?>"
                                alt="Current photo"
                                class="h-16 w-16 rounded-lg border border-gray-200 object-cover"
                            >
                            <span class="text-xs text-gray-500">Current photo — choose a file below to replace it.</span>
                        </div>
                    <?php endif; ?>

                    <input
                        type="file"
                        name="photo"
                        accept="image/*"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-ascot-dark hover:file:bg-green-100"
                    >
                </div>

                <div class="mt-2 flex gap-3 md:col-span-2">
                    <button type="submit" onclick="this.disabled=true; this.form.submit();" class="gs-button-primary">
                        Save Changes
                    </button>
                    <a href="graduates.php" class="gs-button-secondary">Cancel</a>
                </div>

            </form>
        </div>

    </main>

</div>

<?php require_once 'includes/footer.php'; ?>