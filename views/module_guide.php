<?php $HD = require __DIR__ . '/../config/huong_dan.php'; ?>
<!-- MÀN HƯỚNG DẪN SỬ DỤNG — nội dung từ config/huong_dan.php -->
<div data-module="guide" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')"
                class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <div class="min-w-0">
            <h2 class="text-xl font-black text-slate-800 dark:text-white tracking-tight leading-tight">Hướng dẫn sử dụng</h2>
            <p class="text-micro text-slate-500">Thao tác cơ bản theo từng vai</p>
        </div>
    </div>

    <!-- Giới thiệu -->
    <div class="bg-blue-50 border border-blue-100 rounded-card p-4 mb-5 flex items-start gap-2.5">
        <i data-lucide="info" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5"></i>
        <p class="text-micro text-blue-700 leading-relaxed"><?= htmlspecialchars($HD['gioi_thieu']) ?></p>
    </div>

    <div x-data="{ gr: user.role }">

        <!-- 2. DÙNG CHUNG -->
        <h3 class="text-xs font-black text-slate-500 uppercase tracking-wider mb-3 px-1">Dùng chung cho mọi vai</h3>
        <div class="space-y-3 mb-6">
            <?php foreach ($HD['chung'] as $m): ?>
            <div class="bg-white dark:bg-slate-700 rounded-card p-4 shadow-sm border border-slate-100 dark:border-slate-600">
                <p class="text-sm font-black text-slate-800 dark:text-white mb-2 flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 shrink-0"></i>
                    <?= htmlspecialchars($m['title']) ?>
                </p>
                <div class="space-y-1.5">
                    <?php foreach ($m['steps'] as $i => $s): ?>
                    <div class="flex gap-2.5 items-start">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-slate-100 text-slate-500 text-micro font-black flex items-center justify-center mt-0.5"><?= $i + 1 ?></span>
                        <span class="text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($s) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 3. THEO VAI -->
        <h3 class="text-xs font-black text-slate-500 uppercase tracking-wider mb-3 px-1">Theo vai của bạn</h3>

        <!-- Chọn vai để xem (mặc định vai của bạn) -->
        <div class="bg-white rounded-field p-1.5 shadow-sm border border-slate-100 flex gap-1.5 mb-4 overflow-x-auto hide-scrollbar">
            <?php foreach ($HD['vai'] as $key => $r): ?>
            <button @click="gr = '<?= $key ?>'" type="button"
                    class="shrink-0 whitespace-nowrap px-3.5 py-2 rounded-2xl font-bold text-micro transition-colors"
                    :class="gr === '<?= $key ?>' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
                <?= htmlspecialchars($r['label']) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($HD['vai'] as $key => $r): ?>
        <div x-show="gr === '<?= $key ?>'" style="display: none;" class="space-y-3">
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 text-white rounded-card p-4">
                <p class="font-black text-base leading-tight"><?= htmlspecialchars($r['label']) ?></p>
                <p class="text-micro text-white/80 mt-0.5"><?= htmlspecialchars($r['mo_ta']) ?></p>
            </div>
            <?php foreach ($r['items'] as $m): ?>
            <div class="bg-white dark:bg-slate-700 rounded-card p-4 shadow-sm border border-slate-100 dark:border-slate-600">
                <p class="text-sm font-black text-slate-800 dark:text-white mb-2 flex items-center gap-2">
                    <i data-lucide="chevron-right" class="w-4 h-4 text-blue-500 shrink-0"></i>
                    <?= htmlspecialchars($m['title']) ?>
                </p>
                <div class="space-y-1.5">
                    <?php foreach ($m['steps'] as $i => $s): ?>
                    <div class="flex gap-2.5 items-start">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-slate-100 text-slate-500 text-micro font-black flex items-center justify-center mt-0.5"><?= $i + 1 ?></span>
                        <span class="text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($s) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

    </div>
</div>
