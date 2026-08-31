<!-- MÀN HÌNH THÔNG BÁO -->
<div x-show="currentModule === 'announcements'" style="display: none;" class="module-panel pt-6 pb-10 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center min-w-0">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Thông Báo</h2>
        </div>

        <button x-show="canManageAnnouncements" @click="openCreateAnnouncement()" class="shrink-0 bg-blue-600 text-white px-4 py-2 rounded-xl font-bold text-sm shadow-md shadow-blue-200 flex items-center active:scale-95 transition-transform">
            <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Phát mới
        </button>
    </div>

    <!-- ==========================================================
         KHUNG NHÌN CỦA GLV: chỉ đọc
         ========================================================== -->
    <div x-show="!canManageAnnouncements">
        <div x-show="unreadAnnouncementCount > 0" style="display: none;" class="flex justify-end mb-3">
            <button @click="markAllAnnouncementsRead()" class="flex items-center gap-1.5 px-3 py-2 bg-white text-slate-500 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-slate-200 shadow-sm">
                <i data-lucide="check-check" class="w-4 h-4"></i> Đánh dấu đã đọc hết
            </button>
        </div>

        <div class="space-y-4 xl:space-y-0 xl:grid xl:grid-cols-2 xl:gap-4 xl:items-start">
            <template x-for="a in visibleAnnouncements" :key="a.id">
                <div @click="markAnnouncementRead(a)"
                     style="content-visibility: auto; contain-intrinsic-size: auto 180px;"
                     class="bg-white rounded-card p-5 shadow-sm border transition-colors"
                     :class="readAnnouncements.includes(a.id) ? 'border-slate-100' : 'border-blue-200 border-l-4 border-l-blue-600'">

                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-10 h-10 shrink-0 rounded-2xl flex items-center justify-center border"
                             :class="announcementLevelClass(a.level)">
                            <i x-show="a.level === 'khẩn'" data-lucide="siren" class="w-5 h-5"></i>
                            <i x-show="a.level === 'quan trọng'" data-lucide="alert-triangle" class="w-5 h-5"></i>
                            <i x-show="a.level === 'thường'" data-lucide="megaphone" class="w-5 h-5"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                      :class="announcementLevelClass(a.level)" x-text="a.level"></span>
                                <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-500"
                                      x-text="audienceLabel(a)"></span>
                                <span x-show="!readAnnouncements.includes(a.id)" style="display: none;" class="w-2 h-2 rounded-full bg-blue-600"></span>
                            </div>
                            <h3 class="text-base font-black text-slate-800 leading-snug" x-text="a.title"></h3>
                        </div>
                    </div>

                    <p class="text-sm text-slate-600 leading-relaxed mb-3" x-text="a.body"></p>

                    <div class="flex items-center justify-between text-micro text-slate-500 border-t border-slate-100 pt-3">
                        <span>Phát bởi <span class="font-bold text-slate-500" x-text="a.createdBy"></span></span>
                        <span x-text="a.publishedAt"></span>
                    </div>
                </div>
            </template>

            <div x-show="visibleAnnouncements.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="bell-off" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Chưa có thông báo nào.</p>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         KHUNG NHÌN CỦA BAN ĐIỀU HÀNH: quản lý
         Thấy cả bản nháp và bản đã hết hạn.
         ========================================================== -->
    <div x-show="canManageAnnouncements" style="display: none;">
        <div class="space-y-4 xl:space-y-0 xl:grid xl:grid-cols-2 xl:gap-4 xl:items-start">
            <template x-for="a in manageableAnnouncements" :key="a.id">
                <div style="content-visibility: auto; contain-intrinsic-size: auto 240px;"
                     class="bg-white rounded-card p-5 shadow-sm border"
                     :class="a.status === 'nháp' ? 'border-slate-200 border-dashed' : 'border-slate-100'">

                    <div class="flex justify-between items-start gap-3 mb-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                      :class="announcementLevelClass(a.level)" x-text="a.level"></span>
                                <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-500"
                                      x-text="audienceLabel(a)"></span>
                                <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                                      :class="a.status === 'đã phát' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                                      x-text="a.status"></span>
                                <span x-show="isAnnouncementExpired(a)" style="display: none;" class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-rose-50 text-rose-600">Hết hạn</span>
                            </div>
                            <h3 class="text-base font-black text-slate-800 leading-snug" x-text="a.title"></h3>
                        </div>

                        <div x-show="canEditAnnouncement(a)" class="flex flex-col gap-2 shrink-0">
                            <button aria-label="Sửa thông báo" @click="openEditAnnouncement(a)" class="tap-safe w-8 h-8 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <button aria-label="Xóa thông báo" @click="deleteAnnouncement(a.id)" class="tap-safe w-8 h-8 bg-red-50 rounded-full flex items-center justify-center text-red-400 active:scale-90 border border-red-100">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>

                    <p class="text-sm text-slate-600 leading-relaxed mb-3" x-text="a.body"></p>

                    <div class="bg-slate-50 rounded-2xl p-3.5 space-y-2 mb-4">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-500">Thời điểm phát</span>
                            <span class="font-bold text-slate-700" x-text="a.publishedAt || 'Chưa phát'"></span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-500">Hạn hiển thị</span>
                            <span class="font-bold text-slate-700" x-text="a.expiresAt ? formatDate(a.expiresAt) : 'Không giới hạn'"></span>
                        </div>
                    </div>

                    <!-- Phát / thu hồi -->
                    <button x-show="canEditAnnouncement(a)" @click="toggleAnnouncementStatus(a)"
                            class="w-full py-3 rounded-2xl font-bold text-sm active:scale-[0.98] transition-transform flex items-center justify-center gap-2 border"
                            :class="a.status === 'đã phát' ? 'bg-slate-100 text-slate-600 border-slate-200' : 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-200'">
                        <i x-show="a.status === 'đã phát'" data-lucide="undo-2" class="w-4 h-4"></i>
                        <i x-show="a.status !== 'đã phát'" data-lucide="send" class="w-4 h-4"></i>
                        <span x-text="a.status === 'đã phát' ? 'Thu hồi về nháp' : 'Phát thông báo'"></span>
                    </button>
                </div>
            </template>

            <div x-show="manageableAnnouncements.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="megaphone" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Chưa có thông báo nào.</p>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         POPUP SOẠN THÔNG BÁO
         ========================================================== -->
    <div x-show="showAnnouncementModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showAnnouncementModal" x-transition.opacity.duration.300ms @click="showAnnouncementModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showAnnouncementModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl h-[88dvh] sm:h-[80dvh] flex flex-col overflow-hidden">

            <div class="flex justify-center pt-3 pb-2 bg-white"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white">
                <h3 class="text-lg font-black text-slate-800" x-text="isEditingAnnouncement ? 'Sửa thông báo' : 'Phát thông báo mới'"></h3>
                <button aria-label="Đóng" @click="showAnnouncementModal = false" class="tap-safe w-8 h-8 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <input type="hidden" name="_csrf" :value="window.TNTT.csrfToken">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tiêu đề</label>
                    <input x-model="announcementForm.title" type="text" placeholder="VD: Họp GLV toàn đoàn..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Nội dung</label>
                    <textarea x-model="announcementForm.body" rows="5" placeholder="Ghi rõ thời gian, địa điểm, việc cần chuẩn bị..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
                </div>

                <!-- Mức độ -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Mức độ</label>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="lv in announcementLevels" :key="lv">
                            <button @click="announcementForm.level = lv" type="button"
                                    class="py-2.5 rounded-xl font-bold text-xs border transition-colors capitalize"
                                    :class="announcementForm.level === lv ? announcementLevelClass(lv) + ' ring-2 ring-offset-1 ring-slate-300' : 'bg-slate-50 text-slate-400 border-slate-200'"
                                    x-text="lv"></button>
                        </template>
                    </div>
                </div>

                <!-- Đối tượng nhận -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Gửi tới</label>
                    <select x-model="announcementForm.audienceType" :disabled="audienceLocked" class="w-full disabled:opacity-60 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="toàn đoàn">Toàn đoàn</option>
                        <option value="khối">Một khối</option>
                        <option value="lớp">Một lớp</option>
                    </select>
                </div>

                <div x-show="announcementForm.audienceType === 'khối'" style="display: none;">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Chọn khối</label>
                    <select x-model="announcementForm.audienceValue" :disabled="audienceLocked" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">-- Chọn khối --</option>
                        <template x-for="b in blocks" :key="b">
                            <option :value="b" x-text="b"></option>
                        </template>
                    </select>
                </div>

                <div x-show="announcementForm.audienceType === 'lớp'" style="display: none;">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Chọn lớp</label>
                    <select x-model="announcementForm.audienceValue" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">-- Chọn lớp --</option>
                        <template x-for="c in classes" :key="c.name">
                            <option :value="c.name" x-text="c.name"></option>
                        </template>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 pb-6">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Hạn hiển thị</label>
                        <input x-model="announcementForm.expiresAt" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <p class="text-micro text-slate-500 mt-1 ml-1">Để trống là không giới hạn</p>
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Trạng thái</label>
                        <select x-model="announcementForm.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="đã phát">Phát ngay</option>
                            <option value="nháp">Lưu nháp</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100 bg-white">
                <button @click="saveAnnouncement()" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="send" class="w-5 h-5 mr-2"></i>
                    <span x-text="announcementForm.status === 'nháp' ? 'Lưu nháp' : 'Phát thông báo'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
