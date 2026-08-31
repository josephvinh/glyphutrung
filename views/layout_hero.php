<!-- ==========================================================
     HERO: dải thẻ trượt ngang, nằm đè lên bụng Header
     - Điện thoại : trượt ngang, snap từng thẻ
     - Tablet trở lên : 3 thẻ chia đều 1 hàng, hết trượt
     Cả 3 thẻ đều bấm được, dẫn thẳng vào module tương ứng.
     ========================================================== -->

<!-- Dashboard Stats Cards (Admin only) -->
<div x-show="isAdmin" class="mb-4">
    <div class="grid grid-cols-3 gap-3">
        <!-- Attendance Today -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                </div>
            </div>
            <p class="text-2xl font-black text-slate-800" x-text="todayAttendance.present + '/' + todayAttendance.total"></p>
            <p class="text-micro text-slate-500">Điểm danh hôm nay</p>
            <div class="mt-2 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full transition-all"
                     :style="'width: ' + attendanceRate + '%'"></div>
            </div>
        </div>

        <!-- Pending Leaves -->
        <div @click="openLeave()" class="bg-white rounded-card p-4 shadow-sm border border-slate-100 cursor-pointer hover:shadow-md transition-shadow active:scale-[0.98]">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                </div>
                <span x-show="pendingLeaves > 0" style="display: none;"
                      class="ml-auto text-micro font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded-lg"
                      x-text="pendingLeaves"></span>
            </div>
            <p class="text-2xl font-black text-slate-800" x-text="pendingLeaves"></p>
            <p class="text-micro text-slate-500">Đơn chờ duyệt</p>
        </div>

        <!-- Unread Announcements -->
        <div @click="openAnnouncements()" class="bg-white rounded-card p-4 shadow-sm border border-slate-100 cursor-pointer hover:shadow-md transition-shadow active:scale-[0.98]">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                </div>
                <span x-show="unreadAnnouncements > 0" style="display: none;"
                      class="ml-auto text-micro font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-lg"
                      x-text="unreadAnnouncements"></span>
            </div>
            <p class="text-2xl font-black text-slate-800" x-text="unreadAnnouncements"></p>
            <p class="text-micro text-slate-500">Thông báo mới</p>
        </div>
    </div>
</div>

<div class="relative z-[110] -mt-5">
    <!-- .bleed-x kéo dải này tràn ra sát mép, bù lại padding ngang của vùng nội dung -->
    <div class="bleed-x flex overflow-x-auto sm:overflow-visible gap-4 pb-4 px-4 sm:px-6 pt-2 snap-x snap-mandatory hide-scrollbar">

        <!-- Thẻ 1: Thông báo mới nhất từ BĐH -->
        <button @click="openAnnouncements()" type="button"
                class="min-w-[240px] sm:min-w-0 sm:flex-1 snap-center bg-white p-4 rounded-3xl shadow-md border border-slate-100 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="flex justify-between items-start mb-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                </div>
                <span x-show="unreadAnnouncementCount > 0" style="display: none;"
                      class="text-micro font-bold uppercase tracking-wider text-amber-500 bg-amber-50 px-2 py-1 rounded-lg"
                      x-text="unreadAnnouncementCount + ' mới'"></span>
            </div>
            <div>
                <h3 class="text-slate-500 text-xs font-medium mb-1">Thông báo từ BĐH</h3>
                <p class="text-slate-800 font-bold text-sm leading-tight"
                   x-text="latestAnnouncement ? latestAnnouncement.title : 'Chưa có thông báo nào.'"></p>
            </div>
        </button>

        <!-- Thẻ 2: Sĩ số lớp đang phụ trách -->
        <button @click="changeModule('students')" type="button"
                class="min-w-[200px] sm:min-w-0 sm:flex-1 snap-center bg-white p-4 rounded-3xl shadow-md border border-slate-100 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center mb-3">
                <i data-lucide="users" class="w-5 h-5"></i>
            </div>
            <div>
                <!-- Quản Trị và Ban Điều Hành không phụ trách lớp nào, nên
                     hiện tổng số em trong phạm vi họ thấy thay vì "lớp ()" rỗng. -->
                <h3 class="text-slate-500 text-xs font-medium mb-1"
                    x-text="user.assignedClass ? 'Sĩ số lớp (' + user.assignedClass + ')'
                          : (user.managedBlock ? 'Sĩ số khối ' + user.managedBlock : 'Tổng sĩ số toàn đoàn')"></h3>
                <p class="text-slate-800 font-black text-2xl"><span x-text="user.assignedClass ? myClassSize : accessibleStudents.length"></span> <span class="text-sm font-medium text-slate-400">em</span></p>
            </div>
        </button>

        <!-- Thẻ 3: Sinh nhật tháng này -->
        <button @click="openBirthdays()" type="button"
                class="min-w-[200px] sm:min-w-0 sm:flex-1 snap-center bg-white p-4 rounded-3xl shadow-md border border-slate-100 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="flex justify-between items-start mb-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center">
                    <i data-lucide="cake" class="w-5 h-5"></i>
                </div>
                <span x-show="birthdaysToday.length > 0" style="display: none;"
                      class="text-micro font-bold uppercase tracking-wider text-rose-600 bg-rose-50 px-2 py-1 rounded-lg">Hôm nay</span>
            </div>
            <div>
                <h3 class="text-slate-500 text-xs font-medium mb-1">Sinh nhật tháng này</h3>
                <p class="text-slate-800 font-black text-2xl"><span x-text="birthdaysInMonth.length"></span> <span class="text-sm font-medium text-slate-400">em</span></p>
            </div>
        </button>

    </div>
</div>
