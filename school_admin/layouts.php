<?php
require_once __DIR__ . '/../config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}


$school_id = $_SESSION['school_id'];

$errors = [];
$success = false;

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
    $layout_id = $_POST['layout_id'];

    $config = [
        'background_color' => $_POST['background_color'],
        'text_color'       => $_POST['text_color'],
        'accent_color'     => $_POST['accent_color'],
        'header_text'      => trim($_POST['header_text']),
        'show_photo'       => isset($_POST['show_photo']),
    ];
    $configJson = json_encode($config);

    $updateStmt = mysqli_prepare($conn, "UPDATE layout SET layout_config = ? WHERE layout_id = ? AND school_id = ?");
    mysqli_stmt_bind_param($updateStmt, 'sii', $configJson, $layout_id, $school_id);
    mysqli_stmt_execute($updateStmt);

    $success = true;
}

// --- Check if this school already has its own layout ---
$myLayoutStmt = mysqli_prepare($conn, "SELECT * FROM layout WHERE school_id = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($myLayoutStmt, 'i', $school_id);
mysqli_stmt_execute($myLayoutStmt);
$myLayoutResult = mysqli_stmt_get_result($myLayoutStmt);
$myLayout = mysqli_fetch_assoc($myLayoutResult);

$config = $myLayout ? json_decode($myLayout['layout_config'], true) : null;

// --- If no layout yet, load available global templates to choose from ---
$templates = [];
if (!$myLayout) {
    $templatesResult = mysqli_query($conn, "SELECT * FROM layout WHERE school_id IS NULL");
    while ($row = mysqli_fetch_assoc($templatesResult)) {
        $templates[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Layout Management</title>
    <link rel="stylesheet" href="../AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link"><?= $_SESSION['full_name'] ?> (<?= $_SESSION['role'] ?>)</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="dashboard.php" class="brand-link">
            <span class="brand-text font-weight-light">GradScan</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="graduates.php" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Graduate Management</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="layouts.php" class="nav-link active">
                            <i class="nav-icon fas fa-desktop"></i>
                            <p>Layout Management</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="scan_history.php" class="nav-link">
                            <i class="nav-icon fas fa-history"></i>
                            <p>Scan History</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <h1>Layout Management</h1>
        </div>
        <div class="content">

            <?php if ($success): ?>
                <div class="alert alert-success">Layout saved successfully.</div>
            <?php endif; ?>

            <?php if (!$myLayout): ?>
                <!-- --- Step: Choose Template --- -->
                <div class="card">
                    <div class="card-header"><strong>Choose a Template to Start</strong></div>
                    <div class="card-body">
                        <?php if (empty($templates)): ?>
                            <p>No templates available yet.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($templates as $tpl):
                                    $tplConfig = json_decode($tpl['layout_config'], true);
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div style="background-color: <?= htmlspecialchars($tplConfig['background_color']) ?>; color: <?= htmlspecialchars($tplConfig['text_color']) ?>; padding: 20px; text-align: center;">
                                            <?= htmlspecialchars($tplConfig['header_text']) ?>
                                        </div>
                                        <div class="card-body">
                                            <p><?= htmlspecialchars($tpl['layout_name']) ?></p>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="choose_template">
                                                <input type="hidden" name="template_id" value="<?= $tpl['layout_id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Use This Template</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- --- Step: Customize Layout + Preview Layout --- -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><strong>Customize</strong></div>
                            <div class="card-body">
                                <form method="POST" id="layoutForm">
                                    <input type="hidden" name="action" value="save_layout">
                                    <input type="hidden" name="layout_id" value="<?= $myLayout['layout_id'] ?>">

                                    <div class="form-group">
                                        <label>Background Color</label>
                                        <input type="color" name="background_color" id="background_color" class="form-control" value="<?= htmlspecialchars($config['background_color']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Text Color</label>
                                        <input type="color" name="text_color" id="text_color" class="form-control" value="<?= htmlspecialchars($config['text_color']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Accent Color</label>
                                        <input type="color" name="accent_color" id="accent_color" class="form-control" value="<?= htmlspecialchars($config['accent_color']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Header Text</label>
                                        <input type="text" name="header_text" id="header_text" class="form-control" value="<?= htmlspecialchars($config['header_text']) ?>">
                                    </div>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" name="show_photo" id="show_photo" class="form-check-input" <?= $config['show_photo'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="show_photo">Show Graduate Photo</label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Save Layout</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><strong>Preview</strong></div>
                            <div class="card-body">
                                <!-- --- Step: Preview Layout (live, updates as you edit) --- -->
                                <div id="previewBox" style="border-radius: 8px; padding: 40px; text-align: center; min-height: 300px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                    <div id="previewPhoto" style="width: 100px; height: 100px; border-radius: 50%; background: #ccc; margin-bottom: 15px;"></div>
                                    <h2 id="previewHeader"></h2>
                                    <div id="previewAccent" style="width: 60px; height: 4px; margin-top: 10px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    const bgColor = document.getElementById('background_color');
                    const textColor = document.getElementById('text_color');
                    const accentColor = document.getElementById('accent_color');
                    const headerText = document.getElementById('header_text');
                    const showPhoto = document.getElementById('show_photo');

                    const previewBox = document.getElementById('previewBox');
                    const previewPhoto = document.getElementById('previewPhoto');
                    const previewHeader = document.getElementById('previewHeader');
                    const previewAccent = document.getElementById('previewAccent');

                    function updatePreview() {
                        previewBox.style.backgroundColor = bgColor.value;
                        previewHeader.style.color = textColor.value;
                        previewHeader.textContent = headerText.value;
                        previewAccent.style.backgroundColor = accentColor.value;
                        previewPhoto.style.display = showPhoto.checked ? 'block' : 'none';
                    }

                    [bgColor, textColor, accentColor, headerText, showPhoto].forEach(el => {
                        el.addEventListener('input', updatePreview);
                    });

                    updatePreview();
                </script>
            <?php endif; ?>

        </div>
    </div>

</div>

<script src="../AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="../AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>
