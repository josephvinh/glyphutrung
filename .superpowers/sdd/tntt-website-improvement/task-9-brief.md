# Task 9 Brief: Feature - Calendar View

## Task Description
Add a calendar view module to display the weekly activity schedule.

## Files to Create/Modify

### Create: `views/module_calendar.php`
```php
<div x-show="currentModule === 'calendar'" class="module-panel">
    <div class="flex items-center justify-between mb-4">
        <button @click="prevMonth">&lt;</button>
        <h2 x-text="calendarMonth"></h2>
        <button @click="nextMonth">&gt;</button>
    </div>

    <div class="grid grid-cols-7 gap-1">
        <template x-for="day in weekDays"><span x-text="day"></span></template>
        <template x-for="date in calendarDays" :key="date.key">
            <div class="min-h-[80px] p-2 bg-white rounded">
                <span x-text="date.day"></span>
                <template x-for="event in date.events.slice(0,2)">
                    <div class="text-micro truncate bg-blue-100 text-blue-700 px-1 rounded"
                         x-text="event.name"></div>
                </template>
            </div>
        </template>
    </div>
</div>
```

### Create: `public/assets/js/modules/calendar.js`
```javascript
window.TNTT.calendar = {
    data: () => ({
        currentDate: new Date(),
        programs: [],

        get calendarDays() {
            // Generate calendar days with events
        },

        get weekDays() {
            return ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        }
    })
};
```

### Modify: `public/index.php`
Add calendar module to the navigation and include calendar.js.

## Requirements
1. Calendar displays monthly view with day grid
2. Events shown on calendar days
3. Navigation to switch months
4. Click day to see events
5. Uses existing Alpine.js patterns

## Acceptance Criteria
1. Calendar module accessible from navigation
2. Shows programs/events on correct days
3. Month navigation works
4. Responsive layout for mobile
5. Uses existing Tailwind CSS classes
