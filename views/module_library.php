<?php /* THƯ VIỆN TÀI LIỆU — giao diện. Nạp dữ liệu khi mở (x-init="loadLibrary()"). */ ?>
<div x-init="loadLibrary()" class="p-4 sm:p-6 max-w-3xl mx-auto">

    <!-- Đầu trang -->
    <div class="flex items-center mb-4">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')"
                class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-3">
            <i data-lucide="chevron-left" class="w-5 h-5 text-slate-600"></i>
        </button>
        <div class="flex-1 min-w-0">
            <h2 class="text-lg font-black text-slate-800">Thư viện &amp; Sổ tay</h2>
            <p class="text-micro text-slate-500">Tài liệu để tải · bài viết tra cứu nhanh</p>
        </div>
        <!-- Quản chủ đề: chỉ người duyệt được mới thấy -->
        <button x-show="libCanEdit" style="display:none" @click="openCatManager()" aria-label="Quản chủ đề"
                class="shrink-0 w-10 h-10 mr-2 bg-white border border-slate-200 rounded-2xl flex items-center justify-center active:scale-90 transition-transform shadow-sm">
            <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i>
        </button>
        <button @click="openCompose('article')"
                class="shrink-0 bg-blue-600 text-white font-bold text-sm px-4 py-2.5 rounded-2xl active:scale-95 transition-transform shadow-md shadow-blue-200 flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Soạn
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
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
            <input x-model="lib.q" @input.debounce.400ms="libRefresh()" type="text" placeholder="Tìm theo tiêu đề hoặc nội dung..."
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
        <div x-show="lib.loading" style="display:none" class="text-center py-12 text-slate-500 text-sm">Đang tải…</div>

        <!-- Rỗng -->
        <div x-show="!lib.loading && lib.items.length===0" style="display:none"
             class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="folder-open" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
            <p class="text-slate-600 font-semibold">Chưa có gì ở đây</p>
            <p class="text-slate-500 text-sm">Bấm "Soạn" để viết bài tra cứu hoặc đăng tệp đầu tiên.</p>
        </div>

        <!-- Lưới thẻ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full">
            <template x-for="it in lib.items" :key="it.id">
                <button @click="openLibItem(it)" type="button" class="min-w-0 text-left bg-white rounded-card p-4 shadow-sm border border-slate-100 active:scale-[0.98] transition-transform flex gap-3 overflow-hidden">
                    <div class="w-11 h-11 shrink-0 rounded-2xl flex items-center justify-center border"
                         :class="it.type==='article' ? 'bg-blue-50 border-blue-200 text-blue-600' : 'bg-amber-50 border-amber-100 text-amber-600'">
                        <i :data-lucide="libItemIcon(it)" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0" style="max-width:100%;overflow:hidden">
                        <p class="text-sm font-bold text-slate-800 truncate" x-text="it.title"></p>
                        <p class="text-xs text-slate-500 truncate mt-0.5" style="max-width:100%" x-show="it.type==='article' && it.body" x-text="it.body"></p>
                    </div>
                </button>
            </template>
        </div>
    </div>

    <!-- ================= TAB: CỦA TÔI ================= -->
    <div x-show="lib.tab==='mine'" style="display:none">
        <div class="relative mb-3">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
            <input x-model="lib.mineQ" @input.debounce.400ms="libLoadMine()" type="text" placeholder="Tìm trong tài liệu của tôi..."
                   class="w-full bg-white border border-slate-200 rounded-field py-3 pl-11 pr-4 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
        </div>
        <div x-show="lib.mineItems.length===0" style="display:none" class="text-center py-12 text-slate-500 text-sm"
             x-text="lib.mineQ ? 'Không tìm thấy tài liệu nào khớp.' : 'Bạn chưa đăng tài liệu nào.'"></div>
        <div class="space-y-3">
            <template x-for="it in lib.mineItems" :key="it.id">
                <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 flex gap-3 items-start">
                    <div class="w-11 h-11 shrink-0 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500">
                        <i :data-lucide="libItemIcon(it)" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0" style="max-width:100%;overflow:hidden">
                        <p class="text-sm font-bold text-slate-800 truncate" x-text="it.title"></p>
                        <p x-show="it.type==='file' && it.originalName" style="display:none" class="text-micro text-slate-500 truncate" x-text="it.originalName"></p>
                        <span class="inline-block mt-1 text-micro font-bold px-2 py-0.5 rounded-full"
                              :class="it.status==='da_duyet' ? 'bg-emerald-50 text-emerald-600' : (it.status==='cho_duyet' ? 'bg-amber-50 text-amber-600' : 'bg-rose-50 text-rose-600')"
                              x-text="libStatusLabel(it.status)"></span>
                        <p x-show="it.status==='tu_choi' && it.rejectReason" style="display:none" class="text-micro text-rose-500 mt-1" x-text="'Lý do: ' + it.rejectReason"></p>
                    </div>
                    <div class="flex flex-col gap-1.5 shrink-0">
                        <button @click="openLibItem(it)" class="text-xs font-bold text-blue-600 px-2 py-1">Xem</button>
                        <button x-show="it.type==='article'" style="display:none" @click="editArticle(it)" class="text-xs font-bold text-slate-500 px-2 py-1">Sửa</button>
                        <button x-show="it.type==='file'" style="display:none" @click="editFileItem(it)" class="text-xs font-bold text-slate-500 px-2 py-1">Sửa</button>
                        <button @click="libDelete(it)" class="text-xs font-bold text-rose-500 px-2 py-1">Gỡ</button>
                    </div>
                </div>
            </template>
        </div>
        <!-- Xem thêm (phân trang) -->
        <button x-show="lib.hasMore.mine" style="display:none" @click="libMore()" :disabled="lib.loadingMore"
                class="w-full mt-3 py-3 rounded-2xl bg-white border border-slate-200 text-sm font-bold text-slate-600 active:scale-[0.98] transition-transform disabled:opacity-50"
                x-text="lib.loadingMore ? 'Đang tải…' : ('Xem thêm (' + lib.mineItems.length + '/' + lib.total.mine + ')')"></button>
    </div>

    <!-- ================= TAB: CHỜ DUYỆT (BĐH) ================= -->
    <div x-show="lib.tab==='pending'" style="display:none">
        <div class="relative mb-3">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
            <input x-model="lib.pendingQ" @input.debounce.400ms="libLoadPending()" type="text" placeholder="Tìm trong hàng chờ duyệt..."
                   class="w-full bg-white border border-slate-200 rounded-field py-3 pl-11 pr-4 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
        </div>
        <div x-show="lib.pending.length===0" style="display:none" class="text-center py-12 text-slate-500 text-sm"
             x-text="lib.pendingQ ? 'Không có mục nào khớp.' : 'Không có tài liệu nào chờ duyệt. 🎉'"></div>
        <div class="space-y-3">
            <template x-for="it in lib.pending" :key="it.id">
                <div class="bg-white rounded-card p-4 shadow-sm border border-amber-100">
                    <div class="flex gap-3 items-start">
                        <div class="w-11 h-11 shrink-0 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                            <i :data-lucide="libItemIcon(it)" class="w-5 h-5"></i>
                        </div>
                        <div class="flex-1 min-w-0" style="max-width:100%;overflow:hidden">
                            <p class="text-sm font-bold text-slate-800 truncate" x-text="it.title"></p>
                            <p class="text-micro text-slate-500 truncate" x-text="(it.categoryName ? it.categoryName + ' · ' : '') + (it.type==='article' ? 'Sổ tay' : it.ext.toUpperCase() + ' · ' + libSizeLabel(it.sizeKb))"></p>
                            <p class="text-micro text-slate-500 truncate" x-show="it.uploaderName" x-text="'Đăng bởi ' + it.uploaderName"></p>
                            <p x-show="(it.description || it.body)" style="display:none;max-width:100%" class="text-xs text-slate-500 mt-1 truncate" x-text="it.body || it.description"></p>
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
        <!-- Xem thêm (phân trang) -->
        <button x-show="lib.hasMore.pending" style="display:none" @click="libMore()" :disabled="lib.loadingMore"
                class="w-full mt-3 py-3 rounded-2xl bg-white border border-slate-200 text-sm font-bold text-slate-600 active:scale-[0.98] transition-transform disabled:opacity-50"
                x-text="lib.loadingMore ? 'Đang tải…' : ('Xem thêm (' + lib.pending.length + '/' + lib.total.pending + ')')"></button>
    </div>

    <!-- ================= MODAL: SOẠN (bài viết sổ tay / đăng tệp) ================= -->
    <div x-show="libCompose.open" style="display:none" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="libCompose.open=false"></div>
        <div class="relative w-full max-w-md bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl p-5 max-h-[92dvh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-black text-slate-800"
                    x-text="libCompose.id ? (libCompose.mode==='article' ? 'Sửa bài sổ tay' : 'Sửa tài liệu') : 'Soạn mới'"></h3>
                <button aria-label="Đóng" @click="libCompose.open=false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <!-- Chọn chế độ (ẩn khi đang sửa) -->
            <div x-show="!libCompose.id" class="flex gap-2 mb-4">
                <button @click="libCompose.mode='article'" type="button" class="flex-1 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-1.5 active:scale-95 transition-transform"
                        :class="libCompose.mode==='article' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500'">
                    <i data-lucide="scroll-text" class="w-4 h-4"></i> Viết bài
                </button>
                <button @click="libCompose.mode='file'" type="button" class="flex-1 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-1.5 active:scale-95 transition-transform"
                        :class="libCompose.mode==='file' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-500'">
                    <i data-lucide="file-up" class="w-4 h-4"></i> Đăng tệp
                </button>
            </div>

            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Tiêu đề</label>
            <input x-model="libCompose.title" type="text" :placeholder="libCompose.mode==='article' ? 'VD: Kinh Sáng Danh' : 'VD: Giáo án Khai Tâm bài 5'"
                   class="w-full bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm font-semibold text-slate-800 mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500">

            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Chủ đề</label>
            <select x-model="libCompose.categoryId"
                    class="w-full bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm font-semibold text-slate-800 mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <template x-for="c in lib.categories" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>

            <!-- Chế độ BÀI VIẾT: nội dung tra cứu -->
            <template x-if="libCompose.mode==='article'">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Nội dung (đọc thẳng, tra cứu nhanh)</label>
                    <textarea x-model="libCompose.body" rows="8" placeholder="Nhập nội dung: kinh, nghi thức, quy trình, lời bài hát…"
                              class="w-full bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm text-slate-700 mb-4 leading-relaxed focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </template>

            <!-- Chế độ TỆP: mô tả + file -->
            <template x-if="libCompose.mode==='file'">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Mô tả (không bắt buộc)</label>
                    <textarea x-model="libCompose.description" rows="2" placeholder="Vài dòng giới thiệu…"
                              class="w-full bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm text-slate-700 mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    <!-- Đang sửa mục tệp: nói rõ không chọn tệp mới = giữ tệp cũ -->
                    <p x-show="libCompose.hasFile" style="display:none" class="text-micro text-slate-500 mb-2 flex items-center gap-1.5">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        <span>Tệp hiện tại đang được giữ. Chọn tệp mới bên dưới nếu muốn thay.</span>
                    </p>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5"
                           x-text="libCompose.hasFile ? 'Thay tệp (không bắt buộc)' : 'File (PDF, ảnh, Word, PowerPoint · tối đa 15MB)'"></label>
                    <label class="flex items-center gap-2 w-full bg-slate-50 border border-dashed border-slate-300 rounded-field py-3 px-3 text-sm text-slate-500 cursor-pointer active:scale-[0.99] transition-transform mb-4">
                        <i data-lucide="file-up" class="w-4 h-4 shrink-0"></i>
                        <span class="truncate" x-text="libCompose.fileName || (libCompose.hasFile ? 'Giữ tệp hiện tại' : 'Chọn file…')"></span>
                        <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.ppt,.pptx" @change="libPickFile($event)">
                    </label>
                </div>
            </template>

            <button @click="submitCompose()" :disabled="libCompose.busy"
                    class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 disabled:opacity-50 flex justify-center items-center gap-2">
                <span x-text="libCompose.busy ? 'Đang gửi…' : (libCompose.mode==='article' ? (libCompose.id ? 'Lưu' : 'Đăng bài') : (libCompose.id ? 'Lưu thay đổi' : 'Gửi tệp (chờ duyệt)'))"></span>
            </button>
        </div>
    </div>

    <!-- ================= MODAL: TỪ CHỐI (nhập lý do) ================= -->
    <div x-show="libRejectBox.open" style="display:none" class="fixed inset-0 z-[210] flex items-end justify-center sm:items-center sm:p-6">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="libRejectBox.open=false"></div>
        <div class="relative w-full max-w-md bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl p-5">
            <h3 class="text-base font-black text-slate-800 mb-1">Từ chối tài liệu</h3>
            <p class="text-xs text-slate-500 mb-3 truncate" x-text="(libRejectBox.item || {}).title || ''"></p>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Lý do (người đăng sẽ đọc được)</label>
            <textarea x-model="libRejectBox.reason" rows="3" placeholder="VD: Trùng tài liệu đã có, hoặc file mờ không đọc được…"
                      class="w-full bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm text-slate-700 mb-4 focus:outline-none focus:ring-2 focus:ring-rose-500"></textarea>
            <div class="flex gap-2">
                <button @click="libRejectBox.open=false" class="flex-1 bg-slate-100 text-slate-600 font-bold text-sm py-3 rounded-2xl active:scale-95 transition-transform">Hủy</button>
                <button @click="libConfirmReject()" :disabled="libRejectBox.busy"
                        class="flex-1 bg-rose-500 text-white font-bold text-sm py-3 rounded-2xl active:scale-95 transition-transform disabled:opacity-50"
                        x-text="libRejectBox.busy ? 'Đang gửi…' : 'Từ chối'"></button>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: QUẢN CHỦ ĐỀ (BĐH) ================= -->
    <div x-show="libCat.open" style="display:none" class="fixed inset-0 z-[205] flex items-end justify-center sm:items-center sm:p-6">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="libCat.open=false"></div>
        <div class="relative w-full max-w-md bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl p-5 max-h-[92dvh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-black text-slate-800">Quản chủ đề</h3>
                <button aria-label="Đóng" @click="libCat.open=false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <p class="text-xs text-slate-500 mb-3">Ẩn một chủ đề thì tài liệu cũ vẫn còn, chỉ không còn hiện trong bộ lọc.</p>

            <div class="space-y-2 mb-4">
                <template x-for="c in libCat.items" :key="c.id">
                    <div class="flex items-center gap-2 bg-slate-50 rounded-2xl px-3 py-2">
                        <input :value="c.name" @change="libSaveCategory(c.id, $event.target.value)"
                               class="flex-1 min-w-0 bg-white border border-slate-200 rounded-xl py-2 px-3 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="shrink-0 text-micro font-bold px-2 py-0.5 rounded-full"
                              :class="Number(c.is_active)===1 ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-200 text-slate-500'"
                              x-text="Number(c.is_active)===1 ? 'Đang dùng' : 'Đã ẩn'"></span>
                        <button @click="libToggleCategory(c)" :disabled="libCat.busy"
                                class="shrink-0 text-xs font-bold px-2 py-1 disabled:opacity-50"
                                :class="Number(c.is_active)===1 ? 'text-rose-500' : 'text-emerald-600'"
                                x-text="Number(c.is_active)===1 ? 'Ẩn' : 'Hiện'"></button>
                    </div>
                </template>
            </div>

            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Thêm chủ đề mới</label>
            <div class="flex gap-2">
                <input x-model="libCat.newName" @keydown.enter="libSaveCategory(0, libCat.newName)" type="text" placeholder="VD: Nghi thức"
                       class="flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded-field py-3 px-3 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button @click="libSaveCategory(0, libCat.newName)" :disabled="libCat.busy"
                        class="shrink-0 bg-blue-600 text-white font-bold text-sm px-4 rounded-2xl active:scale-95 transition-transform disabled:opacity-50">Thêm</button>
            </div>
        </div>
    </div>
</div>
