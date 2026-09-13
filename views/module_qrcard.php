<!-- MÀN IN THẺ QR (một thẻ trong module Thiếu Nhi) -->
<div data-module="qrcard" class="module-panel pt-6 pb-24 relative">

    <!-- Thanh điều hướng gộp (Thiếu nhi) -->
    <?php include __DIR__ . '/partial_children_tabs.php'; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- CỘT TRÁI: TUỲ CHỌN -->
        <div class="space-y-4">

            <!-- Phạm vi in -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Phạm vi in</h3>
                <div class="flex gap-1.5 mb-3">
                    <template x-for="opt in [{v:'class',t:'Theo lớp'},{v:'block',t:'Theo khối'},{v:'all',t:'Tất cả'}]" :key="opt.v">
                        <button type="button" @click="qrScopeType = opt.v; qrOnScopeType()"
                                class="flex-1 px-3 py-2 rounded-xl font-bold text-xs border transition-colors"
                                :class="qrScopeType === opt.v ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'"
                                x-text="opt.t"></button>
                    </template>
                </div>
                <select x-show="qrScopeType === 'class'" x-model="qrScopeValue" @change="qrSyncSelected()"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    <template x-for="c in availableClasses" :key="c"><option :value="c" x-text="c"></option></template>
                </select>
                <select x-show="qrScopeType === 'block'" style="display:none" x-model="qrScopeValue" @change="qrSyncSelected()"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    <template x-for="b in availableBlocks" :key="b"><option :value="b" x-text="b"></option></template>
                </select>

                <div class="mt-3 border border-slate-100 rounded-xl overflow-hidden">
                    <div class="bg-slate-50 px-3 py-2 flex items-center justify-between border-b border-slate-100">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer">
                            <input type="checkbox" 
                                   :checked="qrSelectedIds.length === qrScopeStudents.length && qrScopeStudents.length > 0"
                                   @change="$event.target.checked ? (qrSelectedIds = qrScopeStudents.map(s => s.id)) : (qrSelectedIds = [])"
                                   class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            Chọn tất cả
                        </label>
                        <span class="text-micro font-bold text-blue-600" x-text="qrSelectedIds.length + ' / ' + qrScopeStudents.length"></span>
                    </div>
                    <div class="max-h-48 overflow-y-auto bg-white p-2 space-y-1">
                        <template x-for="s in qrScopeStudents" :key="s.id">
                            <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors"
                                   :class="qrSelectedIds.includes(s.id) ? 'bg-blue-50/50' : ''">
                                <input type="checkbox" :value="s.id" x-model.number="qrSelectedIds"
                                       class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 shrink-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800 leading-tight">
                                        <span class="font-normal text-slate-500 mr-0.5" x-text="s.holyName"></span>
                                        <span x-text="s.name"></span>
                                    </p>
                                    <p class="text-micro text-slate-500" x-text="s.code + ' • Lớp ' + s.className"></p>
                                </div>
                            </label>
                        </template>
                        <div x-show="qrScopeStudents.length === 0" class="text-center py-4 text-xs text-slate-400 font-medium">Không có em nào trong phạm vi này.</div>
                    </div>
                </div>
            </div>

            <!-- Thông tin trên thẻ -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Thông tin trên thẻ</h3>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="f in [{k:'code',t:'Mã số'},{k:'holyName',t:'Tên thánh'},{k:'name',t:'Họ tên'},{k:'className',t:'Lớp'},{k:'block',t:'Khối'},{k:'birthDate',t:'Ngày sinh'}]" :key="f.k">
                        <label class="flex items-center gap-2 text-sm text-slate-700 bg-slate-50 rounded-xl px-3 py-2 cursor-pointer">
                            <input type="checkbox" x-model="qrFields[f.k]" class="w-4 h-4 rounded">
                            <span x-text="f.t"></span>
                        </label>
                    </template>
                </div>
                <p class="text-micro text-amber-700 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2 mt-3 leading-relaxed">
                    Mã QR luôn chỉ chứa <b>mã số</b> — an toàn nếu thẻ rơi. Các mục bật ở trên là chữ IN trên thẻ; bật Ngày sinh nghĩa là in ngày sinh lên giấy.
                </p>
            </div>

            <!-- Kiểu thẻ & bố cục -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Kiểu thẻ &amp; bố cục</h3>
                <div class="flex gap-1.5 mb-3">
                    <button type="button" @click="qrTemplate='compact'"
                            class="flex-1 px-3 py-2 rounded-xl font-bold text-xs border transition-colors"
                            :class="qrTemplate==='compact' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'">Gọn (cắt dán)</button>
                    <button type="button" @click="qrTemplate='badge'"
                            class="flex-1 px-3 py-2 rounded-xl font-bold text-xs border transition-colors"
                            :class="qrTemplate==='badge' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'">Thẻ đeo</button>
                </div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-slate-700">Số thẻ mỗi hàng</span>
                    <div class="flex gap-1.5">
                        <template x-for="n in [2,3,4]" :key="n">
                            <button type="button" @click="qrPerRow=n"
                                    class="w-8 h-8 rounded-lg font-bold text-xs border transition-colors"
                                    :class="qrPerRow===n ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'"
                                    x-text="n"></button>
                        </template>
                    </div>
                </div>
                <label class="flex items-center justify-between text-sm text-slate-700 py-1.5">
                    <span>Viền nét đứt để cắt</span>
                    <input type="checkbox" x-model="qrCutLines" class="w-4 h-4 rounded">
                </label>
                <label class="flex items-center justify-between text-sm text-slate-700 py-1.5">
                    <span>In tiêu đề đoàn</span>
                    <input type="checkbox" x-model="qrHeader" class="w-4 h-4 rounded">
                </label>
                <input x-show="qrHeader" x-model="qrHeaderText" type="text" placeholder="Tiêu đề đoàn / niên khoá"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 mt-1">
            </div>

            <button @click="inTheQR()" :disabled="qrTheDangLam || qrPrintStudents.length === 0"
                    class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center gap-2 disabled:opacity-50">
                <i data-lucide="printer" class="w-5 h-5"></i>
                <span x-text="qrTheDangLam ? 'Đang tạo…' : 'In thẻ (' + qrPrintStudents.length + ' em)'"></span>
            </button>
        </div>

        <!-- CỘT PHẢI: XEM TRƯỚC -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Xem trước <span class="text-slate-400 normal-case font-medium">(tối đa 6 thẻ)</span></h3>
            <div class="bg-slate-50 rounded-2xl p-3 overflow-x-auto" x-html="qrPreviewHtml"></div>
        </div>
    </div>
</div>
