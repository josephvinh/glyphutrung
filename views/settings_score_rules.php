<!-- QUY TẮC ĐIỂM HỌC TẬP -->
<div class="space-y-4" x-data="{
    scoreTypes: [],
    rules: {},
    rankThresholds: { gioi: 8, kha: 6.5, tb: 5, yeu: 0 },
    showAddModal: false,
    newType: { code: '', label: '', short_label: '', weight: 1, max_exams: 3, category: 'homework' },

    async loadRules() {
        const resp = await fetch('/api/rules.php?type=score');
        if (!resp.ok) return;
        const data = await resp.json();
        this.scoreTypes = data.score_types || [];
        this.rules = data.rules || {};
        if (this.rules.rank_thresholds?.rule_value) {
            try { this.rankThresholds = JSON.parse(this.rules.rank_thresholds.rule_value); } catch(e) {}
        }
    },

    keyLabel(k) {
        const map = { gioi: 'Giỏi ≥', kha: 'Khá ≥', tb: 'TB ≥', yeu: 'Yếu <' };
        return map[k] || k;
    },

    async saveType(t) {
        const resp = await fetch('/api/score_types.php?code=' + t.code, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(t)
        });
        if (resp.ok) {
            window.TNTT.toast.success('Đã lưu');
            await this.loadRules();
            window.TNTT.scores?.initScoreRules();
        }
    },

    async deleteType(code) {
        if (!confirm('Xóa loại đầu điểm này?')) return;
        const resp = await fetch('/api/score_types.php?code=' + code, { method: 'DELETE' });
        const d = await resp.json();
        if (d.error) { window.TNTT.toast.error(d.error); return; }
        window.TNTT.toast.success('Đã xóa');
        await this.loadRules();
        window.TNTT.scores?.initScoreRules();
    },

    async addType() {
        if (!this.newType.code || !this.newType.label) {
            window.TNTT.toast.warning('Cần nhập mã và tên.');
            return;
        }
        const resp = await fetch('/api/score_types.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(this.newType)
        });
        const d = await resp.json();
        if (d.error) { window.TNTT.toast.error(d.error); return; }
        window.TNTT.toast.success('Đã thêm');
        this.showAddModal = false;
        this.newType = { code: '', label: '', short_label: '', weight: 1, max_exams: 3, category: 'homework' };
        await this.loadRules();
        window.TNTT.scores?.initScoreRules();
    },

    async saveRankThresholds() {
        const resp = await fetch('/api/rules.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                rule_key: 'rank_thresholds',
                rule_value: JSON.stringify(this.rankThresholds),
                rule_label: 'Ngưỡng xếp loại học lực',
                rule_type: 'threshold'
            })
        });
        if (resp.ok) {
            window.TNTT.toast.success('Đã lưu xếp loại');
            window.TNTT.scores?.initScoreRules();
        }
    }
}" x-init="loadRules()">

    <!-- ĐẦU ĐIỂM -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="list" class="w-4 h-4 text-blue-500"></i>
            Loại đầu điểm
        </h3>

        <!-- Desktop: bảng -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left py-2 text-xs font-bold text-slate-500 uppercase">Loại đầu điểm</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase">Hệ số</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase">Tối đa bài</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase w-16">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="t in scoreTypes" :key="t.code">
                        <tr class="border-b border-slate-50">
                            <td class="py-2.5">
                                <span x-text="t.label" class="font-semibold text-slate-800"></span>
                                <span class="text-xs text-slate-400 ml-1" x-text="'(' + t.code + ')'"></span>
                            </td>
                            <td class="py-2 text-center">
                                <input type="number" min="1" max="10" x-model.number="t.weight"
                                       @change="saveType(t)"
                                       class="w-14 text-center bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 text-center">
                                <input type="number" min="1" max="20" x-model.number="t.max_exams"
                                       @change="saveType(t)"
                                       class="w-14 text-center bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 text-center">
                                <button @click="deleteType(t.code)" type="button"
                                        class="p-2 text-slate-400 hover:text-rose-500 transition-colors">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile: card stack -->
        <div class="md:hidden space-y-2">
            <template x-for="t in scoreTypes" :key="t.code">
                <div class="bg-slate-50 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-slate-800" x-text="t.label"></span>
                        <button @click="deleteType(t.code)" type="button"
                                class="p-1.5 text-slate-400 hover:text-rose-500">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="text-xs text-slate-500 block mb-1">Hệ số</label>
                            <input type="number" min="1" max="10" x-model.number="t.weight"
                                   @change="saveType(t)"
                                   class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div class="flex-1">
                            <label class="text-xs text-slate-500 block mb-1">Tối đa bài</label>
                            <input type="number" min="1" max="20" x-model.number="t.max_exams"
                                   @change="saveType(t)"
                                   class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <button @click="showAddModal = true" type="button"
                class="mt-3 flex items-center gap-2 px-4 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-600 rounded-xl font-bold text-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Thêm loại đầu điểm
        </button>
    </div>

    <!-- XẾP LOẠI HỌC LỰC -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="award" class="w-4 h-4 text-amber-500"></i>
            Xếp loại học lực
        </h3>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <template x-for="[key, val] in Object.entries(rankThresholds)" :key="key">
                <div>
                    <label class="text-xs text-slate-500 block mb-1" x-text="keyLabel(key)"></label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400" x-text="key === 'yeu' ? '<' : '≥'"></span>
                        <input type="number" step="0.1" min="0" max="10" x-model.number="rankThresholds[key]"
                               class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-semibold">
                    </div>
                </div>
            </template>
        </div>

        <button @click="saveRankThresholds()" type="button"
                class="mt-4 w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors">
            <i data-lucide="save" class="w-4 h-4"></i> Lưu xếp loại
        </button>
    </div>

    <!-- MODAL: Thêm loại đầu điểm -->
    <div x-show="showAddModal" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div @click.away="showAddModal = false"
             class="bg-white rounded-2xl w-full max-w-sm p-5 shadow-xl">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Thêm loại đầu điểm</h3>

            <div class="space-y-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Mã (key)</label>
                    <input type="text" x-model="newType.code" placeholder="mieng"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Tên hiển thị</label>
                    <input type="text" x-model="newType.label" placeholder="Miệng"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Viết tắt</label>
                    <input type="text" x-model="newType.short_label" placeholder="M"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Hệ số</label>
                        <input type="number" min="1" max="10" x-model.number="newType.weight" value="1"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Tối đa bài</label>
                        <input type="number" min="1" max="20" x-model.number="newType.max_exams" value="3"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button @click="showAddModal = false" type="button"
                        class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-bold text-sm transition-colors">
                    Hủy
                </button>
                <button @click="addType()" type="button"
                        class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm transition-colors">
                    Thêm
                </button>
            </div>
        </div>
    </div>
</div>
