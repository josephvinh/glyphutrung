# Implementation Plan: Attendance Analytics Dashboard

## Files to Create

### 1. `public/assets/css/analytics.css`
CSS for chart containers and stats cards.

### 2. `views/module_analytics.php`
Analytics view with Alpine.js directives displaying:
- Overview stats cards (total sessions, attendance rates)
- Weekly attendance chart using CSS bars
- Class-by-class breakdown with progress bars
- Low attendance students list (<70%)

### 3. `public/assets/js/modules/analytics.js`
Analytics data logic:
- `totalSessions` - total program sessions
- `attendanceRate` - overall attendance percentage
- `excusedRate` - excused absence rate
- `unexcusedRate` - unexcused absence rate
- `weeklyData` - attendance data grouped by week
- `classData` - attendance rate by class
- `lowAttendance` - students with <70% attendance

## Files to Modify

### 4. `public/assets/js/modules/core.js`
- Add 'analytics' to `moduleDefs` array
- Add 'analytics' to `moduleEnabled`
- Add 'analytics' to `permissions`
- Add 'analytics' to `openModule()` handler

### 5. `public/index.php`
- Include `module_analytics.php` after other modules
- Add 'analytics' to the JS module list array
- Include `analytics.css` stylesheet

## Implementation Details

### Stats Calculation
- Use existing `attendances`, `leaveRequests`, and `programs` data
- Calculate rates from attendance records
- Filter students by <70% for low attendance list

### Chart Implementation
- Weekly chart: CSS bar chart with Alpine.js dynamic heights
- Class chart: horizontal progress bars
- No external chart libraries

## Dependencies
- Uses existing Tailwind CSS classes
- Uses Alpine.js for reactivity
- Uses Lucide icons for visual elements
