<!-- VIỆC CẦN LÀM — dùng chung Trang chủ + Cá nhân -->
<div x-show="myTasks.length > 0" style="display:none" class="mb-5">
    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 px-1">Việc cần làm</h3>
    <div class="space-y-2.5">
        <template x-for="t in myTasks" :key="t.key">
            <button @click="openModule(t.go)" type="button"
                    class="w-full flex items-center gap-3 bg-white rounded-card p-3.5 shadow-sm border text-left active:scale-[0.99] transition-transform"
                    :class="t.cls">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" :class="t.cls">
                    <i :data-lucide="t.icon" class="w-4.5 h-4.5"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-black text-slate-800 leading-snug" x-text="t.text"></span>
                    <span class="block text-micro text-slate-500" x-text="t.detail"></span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 ml-auto shrink-0"></i>
            </button>
        </template>
    </div>
</div>
