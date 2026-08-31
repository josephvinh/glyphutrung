# Task 8 Brief: Feature - Enhanced Dashboard

## Task Description
Add an enhanced dashboard for Ban Điều Hành (Board of Directors) with quick stats and recent activities.

## Files to Create/Modify

### Create: `public/assets/js/modules/dashboard.js`
```javascript
window.TNTT = window.TNTT || {};
window.TNTT.dashboard = {
    data: () => ({
        todayAttendance: { present: 0, total: 0 },
        pendingLeaves: 0,
        unreadAnnouncements: 0,
        recentLogs: [],

        get attendanceRate() {
            const total = this.todayAttendance.total || 1;
            return Math.round((this.todayAttendance.present / total) * 100);
        }
    })
};
```

### Modify: `views/layout_hero.php`
Add dashboard cards for BĐH:
```html
<div x-show="isAdmin" class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white rounded-card p-4 shadow-sm">
        <p class="text-micro font-bold text-slate-500">Hôm nay</p>
        <p class="text-2xl font-black text-emerald-600" x-text="todayAttendance.present + '/' + todayAttendance.total"></p>
        <p class="text-micro text-slate-500">Điểm danh</p>
    </div>
    <!-- More cards for pending leaves, announcements, etc -->
</div>
```

## Requirements
1. Dashboard shows quick stats (attendance, pending leaves, announcements)
2. Admin-only visibility (x-show="isAdmin")
3. Data from window.TNTT_BOOT
4. Responsive grid layout
5. Real-time updates when data changes

## Acceptance Criteria
1. Dashboard cards visible for admin users
2. Shows today's attendance count
3. Shows pending leave request count
4. Shows unread announcement count
5. Uses existing Alpine.js patterns from codebase
