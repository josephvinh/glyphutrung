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
        .item { padding: 0.75rem; background: #ffffff; border-radius: 0.5rem; text-align: center; border: 1px solid #e5e7eb; }
        body.dark .item { background: #334155; border-color: #475569; }
        h2 { margin-bottom: 0.75rem; font-size: 1rem; }
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

    <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:1.5rem;color:#0f172a;" class="dark-title">Test Theme Visibility</h1>

    <!-- 1. Header -->
    <div class="section">
        <h2 style="color:#0f172a;">1. HEADER - Tên User</h2>
        <div class="item" style="text-align:left;">
            <p class="text-xs uppercase tracking-wide" style="color:#64748b;" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">Chào ngày mới,</p>
            <h1 class="text-lg font-bold" :style="dark ? 'color:#ffffff;' : 'color:#0f172a;'" x-text="dark ? 'Maria Nguyễn Thị A (Dark)' : 'Maria Nguyễn Thị A'">Maria Nguyễn Thị A</h1>
            <div class="inline-flex items-center mt-2 px-3 py-1 rounded-full" :style="dark ? 'background:#475569;' : 'background:#e2e8f0;'">
                <i data-lucide="shield-check" class="w-3 h-3" :style="dark ? 'color:#34d399;' : 'color:#059669;'" style="margin-right:0.25rem;"></i>
                <span class="text-xs font-medium" :style="dark ? 'color:#e2e8f0;' : 'color:#334155;'">GLV • Khối 1</span>
            </div>
        </div>
    </div>

    <!-- 2. Login Icons -->
    <div class="section">
        <h2 style="color:#0f172a;">2. LOGIN - Icons</h2>
        <div class="grid">
            <div class="item">
                <i data-lucide="phone" class="w-5 h-5" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'" style="display:block;margin:auto;"></i>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">Phone icon</p>
            </div>
            <div class="item">
                <i data-lucide="key-round" class="w-5 h-5" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'" style="display:block;margin:auto;"></i>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">Key icon</p>
            </div>
            <div class="item">
                <i data-lucide="eye" class="w-5 h-5" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'" style="display:block;margin:auto;"></i>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">Eye icon</p>
            </div>
        </div>
    </div>

    <!-- 3. Student List -->
    <div class="section">
        <h2 style="color:#0f172a;">3. STUDENT LIST - Tên các em</h2>
        <div class="item" style="text-align:left;">
            <p>
                <span class="font-bold" :style="dark ? 'color:#60a5fa;' : 'color:#2563eb;'">GLV001</span>
                <span style="margin:0 0.25rem;" :style="dark ? 'color:#64748b;' : 'color:#94a3b8;'">•</span>
                <span class="font-medium" :style="dark ? 'color:#94a3b8;' : 'color:#475569;'">Khai Tâm 1A</span>
            </p>
            <p class="font-semibold mt-1" :style="dark ? 'color:#ffffff;' : 'color:#0f172a;'">
                Lê Thị B <span class="font-normal" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">Maria</span>
            </p>
            <p class="mt-1">
                <span class="px-2 py-0.5 rounded text-xs font-bold uppercase" :style="dark ? 'background:#065f46;color:#6ee7b7;' : 'background:#d1fae5;color:#047857;'">đang sinh hoạt</span>
            </p>
        </div>
    </div>

    <!-- 4. Text Colors -->
    <div class="section">
        <h2 style="color:#0f172a;">4. TEXT COLORS</h2>
        <div class="grid">
            <div class="item">
                <p class="font-semibold" :style="dark ? 'color:#ffffff;' : 'color:#0f172a;'">#ffffff/#0f172a</p>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">white/slate-900</p>
            </div>
            <div class="item">
                <p class="font-semibold" :style="dark ? 'color:#f8fafc;' : 'color:#1e293b;'">#f8fafc/#1e293b</p>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">slate-50/slate-800</p>
            </div>
            <div class="item">
                <p class="font-semibold" :style="dark ? 'color:#cbd5e1;' : 'color:#334155;'">#cbd5e1/#334155</p>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">slate-300/slate-700</p>
            </div>
            <div class="item">
                <p class="font-semibold" :style="dark ? 'color:#94a3b8;' : 'color:#475569;'">#94a3b8/#475569</p>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">slate-400/slate-600</p>
            </div>
        </div>
    </div>

    <!-- 5. Icon Colors -->
    <div class="section">
        <h2 style="color:#0f172a;">5. ICON COLORS</h2>
        <div class="grid">
            <div class="item">
                <i data-lucide="star" class="w-6 h-6" :style="dark ? 'color:#60a5fa;' : 'color:#3b82f6;'" style="display:block;margin:auto;"></i>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">blue</p>
            </div>
            <div class="item">
                <i data-lucide="check" class="w-6 h-6" :style="dark ? 'color:#34d399;' : 'color:#10b981;'" style="display:block;margin:auto;"></i>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">emerald</p>
            </div>
            <div class="item">
                <i data-lucide="alert" class="w-6 h-6" :style="dark ? 'color:#fbbf24;' : 'color:#f59e0b;'" style="display:block;margin:auto;"></i>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">amber</p>
            </div>
            <div class="item">
                <i data-lucide="x" class="w-6 h-6" :style="dark ? 'color:#fb7185;' : 'color:#ef4444;'" style="display:block;margin:auto;"></i>
                <p class="text-xs mt-1" :style="dark ? 'color:#94a3b8;' : 'color:#64748b;'">rose</p>
            </div>
        </div>
    </div>

    <!-- 6. Buttons -->
    <div class="section">
        <h2 style="color:#0f172a;">6. BUTTONS</h2>
        <div class="grid">
            <div class="item">
                <button class="w-full rounded-lg py-2 px-3 font-bold text-sm" :style="dark ? 'background:#3b82f6;color:#fff;' : 'background:#2563eb;color:#fff;'">Primary</button>
            </div>
            <div class="item">
                <button class="w-full rounded-lg py-2 px-3 font-bold text-sm" :style="dark ? 'background:#475569;color:#f8fafc;border:1px solid #64748b;' : 'background:#fff;color:#1e293b;border:1px solid #e5e7eb;'">White</button>
            </div>
            <div class="item">
                <button class="w-full rounded-lg py-2 px-3 font-bold text-sm" :style="dark ? 'background:#1e293b;color:#f8fafc;' : 'background:#1e293b;color:#fff;'">Slate-800</button>
            </div>
        </div>
    </div>

    <!-- 7. Badges -->
    <div class="section">
        <h2 style="color:#0f172a;">7. BADGES</h2>
        <div class="grid">
            <div class="item">
                <span class="px-2 py-1 rounded-full font-bold text-xs" :style="dark ? 'background:#064e3b;color:#6ee7b7;' : 'background:#d1fae5;color:#047857;'">Active</span>
            </div>
            <div class="item">
                <span class="px-2 py-1 rounded-full font-bold text-xs" :style="dark ? 'background:#9f1239;color:#fda4af;' : 'background:#ffe4e6;color:#be123c;'">Inactive</span>
            </div>
            <div class="item">
                <span class="px-2 py-1 rounded-full font-bold text-xs" :style="dark ? 'background:#1e3a8a;color:#93c5fd;' : 'background:#dbeafe;color:#1d4ed8;'">Info</span>
            </div>
            <div class="item">
                <span class="px-2 py-1 rounded-full font-bold text-xs" :style="dark ? 'background:#78350f;color:#fcd34d;' : 'background:#fef3c7;color:#d97706;'">Warning</span>
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
