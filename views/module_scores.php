<!-- MÀN ĐIỂM SỐ -->
<div data-module="scores" class="module-panel pt-6 pb-24 relative">
<?php include __DIR__ . '/partial_heavy_loading.php'; ?>

    <!-- 1. THANH ĐIỀU HƯỚNG GỘP (Thiếu nhi) -->
    <?php include __DIR__ . '/partial_children_tabs.php'; ?>

    <div class="flex justify-end mb-4">
        <button @click="exportScoresExcel()" type="button" class="shrink-0 flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-blue-100 shadow-sm">
            <i data-lucide="file-up" class="w-4 h-4"></i> Xuất
        </button>
    </div>

    <div x-show="!canWriteScores" style="display: none;" class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
        <i data-lucide="eye" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
        <p class="text-micro text-slate-600 leading-snug">Bạn đang ở chế độ <span class="font-bold">chỉ xem</span>.</p>
    </div>

    <!-- 2. CHỌN LỚP -->
    <?php $scopeClassModel = 'scoreClass'; include __DIR__ . '/partial_scope_filter.php'; ?>

    <!-- Học kỳ -->
    <div x-show="scoreClass !== ''" style="display: none;" class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
        <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Học kỳ</label>
        <select x-model.number="scoreTermId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            <template x-for="t in terms" :key="t.id">
                <option :value="t.id" x-text="t.name"></option>
            </template>
        </select>
    </div>

    <!-- Chưa chọn lớp -->
    <div x-show="scoreClass === ''" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
        <i data-lucide="filter" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
        <p class="text-slate-600 font-semibold text-base mb-1">Chọn lớp để xem điểm</p>
        <p class="text-slate-500 text-sm">Điểm số xem theo từng lớp — bấm nút lọc <i data-lucide="filter" class="inline w-3.5 h-3.5 -mt-0.5"></i> phía trên rồi chọn lớp.</p>
    </div>

    <!-- 3. HAI TAB -->
    <div x-show="scoreClass !== ''" style="display: none;" role="tablist" aria-label="Chế độ điểm số" class="bg-white rounded-field p-1.5 shadow-sm border border-slate-100 flex gap-1.5 mb-4">
        <button @click="scoreTab = 'enter'" type="button" role="tab" :aria-selected="scoreTab === 'enter' ? 'true' : 'false'"
                class="flex-1 py-2.5 rounded-2xl font-bold text-xs transition-colors flex items-center justify-center gap-1.5"
                :class="scoreTab === 'enter' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            <i data-lucide="pencil" class="w-4 h-4"></i> Nhập điểm
        </button>
        <button @click="scoreTab = 'table'" type="button" role="tab" :aria-selected="scoreTab === 'table' ? 'true' : 'false'"
                class="flex-1 py-2.5 rounded-2xl font-bold text-xs transition-colors flex items-center justify-center gap-1.5"
                :class="scoreTab === 'table' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            <i data-lucide="table" class="w-4 h-4"></i> Bảng điểm
        </button>
    </div>

    <!-- ==========================================================
         TAB 1: NHẬP ĐIỂM — mỗi loại có N cột, N ≤ max_exams
         ========================================================== -->
    <div x-show="scoreClass !== '' && scoreTab === 'enter'" style="display: none;" role="tabpanel" aria-label="Nhập điểm">

        <!-- Chọn đầu điểm -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
            <template x-for="t in TNTT.scores.scoreTypes" :key="t.code">
                <button @click="scoreType = t.code" type="button"
                        class="py-2.5 rounded-xl font-bold text-micro border transition-colors flex flex-col items-center gap-0.5"
                        :class="scoreType === t.code ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'">
                    <span x-text="t.label"></span>
                    <span class="font-medium opacity-70" x-text="'HS' + t.weight"></span>
                </button>
            </template>
        </div>

        <!-- Tiến độ: đếm em có ≥1 bài đã chấm của loại hiện tại -->
        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100 mb-4">
            <div class="flex justify-between items-baseline mb-2">
                <span class="text-xs font-bold text-slate-500">
                    Đã nhập <span class="text-blue-600 text-base" x-text="scoreProgress.done"></span> / <span x-text="scoreProgress.total"></span>
                </span>
                <span class="text-micro font-bold text-slate-500" x-text="currentScoreType?.label || ''"></span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-blue-600 transition-all"
                     :style="'width:' + percent(scoreProgress.done, scoreProgress.total) + '%'"></div>
            </div>
        </div>

        <!-- Tên cột động: Miệng 1 · Miệng 2 · … -->
        <div class="hidden md:grid gap-3 mb-2 px-1" style="grid-template-columns: 1fr repeat(20, 64px);">
            <span class="text-micro font-bold text-slate-400 uppercase tracking-wide self-end pb-1">Học sinh</span>
            <template x-for="n in (currentScoreType?.max_exams || 3)" :key="n">
                <span class="text-micro font-bold text-slate-400 uppercase tracking-wide text-center self-end pb-1"
                      x-text="currentScoreType?.short_label + ' ' + n"></span>
            </template>
        </div>

        <div class="space-y-2.5">
            <!-- Skeleton -->
            <template x-if="syncing && scoreStudents.length === 0">
                <div class="space-y-2.5">
                    <template x-for="i in 5" :key="'sk-sc-' + i">
                        <div class="bg-white rounded-field p-3.5 shadow-sm border border-slate-100 flex gap-3">
                            <div class="flex-1">
                                <div class="skeleton skeleton-text-sm w-16 mb-1"></div>
                                <div class="skeleton skeleton-text w-32"></div>
                            </div>
                            <div class="flex gap-3">
                                <div class="skeleton w-16 h-10 rounded-xl"></div>
                                <div class="skeleton w-16 h-10 rounded-xl"></div>
                                <div class="skeleton w-16 h-10 rounded-xl"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-for="s in scoreStudents" :key="s.id">
                <div class="bg-white rounded-field p-3 shadow-sm border flex items-center gap-3 overflow-x-auto hide-scrollbar"
                     :class="examCountOfType(s.id, scoreType, scoreTermId) > 0 ? 'border-slate-100' : 'border-slate-200 border-dashed'">

                    <!-- Thông tin học sinh -->
                    <div class="shrink-0 w-32 min-w-0">
                        <p class="text-micro font-bold text-blue-600 leading-tight" x-text="s.code"></p>
                        <p class="text-sm font-black text-slate-800 leading-snug truncate">
                            <span class="font-normal text-slate-500" x-text="s.holyName"></span>
                            <span x-text="s.name"></span>
                        </p>
                    </div>

                    <!-- Cột điểm động -->
                    <div class="flex items-center gap-2 flex-1 min-w-0 justify-end">
                        <template x-for="n in examNumsOfType(s.id, scoreType, scoreTermId)" :key="n">
                            <div class="flex items-center gap-1">
                                <input type="number" min="0" max="10" step="0.1" inputmode="decimal" placeholder="–"
                                       :aria-label="currentScoreType?.label + ' ' + n + ' của ' + s.name"
                                       :disabled="!canWriteScores"
                                       :value="scoreOfExam(s.id, scoreType, n, scoreTermId)"
                                       @change="onScoreInput(s.id, scoreType, n, $event)"
                                       class="w-16 shrink-0 text-center rounded-xl px-2 py-2.5 text-base font-black focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all disabled:opacity-60"
                                       :class="scoreOfExam(s.id, scoreType, n, scoreTermId) !== ''
                                           ? 'bg-blue-50 border-blue-300 text-blue-700'
                                           : 'bg-slate-50 border-slate-200 text-slate-800'">
                                <!-- Nút xóa bài (chỉ hiện khi có nhiều hơn 1 bài) -->
                                <button x-show="examNumsOfType(s.id, scoreType, scoreTermId).length > 1"
                                        @click="confirmDeleteExam(s, scoreType, n)"
                                        type="button" :disabled="!canWriteScores"
                                        class="p-1 text-slate-300 hover:text-rose-500 disabled:opacity-30 transition-colors shrink-0"
                                        :aria-label="'Xóa bài ' + n">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </template>

                        <!-- Nút [+ Thêm bài] — chỉ hiện khi chưa đạt max -->
                        <button x-show="canAddExam(s.id, scoreType, scoreTermId) && canWriteScores"
                                @click="addExamForStudent(s.id, scoreType, scoreTermId)"
                                type="button"
                                class="shrink-0 w-8 h-8 rounded-xl border-2 border-dashed border-blue-300 text-blue-400 hover:border-blue-500 hover:text-blue-600 hover:bg-blue-50 transition-colors flex items-center justify-center"
                                :aria-label="'Thêm bài ' + currentScoreType?.label">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            </template>

            <div x-show="scoreStudents.length === 0 && !syncing" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="users-x" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
                <p class="text-slate-600 font-semibold text-base mb-1">Lớp này chưa có em nào</p>
                <p class="text-slate-500 text-sm">Hãy kiểm tra lớp đã chọn hoặc thêm thiếu nhi vào lớp</p>
            </div>
        </div>

        <p class="text-center text-micro text-slate-500 mt-5 px-6 leading-relaxed">
            Để trống ô là không tính điểm, không phải chấm 0.<br>
            Điểm trung bình chỉ tính trên các bài đã chấm.
        </p>
    </div>

    <!-- ==========================================================
         TAB 2: BẢNG ĐIỂM — hiển thị N cột động + ĐTB
         ========================================================== -->
    <div x-show="scoreClass !== '' && scoreTab === 'table'" style="display: none;" role="tabpanel" aria-label="Bảng điểm">

        <!-- Lưu ý cách tính động -->
        <div class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
            <i data-lucide="info" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
            <p class="text-micro text-slate-600 leading-snug">
                <span class="font-bold">Cách A:</span>
                TB mỗi loại đầu điểm = avg(N bài đã chấm),
                rồi ĐTB = Σ(TB_loại × HS) / Σ(HS).
                Loại nào chưa chấm bài nào → bỏ qua cả tử lẫn mẫu.
            </p>
        </div>

        <!-- Desktop: bảng cuộn ngang -->
        <div class="hidden md:block bg-white rounded-card border border-slate-100 shadow-sm overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left px-4 py-3 text-micro font-bold text-slate-500 uppercase sticky left-0 bg-slate-50 z-10">Họ và tên</th>
                        <template x-for="t in TNTT.scores.scoreTypes" :key="t.code">
                            <template x-for="n in examNumsOfType(scoreStudents[0]?.id, t.code, scoreTermId)" :key="t.code + '_' + n">
                                <th class="px-2 py-3 text-micro font-bold text-slate-500 uppercase text-center whitespace-nowrap">
                                    <span x-text="t.short_label"></span><sup class="text-xs" x-text="n"></sup>
                                    <span class="block font-normal text-slate-400 text-xs normal-case" x-text="'HS' + t.weight"></span>
                                </th>
                            </template>
                        </template>
                        <th class="px-3 py-3 text-micro font-bold text-slate-500 uppercase text-center bg-blue-50">ĐTB</th>
                        <th class="px-3 py-3 text-micro font-bold text-slate-500 uppercase text-center">Học lực</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="s in scoreStudents" :key="s.id">
                        <tr class="border-b border-slate-50 hover:bg-slate-50/50 last:border-0">
                            <td class="px-4 py-3 sticky left-0 bg-white z-10">
                                <p class="text-micro font-bold text-blue-600 leading-none" x-text="s.code"></p>
                                <p class="text-sm font-bold text-slate-800 leading-snug mt-0.5" x-text="s.name"></p>
                            </td>
                            <template x-for="t in TNTT.scores.scoreTypes" :key="t.code">
                                <template x-for="n in examNumsOfType(s.id, t.code, scoreTermId)" :key="t.code + '_' + n">
                                    <td class="px-2 py-3 text-center font-semibold whitespace-nowrap"
                                        :class="scoreOfExam(s.id, t.code, n, scoreTermId) === '' ? 'text-slate-300' : 'text-slate-700'"
                                        x-text="scoreOfExam(s.id, t.code, n, scoreTermId) === '' ? '–' : scoreOfExam(s.id, t.code, n, scoreTermId)"></td>
                                </template>
                            </template>
                            <td class="px-3 py-3 text-center font-black text-slate-900 bg-blue-50/30"
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

        <!-- Mobile: card stack — mỗi em một card -->
        <div class="md:hidden space-y-3">
            <template x-for="s in scoreStudents" :key="s.id">
                <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="text-micro font-bold text-blue-600" x-text="s.code"></p>
                            <p class="font-black text-slate-800" x-text="s.name"></p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xl font-black"
                               :class="termAverage(s.id, scoreTermId) === null ? 'text-slate-300' : 'text-blue-600'"
                               x-text="termAverage(s.id, scoreTermId) === null ? '–' : termAverage(s.id, scoreTermId)"></p>
                            <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-lg border"
                                  :class="scoreRankClass(termAverage(s.id, scoreTermId))"
                                  x-text="academicRank(termAverage(s.id, scoreTermId))"></span>
                        </div>
                    </div>

                    <!-- Grid điểm từng loại -->
                    <div class="space-y-2">
                        <template x-for="t in TNTT.scores.scoreTypes" :key="t.code">
                            <div class="bg-slate-50 rounded-xl p-2.5">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-bold text-slate-500" x-text="t.label"></span>
                                    <span class="text-xs text-slate-400">HS <span x-text="t.weight"></span></span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="n in examNumsOfType(s.id, t.code, scoreTermId)" :key="n">
                                        <div class="flex items-center gap-1">
                                            <div class="w-8 text-center text-xs text-slate-400 font-medium" x-text="n"></div>
                                            <div class="w-10 h-8 rounded-lg border text-center text-sm font-bold flex items-center justify-center"
                                                 :class="scoreOfExam(s.id, t.code, n, scoreTermId) !== ''
                                                     ? 'bg-blue-50 border-blue-200 text-blue-700'
                                                     : 'bg-white border-slate-200 text-slate-400'"
                                                 x-text="scoreOfExam(s.id, t.code, n, scoreTermId) !== '' ? scoreOfExam(s.id, t.code, n, scoreTermId) : '–'"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div x-show="scoreStudents.length === 0 && !syncing" style="display: none;" class="text-center py-8 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="users-x" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Chưa có em nào trong lớp</p>
            </div>
        </div>
    </div>
</div>
