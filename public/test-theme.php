<?php
/**
 * Test Theme Visibility - Kiểm tra light/dark mode
 * Copy file này vào thư mục public/ và truy cập /test-theme.php
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Theme Visibility</title>
    <script defer src="assets/js/vendor/alpine.js"></script>
    <script defer src="assets/js/vendor/lucide-icons.js"></script>
    <link rel="stylesheet" href="assets/css/bundle.php">
    <style>
        body { padding: 2rem; font-family: system-ui, sans-serif; }
        .test-section { margin: 2rem 0; padding: 1.5rem; border-radius: 1rem; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .test-item { padding: 1rem; border-radius: 0.5rem; background: white; border: 1px solid #e5e7eb; }
        h2 { margin-bottom: 1rem; color: #0f172a; }
        /* Force dark mode styles when body has .dark class */
        body.dark .test-item { background: #1e293b; border-color: #334155; }
        body.dark h2 { color: #f8fafc; }
        body.dark .text-slate-900 { color: #f8fafc !important; }
        body.dark .text-slate-800 { color: #f8fafc !important; }
        body.dark .text-slate-700 { color: #cbd5e1 !important; }
        body.dark .text-slate-600 { color: #94a3b8 !important; }
        body.dark .text-slate-500 { color: #94a3b8 !important; }
        body.dark .text-slate-400 { color: #64748b !important; }
        body.dark .text-white { color: #ffffff !important; }
    </style>
</head>
<body x-data="{ dark: false }">
    <div style="position:fixed;top:1rem;right:1rem;z-index:9999;">
        <button @click="dark = !dark; $el.closest('body').classList.toggle('dark', dark)"
                class="px-4 py-2 rounded-full font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-white">
            <span x-text="dark ? '🌙 Dark' : '☀️ Light'"></span> Mode
        </button>
    </div>

    <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:2rem;" class="text-slate-900 dark:text-white">🧪 Test Theme Visibility</h1>

    <!-- Text Colors -->
    <div class="test-section glass-card">
        <h2>1. Text Colors</h2>
        <div class="test-grid">
            <div class="test-item">
                <p class="text-slate-900 dark:text-white">text-slate-900</p>
            </div>
            <div class="test-item">
                <p class="text-slate-800 dark:text-white">text-slate-800</p>
            </div>
            <div class="test-item">
                <p class="text-slate-700 dark:text-slate-300">text-slate-700</p>
            </div>
            <div class="test-item">
                <p class="text-slate-600 dark:text-slate-400">text-slate-600</p>
            </div>
            <div class="test-item">
                <p class="text-slate-500 dark:text-slate-400">text-slate-500</p>
            </div>
            <div class="test-item">
                <p class="text-slate-400 dark:text-slate-500">text-slate-400</p>
            </div>
        </div>
    </div>

    <!-- Icon Colors -->
    <div class="test-section glass-card">
        <h2>2. Icon Colors</h2>
        <div class="test-grid">
            <div class="test-item text-center">
                <i data-lucide="star" class="w-8 h-8 text-blue-500 dark:text-blue-400 mx-auto"></i>
                <p class="text-xs mt-1 text-slate-600 dark:text-slate-400">blue-500</p>
            </div>
            <div class="test-item text-center">
                <i data-lucide="check" class="w-8 h-8 text-emerald-500 dark:text-emerald-400 mx-auto"></i>
                <p class="text-xs mt-1 text-slate-600 dark:text-slate-400">emerald-500</p>
            </div>
            <div class="test-item text-center">
                <i data-lucide="alert" class="w-8 h-8 text-amber-500 dark:text-amber-400 mx-auto"></i>
                <p class="text-xs mt-1 text-slate-600 dark:text-slate-400">amber-500</p>
            </div>
            <div class="test-item text-center">
                <i data-lucide="x" class="w-8 h-8 text-rose-500 dark:text-rose-400 mx-auto"></i>
                <p class="text-xs mt-1 text-slate-600 dark:text-slate-400">rose-500</p>
            </div>
        </div>
    </div>

    <!-- Buttons -->
    <div class="test-section glass-card">
        <h2>3. Buttons</h2>
        <div class="test-grid">
            <div class="test-item">
                <button class="w-full bg-blue-600 text-white rounded-xl py-2 px-4 font-bold mb-2">Primary</button>
                <button class="w-full bg-white border border-slate-200 dark:bg-slate-700 dark:border-slate-600 dark:text-white rounded-xl py-2 px-4 font-bold">White</button>
            </div>
            <div class="test-item">
                <button class="w-full bg-slate-800 dark:bg-slate-600 text-white rounded-xl py-2 px-4 font-bold mb-2">Slate-800</button>
                <button class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 dark:text-white rounded-xl py-2 px-4 font-bold">Slate-50</button>
            </div>
        </div>
    </div>

    <!-- Badges -->
    <div class="test-section glass-card">
        <h2>4. Badges</h2>
        <div class="test-grid">
            <div class="test-item">
                <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-bold text-sm">Active</span>
                <span class="inline-block px-3 py-1 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300 font-bold text-sm ml-2">Inactive</span>
            </div>
            <div class="test-item">
                <span class="inline-block px-3 py-1 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-bold text-sm">Info</span>
                <span class="inline-block px-3 py-1 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-300 font-bold text-sm ml-2">Warning</span>
            </div>
        </div>
    </div>

    <!-- Header Sim -->
    <div class="test-section glass-card">
        <h2>5. Header Simulation</h2>
        <div class="test-item">
            <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">Chào ngày mới,</p>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                <span>Maria</span> <span>Nguyễn Thị A</span>
            </h1>
            <div class="inline-flex items-center bg-slate-200 dark:bg-slate-700 px-3 py-1 rounded-full mt-2">
                <i data-lucide="shield-check" class="w-3 h-3 text-emerald-600 dark:text-emerald-400 mr-1.5"></i>
                <p class="text-xs text-slate-700 dark:text-slate-300 font-medium">Giáo Lý Viên • Khối 1</p>
            </div>
        </div>
    </div>

    <!-- Login Icons Sim -->
    <div class="test-section glass-card">
        <h2>6. Login Icons Simulation</h2>
        <div class="test-item">
            <div class="relative mb-3">
                <i data-lucide="phone" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-600 dark:text-slate-300"></i>
                <input type="text" placeholder="09xxxxxxxx" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl py-3 pl-11 pr-4 text-slate-800 dark:text-white">
            </div>
            <div class="relative">
                <i data-lucide="key-round" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-600 dark:text-slate-300"></i>
                <input type="password" placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl py-3 pl-11 pr-4 text-slate-800 dark:text-white">
            </div>
        </div>
    </div>

    <!-- Student List Sim -->
    <div class="test-section glass-card">
        <h2>7. Student List Simulation</h2>
        <div class="space-y-2">
            <div class="flex items-center gap-3 p-3 bg-white dark:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-600">
                <span class="font-bold text-blue-600 dark:text-blue-400">GLV001</span>
                <span class="text-slate-400 dark:text-slate-500">•</span>
                <span class="text-slate-600 dark:text-slate-400 font-medium">Khai Tâm 1A</span>
            </div>
            <div class="flex items-center gap-3 p-3 bg-white dark:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-600">
                <span class="font-bold text-slate-800 dark:text-white">Lê Thị B</span>
                <span class="text-slate-500 dark:text-slate-400 font-normal">Maria</span>
            </div>
            <div class="flex items-center gap-3 p-3 bg-white dark:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-600">
                <span class="px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-300 font-bold text-xs uppercase">đang sinh hoạt</span>
            </div>
        </div>
    </div>

    <div style="margin-top:3rem;padding:1rem;background:#f0f9ff;border-radius:1rem;text-align:center;" class="dark:bg-slate-800">
        <p style="color:#0369a1;font-weight:600;" class="dark:text-blue-300">Toggle Dark/Light mode ở góc phải để kiểm tra</p>
        <p style="color:#64748b;font-size:0.875rem;margin-top:0.5rem;" class="dark:text-slate-400">
            Nếu chữ vẫn không thấy trong dark mode → CSS chưa được load đúng
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
