<!-- ==========================================================
     THANH ĐIỀU HƯỚNG DƯỚI
     Chỉ giữ 3 lối đi chính. Danh sách / Xin phép / Chương trình
     vào từ lưới App Center ngoài Trang chủ.
     - Điện thoại : bám đáy, bo tròn 2 góc trên, có safe-area iPhone
     - Tablet trở lên : thành thanh pill nổi, thụt vào trong khung app
     ========================================================== -->
<nav class="app-bottomnav">
    <!-- max-w khớp với app-shell trong public/index.php để thanh nav không lệch biên -->
    <div class="nav-outer max-w-md sm:max-w-xl lg:max-w-6xl xl:max-w-7xl">
    <div class="nav-inner">
        <div class="flex items-stretch justify-around px-2 py-2">

            <!-- TRANG CHỦ -->
            <button @click="changeModule('dashboard')" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'dashboard' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-xl flex items-center justify-center transition-colors"
                      :class="currentModule === 'dashboard' ? 'bg-blue-50' : 'bg-transparent'">
                    <i data-lucide="home" class="w-5 h-5 pointer-events-none"></i>
                </span>
                <span class="text-micro font-bold leading-none">Trang chủ</span>
            </button>

            <!-- ĐIỂM DANH -->
            <button @click="openAttendance()" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'attendance' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-xl flex items-center justify-center transition-colors"
                      :class="currentModule === 'attendance' ? 'bg-blue-50' : 'bg-transparent'">
                    <i data-lucide="clipboard-check" class="w-5 h-5 pointer-events-none"></i>
                </span>
                <span class="text-micro font-bold leading-none">Điểm danh</span>
            </button>

            <!-- CÀI ĐẶT — ai cũng thấy.
                 Thẻ đầu là hồ sơ Cá nhân của chính mình; bốn thẻ quản trị
                 bên trong vẫn chỉ Quản Trị Hệ Thống mới có. -->
            <button @click="openSettings()" type="button"
                    class="flex-1 flex flex-col items-center gap-1 py-2 rounded-2xl active:scale-90 transition-transform"
                    :class="currentModule === 'settings' ? 'text-blue-600' : 'text-slate-400'">
                <span class="w-10 h-8 rounded-xl flex items-center justify-center transition-colors relative"
                      :class="currentModule === 'settings' ? 'bg-blue-50' : 'bg-transparent'">
                    <i data-lucide="settings" class="w-5 h-5 pointer-events-none"></i>
                    <!-- Nhắc: việc cần làm của mình, hoặc chức năng đang tạm khóa -->
                    <span x-show="myTasks.length > 0 || maintenanceCount > 0" style="display: none;"
                          class="absolute top-0 right-1 w-2 h-2 rounded-full bg-rose-500 border border-white"></span>
                </span>
                <span class="text-micro font-bold leading-none">Cài đặt</span>
            </button>

        </div>
    </div>
    </div>
</nav>
