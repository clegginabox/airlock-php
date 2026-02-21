import { spinnerIcon, checkIcon, errorIcon, warnIcon } from '../lib/alerts.js';

(function () {
    // Per-tab identity — sessionStorage is isolated per tab
    if (!sessionStorage.getItem('clientId')) {
        sessionStorage.setItem('clientId', crypto.randomUUID());
    }
    const CLIENT_ID = sessionStorage.getItem('clientId');

    const CAPACITY = 1;
    const REDIRECT_DELAY = 1500;

    const goBtn = document.getElementById('go');
    const resetBtn = document.getElementById('reset');
    const statusDiv = document.getElementById('status');
    const positionDiv = document.getElementById('position');
    const statusLabel = document.getElementById('status-label');
    const queueArea = document.getElementById('queue-area');
    const queueBadges = document.getElementById('queue-badges');
    const barrier = document.getElementById('barrier');
    const barrierLabel = document.getElementById('barrier-label');
    const slots = [
        document.getElementById('slot-0'),
    ];

    const eventLog = document.getElementById('event-log');
    const chamber = document.getElementById('chamber');
    const queueCount = document.getElementById('queue-count');
    const queueCountValue = document.getElementById('queue-count-value');

    let eventSource = null;
    let occupied = 0;
    let userSlot = -1;
    let userState = 'idle'; // idle | queued | admitted
    let liveOccupant = null;
    let queueDepth = 0;

    // --- Visual helpers ---

    function updateSlots(count, youIn) {
        occupied = count;
        slots.forEach((slot, i) => {
            slot.className = 'slot';
            if (i < count) {
                if (youIn && i === (userSlot >= 0 ? userSlot : count - 1)) {
                    slot.className = 'slot you';
                    slot.textContent = 'You';
                } else {
                    slot.className = 'slot occupied';
                    slot.textContent = 'In';
                }
            } else {
                slot.textContent = 'Empty';
            }
        });
    }

    function updateBarrier(full) {
        if (full) {
            barrier.className = 'barrier sealed mx-auto mt-5 w-3/4 sm:w-1/2';
            barrierLabel.textContent = 'Sealed';
            barrierLabel.className = 'mt-2 text-center text-xs font-medium text-error';
        } else {
            barrier.className = 'barrier open mx-auto mt-5 w-3/4 sm:w-1/2';
            barrierLabel.textContent = 'Open';
            barrierLabel.className = 'mt-2 text-center text-xs font-medium text-base-content/30';
        }
    }

    function showQueue(position, total) {
        queueArea.classList.remove('hidden');
        queueBadges.innerHTML = '';
        for (let i = 1; i <= total; i++) {
            const badge = document.createElement('div');
            badge.className = 'queue-badge' + (i === position ? ' you' : '');
            badge.textContent = i;
            queueBadges.appendChild(badge);
        }
    }

    function hideQueue() {
        queueArea.classList.add('hidden');
        queueBadges.innerHTML = '';
    }

    function setStatusLabel(text, color) {
        statusLabel.textContent = text;
        statusLabel.className = 'mt-2 text-2xl font-black ' + (color || 'text-base-content/30');
    }

    function setStatus(html) {
        statusDiv.innerHTML = html;
    }

    var LQ_ALERT_CLASSES = {
        info: 'alert alert-info',
        success: 'alert alert-success',
        warning: 'alert alert-warning',
        error: 'alert alert-error',
    };

    function alertHtml(type, icon, text) {
        return '<div class="' + (LQ_ALERT_CLASSES[type] || 'alert') + ' lq-status-alert mt-5">' +
            '<span>' + icon + '</span>' +
            '<span>' + text + '</span>' +
            '</div>';
    }

    // --- Live chamber (driven by event log) ---

    function extractName(html) {
        var m = html.match(/<strong>([^<]+)<\/strong>/);
        return m ? m[1] : null;
    }

    function flashChamber() {
        chamber.classList.remove('chamber-flash');
        void chamber.offsetWidth; // reflow to restart animation
        chamber.classList.add('chamber-flash');
    }

    function setLiveSlot(name) {
        var slot = slots[0];
        slot.className = 'slot live';
        slot.textContent = name;
        liveOccupant = name;
        updateBarrier(true);
        flashChamber();
    }

    function clearLiveSlot() {
        var slot = slots[0];
        slot.className = 'slot exiting';
        liveOccupant = null;
        setTimeout(function () {
            // Only reset if nothing else claimed the slot during the exit animation
            if (!liveOccupant && userState !== 'admitted') {
                slot.className = 'slot';
                slot.textContent = 'Empty';
                updateBarrier(false);
            }
        }, 300);
    }

    function updateQueueDepth(delta) {
/*        queueDepth = Math.max(0, queueDepth + delta);
        queueCountValue.textContent = queueDepth;
        if (queueDepth > 0) {
            queueCount.classList.remove('hidden');
        } else {
            queueCount.classList.add('hidden');
        }*/
    }

    eventLog.addEventListener('airlock-log-entry', function (e) {
        var text = e.detail.text;

        // Don't override the user's own "You" slot
        if (userState === 'admitted') return;

        if (text.includes('entered the airlock')) {
            setLiveSlot(extractName(text) || '?');
            updateQueueDepth(-1);
        } else if (text.includes('joined the queue')) {
            updateQueueDepth(1);
        } else if (text.includes('left the queue')) {
            updateQueueDepth(-1);
        } else if (text.includes('lock released')) {
            clearLiveSlot();
        }
    });

    // --- Actions ---

    goBtn.addEventListener('click', async function () {
        goBtn.disabled = true;
        goBtn.classList.add('btn-disabled');
        setStatus(alertHtml('info', spinnerIcon, 'Requesting entry...'));
        setStatusLabel('Joining...', 'text-info');

        try {
            const res = await fetch('/redis-lottery-queue/start', {
                method: 'POST',
                headers: { 'X-Client-Id': CLIENT_ID },
            });
            const data = await res.json();

            if (!data.ok) {
                setStatus(alertHtml('error', errorIcon, data.error || 'Request failed'));
                setStatusLabel('Error', 'text-error');
                goBtn.disabled = false;
                goBtn.classList.remove('btn-disabled');
                return;
            }

            if (data.status === 'admitted') {
                // Got in immediately
                userState = 'admitted';
                userSlot = data.position !== undefined ? data.position : occupied;
                updateSlots(Math.min(occupied + 1, CAPACITY), true);
                updateBarrier(occupied >= CAPACITY);
                setStatus(alertHtml('success', checkIcon, 'You got in immediately! Redirecting...'));
                setStatusLabel('Inside', 'text-success');
                setTimeout(function () {
                    window.location.href = '/redis-lottery-queue/success';
                }, REDIRECT_DELAY);
            } else if (data.status === 'queued') {
                // Queued — wait for Mercure notification
                userState = 'queued';
                var pos = data.position || '?';
                updateSlots(CAPACITY, false);
                updateBarrier(true);
                showQueue(pos, Math.max(pos, 1));
                setStatus(alertHtml('warning', spinnerIcon, 'Waiting in lottery queue...'));
                setStatusLabel('Queued #' + pos, 'text-warning');
                positionDiv.innerHTML = '<p class="mt-3 text-sm text-base-content/50">Position: <strong class="text-base-content">' + pos + '</strong></p>';

                // Subscribe to Mercure for your_turn event
                if (data.hubUrl && data.topic) {
                    var url = new URL(data.hubUrl);
                    url.searchParams.append('topic', data.topic);
                    if (data.token) {
                        // Set JWT as cookie — EventSource can't send Authorization headers
                        document.cookie = 'mercureAuthorization=' + encodeURIComponent(data.token) + '; path=/.well-known/mercure; SameSite=Strict';
                    }
                    eventSource = new EventSource(url, { withCredentials: true });

                    eventSource.onmessage = async function (event) {
                        var msg = JSON.parse(event.data);
                        if (msg.event === 'your_turn') {
                            if (eventSource) {
                                eventSource.close();
                                eventSource = null;
                            }
                            setStatus(alertHtml('info', spinnerIcon, 'Your turn! Claiming slot...'));
                            setStatusLabel('Claiming...', 'text-info');

                            // Claim the slot by calling start again
                            try {
                                var claimRes = await fetch('/redis-lottery-queue/start', {
                                    method: 'POST',
                                    headers: { 'X-Client-Id': CLIENT_ID },
                                });
                                var claimData = await claimRes.json();

                                if (claimData.ok && claimData.status === 'admitted') {
                                    userState = 'admitted';
                                    hideQueue();
                                    userSlot = 0;
                                    updateSlots(Math.min(CAPACITY, occupied), true);
                                    setStatus(alertHtml('success', checkIcon, 'Your turn! Redirecting...'));
                                    setStatusLabel('Inside', 'text-success');
                                    positionDiv.innerHTML = '';
                                    setTimeout(function () {
                                        window.location.href = '/redis-lottery-queue/success';
                                    }, REDIRECT_DELAY);
                                } else {
                                    // Rare race — someone else grabbed it, re-queue
                                    setStatus(alertHtml('warning', spinnerIcon, 'Waiting in lottery queue...'));
                                    setStatusLabel('Queued', 'text-warning');
                                }
                            } catch (err) {
                                setStatus(alertHtml('error', errorIcon, 'Failed to claim slot'));
                                setStatusLabel('Error', 'text-error');
                            }
                        }
                    };

                    eventSource.onerror = function () {
                        // Silently reconnect — EventSource handles this
                    };
                }
            }
        } catch (err) {
            setStatus(alertHtml('error', errorIcon, 'Network error: ' + err.message));
            setStatusLabel('Error', 'text-error');
            goBtn.disabled = false;
            goBtn.classList.remove('btn-disabled');
        }
    });

    resetBtn.addEventListener('click', async function () {
        resetBtn.disabled = true;
        resetBtn.classList.add('btn-disabled');

        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }

        try {
            await fetch('/reset', { method: 'GET' });
        } catch (e) {
            // ignore
        }

        // Reset visuals
        setStatus('');
        positionDiv.innerHTML = '';
        userSlot = -1;
        userState = 'idle';
        liveOccupant = null;
        queueDepth = 0;
        occupied = 0;
        updateSlots(0, false);
        updateBarrier(false);
        hideQueue();
        queueCount.classList.add('hidden');
        queueCountValue.textContent = '0';
        eventLog.clear();
        setStatusLabel('Idle', '');
        statusLabel.className = 'mt-2 text-2xl font-black text-base-content/30';

        goBtn.disabled = false;
        goBtn.classList.remove('btn-disabled');
        resetBtn.disabled = false;
        resetBtn.classList.remove('btn-disabled');
    });
})();
