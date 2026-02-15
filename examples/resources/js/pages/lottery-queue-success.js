(function () {
    // --- Confetti ---
    var container = document.getElementById('confetti');
    var colors = [
        'oklch(0.75 0.18 150)', // green
        'oklch(0.7 0.15 250)',  // blue
        'oklch(0.8 0.15 85)',   // yellow
        'oklch(0.7 0.2 330)',   // pink
        'oklch(0.75 0.15 30)',  // orange
        'oklch(0.65 0.2 290)', // purple
    ];

    for (var i = 0; i < 40; i++) {
        var piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + '%';
        piece.style.background = colors[Math.floor(Math.random() * colors.length)];
        piece.style.width = (Math.random() * 8 + 6) + 'px';
        piece.style.height = (Math.random() * 8 + 6) + 'px';
        piece.style.animationDuration = (Math.random() * 2 + 2.5) + 's';
        piece.style.animationDelay = (Math.random() * 1.5) + 's';
        piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
        container.appendChild(piece);
    }

    // --- Countdown + auto-release ---
    var remaining = 5;
    var countdownText = document.getElementById('countdown-text');

    var timer = setInterval(function () {
        remaining--;
        countdownText.textContent = remaining;
        if (remaining <= 0) {
            clearInterval(timer);
            countdownText.textContent = '0';
            // Release slot
            var clientId = sessionStorage.getItem('clientId') || '';
                fetch('/redis-lottery-queue/release', {
                    method: 'POST',
                    headers: { 'X-Client-Id': clientId },
                })
                .catch(function () {});
        }
    }, 1000);
})();
