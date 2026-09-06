<!-- ==========================================================
     THANH ĐIỀU HƯỚNG DƯỚI — 5 TAB
     Phủ ~90% hành động hằng ngày: Trang chủ / Thiếu Nhi / Điểm danh /
     Thông báo / Cá nhân. Mỗi tab giữ nguyên class flex-1 để chia đều.

     - Điện thoại : bám đáy, bo tròn 2 góc trên, có safe-area iPhone
     - Tablet trở lên : thanh pill nổi, thụt vào trong khung app
     ========================================================== -->
<nav class="app-bottomnav">
    <!-- max-w khớp với app-shell trong public/index.php để thanh nav không lệch biên -->
    <div class="nav-outer max-w-md sm:max-w-xl lg:max-w-6xl xl:max-w-7xl">
    <div class="nav-inner">
        <div class="flex items-stretch justify-around px-1.5 py-2">

            <!-- 1. TRANG CHỦ — luôn hiện -->
            <button @click="changeModule('dashboard')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'dashboard' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-xl flex items-center justify-center transition-colors"
                      :class="currentModule === 'dashboard' ? 'bg-blue-50' : 'bg-transparent'">
                    <i data-lucide="home" class="w-5 h-5 pointer-events-none"></i>
                </span>
                <span class="text-micro font-bold leading-none">Trang chủ</span>
            </button>

            <!-- 2. THIẾU NHI — chỉ ai có quyền students mới thấy -->
            <button x-show="canAccess('students')" @click="changeModule('students')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'students' ? 'text-blue-600' : 'text-slate-400'"
                    style="display: none;">
                <span class="w-10 h-8 rounded-xl flex items-center justify-center transition-colors"
                      :class="currentModule === 'students' ? 'bg-blue-50' : 'bg-transparent'">
                    <i data-lucide="users" class="w-5 h-5 pointer-events-none"></i>
                </span>
                <span class="text-micro font-bold leading-none">Thiếu Nhi</span>
            </button>

            <!-- 3. ĐIỂM DANH — chỉ ai có quyền attendance mới thấy -->
            <button x-show="canAccess('attendance')" @click="openAttendance()" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'attendance' ? 'text-blue-600' : 'text-slate-400'"
                    style="display: none;">
                <span class="w-10 h-8 rounded-xl flex items-center justify-center transition-colors"
                      :class="currentModule === 'attendance' ? 'bg-blue-50' : 'bg-transparent'">
                    <i data-lucide="clipboard-check" class="w-5 h-5 pointer-events-none"></i>
                </span>
                <span class="text-micro font-bold leading-none">Điểm danh</span>
            </button>

            <!-- 4. THÔNG BÁO — ai cũng thấy; chấm đỏ khi có thông báo chưa đọc -->
            <button @click="openAnnouncements()" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'announcements' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-xl flex items-center justify-center transition-colors relative"
                      :class="currentModule === 'announcements' ? 'bg-blue-50' : 'bg-transparent'">
                    <i data-lucide="megaphone" class="w-5 h-5 pointer-events-none"></i>
                    <!-- Chấm đỏ: có thông báo chưa đọc -->
                    <span x-show="unreadAnnouncementCount > 0" style="display: none;"
                          class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-rose-500 border-2 border-white"></span>
                </span>
                <span class="text-micro font-bold leading-none">Thông báo</span>
            </button>

            <!-- 5. CÁ NHÂN — luôn hiện; chấm đỏ khi có việc cần làm hoặc bảo trì -->
            <button @click="openSettings('profile')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'settings' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-xl flex items-center justify-center transition-colors relative"
                      :class="currentModule === 'settings' ? 'bg-blue-50' : 'bg-transparent'">
                    <i data-lucide="user" class="w-5 h-5 pointer-events-none"></i>
                    <!-- Chấm đỏ: việc cần làm hoặc chức năng đang bảo trì -->
                    <span x-show="myTasks.length > 0 || maintenanceCount > 0" style="display: none;"
                          class="absolute top-0 right-1 w-2 h-2 rounded-full bg-rose-500 border border-white"></span>
                </span>
                <span class="text-micro font-bold leading-none">Cá nhân</span>
            </button>

        </div>
    </div>
    </div>
</nav>
