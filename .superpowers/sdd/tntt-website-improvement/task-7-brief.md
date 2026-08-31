# Task 7 Brief: UX - Skeleton Loading States

## Task Description
Add skeleton loading states to improve perceived performance while data is loading.

## Files to Create/Modify

### Create: `public/assets/css/skeleton.css`
```css
/* public/assets/css/skeleton.css */
.skeleton {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
    border-radius: 0.5rem;
}

@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-text { height: 1rem; margin-bottom: 0.5rem; }
.skeleton-title { height: 1.5rem; width: 60%; margin-bottom: 1rem; }
.skeleton-avatar { width: 3rem; height: 3rem; border-radius: 50%; }
.skeleton-card { height: 6rem; margin-bottom: 1rem; }
```

### Modify: `views/module_students.php`
Add skeleton loading state:

```html
<template x-if="loading">
    <div>
        <template x-for="i in 5">
            <div class="bg-white rounded-card p-4 mb-3">
                <div class="flex items-center gap-3">
                    <div class="skeleton skeleton-avatar"></div>
                    <div class="flex-1">
                        <div class="skeleton skeleton-title"></div>
                        <div class="skeleton skeleton-text w-40"></div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
```

### Modify: `views/module_attendance.php`
Add skeleton for attendance list.

### Modify: `views/module_stats.php`
Add skeleton for statistics cards.

## Requirements
1. Skeleton CSS with loading animation
2. Skeleton shown while data is loading
3. Replace skeleton with actual content when loaded
4. Skeleton matches the layout of real content
5. Smooth transition from skeleton to content

## Acceptance Criteria
1. Skeleton CSS defined and included
2. Loading state managed in Alpine.js components
3. Skeletons appear before data loads
4. Content replaces skeletons when loaded
5. No layout shift when content loads
