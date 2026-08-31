# Task: Toast Notifications

## Task Description
Replace JavaScript alert() and confirm() dialogs with modern toast notifications.

## Files to Create/Modify

### Create: `public/assets/css/toast.css`
```css
.toast-container {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.toast {
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    background: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    animation: slideIn 0.3s ease;
    max-width: 350px;
}

.toast.success { border-left: 4px solid #10b981; }
.toast.error { border-left: 4px solid #ef4444; }
.toast.warning { border-left: 4px solid #f59e0b; }
.toast.info { border-left: 4px solid #3b82f6; }

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.toast.fade-out {
    animation: slideOut 0.3s ease forwards;
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
```

### Create: `public/assets/js/modules/toast.js`
```javascript
window.TNTT = window.TNTT || {};
window.TNTT.toast = {
    container: null,
    
    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },
    
    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        
        this.container.appendChild(toast);
        
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    },
    
    success(message, duration) { return this.show(message, 'success', duration); },
    error(message, duration) { return this.show(message, 'error', duration); },
    warning(message, duration) { return this.show(message, 'warning', duration); },
    info(message, duration) { return this.show(message, 'info', duration); },
    
    confirm(message) {
        return new Promise((resolve) => {
            // Use native confirm for simplicity, can be enhanced later
            resolve(confirm(message));
        });
    }
};
```

### Modify: `public/assets/js/modules/core.js`
Replace alert() calls with toast:
```javascript
// Instead of: alert('Error message');
// Use: window.TNTT.toast.error('Error message');
```

## Requirements
1. Toast notifications appear in bottom-right corner
2. Auto-dismiss after 4 seconds
3. Support success/error/warning/info types
4. Smooth animation on show/hide
5. Stack multiple toasts

## Acceptance Criteria
1. Replace all alert() calls with toast notifications
2. Toast auto-dismisses
3. Multiple toasts stack properly
4. Works with existing Tailwind CSS
