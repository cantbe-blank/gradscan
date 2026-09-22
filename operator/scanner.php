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

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="dashboard.php" class="brand-link">
            <span class="brand-text font-weight-light">GradScan</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="nav-icon fas fa-camera"></i>
                            <p>Scanner</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <h1>Scanner</h1>
        </div>
        <div class="content">
            <div class="card">
                <div class="card-body text-center">

                    <video id="video" width="480" height="360" style="border: 2px solid #333; background: #000;" playsinline></video>
                    <canvas id="canvas" style="display: none;"></canvas>

                    <div class="mt-3">
                        <div id="statusText" class="alert alert-info">Camera not started yet.</div>
                        <div id="resultText" class="alert alert-success" style="display: none;"></div>
                        <button id="displayNowBtn" class="btn btn-success btn-lg mt-2" style="display:none;" onclick="triggerDisplay()">
                            <i class="fas fa-tv"></i> Display Now
                        </button>
                        <button id="endSessionBtn" class="btn btn-outline-danger btn-lg mt-2" onclick="endSession()">
                            <i class="fas fa-stop"></i> End Scanner Session
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

<script src="../AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="../AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
<script src="../libs/jsQR.js"></script>
<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const statusText = document.getElementById('statusText');
    const resultText = document.getElementById('resultText');

    let lastScannedToken = null;
    let lastScanTime = 0;

    const displayChannel = new BroadcastChannel('gradscan_display');
    let displayWindow = null;
    let pendingDisplayData = null;

    let sessionScans = [];

    // --- Auto-open the audience display window once, on page load ---
    displayWindow = window.open('audience_display.php', 'GradScanDisplay', 'width=1280,height=720');

    // --- Step: Initialize Camera ---
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function (stream) {
            video.srcObject = stream;
            video.setAttribute('playsinline', true);
            video.play();
            statusText.textContent = 'Camera ready. Waiting for QR code...';
            requestAnimationFrame(tick);
        })
        .catch(function (err) {
            statusText.textContent = 'Could not access camera: ' + err.message;
            statusText.className = 'alert alert-danger';
        });

    // --- Step: Wait for QR Code / QR Detected? / Decode QR ---
    function tick() {
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.height = video.videoHeight;
            canvas.width = video.videoWidth;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);

            if (code) {
                const now = Date.now();
                // Basic debounce: ignore the same token if scanned again within 3 seconds,
                // so one physical QR held in front of the camera doesn't fire repeatedly.
                if (code.data !== lastScannedToken || (now - lastScanTime) > 3000) {
                    lastScannedToken = code.data;
                    lastScanTime = now;

                    resultText.style.display = 'block';
                    resultText.textContent = 'QR Detected: ' + code.data;
                    console.log('Decoded QR token:', code.data);
                    console.log('Token length:', code.data.length);

                    fetch('scan_process.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ token: code.data })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            resultText.className = 'alert alert-danger';
                            resultText.textContent = data.error;
                            document.getElementById('displayNowBtn').style.display = 'none';
                        } else {
                            resultText.className = 'alert alert-success';
                            resultText.textContent = 'Scanned: ' + data.graduate.first_name + ' ' + data.graduate.last_name + ' — ready to display.';

                            pendingDisplayData = data;
                            document.getElementById('displayNowBtn').style.display = 'inline-block';
                            sessionScans.push({
                                student_id: data.graduate.student_id,
                                name: data.graduate.first_name + ' ' + data.graduate.last_name,
                                course: data.graduate.course,
                                time: new Date().toLocaleTimeString()
                            });
                            
                        }
                    });

                    
                }
            }
        }
        requestAnimationFrame(tick);
    }
    function triggerDisplay() {
                        if (pendingDisplayData) {
                            displayChannel.postMessage(pendingDisplayData);
                            document.getElementById('displayNowBtn').style.display = 'none';
                        }
                    }
    function endSession() {
    // Stop the camera
    if (video.srcObject) {
        video.srcObject.getTracks().forEach(track => track.stop());
    }

    // Build and download the CSV
    if (sessionScans.length > 0) {
        let csv = 'Student ID,Name,Course,Time\n';
        sessionScans.forEach(scan => {
            csv += `${scan.student_id},"${scan.name}",${scan.course},${scan.time}\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'scan_session_' + new Date().toISOString().slice(0,10) + '.csv';
        link.click();
    }

        window.location.href = 'dashboard.php';
    }
</script>
</body>
</html>
