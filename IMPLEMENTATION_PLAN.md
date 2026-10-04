# Remove Dark Mode - Implementation Plan

## Phase 1: Delete Files

### Task 1.1: Delete dark.css
- **File:** `public/assets/css/dark.css`
- **Action:** `git rm public/assets/css/dark.css`
- **Verification:** File không còn tồn tại

### Task 1.2: Delete theme_toggle.php
- **File:** `public/theme_toggle.php`
- **Action:** `git rm public/theme_toggle.php`
- **Verification:** File không còn tồn tại

## Phase 2: Modify asset_manifest.php

### Task 2.1: Remove 'dark' from CSS array
- **File:** `public/assets/asset_manifest.php`
- **Change:** Line 27, `'css' => [..., 'dark', ...]` → remove `'dark'`
- **Before:** `'css' => ['tailwind', 'font', 'app', 'dark', 'skeleton', 'analytics', 'toast', 'brand']`
- **After:** `'css' => ['tailwind', 'font', 'app', 'skeleton', 'analytics', 'toast', 'brand']`

## Phase 3: Modify core.js

### Task 3.1: Remove darkMode property
- **File:** `public/assets/js/modules/core.js`
- **Lines:** 92-98
- **Content to remove:**
```javascript
// Chế độ tối: chỉ bật khi người dùng tự chọn; mặc định SÁNG.
dark: (() => {
    try {
        const saved = localStorage.getItem('darkMode');
        if (saved !== null) return saved === 'true';
    } catch (e) { /* localStorage bị chặn — dùng mặc định */ }
    return false;
})(),
```

### Task 3.2: Remove applyDarkMode method
- **File:** `public/assets/js/modules/core.js`
- **Lines:** 108-112
- **Content to remove:**
```javascript
applyDarkMode() {
    document.documentElement.classList.toggle('dark', this.dark);
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', this.dark ? '#0f172a' : '#c8203a');
},
```

### Task 3.3: Remove toggleDark method
- **File:** `public/assets/js/modules/core.js`
- **Lines:** 114-119
- **Content to remove:**
```javascript
toggleDark() {
    this.dark = !this.dark;
    this.applyDarkMode();
    try { localStorage.setItem('darkMode', this.dark); } catch (e) { /* bỏ qua */ }
    this.$nextTick(() => window.lucide && lucide.createIcons());
},
```

### Task 3.4: Remove initCore dark mode call
- **File:** `public/assets/js/modules/core.js`
- **Lines:** 101-106
- **Change from:**
```javascript
initCore() {
    this.applyDarkMode();
    if (typeof this.initOfflineAttendance === 'function') {
        this.initOfflineAttendance();
    }
},
```
- **To:**
```javascript
initCore() {
    if (typeof this.initOfflineAttendance === 'function') {
        this.initOfflineAttendance();
    }
},
```

## Phase 4: Modify tailwind.config.js

### Task 4.1: Remove darkMode config
- **File:** `tailwind.config.js`
- **Action:** Xoá `darkMode: 'class',` line

## Phase 5: Modify tailwind.css

### Task 5.1: Remove dark mode media query and variants
- **File:** `public/assets/css/tailwind.css`
- **Action:** Xoá `@media (prefers-color-scheme: dark)` block
- **Note:** File này là built output, nên xoá thủ công các dark mode rules

## Phase 6: Modify index.php

### Task 6.1: Remove theme_toggle include
- **File:** `public/index.php`
- **Action:** Xoá `<?php include 'theme_toggle.php'; ?>`

## Phase 7: Check view files for .dark classes

### Task 7.1: Search and remove .dark classes
- **Files to check:**
  - `views/layout_header.php`
  - `views/layout_sidebar.php`
  - `views/layout_hero.php`
  - `views/layout_landing.php`
  - `views/layout_login.php`
  - `public/assets/js/modules/shell.js`
- **Action:** Grep for `.dark` class, remove if found

## Phase 8: Playwright Tests

### Task 8.1: Create test file
- **File:** `tests/e2e/theme-light.spec.js`
- **Test cases:**
  1. `should have light mode by default`
  2. `should display correct colors on landing page`
  3. `should navigate to app shell`
  4. `should display students module`
  5. `should display attendance module`
  6. `should have no theme toggle button`

### Task 8.2: Run tests
- **Command:** `npx playwright test tests/e2e/theme-light.spec.js`

## Phase 9: Manual Verification

### Task 9.1: Visual inspection checklist
- [ ] Landing page: nền trắng
- [ ] Dashboard: cards đúng màu
- [ ] No dark class on html element
- [ ] No theme toggle button visible
- [ ] All modules accessible
