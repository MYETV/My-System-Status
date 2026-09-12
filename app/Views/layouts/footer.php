<!-- path: app/Views/layouts/footer.php -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar-wrapper');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('d-none');
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // Check if user_timezone cookie already exists
    const hasTimezoneCookie = document.cookie.split(';').some(item => item.trim().startsWith('user_timezone='));

    if (!hasTimezoneCookie) {
        try {
            // Read client local browser timezone
            const detectedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (detectedTimezone) {
                // Post detected timezone to backend
                fetch('/timezone/set', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'timezone=' + encodeURIComponent(detectedTimezone)
                }).then(() => {
                    // Timezone cookie is now set silently for all next requests
                });
            }
        } catch (e) {
            console.warn('Unable to detect local timezone:', e);
        }
    }
});
</script>