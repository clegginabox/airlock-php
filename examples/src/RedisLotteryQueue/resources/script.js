const statusDiv = document.getElementById('status');
const button = document.getElementById('go');
const resetButton = document.getElementById('reset');
const positionDiv = document.getElementById('position');

let polling = false;

resetButton.onclick = async () => {
    resetButton.disabled = true;
    statusDiv.textContent = 'Resetting...';
    statusDiv.className = 'status';

    try {
        const res = await fetch(`/reset`, { method: 'POST' });
        const data = await res.json();

        statusDiv.textContent = data.message || 'Reset complete';
        statusDiv.className = 'status ok';
        button.disabled = false;

        setTimeout(() => {
            statusDiv.textContent = '';
            statusDiv.className = 'status';
            resetButton.disabled = false;
            if (positionDiv) positionDiv.textContent = '';
        }, 2000);
    } catch (err) {
        statusDiv.textContent = 'Error: ' + err.message;
        statusDiv.className = 'status error';
        resetButton.disabled = false;
    }
};

async function checkQueue() {
    try {
        const res = await fetch('/redis-lottery-queue/check', { method: 'POST' });
        const data = await res.json();

        if (data.status === 'admitted') {
            statusDiv.textContent = 'You got in! Redirecting...';
            statusDiv.className = 'status ok';
            polling = false;
            setTimeout(() => {
                window.location.href = '/redis-lottery-queue/success';
            }, 500);
            return;
        }

        // Still queued
        statusDiv.textContent = 'Waiting in lottery queue...';
        statusDiv.className = 'status wait';
        if (positionDiv) {
            positionDiv.textContent = `Queue size: ~${data.position} people`;
        }

        if (polling) {
            setTimeout(checkQueue, 2000);
        }
    } catch (err) {
        statusDiv.textContent = 'Error: ' + err.message;
        statusDiv.className = 'status error';
        polling = false;
    }
}

button.onclick = async () => {
    button.disabled = true;
    statusDiv.textContent = 'Joining queue...';
    statusDiv.className = 'status';

    try {
        const res = await fetch('/redis-lottery-queue/start', { method: 'POST' });
        const data = await res.json();

        if (!res.ok || !data.ok) {
            statusDiv.textContent = data.error || 'Failed to join';
            statusDiv.className = 'status error';
            button.disabled = false;
            return;
        }

        if (data.status === 'admitted') {
            statusDiv.textContent = 'You got in immediately! Redirecting...';
            statusDiv.className = 'status ok';
            setTimeout(() => {
                window.location.href = '/redis-lottery-queue/success';
            }, 500);
            return;
        }

        // Queued - start polling
        statusDiv.textContent = 'Waiting in lottery queue...';
        statusDiv.className = 'status wait';
        if (positionDiv) {
            positionDiv.textContent = `Queue size: ~${data.position} people`;
        }

        polling = true;
        setTimeout(checkQueue, 2000);
    } catch (err) {
        statusDiv.textContent = 'Error: ' + err.message;
        statusDiv.className = 'status error';
        button.disabled = false;
    }
};
