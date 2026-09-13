<!-- MÀN ĐIỂM SỐ -->
<div data-module="scores" class="module-panel pt-6 pb-10 relative">
<?php include __DIR__ . '/partial_heavy_loading.php'; ?>

    <!-- 1. THANH ĐIỀU HƯỚNG GỘP (Thiếu nhi) -->
    <?php include __DIR__ . '/partial_children_tabs.php'; ?>

    <div class="flex justify-end mb-4">
        <button @click="exportScoresCSV()" class="shrink-0 flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-blue-100 shadow-sm">
            <i data-lucide="file-up" class="w-4 h-4"></i> Xuất
        </button>
    </div>

    <div x-show="!canWriteScores" style="display: none;" class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
        <i data-lucide="eye" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
        <p class="text-micro text-slate-600 leading-snug">Bạn đang ở chế độ <span class="font-bold">chỉ xem</span>.</p>
    </div>

    <!-- 2. CHỌN LỚP — cùng kiểu bộ lọc của Danh sách (thanh + nút phễu) -->
    <?php $scopeClassModel = 'scoreClass'; include __DIR__ . '/partial_scope_filter.php'; ?>

    <!-- Học kỳ: chỉ hiện sau khi đã chọn lớp -->
    <div x-show="scoreClass !== ''" style="display: none;" class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
        <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Học kỳ</label>
        <select x-model.number="scoreTermId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            <template x-for="t in terms" :key="t.id">
                <option :value="t.id" x-text="t.name"></option>
            </template>
        </select>
    </div>

    <!-- Chưa chọn lớp: mời chọn, KHÔNG đổ cả đoàn ra cho nhẹ (giống Danh sách) -->
    <div x-show="scoreClass === ''" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
        <i data-lucide="filter" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
        <p class="text-slate-600 font-semibold text-base mb-1">Chọn lớp để xem điểm</p>
        <p class="text-slate-400 text-sm">Điểm số xem theo từng lớp — bấm nút lọc <i data-lucide="filter" class="inline w-3.5 h-3.5 -mt-0.5"></i> phía trên rồi chọn lớp.</p>
    </div>

    <!-- 3. HAI TAB -->
    <div x-show="scoreClass !== ''" style="display: none;" class="bg-white rounded-field p-1.5 shadow-sm border border-slate-100 flex gap-1.5 mb-4">
        <button @click="scoreTab = 'enter'" type="button"
                class="flex-1 py-2.5 rounded-2xl font-bold text-xs transition-colors flex items-center justify-center gap-1.5"
                :class="scoreTab === 'enter' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            <i data-lucide="pencil" class="w-4 h-4"></i> Nhập điểm
        </button>
        <button @click="scoreTab = 'table'" type="button"
                class="flex-1 py-2.5 rounded-2xl font-bold text-xs transition-colors flex items-center justify-center gap-1.5"
                :class="scoreTab === 'table' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            <i data-lucide="table" class="w-4 h-4"></i> Bảng điểm
        </button>
    </div>

    <!-- ==========================================================
         TAB 1: NHẬP ĐIỂM THEO CỘT
         Chấm xong bài nào nhập cột đó, gõ một mạch từ trên xuống —
         nhanh hơn nhiều so với mở popup từng em.
         ========================================================== -->
    <div x-show="scoreClass !== '' && scoreTab === 'enter'" style="display: none;">

        <!-- Chọn đầu điểm -->
        <div class="grid grid-cols-4 gap-2 mb-4">
            <template x-for="t in scoreTypes" :key="t.key">
                <button @click="scoreType = t.key" type="button"
                        class="py-2.5 rounded-xl font-bold text-micro border transition-colors flex flex-col items-center gap-0.5"
                        :class="scoreType === t.key ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'">
                    <span x-text="t.label"></span>
                    <span class="text-micro font-medium opacity-70" x-text="'hệ số ' + t.weight"></span>
                </button>
            </template>
        </div>

        <!-- Tiến độ chấm -->
        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100 mb-4">
            <div class="flex justify-between items-baseline mb-2">
                <span class="text-xs font-bold text-slate-500">
                    Đã nhập <span class="text-blue-600 text-base" x-text="scoreProgress.done"></span> / <span x-text="scoreProgress.total"></span>
                </span>
                <span class="text-micro font-bold text-slate-500" x-text="currentScoreType.label"></span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-blue-600 transition-all"
                     :style="'width:' + percent(scoreProgress.done, scoreProgress.total) + '%'"></div>
            </div>
        </div>

        <div class="space-y-2.5">
            <template x-for="s in scoreStudents" :key="s.id">
                <div style="content-visibility: auto; contain-intrinsic-size: auto 76px;"
                     class="bg-white rounded-field p-3.5 shadow-sm border flex items-center gap-3"
                     :class="scoreOf(s.id, scoreType) !== '' ? 'border-slate-100' : 'border-slate-200 border-dashed'">

                    <div class="flex-1 min-w-0">
                        <p class="text-micro font-bold text-blue-600 leading-tight" x-text="s.code"></p>
                        <p class="text-sm font-black text-slate-800 leading-snug">
                            <span class="font-normal text-slate-500" x-text="s.holyName"></span>
                            <span x-text="s.name"></span>
                        </p>
                    </div>

                    <input type="number" min="0" max="10" step="0.1" inputmode="decimal" placeholder="–"
                           :disabled="!canWriteScores"
                           :value="scoreOf(s.id, scoreType)"
                           @change="onScoreInput(s.id, scoreType, $event)"
                           class="w-20 shrink-0 text-center rounded-xl px-2 py-2.5 text-base font-black focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all disabled:opacity-60"
                           :class="scoreOf(s.id, scoreType) !== ''
                               ? 'bg-blue-50 border-blue-300 text-blue-700'
                               : 'bg-slate-50 border-slate-200 text-slate-800'">
                </div>
            </template>

            <div x-show="scoreStudents.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="users-x" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
                <p class="text-slate-600 font-semibold text-base mb-1">Lớp này chưa có em nào</p>
                <p class="text-slate-400 text-sm">Hãy kiểm tra lớp đã chọn hoặc thêm thiếu nhi vào lớp</p>
            </div>
        </div>

        <p class="text-center text-micro text-slate-500 mt-5 px-6 leading-relaxed">
            Để trống ô là xóa điểm, không phải chấm 0.<br>
            Điểm trung bình chỉ tính trên các cột đã chấm.
        </p>
    </div>

    <!-- ==========================================================
         TAB 2: BẢNG ĐIỂM
         ========================================================== -->
    <div x-show="scoreClass !== '' && scoreTab === 'table'" style="display: none;">
        <div class="scroll-x bg-white rounded-card border border-slate-100 shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left px-4 py-3 text-micro font-bold text-slate-500 uppercase tracking-wide">Họ và tên</th>
                        <template x-for="t in scoreTypes" :key="t.key">
                            <th class="px-2 py-3 text-micro font-bold text-slate-500 uppercase text-center" x-text="t.short"></th>
                        </template>
                        <th class="px-3 py-3 text-micro font-bold text-slate-500 uppercase text-center">ĐTB</th>
                        <th class="px-3 py-3 text-micro font-bold text-slate-500 uppercase text-center">Học lực</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="s in scoreStudents" :key="s.id">
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-4 py-3">
                                <p class="text-micro font-bold text-blue-600 leading-none" x-text="s.code"></p>
                                <p class="text-sm font-bold text-slate-800 leading-snug mt-0.5 whitespace-nowrap" x-text="s.name"></p>
                            </td>
                            <template x-for="t in scoreTypes" :key="t.key">
                                <td class="px-2 py-3 text-center font-semibold"
                                    :class="scoreOf(s.id, t.key) === '' ? 'text-slate-300' : 'text-slate-700'"
                                    x-text="scoreOf(s.id, t.key) === '' ? '–' : scoreOf(s.id, t.key)"></td>
                            </template>
                            <td class="px-3 py-3 text-center font-black text-slate-900"
                                x-text="termAverage(s.id, scoreTermId) === null ? '–' : termAverage(s.id, scoreTermId)"></td>
                            <td class="px-3 py-3 text-center">
                                <span class="text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg border whitespace-nowrap"
                                      :class="scoreRankClass(termAverage(s.id, scoreTermId))"
                                      x-text="academicRank(termAverage(s.id, scoreTermId))"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mt-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Cách tính điểm trung bình</h3>
            <p class="text-xs text-slate-600 leading-relaxed">
                Cộng điểm nhân hệ số rồi chia tổng hệ số:
                <span class="font-bold text-slate-800">Miệng ×1 · 15 phút ×1 · Giữa kỳ ×2 · Cuối kỳ ×3</span>.
                Cột nào chưa chấm thì không tính vào cả tử lẫn mẫu.
            </p>
            <p class="text-xs text-slate-600 leading-relaxed mt-2">
                Xếp loại học lực: <span class="font-bold text-emerald-600">Giỏi ≥ 8</span> ·
                <span class="font-bold text-blue-600">Khá ≥ 6.5</span> ·
                <span class="font-bold text-amber-600">Trung bình ≥ 5</span> ·
                <span class="font-bold text-rose-600">Yếu &lt; 5</span>
            </p>
        </div>
    </div>
</div>
