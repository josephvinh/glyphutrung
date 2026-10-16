<!-- QUY TẮC CHUYÊN CẦN -->
<div class="space-y-4" x-data="{
    termPrograms: [],
    thresholds: [],
    selectedTermId: null,

    async loadThresholds() {
        const resp = await fetch('/api/attendance_thresholds.php');
        if (resp.ok) this.thresholds = await resp.json();
    },

    async loadTermPrograms() {
        if (!this.selectedTermId) return;
        const resp = await fetch('/api/term_programs.php?term_id=' + this.selectedTermId);
        if (resp.ok) this.termPrograms = await resp.json();
    },

    async toggleProgram(p) {
        p.is_active = !p.is_active;
        await fetch('/api/term_programs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                term_id: this.selectedTermId,
                program_id: p.id,
                is_active: p.is_active
            })
        });
    },

    async addThreshold() {
        const label = prompt('Nhãn mốc (VD: A, B, C):');
        if (!label) return;
        const minPercent = parseFloat(prompt('Ngưỡng tối thiểu % (VD: 90):') || '0');
        const resp = await fetch('/api/attendance_thresholds.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ label, min_percent: minPercent, description: '' })
        });
        if (resp.ok) {
            window.TNTT.toast.success('Đã thêm');
            await this.loadThresholds();
            window.TNTT.scores?.initAttendanceConfig();
        }
    },

    async saveThreshold(t) {
        const resp = await fetch('/api/attendance_thresholds.php?id=' + t.id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(t)
        });
        if (resp.ok) {
            window.TNTT.toast.success('Đã lưu');
            window.TNTT.scores?.initAttendanceConfig();
        }
    },

    async deleteThreshold(t) {
        if (!confirm('Xóa mốc ' + t.label + '?')) return;
        const resp = await fetch('/api/attendance_thresholds.php?id=' + t.id, { method: 'DELETE' });
        if (resp.ok) {
            window.TNTT.toast.success('Đã xóa');
            await this.loadThresholds();
            window.TNTT.scores?.initAttendanceConfig();
        }
    }
}" x-init="
    selectedTermId = window.TNTT?.currentTerm?.id || window.TNTT?.terms?.[0]?.id;
    loadThresholds();
    if (selectedTermId) loadTermPrograms();
">

    <!-- CHƯƠNG TRÌNH THEO HỌC KỲ -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="calendar" class="w-4 h-4 text-green-500"></i>
            Chương trình tính điểm theo học kỳ
        </h3>

        <!-- Chọn HK -->
        <div class="mb-3">
            <select x-model.number="selectedTermId" @change="loadTermPrograms()"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold">
                <template x-for="t in (window.TNTT?.terms || [])" :key="t.id">
                    <option :value="t.id" x-text="t.name"></option>
                </template>
            </select>
        </div>

        <!-- Danh sách programs -->
        <div class="space-y-2">
            <template x-for="p in termPrograms" :key="p.id">
                <label class="flex items-center gap-3 p-3 bg-slate-50 hover:bg-slate-100 rounded-xl cursor-pointer transition-colors"
                       :class="p.is_active ? 'border border-blue-200' : 'border border-transparent'">
                    <input type="checkbox" x-model="p.is_active"
                           @change="toggleProgram(p)"
                           class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <div class="flex-1">
                        <span class="font-semibold text-slate-800" x-text="p.name"></span>
                        <span class="text-xs text-slate-500 ml-2" x-text="'(' + p.expected_sessions + ' buổi)'"></span>
                    </div>
                </label>
            </template>
            <div x-show="termPrograms.length === 0" style="display:none;"
                 class="text-center py-4 text-sm text-slate-500">
                Chưa có chương trình nào cho học kỳ này
            </div>
        </div>
    </div>

    <!-- CÁC MỐC XẾP LOẠI -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="sliders" class="w-4 h-4 text-purple-500"></i>
            Các mốc xếp loại chuyên cần
        </h3>

        <!-- Desktop: bảng -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left py-2 text-xs font-bold text-slate-500 uppercase">Nhãn</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase">Ngưỡng (%)</th>
                        <th class="text-left py-2 text-xs font-bold text-slate-500 uppercase">Mô tả</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase w-24">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="t in thresholds" :key="t.id">
                        <tr class="border-b border-slate-50">
                            <td class="py-2">
                                <input type="text" x-model="t.label"
                                       @change="saveThreshold(t)"
                                       class="w-16 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-bold">
                            </td>
                            <td class="py-2 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="text-slate-400">≥</span>
                                    <input type="number" min="0" max="100" step="0.5" x-model.number="t.min_percent"
                                           @change="saveThreshold(t)"
                                           class="w-16 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm text-center">
                                    <span class="text-slate-400">%</span>
                                </div>
                            </td>
                            <td class="py-2">
                                <input type="text" x-model="t.description" placeholder="Mô tả..."
                                       @change="saveThreshold(t)"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm">
                            </td>
                            <td class="py-2 text-center">
                                <button @click="deleteThreshold(t)" type="button"
                                        class="p-1.5 text-slate-400 hover:text-rose-500">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile: card stack -->
        <div class="md:hidden space-y-3">
            <template x-for="t in thresholds" :key="t.id">
                <div class="bg-slate-50 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <input type="text" x-model="t.label"
                               class="w-16 bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold">
                        <button @click="deleteThreshold(t)" type="button"
                                class="p-2 text-slate-400 hover:text-rose-500">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm text-slate-500">Ngưỡng:</span>
                        <span class="text-sm text-slate-400">≥</span>
                        <input type="number" min="0" max="100" step="0.5" x-model.number="t.min_percent"
                               @change="saveThreshold(t)"
                               class="w-20 bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <span class="text-sm text-slate-500">%</span>
                    </div>
                    <input type="text" x-model="t.description" placeholder="Mô tả..."
                           class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </template>
        </div>

        <button @click="addThreshold()" type="button"
                class="mt-3 flex items-center gap-2 px-4 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-600 rounded-xl font-bold text-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Thêm mốc
        </button>
    </div>
</div>
