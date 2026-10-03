<?php
/**
 * Test Theme Visibility - Chỉ test CSS dark mode overrides
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Dark Mode CSS</title>
    <script defer src="assets/js/vendor/alpine.js"></script>
    <script defer src="assets/js/vendor/lucide-icons.js"></script>
    <link rel="stylesheet" href="assets/css/bundle.php">
    <style>
        body { padding: 2rem; font-family: system-ui, sans-serif; background: #f8fafc; }
        body.dark { background: #0f172a; }
        .test-section { margin: 2rem 0; padding: 1.5rem; border-radius: 1rem; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .test-item { padding: 1rem; border-radius: 0.5rem; background: white; border: 1px solid #e5e7eb; }
        h2 { margin-bottom: 1rem; color: #0f172a; }
    </style>
</head>
<body x-data="{ dark: false }">
    <div style="position:fixed;top:1rem;right:1rem;z-index:9999;">
        <button @click="dark = !dark; document.body.classList.toggle('dark', dark)"
                class="px-4 py-2 rounded-full font-bold text-sm"
                :class="dark ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-800'">
            <span x-text="dark ? '🌙 Dark' : '☀️ Light'"></span>
        </button>
    </div>

    <h1 class="text-2xl font-bold mb-6">🧪 Test Dark Mode CSS</h1>

    <!-- Text Colors - phải có dark: class để override -->
    <div class="test-section glass-card">
        <h2>1. Text Colors (with dark: override)</h2>
        <div class="test-grid">
            <div class="test-item">
                <p class="text-slate-900 dark:text-white">text-slate-900 → white</p>
            </div>
            <div class="test-item">
                <p class="text-slate-800 dark:text-white">text-slate-800 → white</p>
            </div>
            <div class="test-item">
                <p class="text-slate-700 dark:text-slate-300">text-slate-700 → slate-300</p>
            </div>
            <div class="test-item">
                <p class="text-slate-600 dark:text-slate-400">text-slate-600 → slate-400</p>
            </div>
            <div class="test-item">
                <p class="text-slate-500 dark:text-slate-400">text-slate-500 → slate-400</p>
            </div>
            <div class="test-item">
                <p class="text-slate-400 dark:text-slate-500">text-slate-400 → slate-500</p>
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

    <!-- Header Sim -->
    <div class="test-section glass-card">
        <h2>3. Header Simulation</h2>
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

    <!-- Login Icons -->
    <div class="test-section glass-card">
        <h2>4. Login Icons</h2>
        <div class="test-item">
            <div class="relative mb-3">
                <i data-lucide="phone" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                <input type="text" placeholder="09xxxxxxxx" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-slate-800">
            </div>
            <div class="relative">
                <i data-lucide="key-round" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                <input type="password" placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-slate-800">
            </div>
        </div>
    </div>

    <!-- Student List -->
    <div class="test-section glass-card">
        <h2>5. Student List</h2>
        <div class="space-y-2">
            <div class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-100">
                <span class="font-bold text-blue-600">GLV001</span>
                <span class="text-slate-400">•</span>
                <span class="text-slate-600 font-medium">Khai Tâm 1A</span>
            </div>
            <div class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-100">
                <span class="font-bold text-slate-800">Lê Thị B</span>
                <span class="text-slate-500 font-normal">Maria</span>
            </div>
            <div class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-100">
                <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700 font-bold text-xs uppercase">đang sinh hoạt</span>
            </div>
        </div>
    </div>

    <div class="mt-6 p-4 bg-blue-50 rounded-xl text-center">
        <p class="font-semibold text-blue-700">Toggle Dark/Light mode để test</p>
        <p class="text-sm text-blue-600 mt-1">Elements phải hiển thị rõ ở cả 2 chế độ</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    </script>
</body>
</html>
