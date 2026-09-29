<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/layout_helpers.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$school_id = $_SESSION['school_id'];

$errors = [];
$success = false;
$successMessage = '';

// --- Step: Choose Template (clone a global template into this school's own layout) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'choose_template') {
    $template_id = $_POST['template_id'];

    $templateStmt = mysqli_prepare($conn, "SELECT layout_config FROM layout WHERE layout_id = ? AND school_id IS NULL");
    mysqli_stmt_bind_param($templateStmt, 'i', $template_id);
    mysqli_stmt_execute($templateStmt);
    $templateResult = mysqli_stmt_get_result($templateStmt);
    $template = mysqli_fetch_assoc($templateResult);

    if ($template) {
        $cloneStmt = mysqli_prepare($conn, "INSERT INTO layout (school_id, template_id, layout_name, layout_config, is_active) VALUES (?, ?, 'My Layout', ?, 1)");
        mysqli_stmt_bind_param($cloneStmt, 'iis', $school_id, $template_id, $template['layout_config']);
        mysqli_stmt_execute($cloneStmt);
    }

    header('Location: layouts.php');
    exit;
}

// --- Step: Save Layout / Update DB (customize an existing school layout) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_layout') {
    $layout_id = (int)($_POST['layout_id'] ?? 0);

    // Only load layouts that belong to this school
    $curStmt = mysqli_prepare($conn, "SELECT layout_config FROM layout WHERE layout_id = ? AND school_id = ?");
    mysqli_stmt_bind_param($curStmt, 'ii', $layout_id, $school_id);
    mysqli_stmt_execute($curStmt);
    $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($curStmt));

    if ($cur) {
        $current = gs_normalize_layout_config(json_decode($cur['layout_config'], true));
        $bgImage = $current['background_image'];

        // Remove the current background image
        if (!empty($_POST['remove_background']) && $bgImage) {
            gs_delete_layout_image($bgImage, $layout_id);
            $bgImage = null;
        }

        // Upload a new background image (replaces the old one)
        if (isset($_FILES['background_image']) && $_FILES['background_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = gs_save_layout_background($_FILES['background_image'], $layout_id);

            if ($upload['ok']) {
                if ($bgImage) {
                    gs_delete_layout_image($bgImage, $layout_id);
                }
                $bgImage = $upload['path'];
            } else {
                $errors[] = $upload['error'] . ' The rest of your changes were still saved.';
            }
        }

        $postedFields = json_decode($_POST['fields_json'] ?? '', true);

        // Everything posted is clamped/validated by the normalizer
        $config = gs_normalize_layout_config([
            'background_color' => $_POST['background_color'] ?? null,
            'header_text'      => $_POST['header_text'] ?? '',
            'background_image' => $bgImage,
            'fields'           => is_array($postedFields) ? $postedFields : $current['fields'],
        ]);

        // Keep the older text/accent color keys in sync with the field colors
        $config['text_color']   = $config['fields']['name']['color'];
        $config['accent_color'] = $config['fields']['honors']['color'];

        $configJson = json_encode($config);

        $updateStmt = mysqli_prepare($conn, "UPDATE layout SET layout_config = ? WHERE layout_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($updateStmt, 'sii', $configJson, $layout_id, $school_id);
        mysqli_stmt_execute($updateStmt);

        $success = true;
        $successMessage = 'Layout saved successfully.';
    }
}

// --- Step: Import from PowerPoint (.pptx) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_pptx') {
    $layout_id = (int)($_POST['layout_id'] ?? 0);

    $curStmt = mysqli_prepare($conn, "SELECT layout_config FROM layout WHERE layout_id = ? AND school_id = ?");
    mysqli_stmt_bind_param($curStmt, 'ii', $layout_id, $school_id);
    mysqli_stmt_execute($curStmt);
    $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($curStmt));

    if (!$cur) {
        $errors[] = 'Layout not found for your school.';
    } elseif (!isset($_FILES['pptx_file']) || $_FILES['pptx_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a valid PowerPoint (.pptx) file to import.';
    } else {
        $ext = strtolower(pathinfo($_FILES['pptx_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pptx') {
            $errors[] = 'Only .pptx PowerPoint presentations are supported.';
        } else {
            $parsed = gs_parse_pptx_layout($_FILES['pptx_file']['tmp_name']);
            if (!$parsed['ok']) {
                $errors[] = $parsed['error'];
            } else {
                $current = gs_normalize_layout_config(json_decode($cur['layout_config'], true));
                $bgImage = $current['background_image'];

                // Handle optional background image upload alongside the PPTX
                if (isset($_FILES['background_image']) && $_FILES['background_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload = gs_save_layout_background($_FILES['background_image'], $layout_id);
                    if ($upload['ok']) {
                        if ($bgImage) {
                            gs_delete_layout_image($bgImage, $layout_id);
                        }
                        $bgImage = $upload['path'];
                    } else {
                        $errors[] = $upload['error'] . ' Layout placements from PowerPoint were still applied.';
                    }
                }

                // Merge parsed items and hide graduate items missing from the slide
                $mergedFields = $current['fields'];
                $foundCount = 0;
                $hiddenCount = 0;

                foreach (gs_default_layout_fields() as $fieldKey => $defaultField) {
                    if (isset($parsed['fields'][$fieldKey])) {
                        $mergedFields[$fieldKey] = array_merge(
                            $mergedFields[$fieldKey] ?? $defaultField,
                            $parsed['fields'][$fieldKey],
                            ['visible' => true]
                        );
                        $foundCount++;
                    } else {
                        if (isset($mergedFields[$fieldKey])) {
                            $mergedFields[$fieldKey]['visible'] = false;
                            $hiddenCount++;
                        }
                    }
                }

                $newBgColor = $parsed['background_color'] ?? $current['background_color'];
                $newHeaderText = !empty($parsed['header_text']) ? $parsed['header_text'] : $current['header_text'];

                $config = gs_normalize_layout_config([
                    'background_color' => $newBgColor,
                    'header_text'      => $newHeaderText,
                    'background_image' => $bgImage,
                    'fields'           => $mergedFields,
                ]);

                $config['text_color']   = $config['fields']['name']['color'];
                $config['accent_color'] = $config['fields']['honors']['color'];

                $configJson = json_encode($config);
                $updateStmt = mysqli_prepare($conn, "UPDATE layout SET layout_config = ? WHERE layout_id = ? AND school_id = ?");
                mysqli_stmt_bind_param($updateStmt, 'sii', $configJson, $layout_id, $school_id);
                mysqli_stmt_execute($updateStmt);

                $success = true;
                $foundList = !empty($parsed['found']) ? implode(', ', $parsed['found']) : 'none';
                $successMessage = "PowerPoint layout imported successfully! Auto-placed {$foundCount} fields ({$foundList})." . ($hiddenCount > 0 ? " {$hiddenCount} omitted fields were set to hidden." : '');
            }
        }
    }
}

// --- Check if this school already has its own layout ---
$myLayoutStmt = mysqli_prepare($conn, "SELECT * FROM layout WHERE school_id = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($myLayoutStmt, 'i', $school_id);
mysqli_stmt_execute($myLayoutStmt);
$myLayoutResult = mysqli_stmt_get_result($myLayoutStmt);
$myLayout = mysqli_fetch_assoc($myLayoutResult);

$config = $myLayout ? gs_normalize_layout_config(json_decode($myLayout['layout_config'], true)) : null;

// --- If no layout yet, load available global templates to choose from ---
$templates = [];
if (!$myLayout) {
    $templatesResult = mysqli_query($conn, "SELECT * FROM layout WHERE school_id IS NULL");
    while ($row = mysqli_fetch_assoc($templatesResult)) {
        $templates[] = $row;
    }
}

// Sample graduate used for template previews
$sampleGraduate = [
    'first_name' => 'Juan', 'middle_name' => 'D.', 'last_name' => 'Dela Cruz', 'suffix' => '',
    'student_id' => '23-01-0000', 'course' => 'BSIT', 'major' => 'Application Development',
    'honors' => 'cum laude', 'graduation_year' => '2026', 'photo' => null,
];

// Data handed to the editor script (escaped so it can't break out of <script>)
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$fieldsJson   = $config ? json_encode($config['fields'], $jsonFlags) : '{}';
$defaultsJson = json_encode(gs_normalize_layout_config([])['fields'], $jsonFlags);
$bgUrlJson    = json_encode(
    ($config && $config['background_image'])
        ? '../' . $config['background_image'] . '?v=' . @filemtime(dirname(__DIR__) . '/' . $config['background_image'])
        : null,
    $jsonFlags
);

$pageTitle = 'GradScan | Layout Management';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

?>

<div class="gs-main">

    <!-- Top Navigation -->
    <header class="gs-topbar">

        <div>
            <h2 class="gs-topbar-title">Layout Management</h2>
            <p class="gs-topbar-subtitle">Design how each graduate appears on the display screen</p>
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
            <h1 class="gs-page-title">Layout Management</h1>
            <p class="gs-page-description">Upload your school's own design as the background, then place the graduate's details on top of it.</p>
        </section>

        <?php if ($success): ?>
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <?= htmlspecialchars($successMessage ?: 'Layout saved successfully.') ?>
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

        <?php if (!$myLayout): ?>

            <!-- --- Step: Choose Template --- -->
            <div class="gs-card p-8">
                <h3 class="mb-1 text-lg font-bold text-ascot-dark">Choose a Template to Start</h3>
                <p class="mb-5 text-sm text-gray-500">Pick a starting point. You can upload your own background and rearrange everything afterward.</p>

                <?php if (empty($templates)): ?>
                    <p class="text-sm text-gray-500">No templates available yet.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <?php foreach ($templates as $tpl): ?>
                            <div class="overflow-hidden rounded-xl border border-gray-200">

                                <?= gs_render_layout_canvas(json_decode($tpl['layout_config'], true), $sampleGraduate, '../') ?>

                                <div class="p-4">
                                    <p class="mb-3 text-sm font-semibold text-gray-700"><?= htmlspecialchars($tpl['layout_name']) ?></p>

                                    <form method="POST">
                                        <input type="hidden" name="action" value="choose_template">
                                        <input type="hidden" name="template_id" value="<?= (int)$tpl['layout_id'] ?>">
                                        <button type="submit" class="gs-button-primary w-full text-center">
                                            Use This Template
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- --- Step: Import from PowerPoint Card --- -->
            <div class="mb-6 gs-card p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-base font-bold text-ascot-dark flex items-center gap-2">
                            <svg class="h-5 w-5 text-ascot-green" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            Auto-Place from PowerPoint (.pptx)
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Design in PowerPoint using our starter template, then upload the .pptx to auto-align all field positions and fonts.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="../templates/GradScan_Layout_Starter.pptx" download class="gs-button-secondary inline-flex items-center gap-2 whitespace-nowrap">
                            <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download Starter .pptx
                        </a>
                        <button type="button" onclick="const p = document.getElementById('pptxImportPanel'); p.classList.toggle('hidden');" class="gs-button-primary inline-flex items-center gap-2 whitespace-nowrap">
                            Import from PowerPoint
                        </button>
                    </div>
                </div>

                <!-- Collapsible Import Form -->
                <div id="pptxImportPanel" class="hidden mt-6 border-t border-gray-200 pt-6">
                    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <input type="hidden" name="action" value="import_pptx">
                        <input type="hidden" name="layout_id" value="<?= (int)$myLayout['layout_id'] ?>">

                        <div>
                            <label class="gs-label">PowerPoint Presentation (.pptx) <span class="text-red-500">*</span></label>
                            <input type="file" name="pptx_file" accept=".pptx" required
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-ascot-dark hover:file:bg-green-100">
                            <p class="mt-1.5 text-xs text-gray-400">Slide 1 contains the placeholders ({{name}}, {{course}}, {{student_id}}, {{honors}}, {{year}}, {{header}}, photo). Missing items will be hidden.</p>
                        </div>

                        <div>
                            <label class="gs-label">Design Background Image <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="file" name="background_image" accept="image/png,image/jpeg,image/webp"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-ascot-dark hover:file:bg-green-100">
                            <p class="mt-1.5 text-xs text-gray-400">Export Slide 1 as a PNG/JPG in PowerPoint, and upload it here as your 16:9 background.</p>
                        </div>

                        <div class="md:col-span-2 flex items-center gap-3 pt-2">
                            <button type="submit" class="gs-button-primary">Import &amp; Apply Layout</button>
                            <button type="button" onclick="document.getElementById('pptxImportPanel').classList.add('hidden')" class="gs-button-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- --- Step: Customize Layout (drag-and-drop editor) --- -->
            <form method="POST" enctype="multipart/form-data" id="layoutForm" class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_360px]">

                <input type="hidden" name="action" value="save_layout">
                <input type="hidden" name="layout_id" value="<?= (int)$myLayout['layout_id'] ?>">
                <input type="hidden" name="fields_json" id="fields_json" value="">

                <!-- Canvas -->
                <div class="gs-card p-6">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-ascot-dark">Design Canvas</h3>
                            <p class="text-sm text-gray-500">Drag any item to move it. Click one to adjust its size, font, color, and alignment.</p>
                        </div>
                        <button type="button" id="resetPositions" class="gs-button-secondary whitespace-nowrap">Reset positions</button>
                    </div>

                    <div
                        id="canvas"
                        class="overflow-hidden rounded-lg border border-gray-300"
                        style="position:relative;width:100%;aspect-ratio:16/9;container-type:inline-size;background-size:cover;background-position:center;"
                    ></div>

                    <p class="mt-3 text-xs text-gray-400">This is exactly what appears on the display screen (shown here with sample data).</p>
                </div>

                <!-- Controls -->
                <div class="flex flex-col gap-6">

                    <div class="gs-card p-6">
                        <h3 class="mb-4 text-base font-bold text-ascot-dark">Background</h3>

                        <label class="gs-label">Design image</label>
                        <input
                            type="file" name="background_image" id="background_image"
                            accept="image/png,image/jpeg,image/webp"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-ascot-dark hover:file:bg-green-100"
                        >
                        <p class="mt-2 text-xs text-gray-400">Best at 1920 × 1080 (16:9). Other sizes are cropped to fit. JPG, PNG or WEBP, up to 8 MB.</p>

                        <?php if ($config['background_image']): ?>
                            <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="remove_background" id="remove_background" value="1" class="h-4 w-4 rounded border-gray-300">
                                Remove current image
                            </label>
                        <?php endif; ?>

                        <label class="gs-label mt-4">Background color <span class="font-normal text-gray-400">(behind the image)</span></label>
                        <input
                            type="color" name="background_color" id="background_color"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 p-1"
                            value="<?= htmlspecialchars($config['background_color']) ?>"
                        >

                        <label class="gs-label mt-4">Header text</label>
                        <input
                            type="text" name="header_text" id="header_text" maxlength="100"
                            class="gs-input"
                            value="<?= htmlspecialchars($config['header_text']) ?>"
                        >
                    </div>

                    <div class="gs-card p-6">
                        <h3 class="mb-3 text-base font-bold text-ascot-dark">Items</h3>
                        <div id="fieldList" class="flex flex-col gap-1"></div>

                        <div class="mt-5 border-t border-gray-200 pt-4">
                            <p id="ctlTitle" class="mb-3 text-sm font-semibold text-gray-800"></p>

                            <div class="mb-3">
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Width</span><span id="ctlWVal"></span>
                                </div>
                                <input type="range" id="ctlW" min="5" max="100" step="1" class="w-full">
                            </div>

                            <div id="ctlTextRows" class="flex flex-col gap-3">
                                <div>
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>Text size</span><span id="ctlSizeVal"></span>
                                    </div>
                                    <input type="range" id="ctlSize" min="1" max="12" step="0.1" class="w-full">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Font</label>
                                    <select id="ctlFont" class="gs-input py-2 text-sm">
                                        <?php foreach (gs_allowed_fonts() as $fontOption): ?>
                                            <option value="<?= htmlspecialchars($fontOption) ?>">
                                                <?= htmlspecialchars($fontOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Color</label>
                                        <input type="color" id="ctlColor" class="h-10 w-full cursor-pointer rounded-lg border border-gray-300 p-1">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Align</label>
                                        <select id="ctlAlign" class="gs-input py-2">
                                            <option value="left">Left</option>
                                            <option value="center">Center</option>
                                            <option value="right">Right</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="gs-button-primary w-full text-center">Save Layout</button>

                </div>

            </form>

            <script>
            (function () {
                const FIELD_ORDER = ['photo', 'header', 'name', 'student_id', 'course', 'honors', 'graduation_year'];
                const LABELS = {
                    photo: 'Photo (2:3)', header: 'Header text', name: 'Full name', student_id: 'Student ID',
                    course: 'Course & major', honors: 'Honors', graduation_year: 'Graduation year'
                };
                const SAMPLE = {
                    name: 'Juan D. Dela Cruz', student_id: '23-01-0000',
                    course: 'BSIT — Application Development', honors: 'Cum Laude', graduation_year: '2026'
                };
                const WEIGHT = { header: 600, name: 700, student_id: 500, course: 500, honors: 600, graduation_year: 500 };

                const DEFAULTS = <?= $defaultsJson ?>;
                const fields = <?= $fieldsJson ?>;
                let bgUrl = <?= $bgUrlJson ?>;
                let selected = 'name';
                let drag = null;

                const canvas = document.getElementById('canvas');
                const bgColor = document.getElementById('background_color');
                const headerInput = document.getElementById('header_text');
                const bgFile = document.getElementById('background_image');
                const removeBg = document.getElementById('remove_background');
                const fieldList = document.getElementById('fieldList');

                const ctlTitle = document.getElementById('ctlTitle');
                const ctlW = document.getElementById('ctlW');
                const ctlWVal = document.getElementById('ctlWVal');
                const ctlSize = document.getElementById('ctlSize');
                const ctlSizeVal = document.getElementById('ctlSizeVal');
                const ctlFont = document.getElementById('ctlFont');
                const ctlColor = document.getElementById('ctlColor');
                const ctlAlign = document.getElementById('ctlAlign');
                const ctlTextRows = document.getElementById('ctlTextRows');

                function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

                // Photo height as % of canvas height (2:3 photo on a 16:9 canvas)
                function photoHeightPct(w) { return w * 1.5 / 56.25 * 100; }

                function renderCanvas() {
                    canvas.innerHTML = '';
                    canvas.style.backgroundColor = bgColor.value;
                    canvas.style.backgroundImage = bgUrl ? 'url("' + bgUrl + '")' : 'none';

                    FIELD_ORDER.forEach(function (key) {
                        const f = fields[key];
                        if (!f.visible) return;

                        const el = document.createElement('div');
                        el.dataset.field = key;
                        el.style.cssText = 'position:absolute;left:' + f.x + '%;top:' + f.y + '%;width:' + f.w + '%;cursor:move;touch-action:none;user-select:none;';

                        if (key === 'photo') {
                            const ph = document.createElement('div');
                            ph.style.cssText = 'aspect-ratio:2/3;width:100%;background:#cbd5e1;border-radius:0.6cqw;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:1.6cqw;font-weight:600;';
                            ph.textContent = 'PHOTO 2:3';
                            el.appendChild(ph);
                        } else {
                            el.style.fontFamily = "'" + (f.font || 'Segoe UI') + "', sans-serif";
                            el.style.fontSize = f.size + 'cqw';
                            el.style.color = f.color;
                            el.style.textAlign = f.align;
                            el.style.lineHeight = '1.15';
                            el.style.fontWeight = WEIGHT[key];
                            el.textContent = key === 'header' ? (headerInput.value || '(header text)') : SAMPLE[key];
                        }

                        canvas.appendChild(el);
                    });

                    applyOutlines();
                }

                function applyOutlines() {
                    canvas.querySelectorAll('[data-field]').forEach(function (el) {
                        el.style.outline = el.dataset.field === selected
                            ? '2px dashed #FFDD21'
                            : '1px dashed rgba(0,0,0,0.35)';
                    });
                }

                function renderFieldList() {
                    fieldList.innerHTML = '';

                    FIELD_ORDER.forEach(function (key) {
                        const row = document.createElement('label');
                        row.className = 'flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm';
                        row.style.background = key === selected ? '#f0fdf4' : 'transparent';

                        const cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.checked = fields[key].visible;
                        cb.className = 'h-4 w-4 rounded border-gray-300';
                        cb.addEventListener('change', function () {
                            fields[key].visible = cb.checked;
                            renderCanvas();
                        });

                        const name = document.createElement('span');
                        name.textContent = LABELS[key];
                        name.className = 'flex-1 font-medium text-gray-700';
                        name.addEventListener('click', function (e) {
                            e.preventDefault();
                            select(key);
                        });

                        row.appendChild(cb);
                        row.appendChild(name);
                        fieldList.appendChild(row);
                    });
                }

                function syncControls() {
                    const f = fields[selected];
                    ctlTitle.textContent = 'Adjust: ' + LABELS[selected];
                    ctlW.value = f.w;
                    ctlWVal.textContent = Math.round(f.w) + '%';

                    const isText = selected !== 'photo';
                    ctlTextRows.classList.toggle('hidden', !isText);

                    if (isText) {
                        ctlSize.value = f.size;
                        ctlSizeVal.textContent = Number(f.size).toFixed(1);
                        ctlFont.value = f.font || 'Segoe UI';
                        ctlColor.value = f.color;
                        ctlAlign.value = f.align;
                    }
                }

                function select(key) {
                    selected = key;
                    applyOutlines();
                    renderFieldList();
                    syncControls();
                }

                // --- Drag to move ---
                canvas.addEventListener('pointerdown', function (e) {
                    const el = e.target.closest('[data-field]');
                    if (!el) return;

                    const key = el.dataset.field;
                    select(key);

                    drag = {
                        key: key,
                        el: el,
                        startX: e.clientX,
                        startY: e.clientY,
                        ox: fields[key].x,
                        oy: fields[key].y,
                        rect: canvas.getBoundingClientRect()
                    };
                    el.setPointerCapture(e.pointerId);
                });

                canvas.addEventListener('pointermove', function (e) {
                    if (!drag) return;

                    const f = fields[drag.key];
                    const dx = (e.clientX - drag.startX) / drag.rect.width * 100;
                    const dy = (e.clientY - drag.startY) / drag.rect.height * 100;

                    const maxY = drag.key === 'photo' ? 100 - photoHeightPct(f.w) : 96;

                    f.x = clamp(drag.ox + dx, 0, 100 - f.w);
                    f.y = clamp(drag.oy + dy, 0, Math.max(0, maxY));

                    drag.el.style.left = f.x + '%';
                    drag.el.style.top = f.y + '%';
                });

                function endDrag() { drag = null; }
                canvas.addEventListener('pointerup', endDrag);
                canvas.addEventListener('pointercancel', endDrag);

                // --- Controls for the selected item ---
                ctlW.addEventListener('input', function () {
                    const f = fields[selected];
                    f.w = Number(ctlW.value);
                    f.x = clamp(f.x, 0, 100 - f.w);
                    ctlWVal.textContent = Math.round(f.w) + '%';
                    renderCanvas();
                });

                ctlSize.addEventListener('input', function () {
                    fields[selected].size = Number(ctlSize.value);
                    ctlSizeVal.textContent = Number(ctlSize.value).toFixed(1);
                    renderCanvas();
                });

                ctlFont.addEventListener('change', function () {
                    fields[selected].font = ctlFont.value;
                    renderCanvas();
                });

                ctlColor.addEventListener('input', function () {
                    fields[selected].color = ctlColor.value;
                    renderCanvas();
                });

                ctlAlign.addEventListener('change', function () {
                    fields[selected].align = ctlAlign.value;
                    renderCanvas();
                });

                // --- Background + header controls ---
                bgColor.addEventListener('input', renderCanvas);
                headerInput.addEventListener('input', renderCanvas);

                bgFile.addEventListener('change', function () {
                    if (bgFile.files && bgFile.files[0]) {
                        bgUrl = URL.createObjectURL(bgFile.files[0]);
                        if (removeBg) removeBg.checked = false;
                        renderCanvas();
                    }
                });

                if (removeBg) {
                    removeBg.addEventListener('change', function () {
                        if (removeBg.checked) {
                            bgUrl = null;
                            bgFile.value = '';
                            renderCanvas();
                        }
                    });
                }

                // --- Reset positions (keeps colors, fonts, and which items are shown) ---
                document.getElementById('resetPositions').addEventListener('click', function () {
                    FIELD_ORDER.forEach(function (key) {
                        const d = DEFAULTS[key];
                        fields[key].x = d.x;
                        fields[key].y = d.y;
                        fields[key].w = d.w;
                        if (key !== 'photo') {
                            fields[key].size = d.size;
                            fields[key].align = d.align;
                        }
                    });
                    renderCanvas();
                    syncControls();
                });

                // --- Save: send the field positions along with the form ---
                document.getElementById('layoutForm').addEventListener('submit', function () {
                    document.getElementById('fields_json').value = JSON.stringify(fields);
                });

                renderCanvas();
                renderFieldList();
                syncControls();
            })();
            </script>

        <?php endif; ?>

    </main>

</div>

<?php require_once 'includes/footer.php'; ?>
