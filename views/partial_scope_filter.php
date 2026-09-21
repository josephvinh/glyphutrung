<?php
/* ==========================================================
   BỘ LỌC PHẠM VI DÙNG CHUNG — CÙNG KIỂU với tab Danh sách:
   một thanh + nút phễu mở panel chọn Khối/Lớp (hoặc chỉ Khối).
   Danh sách trống cho tới khi chọn.

   Đặt biến TRƯỚC khi include (include xong nên unset để lần sau sạch):
     $scopeClassModel  : tên biến Alpine giữ LỚP đang chọn (chế độ lớp)
     $scopeBlockModel  : tên biến Alpine giữ KHỐI đang chọn (chế độ khối,
                         dùng cho Lên lớp) — đặt cái này thì BỎ chọn lớp
     $scopeSearchModel : (tuỳ chọn) biến ô tìm; '' hoặc không đặt = không có ô tìm

   Chế độ lớp dùng chung state filterBlock để lọc Khối (y như Danh sách),
   nên chọn Khối bên Danh sách hay bên đây đều ăn khớp.
   ========================================================== */
$__cls   = isset($scopeClassModel)  ? $scopeClassModel  : '';
$__blk   = isset($scopeBlockModel)  ? $scopeBlockModel  : '';
$__q     = isset($scopeSearchModel) ? $scopeSearchModel : '';
$__blockMode = ($__blk !== '');
// Biến "đang chọn" để tô nút phễu + chấm đỏ: chế độ khối nhìn $__blk,
// chế độ lớp nhìn cả lớp lẫn filterBlock.
$__active = $__blockMode ? ($__blk . " !== ''") : ($__cls . " !== '' || filterBlock !== ''");
// Lệnh Xóa bộ lọc
$__clear  = $__blockMode ? ($__blk . " = ''") : ($__cls . " = ''; filterBlock = ''");
if ($__q !== '') $__clear .= "; " . $__q . " = ''";
$__nhan   = $__blockMode ? 'khối' : 'lớp';
?>
<div class="mb-4">
    <div class="relative flex gap-2">
        <?php if ($__q !== ''): ?>
        <!-- Ô tìm nhanh (giống Danh sách) -->
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
            <input x-model="<?= $__q ?>" type="text" placeholder="Tìm tên, tên thánh, mã số..." class="w-full bg-white border border-slate-200 rounded-field py-3.5 pl-12 pr-10 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            <button aria-label="Xóa ô tìm kiếm" x-show="<?= $__q ?> !== ''" @click="<?= $__q ?> = ''" style="display: none;" class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 active:scale-90 transition-transform">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </button>
        </div>
        <?php else: ?>
        <!-- Không có ô tìm: thanh cho biết đang chọn gì -->
        <div class="flex-1 flex items-center h-[50px] px-4 rounded-field bg-white border border-slate-200 shadow-sm">
            <i data-lucide="layers" class="w-4 h-4 text-slate-400 mr-2.5 shrink-0"></i>
            <span class="text-sm font-semibold truncate" :class="<?= $__blockMode ? $__blk : $__cls ?> === '' ? 'text-slate-400' : 'text-slate-700'"
                  x-text="<?= $__blockMode ? $__blk : $__cls ?> === '' ? 'Chưa chọn <?= $__nhan ?>' : <?= $__blockMode ? $__blk : $__cls ?>"></span>
        </div>
        <?php endif; ?>

        <button @click="showFilter = !showFilter" type="button" aria-label="Mở bộ lọc" :aria-expanded="showFilter ? 'true' : 'false'"
                :class="showFilter || (<?= $__active ?>) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'"
                class="w-12 shrink-0 rounded-field border shadow-sm flex items-center justify-center active:scale-90 transition-all relative">
            <i data-lucide="filter" class="w-5 h-5"></i>
            <span x-show="(<?= $__active ?>) && !showFilter" style="display: none;" class="absolute -top-1 -right-1 w-3 h-3 bg-rose-500 rounded-full border-2 border-slate-50"></span>
        </button>
    </div>

    <!-- BẢNG LỌC -->
    <div x-show="showFilter" x-collapse style="display: none;" class="mt-3 bg-white p-4 rounded-card shadow-sm border border-slate-100 border-t-4 border-t-blue-500">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
            <?php if ($__blockMode): ?>
            <div class="sm:col-span-2">
                <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Khối</label>
                <select x-model="<?= $__blk ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                    <option value="">— Chọn khối —</option>
                    <template x-for="b in availableBlocks" :key="b">
                        <option :value="b" x-text="b"></option>
                    </template>
                </select>
            </div>
            <?php else: ?>
            <div x-show="availableBlocks.length > 1">
                <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Khối</label>
                <select x-model="filterBlock" @change="<?= $__cls ?> = ''" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                    <option value="">Tất cả các khối</option>
                    <template x-for="b in availableBlocks" :key="b">
                        <option :value="b" x-text="b"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Lớp</label>
                <select x-model="<?= $__cls ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                    <option value="">— Chọn lớp —</option>
                    <template x-for="cls in availableClasses" :key="cls">
                        <option :value="cls" x-text="cls"></option>
                    </template>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-4 flex justify-end" x-show="<?= $__active ?>" style="display: none;">
            <button @click="<?= $__clear ?>" type="button" class="flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-slate-200">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Xóa bộ lọc
            </button>
        </div>
    </div>
</div>
<?php unset($scopeClassModel, $scopeBlockModel, $scopeSearchModel, $__cls, $__blk, $__q, $__blockMode, $__active, $__clear, $__nhan); ?>
