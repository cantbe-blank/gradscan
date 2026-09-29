/**
 * Keystroke capture for a USB HID ("keyboard wedge") QR scanner such as the SM8070.
 *
 * The scanner behaves like a keyboard: it types the QR payload very fast and
 * then sends its suffix key (Enter by default, Tab on some configurations).
 * Human typing is much slower, so a gap longer than MAX_GAP_MS resets the buffer.
 * If the scanner is configured with no suffix at all, a complete 32-char hex
 * token is submitted after a short idle period instead.
 *
 * Characters are read from `keypress`, not `keydown`: scanners in Alt+keypad
 * emulation mode only produce the final character on keypress, while keydown
 * just sees Alt and numpad keys. Every keydown still counts as scanner activity
 * for the gap timer.
 *
 * onDebug (optional) receives a line for every key event, for scanner setup.
 */
function gsListenForScanner(onScan, onDebug) {
    const MAX_GAP_MS     = 100;
    const IDLE_SUBMIT_MS = 150;
    const TOKEN_PATTERN  = /^[0-9a-f]{32}$/i;

    let buffer       = '';
    let lastActivity = 0;
    let idleTimer    = null;

    function flush() {
        clearTimeout(idleTimer);
        const token = buffer.trim();
        buffer = '';

        if (onDebug) onDebug('submit "' + token + '"');
        if (token) {
            onScan(TOKEN_PATTERN.test(token) ? token.toLowerCase() : token);
        }
    }

    function touch() {
        const now = Date.now();
        if (now - lastActivity > MAX_GAP_MS && buffer) {
            if (onDebug) onDebug('gap ' + (now - lastActivity) + 'ms, buffer reset');
            buffer = '';
        }
        lastActivity = now;
    }

    document.addEventListener('keydown', function (e) {
        if (onDebug) onDebug('keydown  key=' + JSON.stringify(e.key) + ' code=' + e.code + (e.altKey ? ' +alt' : ''));

        if (e.key === 'Enter' || e.key === 'Tab') {
            touch();
            if (buffer) {
                // Keep the scanner's Enter from "clicking" a focused button.
                e.preventDefault();
                flush();
            }
            return;
        }

        // Alt/numpad presses of an Alt-code character are scanner activity too.
        if (buffer) lastActivity = Date.now();
    });

    document.addEventListener('keypress', function (e) {
        if (onDebug) onDebug('keypress key=' + JSON.stringify(e.key));

        if (e.ctrlKey || e.metaKey) return;
        if (e.key === 'Enter' || e.key.length !== 1) return;

        touch();
        buffer += e.key;

        clearTimeout(idleTimer);
        if (TOKEN_PATTERN.test(buffer)) {
            idleTimer = setTimeout(flush, IDLE_SUBMIT_MS);
        }
    });
}
