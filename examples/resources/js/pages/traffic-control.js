import { Centrifuge } from 'centrifuge';
import { spinnerIcon, checkIcon, errorIcon, alertHtml } from '../lib/alerts.js';

(function () {
    var PROVIDERS = {
        alpha: { rateLimit: 50, concurrency: null },
        beta:  { rateLimit: 50, concurrency: 5 },
        gamma: { rateLimit: 30, concurrency: null },
    };
    var NAMES = ['alpha', 'beta', 'gamma'];
    var BETA_CONCURRENCY = PROVIDERS.beta.concurrency || 0;

    var burstBtn = document.getElementById('simulate');
    var resetBtn = document.getElementById('reset');
    var statusDiv = document.getElementById('status');
    var logDiv = document.getElementById('log');
    var logEmpty = document.getElementById('log-empty');
    var statAdmitted = document.getElementById('stat-admitted');
    var statRejected = document.getElementById('stat-rejected');
    var concLabel = document.getElementById('conc-label');

    var lanes = {}, fills = {}, countEls = {}, killBtns = {};
    NAMES.forEach(function (n) {
        lanes[n] = document.getElementById('lane-' + n);
        fills[n] = document.getElementById('fill-' + n);
        countEls[n] = document.getElementById('count-' + n);
        killBtns[n] = lanes[n].querySelector('.kill-btn');
    });

    var concSlots = Array.from({ length: BETA_CONCURRENCY }, function (_, i) {
        return document.getElementById('conc-' + i);
    }).filter(Boolean);

    var batchCount = 25;
    var downProviders = {};
    var rateUsed = { alpha: 0, beta: 0, gamma: 0 };
    var betaConcUsed = 0;
    var totalAdmitted = 0;
    var totalRejected = 0;
    var activeBatchId = null;
    var batchDone = false;

    // --- Centrifugo WebSocket ---
    var wsProto = location.protocol === 'https:' ? 'wss:' : 'ws:';
    var centrifuge = new Centrifuge(wsProto + '//' + location.host + '/connection/websocket');

    var sub = centrifuge.newSubscription('traffic-control');

    sub.on('publication', function (ctx) {
        var data = ctx.data;

        // Only process events for the active batch
        if (!activeBatchId || !data.batchId || data.batchId !== activeBatchId) {
            return;
        }

        switch (data.event) {
            case 'admitted':
                handleAdmitted(data);
                break;
            case 'completed':
                handleCompleted(data);
                break;
            case 'rejected':
                handleRejected(data);
                break;
            case 'batch_complete':
                handleBatchComplete(data);
                break;
        }
    });

    sub.subscribe();
    centrifuge.connect();

    // --- Event handlers ---
    function handleAdmitted(data) {
        var name = data.provider;

        if (name === 'beta') {
            betaConcUsed = Math.min(betaConcUsed + 1, BETA_CONCURRENCY);
            updateConcurrency();
        }
    }

    function handleCompleted(data) {
        var name = data.provider;
        var label = name.charAt(0).toUpperCase() + name.slice(1);

        totalAdmitted++;
        rateUsed[name]++;

        if (name === 'beta') {
            betaConcUsed = Math.max(betaConcUsed - 1, 0);
            updateConcurrency();
        }

        updateLane(name);
        flashLane(name, 'admit');
        bumpStat(statAdmitted, totalAdmitted);
        statAdmitted.className = 'mt-2 text-2xl font-black text-success';
        bumpStat(statRejected, totalRejected);
        addLog('success', data.request.substring(0, 8) + ' \u2192 <strong>' + label + '</strong> completed');
    }

    function handleRejected(data) {
        totalRejected++;
        bumpStat(statRejected, totalRejected);
        statRejected.className = 'mt-2 text-2xl font-black text-error';
        addLog('error', data.request.substring(0, 8) + ' rejected');
    }

    function handleBatchComplete(data) {
        batchDone = true;
        activeBatchId = null;
        burstBtn.disabled = false;
        burstBtn.classList.remove('btn-disabled');

        if (totalRejected === 0) {
            statusDiv.innerHTML = alertHtml('success', checkIcon, 'All ' + totalAdmitted + ' requests admitted');
        } else if (totalAdmitted === 0) {
            statusDiv.innerHTML = alertHtml('error', errorIcon, 'All ' + totalRejected + ' requests rejected');
        } else {
            statusDiv.innerHTML = alertHtml('warning', checkIcon, totalAdmitted + ' admitted, ' + totalRejected + ' rejected');
        }
    }

    // --- Batch selector ---
    document.querySelectorAll('.batch-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.batch-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            batchCount = parseInt(btn.dataset.count, 10);
            burstBtn.textContent = 'Burst ' + batchCount + ' Request' + (batchCount === 1 ? '' : 's');
        });
    });

    // --- Log ---
    function addLog(type, text) {
        logEmpty.classList.add('hidden');
        var colors = {
            success: 'bg-success/10 text-success',
            error: 'bg-error/10 text-error',
            warning: 'bg-warning/10 text-warning',
            info: 'bg-info/10 text-info',
        };
        var entry = document.createElement('div');
        entry.className = 'log-entry ' + (colors[type] || '');
        entry.innerHTML = text;
        logDiv.prepend(entry);
        while (logDiv.children.length > 200) logDiv.removeChild(logDiv.lastChild);
    }

    // --- Visual updates ---
    function updateLane(name) {
        var used = rateUsed[name];
        var limit = PROVIDERS[name].rateLimit;
        var pct = Math.min(100, (used / limit) * 100);
        var isFull = used >= limit;
        var isDown = !!downProviders[name];

        fills[name].style.width = pct + '%';
        if (isFull && !isDown) fills[name].classList.add('full');
        else fills[name].classList.remove('full');

        countEls[name].textContent = (isDown ? '\u2014' : used) + '/' + limit;
        if (isFull) countEls[name].className = 'w-14 text-right text-sm font-mono font-bold text-error';
        else if (used > 0) countEls[name].className = 'w-14 text-right text-sm font-mono font-bold text-base-content';
        else countEls[name].className = 'w-14 text-right text-sm font-mono font-bold text-base-content/40';

        lanes[name].classList.toggle('down', isDown);

        var btn = killBtns[name];
        if (isDown) {
            btn.textContent = 'Revive';
            btn.classList.add('revive');
        } else {
            btn.textContent = 'Kill';
            btn.classList.remove('revive');
        }
    }

    function updateConcurrency() {
        concSlots.forEach(function (slot, i) {
            if (i < betaConcUsed) slot.classList.add('filled');
            else slot.classList.remove('filled');
        });
        concLabel.textContent = betaConcUsed + '/' + BETA_CONCURRENCY;
        if (betaConcUsed >= BETA_CONCURRENCY) concLabel.className = 'text-[0.6rem] font-mono font-bold text-error';
        else if (betaConcUsed > 0) concLabel.className = 'text-[0.6rem] font-mono font-bold text-base-content';
        else concLabel.className = 'text-[0.6rem] font-mono font-bold text-base-content/30';
    }

    function bumpStat(el, value) {
        el.textContent = value;
        el.classList.remove('count-bump');
        void el.offsetWidth;
        el.classList.add('count-bump');
    }

    function flashLane(name, type) {
        var cls = type === 'admit' ? 'flash-admit' : 'flash-reject';
        lanes[name].classList.remove(cls);
        void lanes[name].offsetWidth;
        lanes[name].classList.add(cls);
        setTimeout(function () { lanes[name].classList.remove(cls); }, 1000);
    }

    function createBatchId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'batch-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    // --- Kill switch ---
    NAMES.forEach(function (name) {
        killBtns[name].addEventListener('click', function () {
            fetch('/traffic-control/toggle?provider=' + name, { method: 'POST' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.ok) {
                        if (data.down) {
                            downProviders[name] = true;
                            addLog('warning', '<strong>' + name + '</strong> taken offline');
                        } else {
                            delete downProviders[name];
                            addLog('info', '<strong>' + name + '</strong> back online');
                        }
                        updateLane(name);
                    }
                })
                .catch(function () { addLog('error', 'Failed to toggle ' + name); });
        });
    });

    // --- Burst ---
    burstBtn.addEventListener('click', function () {
        var requestedBatchId = createBatchId();

        burstBtn.disabled = true;
        burstBtn.classList.add('btn-disabled');
        batchDone = false;
        activeBatchId = requestedBatchId;
        statusDiv.innerHTML = alertHtml('info', spinnerIcon, 'Sending ' + batchCount + ' concurrent requests\u2026');

        fetch('/traffic-control/burst?count=' + batchCount + '&batchId=' + encodeURIComponent(requestedBatchId), { method: 'POST' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    statusDiv.innerHTML = alertHtml('error', errorIcon, 'Unexpected response');
                    burstBtn.disabled = false;
                    burstBtn.classList.remove('btn-disabled');
                    activeBatchId = null;
                    return;
                }

                if (data.batchId !== requestedBatchId) {
                    activeBatchId = data.batchId;
                }

                addLog('info', 'Burst of <strong>' + data.count + '</strong> fired');

                // Only show "Processing" if batch_complete hasn't already arrived
                if (!batchDone) {
                    statusDiv.innerHTML = alertHtml('info', spinnerIcon,
                        'Processing ' + data.count + ' requests\u2026 events streaming live');
                } else {
                    activeBatchId = null;
                }
            })
            .catch(function (err) {
                statusDiv.innerHTML = alertHtml('error', errorIcon, 'Network error: ' + err.message);
                burstBtn.disabled = false;
                burstBtn.classList.remove('btn-disabled');
                activeBatchId = null;
            });
    });

    // --- Reset ---
    resetBtn.addEventListener('click', function () {
        activeBatchId = null;
        batchDone = false;
        downProviders = {};
        rateUsed = { alpha: 0, beta: 0, gamma: 0 };
        betaConcUsed = 0;
        totalAdmitted = 0;
        totalRejected = 0;

        statusDiv.innerHTML = '';
        logDiv.innerHTML = '';
        logEmpty.classList.remove('hidden');

        statAdmitted.textContent = '0';
        statAdmitted.className = 'mt-2 text-2xl font-black text-success/50';
        statRejected.textContent = '0';
        statRejected.className = 'mt-2 text-2xl font-black text-error/50';

        NAMES.forEach(function (n) {
            updateLane(n);
        });
        concSlots.forEach(function (s) { s.classList.remove('filled'); });
        concLabel.textContent = '0/' + BETA_CONCURRENCY;
        concLabel.className = 'text-[0.6rem] font-mono font-bold text-base-content/30';

        fetch('/traffic-control/reset', { method: 'POST' }).catch(function () {});

        burstBtn.disabled = false;
        burstBtn.classList.remove('btn-disabled');
    });

    // --- Init ---
    fetch('/traffic-control/status')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.providers) {
                data.providers.forEach(function (p) {
                    if (p.down) downProviders[p.name] = true;
                    updateLane(p.name);
                });
            }
        })
        .catch(function () {});
})();
