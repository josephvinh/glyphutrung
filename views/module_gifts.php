<!-- MÀN HÌNH DANH MỤC QUÀ (đổi Mộc lấy quà) -->
<div x-init="loadGifts()" data-module="gifts" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="w-10 h-10 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Danh Mục Quà</h2>
        </div>

        <button x-show="canEditGifts" style="display:none" @click="openCreateGift()" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-xl font-bold text-sm shadow-md shadow-blue-200 flex items-center active:scale-95 transition-transform">
            <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Thêm quà
        </button>
    </div>

    <!-- 2. LƯỚI QUÀ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
        <!-- Loading skeleton -->
        <template x-if="giftsLoading">
            <template x-for="i in 4" :key="'skel-' + i">
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 space-y-3">
                    <div class="skeleton h-20 w-full rounded-2xl"></div>
                    <div class="skeleton h-4 w-full rounded"></div>
                    <div class="skeleton h-4 w-2/3 rounded"></div>
                </div>
            </template>
        </template>

        <template x-for="g in gifts" :key="g.id">
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 relative flex flex-col gap-2"
                 :class="g.status === 'ẩn' ? 'opacity-60' : ''">

                <!-- Ảnh (nếu có) -->
                <div class="w-full aspect-square rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden">
                    <img x-show="g.imageUrl" :src="g.imageUrl" class="w-full h-full object-cover" alt="">
                    <i x-show="!g.imageUrl" data-lucide="gift" class="w-8 h-8 text-slate-300"></i>
                </div>

                <div class="flex-1">
                    <h3 class="text-sm font-black text-slate-800 leading-tight truncate" x-text="g.name"></h3>
                    <p class="text-sm font-bold text-amber-600 mt-1 flex items-center gap-1">
                        <i data-lucide="stamp" class="w-3.5 h-3.5"></i>
                        <span x-text="g.stampCost"></span> Mộc
                    </p>
                    <p class="text-micro text-slate-500 mt-0.5">Tồn kho: <span class="font-bold text-slate-700" x-text="g.stock"></span></p>
                    <span class="inline-block mt-1 text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                          :class="g.status === 'còn bán' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                          x-text="g.status"></span>
                </div>

                <div x-show="canEditGifts" style="display:none" class="flex gap-2 mt-1">
                    <button aria-label="Sửa quà" @click="openEditGift(g)" type="button" class="tap-safe flex-1 h-8 bg-slate-50 rounded-full flex items-center justify-center text-slate-500 active:scale-90 border border-slate-200">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </button>
                    <button aria-label="Xóa quà" @click="deleteGift(g.id)" type="button" class="tap-safe flex-1 h-8 bg-red-50 rounded-full flex items-center justify-center text-red-400 active:scale-90 border border-red-100">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>
        </template>

        <div x-show="!giftsLoading && gifts.length === 0" style="display: none;" class="col-span-full text-center py-10 text-slate-500 text-sm">Chưa có quà nào trong danh mục.</div>
    </div>

    <!-- 3. POPUP THÊM/SỬA QUÀ -->
    <div x-show="showGiftModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-4 md:p-6">
        <div x-show="showGiftModal" x-transition.opacity.duration.300ms @click="showGiftModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showGiftModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[92dvh]">
            <div class="flex justify-center pt-3 pb-2 shrink-0"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-3 border-b border-slate-100 shrink-0">
                <h3 class="text-lg font-black text-slate-800" x-text="isEditingGift ? 'Cập nhật quà' : 'Thêm quà mới'"></h3>
                <button aria-label="Đóng" @click="showGiftModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-5 space-y-4 oversc-contain">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên quà <span class="text-rose-500">*</span></label>
                    <input x-model="giftForm.name" type="text" required placeholder="VD: Bút bi, sổ tay..."
                           aria-required="true"
                           class="w-full bg-slate-50 border rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500"
                           :class="!giftForm.name.trim() && showGiftModal ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Số Mộc đổi <span class="text-rose-500">*</span></label>
                        <input x-model.number="giftForm.stampCost" type="number" min="1" required
                               aria-required="true"
                               class="w-full bg-slate-50 border rounded-xl px-3 py-2.5 text-sm text-slate-800"
                               :class="(!giftForm.stampCost || giftForm.stampCost <= 0) && showGiftModal ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tồn kho</label>
                        <input x-model.number="giftForm.stock" type="number" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    </div>
                </div>
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ảnh quà (URL, không bắt buộc)</label>
                    <input x-model="giftForm.imageUrl" type="text" placeholder="https://..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Trạng thái</label>
                        <select x-model="giftForm.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                            <option value="còn bán">Còn bán</option>
                            <option value="ẩn">Ẩn</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Thứ tự hiển thị</label>
                        <input x-model.number="giftForm.sortOrder" type="number" min="1" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    </div>
                </div>
            </div>
            <div class="p-4 border-t border-slate-100 shrink-0">
                <button @click="saveGift()" type="button" class="w-full bg-blue-600 text-white font-bold py-3 rounded-2xl active:scale-[0.98] shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu Quà
                </button>
            </div>
        </div>
    </div>
</div>
