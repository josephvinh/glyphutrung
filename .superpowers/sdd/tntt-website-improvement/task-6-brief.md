# Task 6 Brief: UX - Dark Mode

## Task Description
Add dark mode support to the TNTT website, allowing users to switch between light and dark themes.

## Files to Create/Modify

### Create: `public/assets/css/dark.css`
```css
@media (prefers-color-scheme: dark) {
    :root {
        --bg-primary: #0f172a;
        --bg-secondary: #1e293b;
        --bg-card: #1e293b;
        --text-primary: #f8fafc;
        --text-secondary: #94a3b8;
        --border-color: #334155;
    }
}

.dark {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-card: #1e293b;
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --border-color: #334155;
}
```

### Modify: `public/assets/js/modules/core.js`
Add dark mode toggle logic in the init function:

```javascript
// Dark mode support
this.dark = localStorage.getItem('darkMode') === 'true' 
    || window.matchMedia('(prefers-color-scheme: dark)').matches;

this.$watch('dark', val => {
    document.documentElement.classList.toggle('dark', val);
    localStorage.setItem('darkMode', val);
});
```

### Modify: `public/assets/css/app.css`
Add dark mode CSS overrides using the CSS variables.

## Requirements
- User preference stored in localStorage
- Respects system preference (prefers-color-scheme)
- Toggle available in the UI
- Smooth transition between themes
- All components support both themes via CSS variables

## Acceptance Criteria
1. Dark mode CSS variables defined
2. Toggle switch in the UI
3. Theme preference persisted in localStorage
4. System preference respected when no localStorage value
5. Smooth transition between themes
