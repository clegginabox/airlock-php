const statusDiv = document.getElementById('status');
const button = document.getElementById('go');
const resetButton = document.getElementById('reset');
const positionDiv = document.getElementById('position');

let mercureSource = null;

const closeMercure = () => {
    if (mercureSource) {
        mercureSource.close();
        mercureSource = null;
    }
};

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
        closeMercure();

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
            closeMercure();
            setTimeout(() => {
                window.location.href = '/redis-lottery-queue/success';
            }, 500);
            return;
        }

        statusDiv.textContent = 'Waiting in lottery queue...';
        statusDiv.className = 'status wait';

        if (positionDiv) {
            positionDiv.textContent = `Queue size: ~${data.position} people`;
        }

        if (data.topic && data.hubUrl) {
            closeMercure();
            const hub = new URL(data.hubUrl, window.location.origin);
            hub.searchParams.append('topic', data.topic);
            if (data.token) {
                hub.searchParams.append('authorization', data.token);
            }

            mercureSource = new EventSource(hub.toString());
            mercureSource.onmessage = (event) => {
                try {
                    const payload = JSON.parse(event.data);
                    if (payload.event !== 'your_turn') {
                        return;
                    }
                } catch (_) {
                    // Allow plain text payloads too.
                }

                statusDiv.textContent = 'Your turn! Redirecting...';
                statusDiv.className = 'status ok';
                closeMercure();

                setTimeout(() => {
                    window.location.href = '/redis-lottery-queue/success';
                }, 500);
            };

            mercureSource.onerror = () => {
                statusDiv.textContent = 'Waiting in lottery queue... (reconnecting)';
                statusDiv.className = 'status wait';
            };
        }
    } catch (err) {
        statusDiv.textContent = 'Error: ' + err.message;
        statusDiv.className = 'status error';
        button.disabled = false;
    }
};
