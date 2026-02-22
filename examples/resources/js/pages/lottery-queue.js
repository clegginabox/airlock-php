import { spinnerIcon, checkIcon, errorIcon, warnIcon } from '../lib/alerts.js';
import { AirlockClient } from '../../../../resources/js/airlock-client.js';

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
    const queueState = document.getElementById('queue-state');
    const chamber = document.getElementById('chamber');
    const queueCount = document.getElementById('queue-count');
    const queueCountValue = document.getElementById('queue-count-value');

    const airlockClient = new AirlockClient({
        clientId: CLIENT_ID,
        startUrl: '/redis-lottery-queue/start',
        claimUrl: '/redis-lottery-queue/claim',
        releaseUrl: '/redis-lottery-queue/release',
    });

    let occupied = 0;
    let userSlot = -1;
    let userState = 'idle'; // idle | queued | claiming | admitted
    let liveOccupant = null;
    let queueDepth = 0;
    let latestClaimNonce = null;
    let isClaiming = false;

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
        return;
/*        queueDepth = Math.max(0, queueDepth + delta);
        queueCountValue.textContent = queueDepth;
        if (queueDepth > 0) {
            queueCount.classList.remove('hidden');
        } else {
            queueCount.classList.add('hidden');
        }*/
    }

    eventLog.addEventListener('airlock-log-entry', function (e) {
        const text = e.detail.text;

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

    if (queueState) {
        queueState.setAttribute('client-id', CLIENT_ID);

        if (typeof queueState.bindClient === 'function') {
            queueState.bindClient(airlockClient);
        }

        queueState.addEventListener('airlock-queue-state', function (e) {
            const detail = e.detail || {};

            if (userState !== 'queued') {
                return;
            }

            if (!Number.isInteger(detail.position)) {
                return;
            }

            const position = detail.position;
            const queueSize = Number.isInteger(detail.queueSize) ? detail.queueSize : position;
            const total = Math.max(1, queueSize, position);

            showQueue(position, total);
            setStatusLabel('Queued #' + position, 'text-warning');
            positionDiv.innerHTML = '<p class="mt-3 text-sm text-base-content/50">Position: <strong class="text-base-content">' + position + '</strong></p>';
        });
    }

    airlockClient.addEventListener('turn', async function (event) {
        if (userState !== 'queued') {
            return;
        }

        latestClaimNonce = event.detail?.claimNonce || null;
        await claimReservation(latestClaimNonce);
    });

    // --- Actions ---

    async function claimReservation(claimNonce) {
        if (isClaiming) {
            return false;
        }

        if (!claimNonce) {
            setStatus(alertHtml('warning', warnIcon, 'Turn notice arrived without a claim nonce. Waiting for next turn...'));
            setStatusLabel('Queued', 'text-warning');
            return false;
        }

        isClaiming = true;
        userState = 'claiming';
        setStatus(alertHtml('info', spinnerIcon, 'Your turn! Claiming slot...'));
        setStatusLabel('Claiming...', 'text-info');

        try {
            const claimData = await airlockClient.claim(claimNonce);

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

                return true;
            }

            userState = 'queued';

            if (claimData.error === 'missed') {
                setStatus(alertHtml('warning', warnIcon, 'Claim window expired. Waiting for next turn...'));
                setStatusLabel('Queued', 'text-warning');
                return false;
            }

            if (claimData.error === 'unavailable') {
                setStatus(alertHtml('warning', spinnerIcon, 'Slot not ready yet. Waiting for next turn...'));
                setStatusLabel('Queued', 'text-warning');
                return false;
            }

            setStatus(alertHtml('warning', warnIcon, 'Claim rejected. Waiting for next turn...'));
            setStatusLabel('Queued', 'text-warning');
            return false;
        } catch (err) {
            userState = 'queued';
            setStatus(alertHtml('error', errorIcon, 'Failed to claim slot'));
            setStatusLabel('Error', 'text-error');
            return false;
        } finally {
            isClaiming = false;
        }
    }

    goBtn.addEventListener('click', async function () {
        goBtn.disabled = true;
        goBtn.classList.add('btn-disabled');
        setStatus(alertHtml('info', spinnerIcon, 'Requesting entry...'));
        setStatusLabel('Joining...', 'text-info');

        try {
            const data = await airlockClient.start();

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
                // Queued — wait for turn notification from AirlockClient
                userState = 'queued';
                const pos = Number.isInteger(data.position) ? data.position : 1;
                updateSlots(CAPACITY, false);
                updateBarrier(true);
                showQueue(pos, Math.max(pos, 1));
                setStatus(alertHtml('warning', spinnerIcon, 'Waiting in lottery queue...'));
                setStatusLabel('Queued #' + pos, 'text-warning');
                positionDiv.innerHTML = '<p class="mt-3 text-sm text-base-content/50">Position: <strong class="text-base-content">' + pos + '</strong></p>';
                latestClaimNonce = data.reservationNonce || null;

                if (latestClaimNonce) {
                    var claimed = await claimReservation(latestClaimNonce);
                    if (claimed) {
                        return;
                    }
                }
            }
        } catch (err) {
            setStatus(alertHtml('error', errorIcon, 'Network error: ' + err.message));
            setStatusLabel('Error', 'text-error');
            goBtn.disabled = false;
            goBtn.classList.remove('btn-disabled');
        }
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', async function () {
            resetBtn.disabled = true;
            resetBtn.classList.add('btn-disabled');

            airlockClient.reset();

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
            latestClaimNonce = null;
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
    }
})();
