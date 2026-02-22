import { spinnerIcon, checkIcon, errorIcon, warnIcon } from '../lib/alerts.js';
import { AirlockClient } from '../../../../resources/js/airlock-client.js';

(function () {
    if (!sessionStorage.getItem('clientId')) {
        sessionStorage.setItem('clientId', crypto.randomUUID());
    }
    const CLIENT_ID = sessionStorage.getItem('clientId');

    const CAPACITY = 3;
    const REDIRECT_DELAY = 1500;

    const joinControl = document.getElementById('join');
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
        document.getElementById('slot-1'),
        document.getElementById('slot-2'),
    ].filter(Boolean);

    const eventLog = document.getElementById('event-log');
    const queueState = document.getElementById('queue-state');
    const chamber = document.getElementById('chamber');
    const queueCount = document.getElementById('queue-count');
    const queueCountValue = document.getElementById('queue-count-value');

    const airlockClient = new AirlockClient({
        clientId: CLIENT_ID,
        startUrl: '/redis-fifo-queue/start',
        claimUrl: '/redis-fifo-queue/claim',
        releaseUrl: '/redis-fifo-queue/release',
    });

    let occupied = 0;
    let userState = 'idle'; // idle | queued | claiming | admitted
    let latestClaimNonce = null;
    let isClaiming = false;

    function updateSlots(count, youIn) {
        let next = Math.max(0, Math.min(CAPACITY, count));

        if (youIn && next < 1) {
            next = 1;
        }

        occupied = next;

        slots.forEach((slot, i) => {
            slot.className = 'slot';
            if (i < next) {
                if (youIn && i === 0) {
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

    function updateQueueCount(size) {
        if (!queueCount || !queueCountValue) {
            return;
        }

        const value = Math.max(0, size);
        queueCountValue.textContent = String(value);

        if (value > 0) {
            queueCount.classList.remove('hidden');
        } else {
            queueCount.classList.add('hidden');
        }
    }

    function showQueue(position, total) {
        queueArea.classList.remove('hidden');
        queueBadges.innerHTML = '';

        for (let i = 1; i <= total; i++) {
            const badge = document.createElement('div');
            badge.className = 'queue-badge' + (i === position ? ' you' : '');
            badge.textContent = String(i);
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

    const ALERT_CLASSES = {
        info: 'alert alert-info',
        success: 'alert alert-success',
        warning: 'alert alert-warning',
        error: 'alert alert-error',
    };

    function alertHtml(type, icon, text) {
        return '<div class="' + (ALERT_CLASSES[type] || 'alert') + ' lq-status-alert mt-5">' +
            '<span>' + icon + '</span>' +
            '<span>' + text + '</span>' +
            '</div>';
    }

    function flashChamber() {
        if (!chamber) {
            return;
        }

        chamber.classList.remove('chamber-flash');
        void chamber.offsetWidth;
        chamber.classList.add('chamber-flash');
    }

    function showPosition(position, queueSize) {
        const total = Math.max(1, queueSize, position);
        showQueue(position, total);
        setStatusLabel('Queued #' + position, 'text-warning');
        positionDiv.innerHTML = '<p class="mt-3 text-sm text-base-content/50">Position: <strong class="text-base-content">' + position + '</strong></p>';
    }

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
                latestClaimNonce = null;
                hideQueue();
                updateSlots(Math.max(occupied, 1), true);
                updateBarrier(occupied >= CAPACITY);
                setStatus(alertHtml('success', checkIcon, 'Your turn! Redirecting...'));
                setStatusLabel('Inside', 'text-success');
                positionDiv.innerHTML = '';
                setTimeout(function () {
                    window.location.href = '/redis-fifo-queue/success';
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
        } catch {
            userState = 'queued';
            setStatus(alertHtml('error', errorIcon, 'Failed to claim slot'));
            setStatusLabel('Error', 'text-error');
            return false;
        } finally {
            isClaiming = false;
        }
    }

    if (eventLog) {
        eventLog.addEventListener('airlock-log-entry', function (e) {
            const text = e.detail?.text || '';

            if (text.includes('entered the airlock')) {
                updateSlots(occupied + 1, userState === 'admitted');
                updateBarrier(occupied >= CAPACITY);
                flashChamber();
            } else if (text.includes('lock released')) {
                updateSlots(occupied - 1, userState === 'admitted');
                updateBarrier(occupied >= CAPACITY);
            }
        });
    }

    if (queueState) {
        queueState.setAttribute('client-id', CLIENT_ID);

        if (typeof queueState.bindClient === 'function') {
            queueState.bindClient(airlockClient);
        }

        queueState.addEventListener('airlock-queue-state', function (e) {
            const detail = e.detail || {};

            if (Number.isInteger(detail.queueSize)) {
                updateQueueCount(detail.queueSize);
            }

            if (userState !== 'queued') {
                return;
            }

            if (!Number.isInteger(detail.position)) {
                return;
            }

            const position = detail.position;
            const queueSize = Number.isInteger(detail.queueSize) ? detail.queueSize : position;
            showPosition(position, queueSize);
        });
    }

    if (joinControl && typeof joinControl.bindClient === 'function') {
        joinControl.bindClient(airlockClient);
    }

    if (joinControl) {
        joinControl.addEventListener('airlock-join-result', async function (event) {
            const data = event.detail || {};

            if (!data.ok) {
                userState = 'idle';
                setStatus(alertHtml('error', errorIcon, data.error || 'Request failed'));
                setStatusLabel('Error', 'text-error');
                return;
            }

            if (data.status === 'admitted') {
                userState = 'admitted';
                latestClaimNonce = null;
                hideQueue();
                updateSlots(Math.max(occupied, 1), true);
                updateBarrier(occupied >= CAPACITY);
                setStatus(alertHtml('success', checkIcon, 'You got in immediately! Redirecting...'));
                setStatusLabel('Inside', 'text-success');
                positionDiv.innerHTML = '';
                setTimeout(function () {
                    window.location.href = '/redis-fifo-queue/success';
                }, REDIRECT_DELAY);
                return;
            }

            if (data.status === 'queued') {
                userState = 'queued';
                const position = Number.isInteger(data.position) ? data.position : 1;
                const queueSize = Number.isInteger(data.position) ? data.position : 1;

                updateSlots(CAPACITY, false);
                updateBarrier(true);
                showPosition(position, queueSize);
                setStatus(alertHtml('warning', spinnerIcon, 'Waiting in FIFO queue...'));

                latestClaimNonce = data.reservationNonce || null;
                if (latestClaimNonce) {
                    const claimed = await claimReservation(latestClaimNonce);
                    if (claimed) {
                        return;
                    }
                }
            }
        });
    }

    airlockClient.addEventListener('turn', async function (event) {
        if (userState !== 'queued') {
            return;
        }

        latestClaimNonce = event.detail?.claimNonce || null;
        await claimReservation(latestClaimNonce);
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', async function () {
            resetBtn.disabled = true;
            resetBtn.classList.add('btn-disabled');

            airlockClient.reset();
            if (joinControl && typeof joinControl.setState === 'function') {
                joinControl.setState('idle');
            }

            try {
                await fetch('/reset', { method: 'GET' });
            } catch {
                // ignore
            }

            setStatus('');
            positionDiv.innerHTML = '';
            userState = 'idle';
            latestClaimNonce = null;
            isClaiming = false;
            occupied = 0;
            updateSlots(0, false);
            updateBarrier(false);
            hideQueue();
            updateQueueCount(0);
            if (eventLog && typeof eventLog.clear === 'function') {
                eventLog.clear();
            }
            setStatusLabel('Idle', '');
            statusLabel.className = 'mt-2 text-2xl font-black text-base-content/30';

            resetBtn.disabled = false;
            resetBtn.classList.remove('btn-disabled');
        });
    }

    updateSlots(0, false);
    updateBarrier(false);
    updateQueueCount(0);
})();
