<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Display</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #1e293b;
            color: #ffffff;
            transition: background-color 0.5s ease;
        }
        #waitingState {
            font-size: 2rem;
            opacity: 0.6;
        }
        #displayState {
            display: none;
            text-align: center;
            padding: 60px;
        }
        #displayState.active {
            display: block;
        }
        #photo {
            width: 220px;
            height: 220px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid rgba(255,255,255,0.3);
            margin-bottom: 30px;
        }
        #headerText {
            font-size: 1.5rem;
            margin-bottom: 10px;
            opacity: 0.8;
        }
        #fullName {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 15px;
        }
        #courseMajor {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        #honors {
            font-size: 1.4rem;
            font-style: italic;
        }
        #accentBar {
            width: 100px;
            height: 5px;
            margin: 20px auto;
        }
    </style>
</head>
<body>

    <div id="waitingState">Waiting for next graduate...</div>

    <div id="displayState">
        <img id="photo" src="" alt="Graduate Photo">
        <div id="headerText"></div>
        <div id="fullName"></div>
        <div id="accentBar"></div>
        <div id="courseMajor"></div>
        <div id="honors"></div>
    </div>

    <script>
        const channel = new BroadcastChannel('gradscan_display');

        const waitingState = document.getElementById('waitingState');
        const displayState = document.getElementById('displayState');
        const photo = document.getElementById('photo');
        const headerText = document.getElementById('headerText');
        const fullName = document.getElementById('fullName');
        const courseMajor = document.getElementById('courseMajor');
        const honorsEl = document.getElementById('honors');
        const accentBar = document.getElementById('accentBar');

        channel.onmessage = function (event) {
            const { graduate, layout } = event.data;

            // --- Step: Generate Display (apply the department's saved layout styling) ---
            if (layout) {
                document.body.style.backgroundColor = layout.background_color || '#1e293b';
                fullName.style.color = layout.text_color || '#ffffff';
                headerText.style.color = layout.text_color || '#ffffff';
                accentBar.style.backgroundColor = layout.accent_color || '#3b82f6';
                headerText.textContent = layout.header_text || 'Congratulations Graduates!';
            }

            // --- Step: Display on Audience Monitor ---
            const middleInitial = graduate.middle_name ? graduate.middle_name.charAt(0) + '.' : '';
            fullName.textContent = `${graduate.first_name} ${middleInitial} ${graduate.last_name} ${graduate.suffix || ''}`.replace(/\s+/g, ' ').trim();

            courseMajor.textContent = graduate.major
                ? `${graduate.course} - ${graduate.major}`
                : graduate.course;

            if (graduate.honors && graduate.honors !== 'none') {
                honorsEl.style.display = 'block';
                honorsEl.textContent = graduate.honors.replace(/\b\w/g, c => c.toUpperCase());
            } else {
                honorsEl.style.display = 'none';
            }

            const showPhoto = !layout || layout.show_photo !== false;
            if (showPhoto && graduate.photo) {
                photo.style.display = 'block';
                photo.src = '../' + graduate.photo;
            } else {
                photo.style.display = 'none';
            }

            waitingState.style.display = 'none';
            displayState.classList.add('active');
        };
    </script>
</body>
</html>
