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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GradScan | Display</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../frontend/dist/output.css">
</head>

<body class="flex h-screen w-screen cursor-default select-none items-center justify-center overflow-hidden bg-black font-poppins text-white">

    <!-- Waiting state -->
    <div id="waitingState" class="flex h-full w-full flex-col items-center justify-center bg-ascot-dark text-center">

        <div class="mb-8 flex h-28 w-28 items-center justify-center rounded-full bg-white p-3">
            <img
                src="../photos/ascot_logo.png"
                alt="ASCOT Logo"
                class="h-full w-full object-contain"
            >
        </div>

        <p class="text-3xl font-semibold text-white/80">
            Waiting for next graduate...
        </p>

        <p id="fullscreenHint" class="mt-6 text-sm text-green-200/70">
            Double-click to toggle fullscreen
        </p>

    </div>

    <!-- Graduate display: a 16:9 canvas fitted inside the screen -->
    <div
        id="displayState"
        class="hidden w-[min(100vw,calc(100vh*16/9))] opacity-0 transition-opacity duration-500"
    ></div>


    <script src="js/hid_scanner.js?v=<?= filemtime(__DIR__ . '/js/hid_scanner.js') ?>"></script>

    <script>
        const displayChannel = new BroadcastChannel('gradscan_display');
        const inputChannel   = new BroadcastChannel('gradscan_scanner_input');

        const waitingState   = document.getElementById('waitingState');
        const displayState   = document.getElementById('displayState');
        const fullscreenHint = document.getElementById('fullscreenHint');

        // --- Step: Display on Audience Monitor ---
        // display_html is the school's active layout, rendered server-side by
        // gs_render_layout_canvas() in scan_process.php (all values escaped there).
        displayChannel.onmessage = function (event) {
            const { display_html } = event.data;
            if (!display_html) return;

            displayState.classList.add('opacity-0');

            setTimeout(function () {
                displayState.innerHTML = display_html;
                waitingState.classList.add('hidden');
                displayState.classList.remove('hidden');

                requestAnimationFrame(() => displayState.classList.remove('opacity-0'));
            }, displayState.classList.contains('hidden') ? 0 : 300);
        };

        // If this window is focused on the projector, scanner keystrokes land
        // here. Forward them to the scanner page, which does the processing.
        gsListenForScanner(token => inputChannel.postMessage({ token }));

        document.addEventListener('dblclick', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen().catch(() => {});
            }
        });

        document.addEventListener('fullscreenchange', function () {
            fullscreenHint.classList.toggle('hidden', !!document.fullscreenElement);
        });
    </script>

</body>
</html>
