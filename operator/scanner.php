<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Scanner</title>
    <link rel="stylesheet" href="../AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../AdminLTE-3.2.0/dist/css/adminlte.min.css">

    <!-- ═══════════════════════════════════════════════════════════════
         CHANGED: Replaced camera-based scanner styles with a clean
                  hardware-scanner UI. No video/canvas styles needed.
         ═══════════════════════════════════════════════════════════════ -->
    <style>
        /* ── Scanner Status Card ── */
        .scanner-ready-ring {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 5px solid #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: pulse-ring 2s ease-in-out infinite;
            background: rgba(40,167,69,0.08);
        }
        .scanner-ready-ring.idle {
            border-color: #6c757d;
            background: rgba(108,117,125,0.08);
            animation: none;
        }
        .scanner-ready-ring.error {
            border-color: #dc3545;
            background: rgba(220,53,69,0.08);
            animation: none;
        }
        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0   rgba(40,167,69,0.35); }
            70%  { box-shadow: 0 0 0 18px rgba(40,167,69,0);    }
            100% { box-shadow: 0 0 0 0   rgba(40,167,69,0);     }
        }

        /* (no hidden input needed — capture is document-level) */

        /* ── Scan Result Card ── */
        #resultCard {
            display: none;
            transition: all 0.3s ease;
        }

        /* ── Session Log Table ── */
        #sessionTable tbody tr:first-child {
            font-weight: 600;
            background-color: rgba(40,167,69,0.07);
        }

        /* ── Scan Counter Badge ── */
        .scan-count-badge {
            font-size: 1.1rem;
            padding: 6px 14px;
        }
    </style>
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
                <span class="nav-link"><?= $_SESSION['full_name'] ?> (Operator)</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
            </li>
        </ul>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════════
         CHANGED: Updated sidebar icon from fa-camera to fa-barcode
                  to reflect the new hardware scanner device.
         ═══════════════════════════════════════════════════════════════ -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="dashboard.php" class="brand-link">
            <span class="brand-text font-weight-light">GradScan</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <!-- CHANGED: fa-camera → fa-barcode -->
                            <i class="nav-icon fas fa-barcode"></i>
                            <p>Scanner</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <!-- CHANGED: Title updated to reflect hardware scanner -->
                        <h1><i class="fas fa-barcode mr-2"></i>QR Scanner Device</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <span class="badge badge-success scan-count-badge">
                            <i class="fas fa-check-circle mr-1"></i>
                            Scanned: <span id="scanCount">0</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">

                    <!-- ── Left Column: Scanner Status ── -->
                    <div class="col-lg-5">

                        <!-- ═══════════════════════════════════════════════════════════
                             CHANGED: Completely removed <video>, <canvas>, and all
                                      camera-related UI. Replaced with a hardware-
                                      scanner status card that:
                                       1. Shows a pulsing "ready" ring when listening
                                       2. Uses a hidden <input> to capture keystrokes
                                          emitted by the USB/HID QR scanner device
                                       3. Processes on Enter (the scanner's terminator)
                             ═══════════════════════════════════════════════════════════ -->
                        <div class="card card-outline card-success" id="scannerCard">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-wifi mr-1"></i> Device Status
                                </h3>
                            </div>
                            <div class="card-body text-center py-4">

                                <!-- Pulsing ring indicator -->
                                <div class="scanner-ready-ring" id="statusRing">
                                    <i class="fas fa-barcode fa-3x text-success" id="statusIcon"></i>
                                </div>

                                <!-- Status message -->
                                <div id="statusText" class="alert alert-success mb-3">
                                    <i class="fas fa-circle-notch fa-spin mr-1"></i>
                                    Ready — point the scanner at a QR code.
                                </div>

                                                        <!-- CHANGED: Replaced hidden off-screen input with a
                                     visible, always-on focus indicator. Keystroke capture
                                     is now done at the document level so it works even
                                     if the page loses focus to the display window. -->
                                <div id="focusIndicator" class="mb-3">
                                    <span class="badge badge-success px-3 py-2" style="font-size:0.95rem;">
                                        <i class="fas fa-lock mr-1"></i> Scanner Active — Ready to Receive Input
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-2">
                                    <button id="displayNowBtn"
                                            class="btn btn-success btn-lg mr-2"
                                            style="display:none;"
                                            onclick="triggerDisplay()">
                                        <i class="fas fa-tv mr-1"></i> Display Now
                                    </button>
                                    <button id="endSessionBtn"
                                            class="btn btn-outline-danger btn-lg"
                                            onclick="endSession()">
                                        <i class="fas fa-stop mr-1"></i> End Session
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Instructions Card -->
                        <!-- CHANGED: New card explaining hardware scanner usage -->
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> How to Use</h3>
                            </div>
                            <div class="card-body">
                                <ol class="pl-3 mb-0" style="line-height: 2;">
                                    <li>Plug in the USB QR scanner device.</li>
                                    <li>Click anywhere on this page to ensure focus.</li>
                                    <li>Point the scanner at the graduate's QR code.</li>
                                    <li>The scanner reads and submits automatically.</li>
                                    <li>Press <strong>Display Now</strong> to push to the audience screen.</li>
                                </ol>
                            </div>
                        </div>

                    </div>

                    <!-- ── Right Column: Result + Session Log ── -->
                    <div class="col-lg-7">

                        <!-- Scan Result Card -->
                        <div class="card card-outline card-success" id="resultCard">
                            <div class="card-header">
                                <h3 class="card-title" id="resultCardTitle">
                                    <i class="fas fa-user-graduate mr-1"></i> Last Scan Result
                                </h3>
                            </div>
                            <div class="card-body" id="resultText"></div>
                        </div>

                        <!-- Session Scan Log -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-list mr-1"></i> Session Scan Log
                                </h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-hover mb-0" id="sessionTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Course</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sessionTableBody">
                                        <tr id="emptyRow">
                                            <td colspan="5" class="text-center text-muted py-3">
                                                No scans yet this session.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div><!-- /.row -->
            </div>
        </div>
    </div>

</div><!-- /.wrapper -->

<script src="../AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="../AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>

<!-- ═══════════════════════════════════════════════════════════════════════
     CHANGED: Entire JavaScript block replaced.
              OLD approach: getUserMedia() → video stream → canvas frame grab
                            → jsQR() image analysis on every animation frame.
              NEW approach: Hidden <input #qrInput> captures keystroke bursts
                            from the USB HID QR scanner. The scanner acts like
                            a keyboard: it types the QR payload then sends Enter.
                            We detect Enter → trim the buffer → submit to the
                            same scan_process.php endpoint → same response flow.

     Benefits:
       • No camera permission required.
       • No CPU-heavy frame analysis loop.
       • Near-instant scan (~50–150 ms hardware decode).
       • Works on any browser/OS with a USB port.
     ═══════════════════════════════════════════════════════════════════════ -->
<script>
    // ── DOM References ──────────────────────────────────────────────
    const statusText    = document.getElementById('statusText');
    const statusRing    = document.getElementById('statusRing');
    const statusIcon    = document.getElementById('statusIcon');
    const resultCard    = document.getElementById('resultCard');
    const resultText    = document.getElementById('resultText');
    const displayNowBtn = document.getElementById('displayNowBtn');
    const sessionBody   = document.getElementById('sessionTableBody');
    const scanCountEl   = document.getElementById('scanCount');
    const focusIndicator = document.getElementById('focusIndicator');

    // ── State ────────────────────────────────────────────────────────
    const displayChannel  = new BroadcastChannel('gradscan_display');
    let   displayWindow   = window.open('audience_display.php', 'GradScanDisplay', 'width=1280,height=720');
    let   pendingDisplayData = null;
    let   sessionScans    = [];
    let   scanCount       = 0;
    let   lastToken       = '';
    let   lastScanTime    = 0;
    let   processingLock  = false;
    // AUTO-DISPLAY: false on first scan (manual), true for all subsequent scans
    let   autoDisplay     = false;

    // ── FIX: Document-level keystroke buffer ─────────────────────────
    // Instead of relying on a focused <input>, we intercept ALL keystrokes
    // at the document level. USB HID QR scanners type very fast (< 50 ms
    // between chars) then send Enter. We buffer chars and flush on Enter.
    // This works even if a button or other element has focus.
    let scanBuffer    = '';
    let lastKeyTime   = 0;
    const SCANNER_TIMEOUT_MS = 100; // reset buffer if gap > 100 ms (human typing is slower)

    document.addEventListener('keypress', function (e) {
        const now = Date.now();

        // If too much time has passed since the last keystroke, this is
        // probably a human typing — reset the buffer to avoid garbage data.
        if (now - lastKeyTime > SCANNER_TIMEOUT_MS && scanBuffer.length > 0) {
            scanBuffer = '';
        }
        lastKeyTime = now;

        if (e.key === 'Enter') {
            const token = scanBuffer.trim();
            scanBuffer = '';

            if (!token) return;

            // Debounce: ignore same token within 3 seconds
            if (token === lastToken && (now - lastScanTime) < 3000) return;
            if (processingLock) return;

            lastToken    = token;
            lastScanTime = now;

            processToken(token);

        } else {
            // Accumulate characters into the buffer
            scanBuffer += e.key;
        }
    });

    // ── Token Processing ─────────────────────────────────────────────
    function processToken(token) {
        processingLock = true;
        setStatusProcessing();
        displayNowBtn.style.display = 'none';

        fetch('scan_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                setStatusError(data.error);
                showResultError(data.error);
            } else {
                const g = data.graduate;
                const fullName = [g.first_name, g.middle_name ? g.middle_name.charAt(0) + '.' : '', g.last_name, g.suffix || '']
                                    .filter(Boolean).join(' ');

                setStatusSuccess(`Scanned: <strong>${fullName}</strong>`);
                showResultSuccess(g, fullName);

                pendingDisplayData = data;

                // AUTO-DISPLAY LOGIC:
                // First scan → show "Display Now" button (manual control).
                // All subsequent scans → push to audience display automatically.
                if (autoDisplay) {
                    displayChannel.postMessage(pendingDisplayData);
                    pendingDisplayData = null;
                    displayNowBtn.style.display = 'none';
                } else {
                    displayNowBtn.style.display = 'inline-block';
                }

                // Append to session log
                scanCount++;
                scanCountEl.textContent = scanCount;
                sessionScans.push({
                    student_id: g.student_id,
                    name:       fullName,
                    course:     g.course,
                    time:       new Date().toLocaleTimeString()
                });
                prependSessionRow(scanCount, g.student_id, fullName, g.course, sessionScans.at(-1).time);
            }
        })
        .catch(() => {
            setStatusError('Network error. Please check connection.');
            showResultError('Network error. Please try again.');
        })
        .finally(() => {
            processingLock = false;
        });
    }

    // ── Status Ring Helpers ──────────────────────────────────────────
    function setStatusProcessing() {
        statusRing.className  = 'scanner-ready-ring';
        statusIcon.className  = 'fas fa-circle-notch fa-spin fa-3x text-primary';
        statusText.className  = 'alert alert-info mb-3';
        statusText.innerHTML  = '<i class="fas fa-circle-notch fa-spin mr-1"></i> Processing scan...';
        focusIndicator.innerHTML = '<span class="badge badge-info px-3 py-2" style="font-size:0.95rem;"><i class="fas fa-spinner fa-spin mr-1"></i> Processing...</span>';
    }
    function setStatusSuccess(msg) {
        statusRing.className  = 'scanner-ready-ring';
        statusIcon.className  = 'fas fa-check-circle fa-3x text-success';
        statusText.className  = 'alert alert-success mb-3';
        statusText.innerHTML  = '<i class="fas fa-check-circle mr-1"></i> ' + msg;
        focusIndicator.innerHTML = '<span class="badge badge-success px-3 py-2" style="font-size:0.95rem;"><i class="fas fa-lock mr-1"></i> Scanner Active — Ready to Receive Input</span>';
        setTimeout(resetStatusReady, 2500);
    }
    function setStatusError(msg) {
        statusRing.className  = 'scanner-ready-ring error';
        statusIcon.className  = 'fas fa-times-circle fa-3x text-danger';
        statusText.className  = 'alert alert-danger mb-3';
        statusText.innerHTML  = '<i class="fas fa-times-circle mr-1"></i> ' + msg;
        focusIndicator.innerHTML = '<span class="badge badge-danger px-3 py-2" style="font-size:0.95rem;"><i class="fas fa-exclamation-circle mr-1"></i> Scan Failed — Try Again</span>';
        setTimeout(resetStatusReady, 3000);
    }
    function resetStatusReady() {
        statusRing.className  = 'scanner-ready-ring';
        statusIcon.className  = 'fas fa-barcode fa-3x text-success';
        statusText.className  = 'alert alert-success mb-3';
        statusText.innerHTML  = '<i class="fas fa-circle-notch fa-spin mr-1"></i> Ready — point the scanner at a QR code.';
        focusIndicator.innerHTML = '<span class="badge badge-success px-3 py-2" style="font-size:0.95rem;"><i class="fas fa-lock mr-1"></i> Scanner Active — Ready to Receive Input</span>';
    }

    // ── Result Card Helpers ──────────────────────────────────────────
    function showResultSuccess(g, fullName) {
        resultCard.style.display = 'block';
        resultCard.className     = 'card card-outline card-success';
        resultText.innerHTML = `
            <div class="d-flex align-items-center">
                ${g.photo
                    ? `<img src="../${g.photo}" class="img-circle elevation-2 mr-3"
                            style="width:70px;height:70px;object-fit:cover;" alt="Photo">`
                    : `<div class="img-circle bg-secondary d-flex align-items-center justify-content-center mr-3"
                             style="width:70px;height:70px;">
                           <i class="fas fa-user fa-2x text-white"></i>
                       </div>`
                }
                <div>
                    <h4 class="mb-1">${fullName}</h4>
                    <p class="mb-0 text-muted">${g.course}${g.major ? ' — ' + g.major : ''}</p>
                    ${g.honors && g.honors !== 'none'
                        ? `<span class="badge badge-warning">${g.honors}</span>`
                        : ''}
                </div>
            </div>`;
    }
    function showResultError(msg) {
        resultCard.style.display = 'block';
        resultCard.className     = 'card card-outline card-danger';
        resultText.innerHTML = `<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>${msg}</p>`;
    }

    // ── Session Log ──────────────────────────────────────────────────
    function prependSessionRow(num, studentId, name, course, time) {
        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${num}</td>
            <td>${studentId}</td>
            <td>${name}</td>
            <td>${course}</td>
            <td>${time}</td>`;
        sessionBody.insertBefore(tr, sessionBody.firstChild);
    }

    // ── Display Trigger ──────────────────────────────────────────────
    function triggerDisplay() {
        if (pendingDisplayData) {
            displayChannel.postMessage(pendingDisplayData);
            pendingDisplayData = null;
            displayNowBtn.style.display = 'none';

            // First manual display done — switch to auto-display for all future scans
            if (!autoDisplay) {
                autoDisplay = true;
                // Update the badge to show auto mode is now active
                focusIndicator.innerHTML = '<span class="badge badge-primary px-3 py-2" style="font-size:0.95rem;"><i class="fas fa-magic mr-1"></i> Auto-Display ON — Scans display instantly</span>';
            }
        }
    }

    // ── End Session ──────────────────────────────────────────────────
    function endSession() {
        if (sessionScans.length > 0) {
            let csv = 'Student ID,Name,Course,Time\n';
            sessionScans.forEach(s => {
                csv += `${s.student_id},"${s.name}",${s.course},${s.time}\n`;
            });
            const blob = new Blob([csv], { type: 'text/csv' });
            const link = document.createElement('a');
            link.href     = URL.createObjectURL(blob);
            link.download = 'scan_session_' + new Date().toISOString().slice(0, 10) + '.csv';
            link.click();
        }
        window.location.href = 'dashboard.php';
    }
</script>
</body>
</html>

