<!-- ==========================================================
     THANH ĐIỀU HƯỚNG DƯỚI — 5 TAB
     Active state: nền accent + icon đổi màu + text đậm

     - Điện thoại : bám đáy, bo tròn 2 góc trên, có safe-area iPhone
     - Tablet trở lên : thanh pill nổi, thụt vào trong khung app
     ========================================================== -->
<nav class="app-bottomnav select-none">
    <!-- max-w khớp với app-shell trong public/index.php để thanh nav không lệch biên -->
    <div class="nav-outer max-w-md lg:max-w-xl xl:max-w-7xl">
    <div class="nav-inner">
        <div class="flex items-stretch justify-around px-1 py-1.5">

<<<<<<< HEAD
            <!-- 1. TRANG CHỦ — luôn hiện -->
            <button @click="router.navigate('/dashboard')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'dashboard' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-lg flex items-center justify-center transition-colors backdrop-blur-sm"
                      :class="currentModule === 'dashboard' ? 'bg-blue-500/20' : 'bg-transparent'">
                    <i data-lucide="home" class="w-5 h-5 pointer-events-none"></i>
=======
            <!-- 1. TRANG CHỦ -->
            <button @click="changeModule('dashboard')" type="button"
                    class="nav-tab"
                    :class="currentModule === 'dashboard' ? 'nav-tab-active' : 'nav-tab-inactive'">
                <span class="nav-tab-icon"
                      :class="currentModule === 'dashboard' ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="home" class="w-6 h-6 pointer-events-none"></i>
>>>>>>> origin/master
                </span>
                <span class="nav-tab-label"
                      :class="currentModule === 'dashboard' ? 'nav-label-active' : 'nav-label-inactive'">Trang chủ</span>
            </button>

<<<<<<< HEAD
            <!-- 2. THIẾU NHI — chỉ ai có quyền students mới thấy -->
            <button x-show="canAccess('students')" @click="router.navigate('/students')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="(currentModule === 'students' || currentModule === 'student_profile') ? 'text-blue-600' : 'text-slate-400'"
=======
            <!-- 2. THIẾU NHI -->
            <button x-show="canAccess('students')" @click="changeModule('students')" type="button"
                    class="nav-tab"
                    :class="(currentModule === 'students' || currentModule === 'student_profile') ? 'nav-tab-active' : 'nav-tab-inactive'"
>>>>>>> origin/master
                    style="display: none;">
                <span class="nav-tab-icon"
                      :class="(currentModule === 'students' || currentModule === 'student_profile') ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="users" class="w-6 h-6 pointer-events-none"></i>
                </span>
                <span class="nav-tab-label"
                      :class="(currentModule === 'students' || currentModule === 'student_profile') ? 'nav-label-active' : 'nav-label-inactive'">Thiếu Nhi</span>
            </button>

<<<<<<< HEAD
            <!-- 3. ĐIỂM DANH — chỉ ai có quyền attendance mới thấy -->
            <button x-show="canAccess('attendance')" @click="router.navigate('/attendance')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'attendance' ? 'text-blue-600' : 'text-slate-400'"
=======
            <!-- 3. ĐIỂM DANH -->
            <button x-show="canAccess('attendance')" @click="openAttendance()" type="button"
                    class="nav-tab"
                    :class="currentModule === 'attendance' ? 'nav-tab-active' : 'nav-tab-inactive'"
>>>>>>> origin/master
                    style="display: none;">
                <span class="nav-tab-icon"
                      :class="currentModule === 'attendance' ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="clipboard-check" class="w-6 h-6 pointer-events-none"></i>
                </span>
                <span class="nav-tab-label"
                      :class="currentModule === 'attendance' ? 'nav-label-active' : 'nav-label-inactive'">Điểm danh</span>
            </button>

<<<<<<< HEAD
            <!-- 4. THÔNG BÁO — ai cũng thấy; chấm đỏ khi có thông báo chưa đọc -->
            <button @click="router.navigate('/announcements')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'announcements' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-lg flex items-center justify-center transition-colors backdrop-blur-sm"
                      :class="currentModule === 'announcements' ? 'bg-blue-500/20' : 'bg-transparent'">
                    <span class="relative inline-flex">
                        <i data-lucide="megaphone" class="w-5 h-5 pointer-events-none"></i>
                        <!-- Chấm đỏ: có thông báo chưa đọc — bám góc trên-phải của icon -->
                        <span x-show="unreadAnnouncementCount > 0" style="display: none;"
                              class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-rose-500 border border-white"></span>
                    </span>
=======
            <!-- 4. THÔNG BÁO -->
            <button @click="openAnnouncements()" type="button"
                    class="nav-tab relative"
                    :class="currentModule === 'announcements' ? 'nav-tab-active' : 'nav-tab-inactive'">
                <span class="nav-tab-icon relative"
                      :class="currentModule === 'announcements' ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="megaphone" class="w-6 h-6 pointer-events-none"></i>
                    <!-- Chấm đỏ thông báo chưa đọc -->
                    <span x-show="unreadAnnouncementCount > 0" style="display: none;"
                          class="absolute top-0 right-1 w-2.5 h-2.5 rounded-full bg-rose-500 border-2 border-white"></span>
>>>>>>> origin/master
                </span>
                <span class="nav-tab-label"
                      :class="currentModule === 'announcements' ? 'nav-label-active' : 'nav-label-inactive'">Thông báo</span>
            </button>

<<<<<<< HEAD
            <!-- 5. CÁ NHÂN — luôn hiện; chấm đỏ khi có việc cần làm hoặc bảo trì -->
            <button @click="router.navigate('/settings')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'settings' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-lg flex items-center justify-center transition-colors backdrop-blur-sm"
                      :class="currentModule === 'settings' ? 'bg-blue-500/20' : 'bg-transparent'">
                    <span class="relative inline-flex">
                        <i data-lucide="user" class="w-5 h-5 pointer-events-none"></i>
                        <!-- Chấm đỏ: việc cần làm hoặc chức năng đang bảo trì -->
                        <span x-show="myTasks.length > 0 || maintenanceCount > 0" style="display: none;"
                              class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-rose-500 border border-white"></span>
                    </span>
=======
            <!-- 5. CÁ NHÂN -->
            <button @click="openSettings('profile')" type="button"
                    class="nav-tab relative"
                    :class="currentModule === 'settings' ? 'nav-tab-active' : 'nav-tab-inactive'">
                <span class="nav-tab-icon relative"
                      :class="currentModule === 'settings' ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="user" class="w-6 h-6 pointer-events-none"></i>
                    <!-- Chấm đỏ việc cần làm -->
                    <span x-show="myTasks.length > 0 || maintenanceCount > 0" style="display: none;"
                          class="absolute top-0 right-1 w-2.5 h-2.5 rounded-full bg-rose-500 border-2 border-white"></span>
>>>>>>> origin/master
                </span>
                <span class="nav-tab-label"
                      :class="currentModule === 'settings' ? 'nav-label-active' : 'nav-label-inactive'">Cá nhân</span>
            </button>

        </div>
    </div>
    </div>
</nav>
