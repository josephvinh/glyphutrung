# Plan: Toast Notifications Implementation

## Overview
Replace JavaScript `alert()` dialogs with modern toast notifications in the TNTT website.

## Files to Create

### 1. `public/assets/css/toast.css`
- Toast container with fixed positioning (bottom-right)
- Individual toast styling with color-coded borders (success/error/warning/info)
- Slide-in and slide-out animations
- Dark mode support

### 2. `public/assets/js/modules/toast.js`
- Toast manager class under `window.TNTT.toast`
- Methods: `init()`, `show(message, type, duration)`, `success()`, `error()`, `warning()`, `info()`
- `confirm()` method using native confirm (can be enhanced later)
- Auto-dismiss with fade-out animation

## Files to Modify

### 3. `public/index.php`
- Add `<link>` tag for toast.css after existing stylesheets
- Add `<script>` tag for toast.js before app.js

### 4. `public/assets/js/modules/core.js`
Replace all `alert()` calls with appropriate toast types:
- Error alerts: `window.TNTT.toast.error('...')`
- Success/info alerts: `window.TNTT.toast.success('...')` or `window.TNTT.toast.info('...')`

## Alert() Calls Found (47 occurrences across multiple files)

### core.js (19 alerts):
- Line 105: Error message on save failure -> `toast.error()`
- Line 143: Import results -> `toast.success()`
- Line 152: Load data error -> `toast.error()`
- Line 168: Server connection error -> `toast.error()`
- Lines 217-220: Password validation -> `toast.warning()`
- Line 225: Password change error -> `toast.error()`
- Line 229: Password changed success -> `toast.success()`
- Line 247: Name validation -> `toast.warning()`
- Line 531: Maintenance message -> `toast.warning()`
- Line 592: Year locked -> `toast.warning()`
- Lines 606, 610: Form validation -> `toast.warning()`
- Lines 618, 642, 653: API errors -> `toast.error()`
- Line 625: Terms adjusted info -> `toast.info()`
- Line 696: Permission warning -> `toast.warning()`

### Other files to update (separate PR or scope):
- `announcements.js` (3 alerts)
- `leave.js` (4 alerts)
- `students.js` (9 alerts)
- `stats.js`, `qrcard.js`, `shell.js`, `push.js`, `scores.js`, `promotion.js`, `reports.js`, `programs.js`, `qrscan.js`, `org.js` (remaining alerts)

## Implementation Order
1. Create `toast.css`
2. Create `toast.js`
3. Modify `index.php` to include new files
4. Modify `core.js` to replace alert() calls
5. Update other JS files with alert() calls (scope for future enhancement)

## Notes
- Keep `confirm()` using native browser confirm() for now
- Toast duration: 4000ms default
- Stack multiple toasts vertically
- Dark mode compatible styling
