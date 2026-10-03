# Theme Colors Guide

## Màu sắc cho Light Mode và Dark Mode

### Text Colors

| Tailwind Class | Light Mode | Dark Mode | Hex Light | Hex Dark |
|----------------|-----------|-----------|-----------|----------|
| `text-white` | #ffffff | #ffffff | #ffffff | #ffffff |
| `text-slate-900` | - | #ffffff | #0f172a | #ffffff |
| `text-slate-800` | - | #f8fafc | #1e293b | #f8fafc |
| `text-slate-700` | - | #cbd5e1 | #334155 | #cbd5e1 |
| `text-slate-600` | - | #94a3b8 | #475569 | #94a3b8 |
| `text-slate-500` | - | #94a3b8 | #64748b | #94a3b8 |
| `text-slate-400` | - | #64748b | #94a3b8 | #64748b |

### Icon Colors

| Tailwind Class | Light Mode | Dark Mode | Hex Light | Hex Dark |
|----------------|-----------|-----------|-----------|----------|
| `text-blue-500` | #3b82f6 | #60a5fa | #3b82f6 | #60a5fa |
| `text-emerald-500` | #10b981 | #34d399 | #10b981 | #34d399 |
| `text-amber-500` | #f59e0b | #fbbf24 | #f59e0b | #fbbf24 |
| `text-rose-500` | #ef4444 | #fb7185 | #ef4444 | #fb7185 |
| `text-slate-500` | #64748b | #94a3b8 | #64748b | #94a3b8 |
| `text-slate-600` | #475569 | #94a3b8 | #475569 | #94a3b8 |

### Badge/Status Colors

| Badge Type | Light Mode | Dark Mode |
|------------|-----------|-----------|
| Active/Success | bg: #d1fae5, text: #047857 | bg: #064e3b, text: #6ee7b7 |
| Inactive/Error | bg: #ffe4e6, text: #be123c | bg: #9f1239, text: #fda4af |
| Info | bg: #dbeafe, text: #1d4ed8 | bg: #1e3a8a, text: #93c5fd |
| Warning | bg: #fef3c7, text: #d97706 | bg: #78350f, text: #fcd34d |

### Button Colors

| Button Type | Light Mode | Dark Mode |
|------------|-----------|-----------|
| Primary | bg: #2563eb, text: #fff | bg: #3b82f6, text: #fff |
| White | bg: #fff, border: #e5e7eb, text: #1e293b | bg: #475569, border: #64748b, text: #f8fafc |
| Slate-800 | bg: #1e293b, text: #fff | bg: #1e293b, text: #f8fafc |

### Background Colors

| Tailwind Class | Light Mode | Dark Mode |
|----------------|-----------|-----------|
| `bg-slate-50` | #f8fafc | rgba(51, 65, 85, 0.5) |
| `bg-slate-100` | #f1f5f9 | #1e293b |
| `bg-slate-200` | #e2e8f0 | rgba(71, 85, 105, 0.5) |
| `bg-white` | #ffffff | #334155 |
| `bg-slate-800` | #1e293b | #1e293b |

---

## Cách sử dụng trong code

### 1. Với Tailwind CSS (Khuyến nghị)

```html
<!-- Text với dark mode -->
<p class="text-slate-800 dark:text-white">Tên user</p>

<!-- Icon với dark mode -->
<i class="text-slate-500 dark:text-slate-300">...</i>

<!-- Badge với dark mode -->
<span class="bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">
  Active
</span>

<!-- Button với dark mode -->
<button class="bg-blue-600 text-white dark:bg-blue-500">
  Primary
</button>
```

### 2. Với Alpine.js (Dynamic Styles)

```html
<div x-data="{ dark: false }">
  <p :style="dark ? 'color:#ffffff;' : 'color:#0f172a;'">
    Tên user
  </p>
  
  <i :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">
    Icon
  </i>
</div>
```

### 3. Với CSS Custom Properties

```css
:root {
  --text-primary: #0f172a;
  --text-secondary: #475569;
}

.dark {
  --text-primary: #ffffff;
  --text-secondary: #94a3b8;
}
```

---

## Checklist cho mỗi Component mới

- [ ] Text chính: có `dark:text-white` hoặc `dark:text-slate-100`
- [ ] Text phụ: có `dark:text-slate-400`
- [ ] Icons: có `dark:text-slate-300` hoặc màu sáng hơn
- [ ] Background: có `dark:bg-slate-700` hoặc tương ứng
- [ ] Badges: có `dark:` variants cho cả bg và text
- [ ] Buttons: có `dark:` variants

---

## Cập nhật

- **2024-10-03**: Tạo tài liệu màu sắc theme
