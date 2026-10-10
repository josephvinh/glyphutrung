<!-- MÀN ĐIỂM SỐ -->
<div data-module="scores" class="module-panel pt-6 pb-24 relative"
     x-effect="if (scoreClass && currentModule === 'scores') loadScoreExams()">
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

    <!-- 2. CHỌN LỚP — cùng kiểu bộ lọc của Danh sách (thanh + nút phễu) -->
    <?php $scopeClassModel = 'scoreClass'; include __DIR__ . '/partial_scope_filter.php'; ?>

    <!-- Học kỳ: chỉ hiện sau khi đã chọn lớp -->
    <div x-show="scoreClass !== ''" style="display: none;" class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
        <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Học kỳ</label>
        <select x-model.number="scoreTermId" @change="onScoreTermChange($event.target.value)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            <template x-for="t in terms" :key="t.id">
                <option :value="t.id" x-text="t.name"></option>
            </template>
        </select>
    </div>

    <!-- Chưa chọn lớp: mời chọn, KHÔNG đổ cả đoàn ra cho nhẹ (giống Danh sách) -->
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
         TAB 1: NHẬP ĐIỂM THEO CỘT
         ========================================================== -->
    <div x-show="scoreClass !== '' && scoreTab === 'enter'" style="display: none;" role="tabpanel" aria-label="Nhập điểm">

        <!-- Chọn loại điểm -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
            <template x-for="t in scoreTypes" :key="t.key">
                <button @click="onScoreTypeChange(t.key)" type="button"
                        class="py-2.5 rounded-xl font-bold text-micro border transition-colors flex flex-col items-center gap-0.5"
                        :class="scoreType === t.key ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'">
                    <span x-text="t.label"></span>
                    <span class="text-micro font-medium opacity-70" x-text="'hệ số ' + t.weight"></span>
                </button>
            </template>
        </div>

        <!-- Chọn bài kiểm tra (nếu có nhiều hơn 1) -->
        <div x-show="currentScoreExams.length > 0" style="display: none;" class="bg-slate-50 rounded-xl p-3 mb-4 border border-slate-200">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs font-bold text-slate-500">BÀI KIỂM TRA:</span>
                <template x-for="e in currentScoreExams" :key="e.id">
                    <button @click="onScoreExamChange(e.id)" type="button"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors"
                            :class="scoreExamId === e.id ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300'">
                        <span x-text="e.name || currentScoreType.label + ' #' + e.id"></span>
                        <span x-show="e.examDate" class="opacity-70 ml-1" x-text="'(' + e.examDate + ')'"></span>
                    </button>
                </template>
                <!-- Nút thêm bài -->
                <button x-show="canWriteScores" @click="showAddExamModal = true" type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border border-dashed border-slate-300 text-slate-400 hover:border-blue-400 hover:text-blue-500 transition-colors flex items-center gap-1">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Thêm bài
                </button>
            </div>
            <!-- Nếu chưa có bài nào, hiện nút tạo bài đầu tiên -->
            <div x-show="currentScoreExams.length === 0 && canWriteScores" style="display: none;">
                <button @click="showAddExamModal = true" type="button"
                        class="w-full py-3 rounded-xl border-2 border-dashed border-slate-300 text-slate-500 text-sm font-bold hover:border-blue-400 hover:text-blue-600 transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    Tạo bài <span x-text="currentScoreType.label"></span> đầu tiên
                </button>
            </div>
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
            <!-- Skeleton khi đang nạp lại số liệu -->
            <template x-if="syncing && scoreStudents.length === 0">
                <div class="space-y-2.5">
                    <template x-for="i in 5" :key="'sk-sc-' + i">
                        <div class="bg-white rounded-field p-3.5 shadow-sm border border-slate-100 flex items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="skeleton skeleton-text-sm w-16 mb-1"></div>
                                <div class="skeleton skeleton-text w-32"></div>
                            </div>
                            <div class="skeleton w-20 h-10 rounded-xl shrink-0"></div>
                        </div>
                    </template>
                </div>
            </template>
            <template x-for="s in scoreStudents" :key="s.id">
                <div style="content-visibility: auto; contain-intrinsic-size: auto 76px;"
                     class="bg-white rounded-field p-3.5 shadow-sm border flex items-center gap-3"
                     :class="scoreOfExam(s.id, scoreExamId) !== '' ? 'border-slate-100' : 'border-slate-200 border-dashed'">

                    <div class="flex-1 min-w-0">
                        <p class="text-micro font-bold text-blue-600 leading-tight" x-text="s.code"></p>
                        <p class="text-sm font-black text-slate-800 leading-snug">
                            <span class="font-normal text-slate-500" x-text="s.holyName"></span>
                            <span x-text="s.name"></span>
                        </p>
                    </div>

                    <input type="number" min="0" max="10" step="0.1" inputmode="decimal" placeholder="–"
                           x-show="scoreExamId !== null"
                           :aria-label="'Điểm ' + (currentScoreExams.find(e => e.id === scoreExamId)?.name || currentScoreType.label) + ' của ' + s.name"
                           :disabled="!canWriteScores"
                           :value="scoreOfExam(s.id, scoreExamId)"
                           @change="onScoreInput(s.id, scoreExamId, $event)"
                           class="w-20 shrink-0 text-center rounded-xl px-2 py-2.5 text-base font-black focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all disabled:opacity-60"
                           :class="scoreOfExam(s.id, scoreExamId) !== ''
                               ? 'bg-blue-50 border-blue-300 text-blue-700'
                               : 'bg-slate-50 border-slate-200 text-slate-800'">
                    <!-- Nếu chưa chọn bài nào -->
                    <span x-show="scoreExamId === null" class="text-slate-400 text-sm font-medium">Chọn bài kiểm tra</span>
                </div>
            </template>

            <div x-show="scoreStudents.length === 0 && !syncing" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="users-x" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
                <p class="text-slate-600 font-semibold text-base mb-1">Lớp này chưa có em nào</p>
                <p class="text-slate-500 text-sm">Hãy kiểm tra lớp đã chọn hoặc thêm thiếu nhi vào lớp</p>
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
    <div x-show="scoreClass !== '' && scoreTab === 'table'" style="display: none;" role="tabpanel" aria-label="Bảng điểm">
        <div class="scroll-x bg-white rounded-card border border-slate-100 shadow-sm overflow-x-auto">
            <table class="w-full text-sm score-table-sticky">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left px-4 py-3 text-micro font-bold text-slate-500 uppercase tracking-wide">Họ và tên</th>
                        <template x-for="t in scoreTypes" :key="t.key">
                            <!-- Nếu có nhiều bài cùng loại, hiện 1 cột lớn gộp, mỗi bài 1 cột con -->
                            <template x-if="scoreExams[t.key] && scoreExams[t.key].length > 0">
                                <th class="px-2 py-3 text-micro font-bold text-slate-500 uppercase text-center border-l border-slate-100"
                                    :colspan="scoreExams[t.key].length">
                                    <span x-text="t.label + ' (hs' + t.weight + ')'"></span>
                                </th>
                            </template>
                            <!-- Nếu chỉ có 1 bài hoặc không có bài nào -->
                            <template x-if="!scoreExams[t.key] || scoreExams[t.key].length === 0">
                                <th class="px-2 py-3 text-micro font-bold text-slate-500 uppercase text-center">
                                    <span x-text="t.label"></span><br><small x-text="'hs' + t.weight"></small>
                                </th>
                            </template>
                        </template>
                        <!-- Header cho các bài cụ thể -->
                        <template x-for="t in scoreTypes" :key="'hd-' + t.key">
                            <template x-for="e in (scoreExams[t.key] || [])" :key="e.id">
                                <th class="px-2 py-2 text-micro font-medium text-slate-400 uppercase text-center border-l border-slate-100">
                                    <span x-text="e.name || t.label + ' #' + e.id"></span>
                                    <template x-if="e.examDate">
                                        <br><small x-text="e.examDate"></small>
                                    </template>
                                </th>
                            </template>
                        </template>
                        <th class="px-3 py-3 text-micro font-bold text-slate-500 uppercase text-center">TB Loại</th>
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
                            <!-- Điểm từng bài -->
                            <template x-for="t in scoreTypes" :key="'row-' + t.key">
                                <template x-for="e in (scoreExams[t.key] || [])" :key="e.id">
                                    <td class="px-2 py-3 text-center font-semibold border-l border-slate-100"
                                        :class="scoreOfExam(s.id, e.id) === '' ? 'text-slate-300' : 'text-slate-700'"
                                        x-text="scoreOfExam(s.id, e.id) === '' ? '–' : scoreOfExam(s.id, e.id)"></td>
                                </template>
                            </template>
                            <!-- Trung bình loại điểm -->
                            <td class="px-2 py-3 text-center font-bold text-blue-600 border-l border-slate-100"
                                x-text="typeAverageOfStudent(s.id, scoreType) !== null ? typeAverageOfStudent(s.id, scoreType) : '–'"></td>
                            <!-- ĐTB học kỳ -->
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
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Cách tính điểm trung bình</h3>
            <p class="text-xs text-slate-600 leading-relaxed">
                Trung bình cộng các bài cùng loại, rồi nhân hệ số:<br>
                <span class="font-bold text-slate-800">
                    TB(Miệng)×1 · TB(15p)×1 · TB(Giữa kỳ)×2 · TB(Cuối kỳ)×3
                </span><br>
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

<!-- MODAL THÊM BÀI KIỂM TRA -->
<div x-show="showAddExamModal" x-cloak style="display: none;"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
     @keydown.escape.window="showAddExamModal = false"
     @click.self="showAddExamModal = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-800">Thêm bài kiểm tra</h3>
            <button @click="showAddExamModal = false" type="button" class="p-1 rounded-lg hover:bg-slate-100 text-slate-400">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form @submit.prevent="submitAddExam">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-1">Loại điểm</label>
                    <p class="text-slate-800 font-semibold" x-text="currentScoreType.label + ' (hệ số ' + currentScoreType.weight + ')'"></p>
                </div>
                <div>
                    <label for="examName" class="block text-sm font-bold text-slate-600 mb-1">Tên bài <span class="text-slate-400 font-normal">(tùy chọn)</span></label>
                    <input type="text" id="examName" x-model="newExamName"
                           :placeholder="currentScoreType.label + ' số ' + (currentScoreExams.length + 1)"
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-medium focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label for="examDate" class="block text-sm font-bold text-slate-600 mb-1">Ngày thi <span class="text-slate-400 font-normal">(tùy chọn)</span></label>
                    <input type="date" id="examDate" x-model="newExamDate"
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-medium focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" @click="showAddExamModal = false"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors">
                    Hủy
                </button>
                <button type="submit" :disabled="addingExam"
                        class="flex-1 py-2.5 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 transition-colors disabled:opacity-50">
                    <span x-show="!addingExam">Tạo bài</span>
                    <span x-show="addingExam">Đang tạo...</span>
                </button>
            </div>
        </form>
    </div>
</div>
