<!-- ==========================================================
     APP CENTER: LƯỚI MENU CHỨC NĂNG
     Sinh ra từ moduleDefs, nên phân quyền + bảo trì tự áp dụng.
     - Một lưới PHẲNG, gộp mọi khu, không tiêu đề nhóm (visibleFlat()).
     - Ẩn trên máy tính (.home-fn-grid trong app.css) vì đã có thanh bên.
     ========================================================== -->
<div class="mb-10 space-y-5">

    <!-- Việc cần làm KHÔNG hiện thành khối ở Trang chủ nữa. Thay vào đó mỗi
         việc nhắc bằng CHẤM SỐ nhỏ trên icon chức năng tương ứng (điểm danh,
         xin phép, thông báo, Thiếu Nhi=phiếu liên lạc, lịch) — xem moduleBadge(). -->

    <!-- BẢNG THI ĐUA (trang công khai, chỉ xem) — mở tab mới để chia sẻ cho các em -->
    <a href="bxh.php" target="_blank" rel="noopener"
       class="brand-gold flex items-center gap-3 rounded-card p-4 active:scale-[0.99] transition-transform">
        <span class="brand-gold-badge w-11 h-11 rounded-2xl flex items-center justify-center text-2xl shrink-0">🏆</span>
        <span class="min-w-0">
            <span class="block font-black leading-tight">Bảng thi đua</span>
            <span class="block text-micro opacity-80">Xếp hạng tự động từ chuyên cần &amp; học tập · mở để khích lệ các em</span>
        </span>
        <i data-lucide="chevron-right" class="w-5 h-5 ml-auto shrink-0 opacity-70"></i>
    </a>

    <!-- TỔNG QUAN: hai thẻ "Sắp tới" + "Thông báo gần đây".
         Lấp khoảng trống trên máy tính, đồng thời đưa lịch + thông báo lên
         ngay Trang chủ. Xếp 2 cột từ lg, dọc trên điện thoại. -->
    <div class="home-overview grid gap-4">

        <!-- SẮP TỚI: việc cá nhân + buổi họp gần nhất -->
        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-black text-slate-800 flex items-center gap-2">
                    <i data-lucide="calendar-check" class="w-4 h-4 text-teal-600"></i> Sắp tới
                </h3>
                <button @click="openNotes()" type="button" class="text-micro font-bold text-blue-600 flex items-center gap-0.5">
                    Mở lịch <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </button>
            </div>
            <div class="space-y-2 flex-1">
                <template x-for="it in homeUpcoming" :key="it.kind + '-' + it.id">
                    <button @click="openNotes()" type="button"
                            class="w-full flex items-center gap-3 p-2.5 rounded-2xl border text-left active:scale-[0.99] transition-transform"
                            :class="it.kind === 'meeting' ? 'bg-teal-50/50 border-teal-100' : 'bg-slate-50 border-slate-100'">
                        <div class="w-12 shrink-0 text-center">
                            <p class="text-micro font-black leading-none" :class="it.kind === 'meeting' ? 'text-teal-600' : 'text-blue-600'" x-text="itemTime(it)"></p>
                            <p class="text-micro font-medium text-slate-400 mt-0.5" x-text="dayLabel(it.at.slice(0,10))"></p>
                        </div>
                        <div class="w-px self-stretch bg-slate-200"></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-800 leading-snug truncate" x-text="(it.kind === 'meeting' ? 'Họp: ' : '') + it.title"></p>
                            <p x-show="it.place" style="display:none" class="text-micro text-slate-500 truncate" x-text="it.place"></p>
                        </div>
                    </button>
                </template>
                <div x-show="homeUpcoming.length === 0" style="display: none;" class="h-full flex flex-col items-center justify-center text-center py-6">
                    <i data-lucide="calendar-check" class="w-8 h-8 text-slate-300 mb-2"></i>
                    <p class="text-micro text-slate-400">Không có việc nào sắp tới.</p>
                </div>
            </div>
        </div>

        <!-- THÔNG BÁO GẦN ĐÂY -->
        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-black text-slate-800 flex items-center gap-2">
                    <i data-lucide="megaphone" class="w-4 h-4 text-rose-500"></i> Thông báo gần đây
                </h3>
                <button @click="openAnnouncements()" type="button" class="text-micro font-bold text-blue-600 flex items-center gap-0.5">
                    Xem tất cả <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </button>
            </div>
            <div class="space-y-2 flex-1">
                <template x-for="a in visibleAnnouncements.slice(0,4)" :key="a.id">
                    <button @click="openAnnouncements()" type="button"
                            class="w-full flex items-start gap-3 p-2.5 rounded-2xl bg-slate-50 border border-slate-100 text-left active:scale-[0.99] transition-transform">
                        <span class="w-2 h-2 rounded-full mt-1.5 shrink-0"
                              :class="a.level === 'khẩn' ? 'bg-rose-500' : (a.level === 'quan trọng' ? 'bg-amber-500' : 'bg-blue-500')"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-800 leading-snug truncate" x-text="a.title"></p>
                            <p class="text-micro text-slate-500 truncate">
                                <span x-text="audienceLabel(a)"></span> · <span x-text="a.publishedAt"></span>
                            </p>
                        </div>
                        <span x-show="!readAnnouncements.includes(a.id)" style="display:none" class="w-2 h-2 rounded-full bg-blue-600 mt-1.5 shrink-0"></span>
                    </button>
                </template>
                <div x-show="visibleAnnouncements.length === 0" style="display: none;" class="h-full flex flex-col items-center justify-center text-center py-6">
                    <i data-lucide="bell-ring" class="w-8 h-8 text-slate-300 mb-2"></i>
                    <p class="text-micro text-slate-400">Chưa có thông báo nào.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- LƯỚI CHỨC NĂNG — phẳng, đồng nhất kiểu nút -->
    <div x-show="visibleFlat().length > 0"
         class="home-fn-grid bg-white rounded-card p-5 sm:p-6 shadow-sm border border-slate-100">
        <div class="grid grid-cols-4 sm:grid-cols-6 gap-x-3 gap-y-5">
            <template x-for="m in visibleFlat()" :key="m.key">
                <button @click="openModule(m.key)"
                        class="flex-col items-center group active:scale-90 transition-transform"
                        :class="[isUnderMaintenance(m.key) ? 'opacity-40' : '',
                                 ['students', 'attendance', 'announcements'].includes(m.key) ? 'hidden md:flex' : 'flex']">
                    <div class="w-14 h-14 bg-slate-50 rounded-field shadow-sm border border-slate-100 flex items-center justify-center mb-2 relative"
                         :class="isUnderMaintenance(m.key) ? 'text-slate-400' : m.color">
                        <i :data-lucide="m.icon" class="w-6 h-6"></i>

                        <!-- Chấm đỏ nhắc việc -->
                        <span x-show="!isUnderMaintenance(m.key) && moduleBadge(m.key) > 0" style="display: none;"
                              class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 rounded-full bg-rose-500 text-white text-micro font-black flex items-center justify-center border-2 border-white shadow-sm"
                              x-text="moduleBadgeLabel(m.key)"></span>

                        <!-- Đang bảo trì -->
                        <span x-show="!moduleEnabled[m.key]" style="display: none;"
                              class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-slate-700 text-white flex items-center justify-center border-2 border-white shadow-sm">
                            <i data-lucide="wrench" class="w-2.5 h-2.5"></i>
                        </span>
                    </div>
                    <span class="text-micro font-semibold text-center leading-tight"
                          :class="isUnderMaintenance(m.key) ? 'text-slate-400' : 'text-slate-600'"
                          x-text="m.label"></span>
                </button>
            </template>
        </div>
    </div>

</div>
