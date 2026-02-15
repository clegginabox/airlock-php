import { spinnerIcon, checkIcon, errorIcon, alertHtml } from '../lib/alerts.js';

(function () {
    var LOCK_DURATION = 3; // seconds — visual estimate

    var goBtn = document.getElementById('go');
    var statusDiv = document.getElementById('status');
    var lockIcon = document.getElementById('lock-icon');
    var lockPath = document.getElementById('lock-path');
    var lockLabel = document.getElementById('lock-label');
    var lockHint = document.getElementById('lock-hint');
    var progress = document.getElementById('progress');
    var progressFill = document.getElementById('progress-fill');

    // Lock SVG paths
    var UNLOCKED_PATH = 'M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z';
    var LOCKED_PATH = 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z';

    var GL_ALERT_CLASSES = {
        info: 'alert alert-info',
        success: 'alert alert-success',
        warning: 'alert alert-warning',
        error: 'alert alert-error',
    };

    function glAlertHtml(type, icon, text) {
        return '<div class="' + (GL_ALERT_CLASSES[type] || 'alert') + ' gl-status-alert mt-5">' +
            '<span>' + icon + '</span>' +
            '<span>' + text + '</span>' +
            '</div>';
    }

    function setLocked() {
        lockIcon.className = 'lock-icon locked';
        lockPath.setAttribute('d', LOCKED_PATH);
        lockLabel.textContent = 'Locked';
        lockLabel.className = 'mt-2 text-2xl font-black text-warning';
        lockHint.textContent = 'Processing...';
        lockHint.className = 'mt-3 text-center text-xs font-medium text-warning/60';
        progress.classList.remove('hidden');
        // Reset animation
        progressFill.style.animation = 'none';
        progressFill.offsetHeight;
        progressFill.style.setProperty('--duration', LOCK_DURATION + 's');
        progressFill.style.animation = '';
    }

    function setUnlocked() {
        lockIcon.className = 'lock-icon done';
        lockPath.setAttribute('d', UNLOCKED_PATH);
        lockLabel.textContent = 'Unlocked';
        lockLabel.className = 'mt-2 text-2xl font-black text-success';
        lockHint.textContent = 'Complete';
        lockHint.className = 'mt-3 text-center text-xs font-medium text-success/60';
        progress.classList.add('hidden');
    }

    function setRejected() {
        lockIcon.className = 'lock-icon rejected';
        lockPath.setAttribute('d', LOCKED_PATH);
        lockLabel.textContent = 'Held';
        lockLabel.className = 'mt-2 text-2xl font-black text-error';
        lockHint.textContent = 'Locked by another request';
        lockHint.className = 'mt-3 text-center text-xs font-medium text-error/60';
        progress.classList.add('hidden');
    }

    function setIdle() {
        lockIcon.className = 'lock-icon';
        lockPath.setAttribute('d', UNLOCKED_PATH);
        lockLabel.textContent = 'Unlocked';
        lockLabel.className = 'mt-2 text-2xl font-black text-base-content/30';
        lockHint.textContent = 'Ready';
        lockHint.className = 'mt-3 text-center text-xs font-medium text-base-content/30';
        progress.classList.add('hidden');
    }

    // Check initial lock state
    fetch('/global-lock/status')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.locked) {
                lockIcon.className = 'lock-icon locked';
                lockPath.setAttribute('d', LOCKED_PATH);
                lockLabel.textContent = 'Locked';
                lockLabel.className = 'mt-2 text-2xl font-black text-warning';
                lockHint.textContent = 'Held by another request';
                lockHint.className = 'mt-3 text-center text-xs font-medium text-warning/60';
            }
        })
        .catch(function () {});

    goBtn.addEventListener('click', async function () {
        statusDiv.innerHTML = glAlertHtml('info', spinnerIcon, 'Processing...');
        statusDiv.className = '';
        setLocked();

        try {
            var res = await fetch('/global-lock/start', { method: 'POST' });
            var data = await res.json();

            if (!res.ok || !data.ok) {
                statusDiv.innerHTML = glAlertHtml('error', errorIcon, data.error || 'Already processing');
                statusDiv.className = 'error';
                setRejected();

                // Auto-reset after 3s
                setTimeout(function () {
                    setIdle();
                    statusDiv.innerHTML = '';
                    statusDiv.className = '';
                }, 3000);
            } else {
                statusDiv.innerHTML = glAlertHtml('success', checkIcon, 'Done!');
                statusDiv.className = 'ok';
                setUnlocked();

                // Auto-reset after 3s
                setTimeout(function () {
                    setIdle();
                    statusDiv.innerHTML = '';
                    statusDiv.className = '';
                }, 3000);
            }
        } catch (err) {
            statusDiv.innerHTML = glAlertHtml('error', errorIcon, 'Network error: ' + err.message);
            statusDiv.className = 'error';
            setRejected();
        }
    });
})();
