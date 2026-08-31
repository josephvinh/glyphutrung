# Task 6 Report: UX - Dark Mode

## Summary
Implemented dark mode support for the TNTT website with toggle switch, localStorage persistence, and system preference detection.

## Files Created

### `public/assets/css/dark.css`
- CSS variables for dark mode colors (slate-900 palette for better eye comfort)
- System preference fallback via `@media (prefers-color-scheme: dark)`
- Dark class overrides for all major UI components:
  - App shell, content areas, navigation
  - Cards, inputs, text colors
  - Borders, shadows, scrollbars
  - Buttons and status badges

## Files Modified

### `public/assets/js/modules/core.js`
Added dark mode state and toggle logic:
- `dark` reactive property initialized from localStorage or system preference
- `toggleDark()` method to switch themes
- `applyDarkMode()` method to update the DOM
- `$watch` handler to persist preference to localStorage

### `public/assets/css/app.css`
Added dark mode overrides:
- Dark background gradient for desktop
- Smooth transition (0.2s) for background-color changes

### `public/index.php`
Added dark.css stylesheet link before closing `</head>` tag.

### `views/layout_header.php`
Added toggle button next to logout:
- Sun icon (shown in dark mode to switch to light)
- Moon icon (shown in light mode to switch to dark)
- Accessible via `aria-label`

## Requirements Checklist

| Requirement | Status |
|-------------|--------|
| Dark mode CSS variables defined | Done |
| Toggle switch in the UI | Done |
| Theme preference persisted in localStorage | Done |
| System preference respected when no localStorage value | Done |
| Smooth transition between themes | Done |

## Technical Details

- **Dark palette**: Uses slate-900 (`#0f172a`) and slate-800 (`#1e293b`) for backgrounds
- **Text**: White (`#f8fafc`) for primary, slate-400 (`#94a3b8`) for secondary
- **Borders**: Slate-700 (`#334155`)
- **localStorage key**: `darkMode` (boolean string)
- **Fallback logic**: If localStorage is null, falls back to `prefers-color-scheme: dark`

## Notes
- The toggle button uses Lucide icons (`sun` and `moon`) which are already bundled
- Transitions are excluded when `prefers-reduced-motion: reduce` is set
- The implementation respects the no-CDN constraint by using self-hosted assets
