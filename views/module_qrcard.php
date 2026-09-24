<!-- MÀN IN THẺ QR (một thẻ trong module Thiếu Nhi) -->
<div data-module="qrcard" class="module-panel pt-6 pb-24 relative">

    <!-- Thanh điều hướng gộp (Thiếu nhi) -->
    <?php include __DIR__ . '/partial_children_tabs.php'; ?>

    <!-- Tabs: Cơ bản | Tùy Chỉnh -->
    <div class="mb-4">
        <div class="flex gap-1 bg-slate-100 p-1 rounded-xl w-fit">
            <button type="button" @click="qrTab = 'basic'"
                    class="px-4 py-2 rounded-lg font-bold text-sm transition-colors"
                    :class="qrTab === 'basic' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                <i data-lucide="layout-template" class="w-4 h-4 inline mr-1.5"></i>Cơ Bản
            </button>
            <button type="button" @click="qrTab = 'custom'"
                    class="px-4 py-2 rounded-lg font-bold text-sm transition-colors"
                    :class="qrTab === 'custom' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 inline mr-1.5"></i>Tùy Chỉnh
            </button>
        </div>
    </div>

    <!-- ======================== TAB CƠ BẢN ======================== -->
    <div x-show="qrTab === 'basic'">
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

    <!-- ======================== TAB TÙY CHỈNH ======================== -->
    <div x-show="qrTab === 'custom'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            <!-- CỘT TRÁI: TUỲ CHỌN -->
            <div class="space-y-4">

                <!-- Templates -->
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Templates</h3>
                    <div class="grid grid-cols-5 gap-2">
                        <template x-for="t in [{v:'basic',t:'Basic',icon:'square'},{v:'classic',t:'Classic',icon:'square-stack'},{v:'badge',t:'Badge',icon:'badge'},{v:'compact',t:'Compact',icon:'rows-2'},{v:'minimal',t:'Minimal',icon:'minus'}]" :key="t.v">
                            <button type="button" @click="qrCustom.template=t.v"
                                    class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 transition-all"
                                    :class="qrCustom.template===t.v ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'">
                                <i :data-lucide="t.icon" class="w-5 h-5" :class="qrCustom.template===t.v ? 'text-blue-600' : 'text-slate-400'"></i>
                                <span class="text-xs font-bold" :class="qrCustom.template===t.v ? 'text-blue-600' : 'text-slate-500'" x-text="t.t"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- QR Settings -->
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">QR Settings</h3>

                    <!-- Error Level -->
                    <div class="mb-4">
                        <label class="text-sm text-slate-600 mb-2 block">Error Level</label>
                        <div class="flex gap-1.5">
                            <template x-for="level in [{v:'L',t:'Low',desc:'7%'},{v:'M',t:'Medium',desc:'15%'},{v:'Q',t:'Quartile',desc:'25%'},{v:'H',t:'High',desc:'30%'}]" :key="level.v">
                                <button type="button" @click="qrCustom.errorLevel=level.v"
                                        class="flex-1 px-2 py-2 rounded-lg border text-center transition-colors"
                                        :class="qrCustom.errorLevel===level.v ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'">
                                    <div class="text-xs font-bold" x-text="level.v"></div>
                                    <div class="text-micro opacity-70" x-text="level.desc"></div>
                                </button>
                            </template>
                        </div>
                        <p class="text-micro text-slate-400 mt-1.5">H = cho phép logo ở giữa QR</p>
                    </div>

                    <!-- Logo Position -->
                    <div>
                        <label class="text-sm text-slate-600 mb-2 block">Vị trí Logo</label>
                        <div class="flex gap-1.5">
                            <template x-for="pos in [{v:'top',t:'Trên QR',icon:'arrow-up'},{v:'center',t:'Trong QR',icon:'scan-center'},{v:'bottom',t:'Dưới QR',icon:'arrow-down'}]" :key="pos.v">
                                <button type="button" @click="qrCustom.logoPosition=pos.v"
                                        class="flex-1 px-3 py-2 rounded-lg border text-center transition-colors"
                                        :class="qrCustom.logoPosition===pos.v ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'">
                                    <i :data-lucide="pos.icon" class="w-4 h-4 mx-auto mb-0.5"></i>
                                    <div class="text-xs font-bold" x-text="pos.t"></div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Logo Upload -->
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Logo Tùy Chỉnh</h3>

                    <!-- Upload Area -->
                    <div class="border-2 border-dashed border-slate-200 rounded-xl p-4 text-center hover:border-blue-300 transition-colors"
                         :class="qrLogoUploading ? 'opacity-50 pointer-events-none' : ''"
                         @drop.prevent="qrHandleLogoDrop($event)"
                         @dragover.prevent="$event.dataTransfer.dropEffect='copy'"
                         @click="$refs.logoInput.click()">
                        <input type="file" x-ref="logoInput" @change="qrUploadLogo($event)" accept="image/*" class="hidden">
                        <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto text-slate-400 mb-2"></i>
                        <p class="text-sm text-slate-600">Kéo thả hoặc <span class="text-blue-600 font-medium">chọn file</span></p>
                        <p class="text-micro text-slate-400 mt-1">JPG, PNG, GIF, WebP, SVG • Tối đa 2MB</p>
                    </div>

                    <!-- Logo List -->
                    <div class="mt-3 space-y-2" x-show="qrLogos.length > 0">
                        <template x-for="logo in qrLogos" :key="logo.id">
                            <div class="flex items-center gap-3 p-2 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors"
                                 :class="qrCustom.logoId === logo.id ? 'ring-2 ring-blue-500' : ''"
                                 @click="qrCustom.logoId = qrCustom.logoId === logo.id ? null : logo.id">
                                <img :src="logo.url" class="w-10 h-10 rounded-lg object-contain bg-white border">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-700 truncate" x-text="logo.original_name"></p>
                                    <p class="text-micro text-slate-400" x-text="logo.size_human"></p>
                                </div>
                                <button type="button" @click.stop="qrDeleteLogo(logo.id)"
                                        class="p-1.5 text-slate-400 hover:text-red-500 transition-colors">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- No Logo Selected -->
                    <p x-show="qrCustom.logoId === null && qrLogos.length > 0" class="text-center text-xs text-slate-400 mt-3">Click vào logo để chọn</p>
                    <p x-show="qrLogos.length === 0" class="text-center text-xs text-slate-400 mt-3">Chưa có logo nào</p>
                </div>

                <!-- Presets -->
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Presets</h3>
                        <button type="button" @click="qrShowPresetModal=true"
                                class="text-xs text-blue-600 font-medium hover:underline">
                            + Tạo Preset
                        </button>
                    </div>

                    <!-- Preset List -->
                    <div class="space-y-2" x-show="qrPresets.length > 0">
                        <template x-for="preset in qrPresets" :key="preset.id">
                            <div class="flex items-center gap-3 p-3 rounded-xl border transition-colors cursor-pointer group"
                                 :class="qrCustomActivePresetId === preset.id ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'"
                                 @click="qrLoadPreset(preset)">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-slate-700 flex items-center gap-2">
                                        <span x-text="preset.name"></span>
                                        <span x-show="preset.is_default" class="text-micro bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">Mặc định</span>
                                    </p>
                                    <p class="text-micro text-slate-400" x-text="qrFormatDate(preset.updated_at)"></p>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button type="button" @click.stop="qrSetDefaultPreset(preset.id)"
                                            class="p-1.5 text-slate-400 hover:text-blue-500 transition-colors"
                                            title="Đặt làm mặc định">
                                        <i data-lucide="star" class="w-4 h-4"></i>
                                    </button>
                                    <button type="button" @click.stop="qrDeletePreset(preset.id)"
                                            class="p-1.5 text-slate-400 hover:text-red-500 transition-colors"
                                            title="Xóa preset">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p x-show="qrPresets.length === 0" class="text-center text-xs text-slate-400 py-4">
                        Chưa có preset nào.<br>Điều chỉnh settings rồi lưu lại.
                    </p>
                </div>

                <!-- Export Options -->
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Xuất file</h3>
                    <div class="flex gap-2">
                        <button @click="qrExport('print')"
                                :disabled="qrTheDangLam || qrPrintStudents.length === 0"
                                class="flex-1 bg-blue-600 text-white font-bold py-3 rounded-xl active:scale-[0.98] transition-transform flex justify-center items-center gap-2 disabled:opacity-50">
                            <i data-lucide="printer" class="w-4 h-4"></i>
                            <span>In</span>
                        </button>
                        <button @click="qrExport('png')"
                                :disabled="qrTheDangLam || qrPrintStudents.length === 0"
                                class="flex-1 bg-green-600 text-white font-bold py-3 rounded-xl active:scale-[0.98] transition-transform flex justify-center items-center gap-2 disabled:opacity-50">
                            <i data-lucide="image" class="w-4 h-4"></i>
                            <span>PNG</span>
                        </button>
                        <button @click="qrExport('pdf')"
                                :disabled="qrTheDangLam || qrPrintStudents.length === 0"
                                class="flex-1 bg-red-600 text-white font-bold py-3 rounded-xl active:scale-[0.98] transition-transform flex justify-center items-center gap-2 disabled:opacity-50">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                            <span>PDF</span>
                        </button>
                    </div>
                    <p class="text-micro text-slate-400 mt-2">Đang chọn: <span x-text="qrPrintStudents.length"></span> em</p>
                </div>
            </div>

            <!-- CỘT PHẢI: XEM TRƯỚC + PRESETS -->
            <div class="space-y-4">
                <!-- Preview -->
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Xem trước <span class="text-slate-400 normal-case font-medium">(tối đa 6 thẻ)</span></h3>
                    <div class="bg-slate-50 rounded-2xl p-3 overflow-x-auto" x-html="qrCustomPreviewHtml"></div>
                </div>

                <!-- Current Config Summary -->
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Cấu hình hiện tại</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="bg-slate-50 rounded-lg px-3 py-2">
                            <span class="text-slate-500">Template:</span>
                            <span class="font-bold text-slate-700 ml-1" x-text="qrCustom.template"></span>
                        </div>
                        <div class="bg-slate-50 rounded-lg px-3 py-2">
                            <span class="text-slate-500">Error Level:</span>
                            <span class="font-bold text-slate-700 ml-1" x-text="qrCustom.errorLevel"></span>
                        </div>
                        <div class="bg-slate-50 rounded-lg px-3 py-2">
                            <span class="text-slate-500">Logo:</span>
                            <span class="font-bold text-slate-700 ml-1" x-text="qrCustom.logoId ? 'Có' : 'Không'"></span>
                        </div>
                        <div class="bg-slate-50 rounded-lg px-3 py-2">
                            <span class="text-slate-500">Position:</span>
                            <span class="font-bold text-slate-700 ml-1" x-text="qrCustom.logoPosition"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Tạo/Sửa Preset -->
    <div x-show="qrShowPresetModal" x-cloak
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
         @click.self="qrShowPresetModal=false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">Lưu Preset</h3>
                <button @click="qrShowPresetModal=false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-700 mb-1.5 block">Tên Preset</label>
                    <input type="text" x-model="qrPresetName"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"
                           placeholder="VD: Thẻ đeo Logo GDG">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="qrPresetIsDefault" class="w-4 h-4 rounded">
                    <span>Đặt làm preset mặc định</span>
                </label>
            </div>
            <div class="px-6 py-4 bg-slate-50 flex justify-end gap-2">
                <button @click="qrShowPresetModal=false"
                        class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">
                    Hủy
                </button>
                <button @click="qrSavePreset()"
                        :disabled="!qrPresetName.trim()"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-colors disabled:opacity-50">
                    Lưu Preset
                </button>
            </div>
        </div>
    </div>
</div>
