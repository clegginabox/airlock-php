const status = document.getElementById('status');
const button = document.getElementById('go');

button.onclick = async () => {
    status.textContent = 'Processing...';
    status.className = 'status';

    try {
        const res = await fetch('./global-lock/start', { method: 'POST' });
        const data = await res.json();

        if (!res.ok || !data.ok) {
            status.textContent = data.error || 'Failed to start';
            status.classList.add('error');
        } else {
            status.textContent = 'Done!';
            status.classList.remove('error');
            status.classList.add('ok');
        }
    } catch (err) {
        status.textContent = 'Error: ' + err.message;
        status.classList.add('error');
    }
};
