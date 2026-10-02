<button id="theme-toggle" type="button"
        class="theme-toggle-btn"
        aria-label="Chuyển chế độ sáng/tối"
        title="Chuyển chế độ sáng/tối">
    <svg class="icon-light" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
    </svg>
    <svg class="icon-dark" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
    </svg>
</button>
<style>
.theme-toggle-btn {
    position: fixed;
    top: 16px;
    right: 16px;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1000;
    transition: all 0.2s ease;
    color: #475569;
}
.theme-toggle-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}
.theme-toggle-btn:active {
    transform: scale(0.95);
}
.theme-toggle-btn .icon-dark { display: none; }
.theme-toggle-btn .icon-light { display: block; }
.dark .theme-toggle-btn {
    background: rgba(30, 41, 59, 0.9);
    border-color: rgba(255, 255, 255, 0.15);
    color: #fbbf24;
}
.dark .theme-toggle-btn .icon-dark { display: block; }
.dark .theme-toggle-btn .icon-light { display: none; }
</style>
<script>
(function() {
    var btn = document.getElementById('theme-toggle');
    if (!btn) return;
    // Apply saved theme on load
    var saved = localStorage.getItem('theme');
    if (saved === 'dark') {
        document.documentElement.classList.add('dark');
    }
    btn.addEventListener('click', function() {
        var isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
})();
</script>