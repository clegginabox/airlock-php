const status = document.getElementById('status');
const button = document.getElementById('go');

const EXAMPLE = '01-lock';

async function pollStatus(clientId) {
    const url = `../status.php?example=${EXAMPLE}&clientId=${encodeURIComponent(clientId)}`;

    while (true) {
        const res = await fetch(url);
        const data = await res.json();

        status.textContent = data.message || data.state;
        status.className = 'status';

        if (data.state === 'running') {
            status.classList.add('wait');
        } else if (data.state === 'done') {
            status.classList.add('ok');
            return;
        } else if (data.state === 'blocked') {
            status.classList.add('error');
            return;
        }

        await new Promise(r => setTimeout(r, 5000));
    }
}

button.onclick = async () => {
    status.textContent = 'Submitting...';
    status.className = 'status';

    try {
        const res = await fetch('./start.php', { method: 'POST' });
        const data = await res.json();

        if (!res.ok || !data.ok) {
            status.textContent = data.error || 'Failed to start';
            status.classList.add('error');
            return;
        }

        await pollStatus(data.clientId);
    } catch (err) {
        status.textContent = 'Error: ' + err.message;
        status.classList.add('error');
    }
};
