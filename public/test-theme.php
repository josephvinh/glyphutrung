<?php
/**
 * Test Theme - Kiểm tra visibility ở cả 2 chế độ
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Theme Visibility</title>
    <link rel="stylesheet" href="assets/css/bundle.php">
    <script defer src="assets/js/vendor/alpine.js"></script>
    <script defer src="assets/js/vendor/lucide-icons.js"></script>
    <style>
        body { font-family: system-ui, sans-serif; padding: 2rem; background: #f8fafc; }
        body.dark { background: #0f172a; }
        .section { margin: 2rem 0; padding: 1.5rem; background: white; border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        body.dark .section { background: #1e293b; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; margin-top: 1rem; }
        .item { padding: 0.75rem; background: #f8fafc; border-radius: 0.5rem; text-align: center; border: 1px solid #e5e7eb; }
        body.dark .item { background: #334155; border-color: #475569; }
        h2 { margin-bottom: 0.75rem; color: #0f172a; font-size: 1rem; }
        body.dark h2 { color: #f8fafc; }
        .toggle { position: fixed; top: 1rem; right: 1rem; padding: 0.5rem 1rem; border-radius: 9999px; font-weight: bold; font-size: 0.875rem; border: none; cursor: pointer; z-index: 9999; }
        .toggle-light { background: #e2e8f0; color: #1e293b; }
        .toggle-dark { background: #334155; color: #f8fafc; }
    </style>
</head>
<body x-data="{ dark: false }">
    <button @click="dark = !dark; document.body.classList.toggle('dark', dark)"
            :class="dark ? 'toggle toggle-dark' : 'toggle toggle-light'">
        <span x-text="dark ? '🌙 Dark' : '☀️ Light'"></span>
    </button>

    <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:1.5rem;" :class="dark ? 'text-white' : 'text-slate-900'">
        Test Theme Visibility
    </h1>

    <!-- 1. Header -->
    <div class="section">
        <h2>1. HEADER - Tên User</h2>
        <div class="item">
            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Chào ngày mới,</p>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Maria Nguyễn Thị A</h1>
            <div class="inline-flex items-center mt-2 px-3 py-1 rounded-full" style="background:rgba(203,213,225,0.5);">
                <i data-lucide="shield-check" class="w-3 h-3 text-emerald-600 dark:text-emerald-400 mr-1"></i>
                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">GLV • Khối 1</span>
            </div>
        </div>
    </div>

    <!-- 2. Login Icons -->
    <div class="section">
        <h2>2. LOGIN - Icons</h2>
        <div class="grid">
            <div class="item">
                <i data-lucide="phone" class="w-5 h-5 text-slate-500 dark:text-slate-300 mx-auto"></i>
                <p class="text-xs mt-1 text-slate-600 dark:text-slate-400">Phone icon</p>
            </div>
            <div class="item">
                <i data-lucide="key-round" class="w-5 h-5 text-slate-500 dark:text-slate-300 mx-auto"></i>
                <p class="text-xs mt-1 text-slate-600 dark:text-slate-400">Key icon</p>
            </div>
            <div class="item">
                <i data-lucide="eye" class="w-5 h-5 text-slate-500 dark:text-slate-300 mx-auto"></i>
                <p class="text-xs mt-1 text-slate-600 dark:text-slate-400">Eye icon</p>
            </div>
        </div>
    </div>

    <!-- 3. Student List -->
    <div class="section">
        <h2>3. STUDENT LIST - Tên các em</h2>
        <div class="item" style="text-align:left;">
            <p class="font-bold text-blue-600 dark:text-blue-400">GLV001 <span class="text-slate-400 dark:text-slate-500">•</span> <span class="font-medium text-slate-600 dark:text-slate-400">Khai Tâm 1A</span></p>
            <p class="font-semibold text-slate-800 dark:text-white mt-1">Lê Thị B <span class="font-normal text-slate-500 dark:text-slate-400">Maria</span></p>
            <p class="mt-1">
                <span class="px-2 py-0.5 rounded text-xs font-bold uppercase bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">đang sinh hoạt</span>
            </p>
        </div>
    </div>

    <!-- 4. Text Colors -->
    <div class="section">
        <h2>4. TEXT COLORS</h2>
        <div class="grid">
            <div class="item">
                <p class="font-semibold text-slate-900 dark:text-white">slate-900</p>
            </div>
            <div class="item">
                <p class="font-semibold text-slate-800 dark:text-white">slate-800</p>
            </div>
            <div class="item">
                <p class="font-semibold text-slate-700 dark:text-slate-300">slate-700</p>
            </div>
            <div class="item">
                <p class="font-semibold text-slate-600 dark:text-slate-400">slate-600</p>
            </div>
            <div class="item">
                <p class="font-semibold text-slate-500 dark:text-slate-400">slate-500</p>
            </div>
            <div class="item">
                <p class="font-semibold text-slate-400 dark:text-slate-500">slate-400</p>
            </div>
        </div>
    </div>

    <!-- 5. Icon Colors -->
    <div class="section">
        <h2>5. ICON COLORS</h2>
        <div class="grid">
            <div class="item">
                <i data-lucide="star" class="w-6 h-6 text-blue-500 dark:text-blue-400 mx-auto"></i>
                <p class="text-xs mt-1">blue-500</p>
            </div>
            <div class="item">
                <i data-lucide="check" class="w-6 h-6 text-emerald-500 dark:text-emerald-400 mx-auto"></i>
                <p class="text-xs mt-1">emerald-500</p>
            </div>
            <div class="item">
                <i data-lucide="alert" class="w-6 h-6 text-amber-500 dark:text-amber-400 mx-auto"></i>
                <p class="text-xs mt-1">amber-500</p>
            </div>
            <div class="item">
                <i data-lucide="x" class="w-6 h-6 text-rose-500 dark:text-rose-400 mx-auto"></i>
                <p class="text-xs mt-1">rose-500</p>
            </div>
        </div>
    </div>

    <!-- 6. Buttons -->
    <div class="section">
        <h2>6. BUTTONS</h2>
        <div class="grid">
            <div class="item">
                <button class="w-full bg-blue-600 text-white rounded-lg py-2 px-3 font-bold text-sm">Primary</button>
            </div>
            <div class="item">
                <button class="w-full bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg py-2 px-3 font-bold text-sm text-slate-800 dark:text-white">White</button>
            </div>
            <div class="item">
                <button class="w-full bg-slate-800 dark:bg-slate-600 text-white rounded-lg py-2 px-3 font-bold text-sm">Slate-800</button>
            </div>
        </div>
    </div>

    <!-- 7. Badges -->
    <div class="section">
        <h2>7. BADGES</h2>
        <div class="grid">
            <div class="item">
                <span class="px-2 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-bold text-xs">Active</span>
            </div>
            <div class="item">
                <span class="px-2 py-1 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300 font-bold text-xs">Inactive</span>
            </div>
            <div class="item">
                <span class="px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-bold text-xs">Info</span>
            </div>
            <div class="item">
                <span class="px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 font-bold text-xs">Warning</span>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    </script>
</body>
</html>
