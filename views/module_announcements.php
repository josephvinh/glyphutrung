<!-- MÀN HÌNH THÔNG BÁO -->
<div data-module="announcements" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center min-w-0">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Thông Báo</h2>
        </div>

        <button x-show="canManageAnnouncements" @click="openCreateAnnouncement()" class="shrink-0 btn-glass text-white px-4 py-2 rounded-xl font-bold text-sm shadow-md shadow-blue-200 flex items-center active:scale-95 transition-transform">
            <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Phát mới
        </button>
    </div>

    <!-- Ô TÌM NHANH — lọc theo tiêu đề/nội dung, dùng chung cho cả hai khung -->
    <div class="relative mb-4">
        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
        <input x-model="announcementSearch" type="text" placeholder="Tìm thông báo theo tiêu đề, nội dung..."
               aria-label="Tìm thông báo"
               class="w-full bg-slate-50 input-glass border border-slate-200 rounded-xl py-2.5 pl-11 pr-10 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder:font-normal">
        <button aria-label="Xóa ô tìm kiếm" x-show="announcementSearch !== ''" @click="announcementSearch = ''" style="display: none;"
                class="tap-safe absolute right-1 top-1/2 -translate-y-1/2 p-2 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
            <div class="w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </div>
        </button>
    </div>

    <!-- ==========================================================
         KHUNG NHÌN CỦA GLV: chỉ đọc
         ========================================================== -->
    <div x-show="!canManageAnnouncements">
        <div x-show="unreadAnnouncementCount > 0" style="display: none;" class="flex justify-end mb-3">
            <button @click="markAllAnnouncementsRead()" type="button" class="flex items-center gap-1.5 px-3 py-2 bg-white text-slate-500 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-slate-200 shadow-sm">
                <i data-lucide="check-check" class="w-4 h-4"></i> Đánh dấu đã đọc hết
            </button>
        </div>

        <div class="space-y-4 xl:space-y-0 xl:grid xl:grid-cols-2 xl:gap-4 xl:items-start">
            <template x-for="a in filteredAnnouncements" :key="a.id">
                <div @click="markAnnouncementRead(a)"
                     style="content-visibility: auto; contain-intrinsic-size: auto 180px;"
                     class="glass-card rounded-card p-5 shadow-sm border transition-colors"
                     :class="readAnnouncements.includes(a.id) ? 'border-slate-100' : 'border-blue-200 border-l-4 border-l-blue-600'">

                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-10 h-10 shrink-0 rounded-2xl flex items-center justify-center border"
                             :class="announcementLevelClass(a.level)">
                            <span x-show="a.level === 'khẩn'" class="inline-flex items-center justify-center"><i data-lucide="siren" class="w-5 h-5"></i></span>
                            <span x-show="a.level === 'quan trọng'" class="inline-flex items-center justify-center"><i data-lucide="alert-triangle" class="w-5 h-5"></i></span>
                            <span x-show="a.level === 'thường'" class="inline-flex items-center justify-center"><i data-lucide="megaphone" class="w-5 h-5"></i></span>
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

                    <!-- Buổi họp: giờ + địa điểm + trả lời tham gia -->
                    <template x-if="a.isMeeting">
                        <div class="bg-blue-50/70 border border-blue-100 rounded-2xl p-3 mb-3" @click.stop>
                            <p class="text-micro font-bold text-blue-700 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="flex items-center gap-1"><i data-lucide="calendar-check" class="w-3.5 h-3.5"></i><span x-text="a.meetingAt"></span></span>
                                <span x-show="a.meetingPlace" style="display:none" class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i><span x-text="a.meetingPlace"></span></span>
                            </p>
                            <div class="flex gap-2 mt-2.5">
                                <button @click.stop="rsvpMeeting(a, 'tham gia')" type="button"
                                        class="flex items-center gap-1 px-3 py-1.5 rounded-xl font-bold text-micro border active:scale-95 transition-transform"
                                        :class="a.myRsvp === 'tham gia' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-emerald-600 border-emerald-200'">
                                    <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Tham gia
                                </button>
                                <button @click.stop="rsvpMeeting(a, 'không tham gia')" type="button"
                                        class="flex items-center gap-1 px-3 py-1.5 rounded-xl font-bold text-micro border active:scale-95 transition-transform"
                                        :class="a.myRsvp === 'không tham gia' ? 'bg-rose-500 text-white border-rose-500' : 'bg-white text-rose-500 border-rose-200'">
                                    <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Không
                                </button>
                            </div>
                        </div>
                    </template>

                    <div class="flex items-center justify-between text-micro text-slate-500 border-t border-slate-100 pt-3">
                        <span>Phát bởi <span class="font-bold text-slate-500" x-text="a.createdBy"></span></span>
                        <span x-text="a.publishedAt"></span>
                    </div>
                </div>
            </template>

            <div x-show="filteredAnnouncements.length === 0" style="display: none;" class="text-center py-12 glass-card rounded-card border border-slate-100 border-dashed">
                <i data-lucide="bell-ring" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
                <p class="text-slate-600 font-semibold text-base mb-1" x-text="announcementSearch ? 'Không tìm thấy thông báo phù hợp' : 'Chưa có thông báo nào'"></p>
                <p class="text-slate-500 text-sm" x-text="announcementSearch ? 'Thử từ khóa khác hoặc xóa ô tìm' : 'Thông báo mới sẽ xuất hiện ở đây'"></p>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         KHUNG NHÌN CỦA BAN ĐIỀU HÀNH: quản lý
         Thấy cả bản nháp và bản đã hết hạn.
         ========================================================== -->
    <div x-show="canManageAnnouncements" style="display: none;">
        <div class="space-y-4 xl:space-y-0 xl:grid xl:grid-cols-2 xl:gap-4 xl:items-start">
            <template x-for="a in filteredManageableAnnouncements" :key="a.id">
                <div style="content-visibility: auto; contain-intrinsic-size: auto 240px;"
                     class="glass-card rounded-card p-5 shadow-sm border"
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
                                <span x-show="a.isMeeting" style="display: none;" class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-blue-50 text-blue-600">Buổi họp</span>
                            </div>
                            <h3 class="text-base font-black text-slate-800 leading-snug" x-text="a.title"></h3>
                        </div>

                        <div x-show="canEditAnnouncement(a)" class="flex flex-col gap-2 shrink-0">
                            <button aria-label="Sửa thông báo" @click="openEditAnnouncement(a)" class="tap-safe w-8 h-8 bg-slate-50 rounded-full flex items-center justify-center text-slate-500 active:scale-90 border border-slate-200">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <button aria-label="Xóa thông báo" @click="deleteAnnouncement(a.id)" class="tap-safe w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-400 active:scale-90 border border-rose-100">
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
                        <div x-show="a.isMeeting" style="display: none;" class="flex items-center justify-between text-xs border-t border-slate-200 pt-2">
                            <span class="font-semibold text-blue-600">Buổi họp lúc</span>
                            <span class="font-bold text-blue-700" x-text="a.meetingAt + (a.meetingPlace ? ' · ' + a.meetingPlace : '')"></span>
                        </div>
                    </div>

                    <!-- Phát / thu hồi -->
                    <button x-show="canEditAnnouncement(a)" @click="toggleAnnouncementStatus(a)"
                            class="w-full py-3 rounded-2xl font-bold text-sm active:scale-[0.98] transition-transform flex items-center justify-center gap-2 border"
                            :class="a.status === 'đã phát' ? 'bg-slate-100 text-slate-600 border-slate-200' : 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-200'">
                        <span x-show="a.status === 'đã phát'" class="inline-flex items-center justify-center"><i data-lucide="undo-2" class="w-4 h-4"></i></span>
                        <span x-show="a.status !== 'đã phát'" class="inline-flex items-center justify-center"><i data-lucide="send" class="w-4 h-4"></i></span>
                        <span x-text="a.status === 'đã phát' ? 'Thu hồi về nháp' : 'Phát thông báo'"></span>
                    </button>

                    <!-- Kết quả họp: người phát bấm xem ai tham gia / không / chưa trả lời -->
                    <button x-show="a.isMeeting && a.status === 'đã phát'" style="display: none;" @click="openMeetingResult(a)"
                            class="w-full mt-2 py-2.5 rounded-2xl font-bold text-xs border border-blue-200 text-blue-700 bg-blue-50 flex items-center justify-center gap-2 active:scale-95 transition-transform">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        Kết quả họp · <span x-text="a.rsvpYes"></span> tham gia / <span x-text="a.rsvpNo"></span> vắng
                    </button>
                </div>
            </template>

            <div x-show="filteredManageableAnnouncements.length === 0" style="display: none;" class="text-center py-12 glass-card rounded-card border border-slate-100 border-dashed">
                <i data-lucide="megaphone" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm" x-text="announcementSearch ? 'Không tìm thấy thông báo phù hợp.' : 'Chưa có thông báo nào.'"></p>
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
                    <input x-model="announcementForm.title" type="text" placeholder="VD: Họp GLV toàn đoàn..." class="w-full bg-slate-50 input-glass border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
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
                                    :class="announcementForm.level === lv ? announcementLevelClass(lv) + ' ring-2 ring-offset-1 ring-slate-300' : 'bg-slate-50 text-slate-500 border-slate-200'"
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
                    <select x-model="announcementForm.audienceValue" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">-- Chọn khối --</option>
                        <template x-for="b in audienceBlockChoices" :key="b">
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

                <!-- Buổi họp: vào lịch cá nhân người nhận + hỏi tham gia -->
                <div class="border-t border-slate-100 pt-4">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input x-model="announcementForm.isMeeting" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-bold text-slate-700">Đây là buổi họp</span>
                        <span class="text-micro text-slate-500">(tự vào lịch + hỏi tham gia)</span>
                    </label>
                    <div x-show="announcementForm.isMeeting" x-collapse style="display: none;" class="mt-3 space-y-3">
                        <div>
                            <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Thời gian họp</label>
                            <input x-model="announcementForm.meetingAt" type="datetime-local" min="2000-01-01T00:00" max="2100-12-31T23:59" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Địa điểm (tuỳ chọn)</label>
                            <input x-model="announcementForm.meetingPlace" type="text" placeholder="VD: Hội trường giáo xứ" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 pb-6">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Hạn hiển thị</label>
                        <input x-model="announcementForm.expiresAt" type="date" min="2000-01-01" max="2100-12-31" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
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
                <button @click="saveAnnouncement()" type="button" class="w-full btn-glass text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="send" class="w-5 h-5 mr-2"></i>
                    <span x-text="announcementForm.status === 'nháp' ? 'Lưu nháp' : 'Phát thông báo'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         POPUP KẾT QUẢ HỌP (người phát xem ai tham gia)
         ========================================================== -->
    <div x-show="showMeetingResult" style="display: none;" class="fixed inset-0 z-[210] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showMeetingResult" x-transition.opacity.duration.300ms @click="showMeetingResult = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showMeetingResult" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl max-h-[85dvh] flex flex-col overflow-hidden">

            <div class="flex justify-center pt-3 pb-2 bg-white"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white">
                <div class="min-w-0">
                    <h3 class="text-lg font-black text-slate-800 leading-tight">Kết quả họp</h3>
                    <p class="text-micro text-slate-500 truncate" x-text="meetingResult.title"></p>
                </div>
                <button aria-label="Đóng" @click="showMeetingResult = false" class="tap-safe w-8 h-8 shrink-0 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <div class="grid grid-cols-3 gap-3 p-5 pb-3">
                <div class="bg-emerald-50 rounded-2xl p-3 text-center border border-emerald-100">
                    <p class="text-2xl font-black text-emerald-600" x-text="meetingResult.yes"></p>
                    <p class="text-micro font-bold text-emerald-500 uppercase tracking-wide">Tham gia</p>
                </div>
                <div class="bg-rose-50 rounded-2xl p-3 text-center border border-rose-100">
                    <p class="text-2xl font-black text-rose-500" x-text="meetingResult.no"></p>
                    <p class="text-micro font-bold text-rose-400 uppercase tracking-wide">Không</p>
                </div>
                <div class="bg-slate-50 rounded-2xl p-3 text-center border border-slate-200">
                    <p class="text-2xl font-black text-slate-500" x-text="meetingResult.pending"></p>
                    <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Chưa trả lời</p>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 pb-6">
                <div x-show="meetingResultBusy" style="display: none;" class="text-center py-8 text-slate-500 text-sm">Đang tải…</div>
                <div class="divide-y divide-slate-100">
                    <template x-for="r in meetingResult.rows" :key="r.name">
                        <div class="flex items-center justify-between py-2.5">
                            <span class="text-sm font-semibold text-slate-700" x-text="r.name"></span>
                            <span class="text-micro font-bold uppercase tracking-wide" :class="rsvpRowClass(r.status)" x-text="r.status"></span>
                        </div>
                    </template>
                </div>
                <div x-show="!meetingResultBusy && meetingResult.rows.length === 0" style="display: none;" class="text-center py-8 text-slate-500 text-sm">
                    Chưa có ai trong phạm vi để hiển thị.
                </div>
            </div>
        </div>
    </div>
</div>
