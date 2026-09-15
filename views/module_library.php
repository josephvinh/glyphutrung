<?php /* THƯ VIỆN TÀI LIỆU — giao diện. Nạp dữ liệu khi mở (x-init="loadLibrary()"). */ ?>
<div x-init="loadLibrary()" class="p-4 sm:p-6 max-w-3xl mx-auto">

    <!-- Đầu trang -->
    <div class="flex items-center mb-4">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')"
                class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-3">
            <i data-lucide="chevron-left" class="w-5 h-5 text-slate-600"></i>
        </button>
        <div class="flex-1 min-w-0">
            <h2 class="text-lg font-black text-slate-800">Thư viện tài liệu</h2>
            <p class="text-micro text-slate-400">Giáo án · đào tạo · bài hát · văn kiện · sinh hoạt</p>
        </div>
        <button @click="openLibUpload()"
                class="shrink-0 bg-blue-600 text-white font-bold text-sm px-4 py-2.5 rounded-2xl active:scale-95 transition-transform shadow-md shadow-blue-200 flex items-center gap-2">
            <i data-lucide="upload" class="w-4 h-4"></i> Đăng
        </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-4">
        <button @click="libSetTab('all')" :class="lib.tab==='all' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                class="px-4 py-2 rounded-full text-sm font-bold active:scale-95 transition-transform">Tất cả</button>
        <button @click="libSetTab('mine')" :class="lib.tab==='mine' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                class="px-4 py-2 rounded-full text-sm font-bold active:scale-95 transition-transform">Của tôi</button>
        <button x-show="libCanEdit" style="display:none" @click="libSetTab('pending')"
                :class="lib.tab==='pending' ? 'bg-amber-500 text-white' : 'bg-white text-amber-600 border border-amber-200'"
                class="px-4 py-2 rounded-full text-sm font-bold active:scale-95 transition-transform flex items-center gap-1.5">
            Chờ duyệt
            <span x-show="lib.pending.length" style="display:none" class="inline-flex items-center justify-center h-5 px-1.5 rounded-full bg-rose-500 text-white text-micro" x-text="lib.pending.length"></span>
        </button>
    </div>

    <!-- ================= TAB: TẤT CẢ ================= -->
    <div x-show="lib.tab==='all'">
        <!-- Tìm + lọc chủ đề -->
        <div class="relative mb-3">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input x-model="lib.q" @input.debounce.400ms="libRefresh()" type="text" placeholder="Tìm theo tiêu đề..."
                   class="w-full bg-white border border-slate-200 rounded-field py-3 pl-11 pr-4 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
        </div>
        <div class="flex flex-wrap gap-2 mb-4">
            <button @click="lib.filter=0; libRefresh()" :class="lib.filter===0 ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                    class="px-3 py-1.5 rounded-full text-xs font-bold active:scale-95 transition-transform">Tất cả chủ đề</button>
            <template x-for="c in lib.categories" :key="c.id">
                <button @click="lib.filter=c.id; libRefresh()" :class="lib.filter===c.id ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                        class="px-3 py-1.5 rounded-full text-xs font-bold active:scale-95 transition-transform" x-text="c.name"></button>
            </template>
        </div>

        <!-- Đang tải -->
        <div x-show="lib.loading" style="display:none" class="text-center py-12 text-slate-400 text-sm">Đang tải…</div>

        <!-- Rỗng -->
        <div x-show="!lib.loading && lib.items.length===0" style="display:none"
             class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="folder-open" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
            <p class="text-slate-600 font-semibold">Chưa có tài liệu nào</p>
            <p class="text-slate-400 text-sm">Bấm "Đăng" để đóng góp tài liệu đầu tiên.</p>
        </div>

        <!-- Lưới thẻ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <template x-for="it in lib.items" :key="it.id">
                <button @click="openLibItem(it)" class="text-left bg-white rounded-card p-4 shadow-sm border border-slate-100 active:scale-[0.98] transition-transform flex gap-3">
                    <div class="w-11 h-11 shrink-0 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                        <i :data-lucide="libIcon(it.ext)" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-800 truncate" x-text="it.title"></p>
                        <p class="text-micro text-slate-400 truncate">
                            <span x-show="it.categoryName" x-text="it.categoryName"></span>
                            <span x-show="it.categoryName"> · </span>
                            <span x-text="it.ext.toUpperCase() + ' · ' + libSizeLabel(it.sizeKb)"></span>
                        </p>
                        <p class="text-micro text-slate-400 truncate mt-0.5" x-show="it.uploaderName" x-text="'Đăng bởi ' + it.uploaderName"></p>
                    </div>
                </button>
            </template>
        </div>
    </div>

    <!-- ================= TAB: CỦA TÔI ================= -->
    <div x-show="lib.tab==='mine'" style="display:none">
        <div x-show="lib.mineItems.length===0" style="display:none" class="text-center py-12 text-slate-400 text-sm">Bạn chưa đăng tài liệu nào.</div>
        <div class="space-y-3">
            <template x-for="it in lib.mineItems" :key="it.id">
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 flex gap-3 items-start">
                    <div class="w-11 h-11 shrink-0 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500">
                        <i :data-lucide="libIcon(it.ext)" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-800 truncate" x-text="it.title"></p>
                        <span class="inline-block mt-1 text-micro font-bold px-2 py-0.5 rounded-full"
                              :class="it.status==='da_duyet' ? 'bg-emerald-50 text-emerald-600' : (it.status==='cho_duyet' ? 'bg-amber-50 text-amber-600' : 'bg-rose-50 text-rose-600')"
                              x-text="libStatusLabel(it.status)"></span>
                        <p x-show="it.status==='tu_choi' && it.rejectReason" style="display:none" class="text-micro text-rose-500 mt-1" x-text="'Lý do: ' + it.rejectReason"></p>
                    </div>
                    <div class="flex flex-col gap-1.5 shrink-0">
                        <button x-show="it.status==='da_duyet'" style="display:none" @click="openLibItem(it)" class="text-xs font-bold text-blue-600 px-2 py-1">Xem</button>
                        <button @click="libDelete(it)" class="text-xs font-bold text-rose-500 px-2 py-1">Gỡ</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- ================= TAB: CHỜ DUYỆT (BĐH) ================= -->
    <div x-show="lib.tab==='pending'" style="display:none">
        <div x-show="lib.pending.length===0" style="display:none" class="text-center py-12 text-slate-400 text-sm">Không có tài liệu nào chờ duyệt. 🎉</div>
        <div class="space-y-3">
            <template x-for="it in lib.pending" :key="it.id">
                <div class="bg-white rounded-card p-4 shadow-sm border border-amber-100">
                    <div class="flex gap-3 items-start">
                        <div class="w-11 h-11 shrink-0 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                            <i :data-lucide="libIcon(it.ext)" class="w-5 h-5"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 truncate" x-text="it.title"></p>
                            <p class="text-micro text-slate-400 truncate" x-text="(it.categoryName ? it.categoryName + ' · ' : '') + it.ext.toUpperCase() + ' · ' + libSizeLabel(it.sizeKb)"></p>
                            <p class="text-micro text-slate-400 truncate" x-show="it.uploaderName" x-text="'Đăng bởi ' + it.uploaderName"></p>
                            <p x-show="it.description" style="display:none" class="text-xs text-slate-500 mt-1" x-text="it.description"></p>
                        </div>
                        <button @click="openLibItem(it)" class="shrink-0 text-xs font-bold text-blue-600 px-2 py-1">Xem trước</button>
                    </div>
                    <div class="flex gap-2 mt-3 pt-3 border-t border-slate-100">
                        <button @click="libApprove(it)" class="flex-1 bg-emerald-500 text-white font-bold text-sm py-2 rounded-xl active:scale-95 transition-transform">Duyệt</button>
                        <button @click="libReject(it)" class="flex-1 bg-white text-rose-600 border border-rose-200 font-bold text-sm py-2 rounded-xl active:scale-95 transition-transform">Từ chối</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- ================= MODAL: ĐĂNG TÀI LIỆU ================= -->
    <div x-show="libUpload.open" style="display:none" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="libUpload.open=false"></div>
        <div class="relative w-full max-w-md bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl p-5 max-h-[92dvh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-black text-slate-800">Đăng tài liệu</h3>
                <button aria-label="Đóng" @click="libUpload.open=false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Tiêu đề</label>
            <input x-model="libUpload.title" type="text" placeholder="VD: Giáo án Khai Tâm bài 5"
                   class="w-full bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm font-semibold text-slate-800 mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500">

            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Chủ đề</label>
            <select x-model="libUpload.categoryId"
                    class="w-full bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm font-semibold text-slate-800 mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <template x-for="c in lib.categories" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>

            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Mô tả (không bắt buộc)</label>
            <textarea x-model="libUpload.description" rows="2" placeholder="Vài dòng giới thiệu…"
                      class="w-full bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm text-slate-700 mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>

            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">File (PDF, ảnh, Word, PowerPoint · tối đa 15MB)</label>
            <label class="flex items-center gap-2 w-full bg-slate-50 border border-dashed border-slate-300 rounded-field py-3 px-3 text-sm text-slate-500 cursor-pointer active:scale-[0.99] transition-transform mb-4">
                <i data-lucide="paperclip" class="w-4 h-4 shrink-0"></i>
                <span class="truncate" x-text="libUpload.fileName || 'Chọn file…'"></span>
                <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.ppt,.pptx" @change="libPickFile($event)">
            </label>

            <button @click="submitLibUpload()" :disabled="libUpload.busy"
                    class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 disabled:opacity-50 flex justify-center items-center gap-2">
                <span x-text="libUpload.busy ? 'Đang gửi…' : 'Gửi (chờ duyệt)'"></span>
            </button>
        </div>
    </div>

    <!-- ================= MODAL: XEM TÀI LIỆU ================= -->
    <div x-show="libViewer.open" style="display:none" class="fixed inset-0 z-[300] flex flex-col bg-slate-900/95">
        <div class="flex items-center gap-3 p-3 text-white shrink-0">
            <button aria-label="Đóng" @click="libViewer.open=false" class="tap-safe w-9 h-9 bg-white/15 rounded-full flex items-center justify-center active:scale-90"><i data-lucide="x" class="w-5 h-5"></i></button>
            <p class="flex-1 min-w-0 truncate font-bold text-sm" x-text="libViewer.item && libViewer.item.title"></p>
            <a x-show="libViewer.item" :href="libViewer.item && (libViewer.item.fileUrl + '&mode=download')"
               class="shrink-0 w-9 h-9 bg-white/15 rounded-full flex items-center justify-center active:scale-90" aria-label="Tải về"><i data-lucide="download" class="w-5 h-5"></i></a>
            <button x-show="libViewer.item && (libCanEdit || lib.tab==='mine')" style="display:none" @click="libDelete(libViewer.item)" class="shrink-0 w-9 h-9 bg-rose-500/80 rounded-full flex items-center justify-center active:scale-90" aria-label="Gỡ"><i data-lucide="trash-2" class="w-5 h-5"></i></button>
        </div>
        <div class="flex-1 min-h-0 bg-white sm:m-4 sm:rounded-2xl overflow-hidden">
            <!-- PDF: nhúng trực tiếp -->
            <template x-if="libViewer.item && libViewer.item.ext==='pdf'">
                <iframe :src="libViewer.item.fileUrl + '&mode=view'" class="w-full h-full border-0"></iframe>
            </template>
            <!-- Ảnh: hiện thẳng -->
            <template x-if="libViewer.item && libViewer.item.viewable && libViewer.item.ext!=='pdf'">
                <div class="w-full h-full overflow-auto flex items-center justify-center p-4 bg-slate-50">
                    <img :src="libViewer.item.fileUrl + '&mode=view'" class="max-w-full h-auto" alt="">
                </div>
            </template>
            <!-- Không xem trực tiếp: mời tải về -->
            <template x-if="libViewer.item && !libViewer.item.viewable">
                <div class="w-full h-full flex flex-col items-center justify-center text-center p-6">
                    <i data-lucide="file-down" class="w-14 h-14 text-slate-300 mb-4"></i>
                    <p class="text-slate-700 font-bold mb-1">Tài liệu này cần tải về để xem</p>
                    <p class="text-slate-400 text-sm mb-4" x-text="libViewer.item && (libViewer.item.ext.toUpperCase() + ' · ' + libSizeLabel(libViewer.item.sizeKb))"></p>
                    <a :href="libViewer.item && (libViewer.item.fileUrl + '&mode=download')"
                       class="bg-blue-600 text-white font-bold px-5 py-3 rounded-2xl active:scale-95 transition-transform inline-flex items-center gap-2"><i data-lucide="download" class="w-4 h-4"></i> Tải về</a>
                </div>
            </template>
        </div>
    </div>

</div>
