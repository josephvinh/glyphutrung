# Task 8 Plan: Enhanced Dashboard for Ban Điều Hành

## Overview
Add an enhanced dashboard with quick stats visible only to admin users (Ban Điều Hành).

## Implementation Steps

### 1. Create Dashboard Module (`public/assets/js/modules/dashboard.js`)
- Create Alpine component for dashboard data
- Define reactive data: todayAttendance, pendingLeaves, unreadAnnouncements
- Compute attendance rate from existing data
- Follow existing module patterns from core.js and other modules

### 2. Modify layout_hero.php
- Add admin-only dashboard cards section
- Display quick stats: attendance, pending leaves, announcements
- Use existing Tailwind classes (rounded-card, shadow-sm, etc.)
- Use Alpine.js x-show="isAdmin" for visibility control

### 3. Update index.php
- Add 'dashboard' to the module loading list

## Files to Modify
1. `public/assets/js/modules/dashboard.js` (CREATE)
2. `views/layout_hero.php` (MODIFY)
3. `public/index.php` (MODIFY - add dashboard to module list)

## Dependencies
- Uses existing window.TNTT_BOOT structure
- Integrates with existing attendance, leave, announcements data
- Follows Alpine.js patterns from existing modules
