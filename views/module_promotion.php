<!-- MÀN LÊN LỚP CUỐI NĂM -->
<div data-module="promotion" class="module-panel pt-6 pb-24 relative">
<?php include __DIR__ . '/partial_heavy_loading.php'; ?>

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <h2 class="text-xl font-black text-slate-800 tracking-tight">Lên Lớp</h2>
    </div>

    <!-- Kết quả lần chuyển vừa xong -->
    <div x-show="promoteDone" style="display: none;" class="bg-emerald-50 border border-emerald-100 rounded-card p-5 mb-5">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 shrink-0 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                <i data-lucide="check" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-sm font-black text-emerald-700">Đã chuyển lớp xong</p>
                <p class="text-micro text-emerald-700" x-text="promoteDone ? 'Khối ' + promoteDone.block + ' · ' + promoteDone.at : ''"></p>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div><p class="text-xl font-black text-emerald-700" x-text="promoteDone ? promoteDone.up : 0"></p><p class="text-micro font-bold text-emerald-500 uppercase">Lên lớp</p></div>
            <div><p class="text-xl font-black text-amber-700" x-text="promoteDone ? promoteDone.stay : 0"></p><p class="text-micro font-bold text-emerald-500 uppercase">Ở lại</p></div>
            <div><p class="text-xl font-black text-blue-600" x-text="promoteDone ? promoteDone.graduate : 0"></p><p class="text-micro font-bold text-emerald-500 uppercase">Ra trường</p></div>
        </div>
    </div>

    <div x-show="!canPromote" style="display: none;" class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
        <i data-lucide="eye" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
        <p class="text-micro text-slate-600 leading-snug">Bạn đang ở chế độ <span class="font-bold">chỉ xem</span>. Chỉ Ban Điều Hành mới chuyển lớp được.</p>
    </div>

    <!-- 2. CHỌN KHỐI — cùng kiểu bộ lọc của Danh sách (thanh + nút phễu) -->
    <?php $scopeBlockModel = 'promoteBlock'; include __DIR__ . '/partial_scope_filter.php'; ?>

    <!-- Chưa chọn khối: mời chọn, KHÔNG xét cả đoàn cho nhẹ (giống Danh sách) -->
    <div x-show="promoteBlock === ''" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
        <i data-lucide="filter" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
        <p class="text-slate-600 font-semibold text-base mb-1">Chọn khối để xét lên lớp</p>
        <p class="text-slate-500 text-sm">Việc xét lên lớp làm theo từng khối — bấm nút lọc <i data-lucide="filter" class="inline w-3.5 h-3.5 -mt-0.5"></i> phía trên rồi chọn khối.</p>
    </div>

    <!-- 3. BA BƯỚC -->
    <div x-show="promoteBlock !== ''" style="display: none;" class="bg-white rounded-field p-1.5 shadow-sm border border-slate-100 flex gap-1.5 mb-4">
        <button @click="promoteTab = 'result'" type="button"
                class="flex-1 py-2.5 rounded-2xl font-bold text-micro transition-colors"
                :class="promoteTab === 'result' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            1 · Xét kết quả
        </button>
        <button @click="promoteTab = 'map'" type="button"
                class="flex-1 py-2.5 rounded-2xl font-bold text-micro transition-colors relative"
                :class="promoteTab === 'map' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            2 · Sơ đồ
            <span x-show="unmappedClasses.length > 0" style="display: none;"
                  class="absolute top-1 right-2 w-2 h-2 rounded-full bg-rose-500"></span>
        </button>
        <button @click="promoteTab = 'run'" type="button"
                class="flex-1 py-2.5 rounded-2xl font-bold text-micro transition-colors"
                :class="promoteTab === 'run' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            3 · Chuyển
        </button>
    </div>

    <!-- ==========================================================
         BƯỚC 1: XÉT KẾT QUẢ
         ========================================================== -->
    <div x-show="promoteBlock !== '' && promoteTab === 'result'" style="display: none;">

        <div class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
            <i data-lucide="info" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
            <p class="text-micro text-slate-600 leading-snug">
                Máy xét <span class="font-bold">Đạt</span> khi điểm cả năm ≥ <span class="font-bold" x-text="PASS_SCORE"></span>
                và chuyên cần ≥ <span class="font-bold" x-text="PASS_ATTENDANCE + '%'"></span>.
                Chạm vào một em để đổi lại quyết định nếu cần.
            </p>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
                <p class="text-2xl font-black text-emerald-600" x-text="promoteSummary.up + promoteSummary.graduate"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Đạt</p>
            </div>
            <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
                <p class="text-2xl font-black text-rose-500" x-text="promoteSummary.stay"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Ở lại</p>
            </div>
            <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
                <p class="text-2xl font-black text-amber-700" x-text="promoteSummary.overridden"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Đã sửa tay</p>
            </div>
        </div>

        <div class="space-y-2.5">
            <template x-for="s in promoteStudents" :key="s.id">
                <button @click="canPromote && toggleOverride(s.id)" type="button"
                        style="content-visibility: auto; contain-intrinsic-size: auto 96px;"
                        class="w-full text-left bg-white rounded-field p-4 shadow-sm border flex items-center gap-3 active:scale-[0.98] transition-transform"
                        :class="promoteVerdict(s.id).final === 'len' ? 'border-emerald-200 bg-emerald-50/30' : 'border-rose-200 bg-rose-50/30'">

                    <div class="w-11 h-11 shrink-0 rounded-2xl flex items-center justify-center border"
                         :class="promoteVerdict(s.id).final === 'len' ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-rose-500 border-rose-500 text-white'">
                        <span x-show="promoteVerdict(s.id).final === 'len'" class="inline-flex items-center justify-center"><i data-lucide="arrow-up" class="w-5 h-5"></i></span>
                        <span x-show="promoteVerdict(s.id).final !== 'len'" class="inline-flex items-center justify-center"><i data-lucide="rotate-ccw" class="w-5 h-5"></i></span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-micro font-bold text-blue-600 leading-tight">
                            <span x-text="s.code"></span>
                            <span class="text-slate-300 mx-1">•</span>
                            <span class="text-slate-500 font-medium" x-text="s.className"></span>
                        </p>
                        <p class="text-sm font-black text-slate-800 leading-snug">
                            <span class="font-normal text-slate-500" x-text="s.holyName"></span>
                            <span x-text="s.name"></span>
                        </p>
                        <p class="text-micro font-medium text-slate-500 mt-0.5">
                            ĐTB <span class="font-bold text-slate-600" x-text="promoteVerdict(s.id).avg === null ? '–' : promoteVerdict(s.id).avg"></span>
                            <span class="text-slate-300 mx-1">·</span>
                            chuyên cần <span class="font-bold text-slate-600" x-text="promoteVerdict(s.id).rate + '%'"></span>
                            <span class="text-slate-300 mx-1">·</span>
                            <span x-text="promoteVerdict(s.id).reason"></span>
                        </p>
                    </div>

                    <div class="shrink-0 flex flex-col items-end gap-1">
                        <span class="text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg border"
                              :class="promoteVerdict(s.id).final === 'len' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100'"
                              x-text="promoteVerdict(s.id).final === 'len' ? (nextClassLabel(s.className) === 'Ra trường' ? 'Ra trường' : 'Lên lớp') : 'Ở lại'"></span>
                        <span x-show="promoteVerdict(s.id).overridden" style="display: none;"
                              class="text-micro font-bold uppercase px-1.5 py-0.5 rounded bg-amber-50 text-amber-600 border border-amber-100">Sửa tay</span>
                    </div>
                </button>
            </template>

            <div x-show="promoteStudents.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="users" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Khối này chưa có em nào.</p>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         BƯỚC 2: SƠ ĐỒ LÊN LỚP
         ========================================================== -->
    <div x-show="promoteBlock !== '' && promoteTab === 'map'" style="display: none;">

        <div class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
            <i data-lucide="info" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
            <p class="text-micro text-slate-600 leading-snug">
                Khai báo mỗi lớp lên lớp nào. Lớp cuối chương trình chọn
                <span class="font-bold">Ra trường</span> — các em sẽ chuyển sang tình trạng đã ra trường.
            </p>
        </div>

        <div class="space-y-3">
            <template x-for="cls in promoteClasses" :key="cls.name">
                <div class="bg-white rounded-field p-4 shadow-sm border"
                     :class="cls.nextClass ? 'border-slate-100' : 'border-rose-200 border-dashed'">

                    <div class="flex items-center gap-2 mb-3">
                        <div class="min-w-0">
                            <p class="text-sm font-black text-slate-800 leading-snug" x-text="cls.name"></p>
                            <p class="text-micro font-medium text-slate-500">
                                <span x-text="classSize(cls.name)"></span> em đang học
                            </p>
                        </div>
                        <i data-lucide="arrow-right" class="w-4 h-4 text-slate-300 mx-auto shrink-0"></i>
                        <span class="shrink-0 text-micro font-bold px-2.5 py-1 rounded-lg border"
                              :class="cls.nextClass ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-rose-50 text-rose-600 border-rose-100'"
                              x-text="nextClassLabel(cls.name)"></span>
                    </div>

                    <select :disabled="!canPromote"
                            :value="cls.nextClass || ''"
                            @change="setNextClass(cls.name, $event.target.value)"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-60">
                        <option value="" :selected="!cls.nextClass">-- Chưa khai báo --</option>
                        <option value="RA_TRUONG" :selected="cls.nextClass === 'RA_TRUONG'">Ra trường</option>
                        <template x-for="c in classes" :key="c.name">
                            <option :value="c.name" :selected="cls.nextClass === c.name" x-text="c.name + ' (' + c.block + ')'"></option>
                        </template>
                    </select>
                </div>
            </template>
        </div>
    </div>

    <!-- ==========================================================
         BƯỚC 3: THỰC HIỆN
         ========================================================== -->
    <div x-show="promoteBlock !== '' && promoteTab === 'run'" style="display: none;">

        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-4">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Sắp thực hiện</h3>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Lên lớp
                    </span>
                    <span class="text-lg font-black text-emerald-600" x-text="promoteSummary.up"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Ra trường
                    </span>
                    <span class="text-lg font-black text-blue-600" x-text="promoteSummary.graduate"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Ở lại lớp
                    </span>
                    <span class="text-lg font-black text-rose-500" x-text="promoteSummary.stay"></span>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                    <span class="text-sm font-bold text-slate-700">Tổng số em</span>
                    <span class="text-lg font-black text-slate-800" x-text="promoteSummary.total"></span>
                </div>
            </div>
        </div>

        <!-- Niên khoá đích: chạy thật thì chuyển các em sang NĂM MỚI,
             không ghi đè năm hiện tại, để lịch sử học còn nguyên -->
        <!-- Trước đây khối này ẩn/hiện theo chế độ dữ liệu giả lập.
             Chế độ đó đã gỡ, app luôn chạy với máy chủ, nên hiện thường trực. -->
        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-4">
            <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Chuyển sang niên khoá</label>
            <select x-model="promoteTargetId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">-- Chọn niên khoá đích --</option>
                <template x-for="y in promoteTargetYears" :key="y.id">
                    <option :value="y.id" x-text="y.name"></option>
                </template>
            </select>
            <p x-show="promoteTargetYears.length === 0" style="display: none;" class="text-micro text-rose-600 mt-2 leading-snug">
                Chưa có niên khoá nào khác đang mở. Hãy mở niên khoá mới ở màn <span class="font-bold">Cài đặt → Niên khoá</span> trước.
            </p>
        </div>

        <!-- Chặn khi sơ đồ chưa đủ -->
        <div x-show="unmappedClasses.length > 0" style="display: none;"
             class="bg-rose-50 border border-rose-100 rounded-2xl p-4 mb-4 flex items-start gap-2.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>
            <div>
                <p class="text-xs font-bold text-rose-700 leading-snug">Chưa khai báo lớp kế tiếp cho:</p>
                <p class="text-micro text-rose-600 mt-0.5" x-text="unmappedClasses.join(', ')"></p>
            </div>
        </div>

        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 mb-4 flex items-start gap-2.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"></i>
            <p class="text-micro text-amber-700 leading-snug">
                Thao tác này đổi lớp của toàn bộ các em trong khối và <span class="font-black">không hoàn tác được</span>.
                Nên xuất danh sách ra file trước khi chạy.
            </p>
        </div>

        <button @click="runPromotion()" type="button" :disabled="!canPromote || unmappedClasses.length > 0"
                class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none">
            <i data-lucide="trending-up" class="w-5 h-5 mr-2"></i>
            Chuyển lớp cho khối <span class="ml-1" x-text="promoteBlock"></span>
        </button>
    </div>
</div>
