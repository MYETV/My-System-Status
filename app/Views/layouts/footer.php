<!-- path: app/Views/layouts/footer.php -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar-wrapper');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (!sidebar || !toggleBtn) return;

    // 1. Restore user preference from localStorage or auto-collapse on small screens
    const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true' || window.innerWidth < 992;
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
    }

    // 2. Handle Burger Click
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        sidebar.classList.toggle('collapsed');
        // Persist preference
        localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
    });

    // 3. Responsive auto-collapse when resizing window
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth < 992) {
                sidebar.classList.add('collapsed');
            } else if (localStorage.getItem('sidebar_collapsed') !== 'true') {
                sidebar.classList.remove('collapsed');
            }
        }, 100);
    });
});
</script>