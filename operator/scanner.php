<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'GradScan | Scanner';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

?>

<div class="gs-main">

    <!-- Top Navigation -->
    <header class="gs-topbar">

        <div>
            <h2 class="gs-topbar-title">
                QR Scanner
            </h2>

            <p class="gs-topbar-subtitle">
                SM8070 USB scanner
            </p>
        </div>


        <div class="flex items-center gap-5">

            <span class="gs-badge gs-badge-success text-sm">
                Scanned: <span id="scanCount">0</span>
            </span>

            <div class="text-right">

                <p class="gs-user-name">
                    <?= htmlspecialchars($_SESSION['full_name']) ?>
                </p>

                <p class="gs-user-role">
                    <?= htmlspecialchars($_SESSION['role']) ?>
                </p>

            </div>

            <a
                href="../logout.php"
                class="gs-button-danger"
            >
                Logout
            </a>

        </div>

    </header>


    <!-- Content -->
    <main class="gs-content">

        <div class="grid gap-6 lg:grid-cols-5">

            <!-- Left Column: Scanner Status -->
            <div class="space-y-6 lg:col-span-2">

                <section class="gs-card px-6 py-8 text-center">

                    <!-- Status ring -->
                    <div id="statusRing" class="gs-scan-ring gs-scan-ring-ready">

                        <svg
                            id="statusIcon"
                            class="h-14 w-14"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"
                            />
                        </svg>

                    </div>

                    <!-- Status message -->
                    <div id="statusText" class="gs-alert gs-alert-success">
                        Ready. Point the scanner at a QR code.
                    </div>

                    <!-- Focus indicator -->
                    <div id="focusIndicator" class="mt-4">
                        <span class="gs-badge gs-badge-success">
                            Listening for scanner input
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex flex-wrap justify-center gap-3">

                        <button
                            id="openDisplayBtn"
                            type="button"
                            class="gs-button-secondary hidden"
                            onclick="openDisplay()"
                        >
                            Open Audience Display
                        </button>

                        <button
                            id="displayNowBtn"
                            type="button"
                            class="gs-button-primary hidden"
                            onclick="triggerDisplay()"
                        >
                            Display Now
                        </button>

                        <button
                            id="endSessionBtn"
                            type="button"
                            class="gs-button-danger py-3"
                            onclick="endSession()"
                        >
                            End Session
                        </button>

                    </div>

                </section>


                <!-- Instructions -->
                <section class="gs-card p-6">

                    <h3 class="mb-4 font-semibold text-gray-800">
                        How to Use
                    </h3>

                    <ol class="list-decimal space-y-2 pl-5 text-sm leading-6 text-gray-600">
                        <li>Plug the SM8070 scanner into a USB port. It types like a keyboard, so no driver is needed.</li>
                        <li>Move the audience display window to the projector. Double-click it to go fullscreen.</li>
                        <li>Scan the graduate's QR code. It is submitted automatically.</li>
                        <li>For the first graduate, press <strong>Display Now</strong>. Later scans appear on the audience screen right away.</li>
                    </ol>

                    <p class="mt-4 text-xs leading-5 text-gray-400">
                        Scans work while either this page or the audience display is the active window.
                    </p>

                </section>

            </div>


            <!-- Right Column: Result + Session Log -->
            <div class="space-y-6 lg:col-span-3">

                <?php if (isset($_GET['debug'])): ?>
                <!-- Scanner debug: shows every key event the page receives -->
                <section class="gs-card">

                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <h3 class="font-semibold text-gray-800">
                            Scanner Debug
                        </h3>

                        <button type="button" class="gs-button-secondary" onclick="debugLog.textContent = ''">
                            Clear
                        </button>
                    </div>

                    <pre id="debugLog" class="h-64 overflow-auto p-4 font-mono text-xs leading-5 text-gray-700"></pre>

                </section>
                <?php endif; ?>

                <!-- Scan Result -->
                <section id="resultCard" class="gs-card hidden">

                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="font-semibold text-gray-800">
                            Last Scan Result
                        </h3>
                    </div>

                    <div id="resultText" class="p-6"></div>

                </section>


                <!-- Session Scan Log -->
                <section class="gs-table-wrapper">

                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="font-semibold text-gray-800">
                            Session Scan Log
                        </h3>
                    </div>

                    <div class="overflow-x-auto">

                        <table class="gs-table">

                            <thead class="gs-table-header">
                                <tr>
                                    <th class="px-6 py-3">#</th>
                                    <th class="px-6 py-3">Student ID</th>
                                    <th class="px-6 py-3">Name</th>
                                    <th class="px-6 py-3">Course</th>
                                    <th class="px-6 py-3">Time</th>
                                </tr>
                            </thead>

                            <tbody id="sessionTableBody" class="divide-y divide-gray-100">
                                <tr id="emptyRow">
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">
                                        No scans yet this session.
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                    </div>

                </section>

            </div>

        </div>

    </main>

</div>


<script src="js/hid_scanner.js?v=<?= filemtime(__DIR__ . '/js/hid_scanner.js') ?>"></script>

<script>
    // DOM References
    const statusText     = document.getElementById('statusText');
    const statusRing     = document.getElementById('statusRing');
    const statusIcon     = document.getElementById('statusIcon');
    const resultCard     = document.getElementById('resultCard');
    const resultText     = document.getElementById('resultText');
    const displayNowBtn  = document.getElementById('displayNowBtn');
    const openDisplayBtn = document.getElementById('openDisplayBtn');
    const sessionBody    = document.getElementById('sessionTableBody');
    const scanCountEl    = document.getElementById('scanCount');
    const focusIndicator = document.getElementById('focusIndicator');

    const ICONS = {
        ready:   '<path stroke-linecap="round" stroke-linejoin="round" d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/>',
        busy:    '<path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9"/>',
        success: '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 3 3 5-6"/>',
        error:   '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="m9 9 6 6m0-6-6 6"/>'
    };

    // State
    // gradscan_display: scanner -> audience display (graduate to show)
    // gradscan_scanner_input: audience display -> scanner (scans typed into that window)
    const displayChannel = new BroadcastChannel('gradscan_display');
    const inputChannel   = new BroadcastChannel('gradscan_scanner_input');
    let displayWindow      = null;
    let pendingDisplayData = null;
    let sessionScans       = [];
    let scanCount          = 0;
    let lastToken          = '';
    let lastScanTime       = 0;
    let processingLock     = false;
    let resetTimer         = null;
    // AUTO-DISPLAY: false on first scan (manual), true for all subsequent scans
    let autoDisplay        = false;

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    // Audience Display Window
    function openDisplay() {
        displayWindow = window.open('audience_display.php', 'GradScanDisplay', 'width=1280,height=720');
        updateDisplayButton();
    }

    function displayIsOpen() {
        return displayWindow && !displayWindow.closed;
    }

    function updateDisplayButton() {
        openDisplayBtn.classList.toggle('hidden', displayIsOpen());
    }

    // Popup blockers may refuse this; the "Open Audience Display" button covers that case.
    openDisplay();

    // Scanner Input
    function handleScan(token) {
        const now = Date.now();

        // Debounce: ignore same token within 3 seconds
        if (token === lastToken && (now - lastScanTime) < 3000) return;
        if (processingLock) return;

        lastToken    = token;
        lastScanTime = now;

        processToken(token);
    }

    const debugLog = document.getElementById('debugLog');
    gsListenForScanner(handleScan, debugLog ? function (line) {
        debugLog.textContent = new Date().toISOString().slice(17, 23) + '  ' + line + '\n' + debugLog.textContent;
    } : null);
    inputChannel.onmessage = e => handleScan(e.data.token);

    // Keystrokes only reach the focused window, so warn when neither
    // this page nor the audience display has focus.
    setInterval(function () {
        updateDisplayButton();

        let focused = document.hasFocus();
        try {
            focused = focused || (displayIsOpen() && displayWindow.document.hasFocus());
        } catch (e) { /* window not ready yet */ }

        if (!processingLock && !resetTimer) {
            setFocusBadge(focused);
        }
    }, 1000);

    // Token Processing
    function processToken(token) {
        processingLock = true;
        setStatusProcessing();
        displayNowBtn.classList.add('hidden');

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
                return;
            }

            const g = data.graduate;
            const fullName = [g.first_name, g.middle_name ? g.middle_name.charAt(0) + '.' : '', g.last_name, g.suffix || '']
                                .filter(Boolean).join(' ');

            setStatusSuccess('Scanned: <strong>' + esc(fullName) + '</strong>');
            showResultSuccess(g, fullName);

            pendingDisplayData = data;

            // AUTO-DISPLAY LOGIC:
            // First scan -> show "Display Now" button (manual control).
            // All subsequent scans -> push to audience display automatically.
            if (autoDisplay) {
                displayChannel.postMessage(pendingDisplayData);
                pendingDisplayData = null;
            } else {
                displayNowBtn.classList.remove('hidden');
            }

            // Append to session log
            scanCount++;
            scanCountEl.textContent = scanCount;
            const scan = {
                student_id: g.student_id,
                name:       fullName,
                course:     g.course,
                time:       new Date().toLocaleTimeString()
            };
            sessionScans.push(scan);
            prependSessionRow(scanCount, scan);
        })
        .catch(() => {
            setStatusError('Network error. Please check connection.');
            showResultError('Network error. Please try again.');
        })
        .finally(() => {
            processingLock = false;
        });
    }

    // Status Helpers
    function setStatus(state, alertClass, html) {
        clearTimeout(resetTimer);
        resetTimer = null;

        const ringClass = { ready: 'gs-scan-ring-ready', busy: 'gs-scan-ring-busy', success: 'gs-scan-ring-ready', error: 'gs-scan-ring-error' }[state];
        statusRing.className = 'gs-scan-ring ' + ringClass;
        statusIcon.innerHTML = ICONS[state];
        statusIcon.classList.toggle('animate-spin', state === 'busy');
        statusText.className = 'gs-alert ' + alertClass;
        statusText.innerHTML = html;
    }

    function setFocusBadge(focused) {
        if (!focused) {
            focusIndicator.innerHTML = '<span class="gs-badge gs-badge-warning">Not listening. Click this page to resume scanning.</span>';
        } else if (autoDisplay) {
            focusIndicator.innerHTML = '<span class="gs-badge gs-badge-info">Auto-Display ON. Scans display instantly.</span>';
        } else {
            focusIndicator.innerHTML = '<span class="gs-badge gs-badge-success">Listening for scanner input</span>';
        }
    }

    function setStatusProcessing() {
        setStatus('busy', 'gs-alert-info', 'Processing scan...');
        focusIndicator.innerHTML = '<span class="gs-badge gs-badge-info">Processing...</span>';
    }

    function setStatusSuccess(msg) {
        setStatus('success', 'gs-alert-success', msg);
        setFocusBadge(true);
        resetTimer = setTimeout(resetStatusReady, 2500);
    }

    function setStatusError(msg) {
        setStatus('error', 'gs-alert-error', esc(msg));
        focusIndicator.innerHTML = '<span class="gs-badge gs-badge-error">Scan failed. Try again.</span>';
        resetTimer = setTimeout(resetStatusReady, 3000);
    }

    function resetStatusReady() {
        setStatus('ready', 'gs-alert-success', 'Ready. Point the scanner at a QR code.');
        setFocusBadge(true);
    }

    // Result Card Helpers
    function showResultSuccess(g, fullName) {
        resultCard.classList.remove('hidden', 'border-red-200');

        const photo = g.photo
            ? `<img src="../${esc(g.photo)}" alt="Photo" class="h-20 w-20 shrink-0 rounded-full object-cover shadow">`
            : `<div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-gray-200 text-gray-400">
                   <svg class="h-10 w-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z"/></svg>
               </div>`;

        const honors = g.honors && g.honors !== 'none'
            ? `<span class="gs-badge gs-badge-warning mt-2 capitalize">${esc(g.honors)}</span>`
            : '';

        resultText.innerHTML = `
            <div class="flex items-center gap-5">
                ${photo}
                <div>
                    <h4 class="text-xl font-semibold text-ascot-dark">${esc(fullName)}</h4>
                    <p class="text-sm text-gray-500">${esc(g.student_id)}</p>
                    <p class="text-sm text-gray-600">${esc(g.course)}${g.major ? ' — ' + esc(g.major) : ''}</p>
                    ${honors}
                </div>
            </div>`;
    }

    function showResultError(msg) {
        resultCard.classList.remove('hidden');
        resultCard.classList.add('border-red-200');
        resultText.innerHTML = `<p class="text-sm font-medium text-red-600">${esc(msg)}</p>`;
    }

    // Session Log
    function prependSessionRow(num, scan) {
        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.className = 'bg-green-50 font-semibold';
        tr.innerHTML = `
            <td class="gs-table-cell">${num}</td>
            <td class="gs-table-cell">${esc(scan.student_id)}</td>
            <td class="gs-table-cell">${esc(scan.name)}</td>
            <td class="gs-table-cell">${esc(scan.course)}</td>
            <td class="gs-table-cell">${esc(scan.time)}</td>`;

        // Only the newest row stays highlighted
        const previous = sessionBody.firstElementChild;
        if (previous) previous.className = '';

        sessionBody.insertBefore(tr, sessionBody.firstChild);
    }

    // Display Trigger
    function triggerDisplay() {
        if (!pendingDisplayData) return;

        displayChannel.postMessage(pendingDisplayData);
        pendingDisplayData = null;
        displayNowBtn.classList.add('hidden');
        displayNowBtn.blur();

        // First manual display done: switch to auto-display for all future scans
        if (!autoDisplay) {
            autoDisplay = true;
            setFocusBadge(true);
        }
    }

    // End Session
    function endSession() {
        if (sessionScans.length > 0) {
            const cell = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
            let csv = 'Student ID,Name,Course,Time\n';
            sessionScans.forEach(s => {
                csv += [s.student_id, s.name, s.course, s.time].map(cell).join(',') + '\n';
            });
            const blob = new Blob([csv], { type: 'text/csv' });
            const link = document.createElement('a');
            link.href     = URL.createObjectURL(blob);
            link.download = 'scan_session_' + new Date().toISOString().slice(0, 10) + '.csv';
            link.click();
        }
        if (displayIsOpen()) displayWindow.close();
        window.location.href = 'dashboard.php';
    }
</script>

<?php require_once 'includes/footer.php'; ?>
