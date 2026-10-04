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

            <!-- 1. TRANG CHỦ -->
            <button @click="changeModule('dashboard')" type="button"
                    class="nav-tab"
                    :class="currentModule === 'dashboard' ? 'nav-tab-active' : 'nav-tab-inactive'">
                <span class="nav-tab-icon"
                      :class="currentModule === 'dashboard' ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="home" class="w-6 h-6 pointer-events-none"></i>
                </span>
                <span class="nav-tab-label"
                      :class="currentModule === 'dashboard' ? 'nav-label-active' : 'nav-label-inactive'">Trang chủ</span>
            </button>

            <!-- 2. THIẾU NHI -->
            <button x-show="canAccess('students')" @click="changeModule('students')" type="button"
                    class="nav-tab"
                    :class="(currentModule === 'students' || currentModule === 'student_profile') ? 'nav-tab-active' : 'nav-tab-inactive'"
                    style="display: none;">
                <span class="nav-tab-icon"
                      :class="(currentModule === 'students' || currentModule === 'student_profile') ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="users" class="w-6 h-6 pointer-events-none"></i>
                </span>
                <span class="nav-tab-label"
                      :class="(currentModule === 'students' || currentModule === 'student_profile') ? 'nav-label-active' : 'nav-label-inactive'">Thiếu Nhi</span>
            </button>

            <!-- 3. ĐIỂM DANH -->
            <button x-show="canAccess('attendance')" @click="openAttendance()" type="button"
                    class="nav-tab"
                    :class="currentModule === 'attendance' ? 'nav-tab-active' : 'nav-tab-inactive'"
                    style="display: none;">
                <span class="nav-tab-icon"
                      :class="currentModule === 'attendance' ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="clipboard-check" class="w-6 h-6 pointer-events-none"></i>
                </span>
                <span class="nav-tab-label"
                      :class="currentModule === 'attendance' ? 'nav-label-active' : 'nav-label-inactive'">Điểm danh</span>
            </button>

            <!-- 4. THÔNG BÁO -->
            <button @click="openAnnouncements()" type="button"
                    class="nav-tab relative"
                    :class="currentModule === 'announcements' ? 'nav-tab-active' : 'nav-tab-inactive'">
                <span class="nav-tab-icon"
                      :class="currentModule === 'announcements' ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="megaphone" class="w-6 h-6 pointer-events-none"></i>
                    <!-- Chấm đỏ thông báo chưa đọc -->
                    <span x-show="unreadAnnouncementCount > 0" style="display: none;"
                          class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-rose-500 border-2 border-white"></span>
                </span>
                <span class="nav-tab-label"
                      :class="currentModule === 'announcements' ? 'nav-label-active' : 'nav-label-inactive'">Thông báo</span>
            </button>

            <!-- 5. CÁ NHÂN -->
            <button @click="openSettings('profile')" type="button"
                    class="nav-tab relative"
                    :class="currentModule === 'settings' ? 'nav-tab-active' : 'nav-tab-inactive'">
                <span class="nav-tab-icon"
                      :class="currentModule === 'settings' ? 'nav-icon-active' : 'nav-icon-inactive'">
                    <i data-lucide="user" class="w-6 h-6 pointer-events-none"></i>
                    <!-- Chấm đỏ việc cần làm -->
                    <span x-show="myTasks.length > 0 || maintenanceCount > 0" style="display: none;"
                          class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-rose-500 border-2 border-white"></span>
                </span>
                <span class="nav-tab-label"
                      :class="currentModule === 'settings' ? 'nav-label-active' : 'nav-label-inactive'">Cá nhân</span>
            </button>

        </div>
    </div>
    </div>
</nav>
