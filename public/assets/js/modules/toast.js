/* ==========================================================
   TOAST NOTIFICATIONS
   Modern toast notification manager
   ========================================================== */
window.TNTT = window.TNTT || {};

window.TNTT.toast = {
    container: null,

    /**
     * Initialize the toast container
     */
    init() {
        if (this.container) return;
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Toast type: 'success', 'error', 'warning', 'info'
     * @param {number} duration - Auto-dismiss duration in ms (0 to disable)
     * @returns {HTMLElement} The toast element
     */
    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;

        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }, duration);
        }

        return toast;
    },

    /**
     * Show a success toast (green)
     * @param {string} message - The message to display
     * @param {number} duration - Auto-dismiss duration in ms
     * @returns {HTMLElement} The toast element
     */
    success(message, duration) {
        return this.show(message, 'success', duration);
    },

    /**
     * Show an error toast (red)
     * @param {string} message - The message to display
     * @param {number} duration - Auto-dismiss duration in ms
     * @returns {HTMLElement} The toast element
     */
    error(message, duration) {
        return this.show(message, 'error', duration);
    },

    /**
     * Show a warning toast (amber)
     * @param {string} message - The message to display
     * @param {number} duration - Auto-dismiss duration in ms
     * @returns {HTMLElement} The toast element
     */
    warning(message, duration) {
        return this.show(message, 'warning', duration);
    },

    /**
     * Show an info toast (blue)
     * @param {string} message - The message to display
     * @param {number} duration - Auto-dismiss duration in ms
     * @returns {HTMLElement} The toast element
     */
    info(message, duration) {
        return this.show(message, 'info', duration);
    },

    /**
     * Show a confirmation dialog
     * @param {string} message - The confirmation message
     * @returns {Promise<boolean>} Resolves to true if confirmed, false otherwise
     */
    confirm(message) {
        // Use native confirm for simplicity, can be enhanced with custom modal later
        return Promise.resolve(confirm(message));
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.TNTT.toast.init());
} else {
    window.TNTT.toast.init();
}
