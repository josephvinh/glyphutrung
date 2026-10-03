<!-- ==========================================================
     HERO: dải thẻ trượt ngang, nằm đè lên bụng Header
     - Điện thoại : trượt ngang, snap từng thẻ
     - Tablet trở lên : 3 thẻ chia đều 1 hàng, hết trượt
     Cả 3 thẻ đều bấm được, dẫn thẳng vào module tương ứng.
     ========================================================== -->

<div class="relative z-[110] -mt-5">
    <!-- .bleed-x kéo dải này tràn ra sát mép, bù lại padding ngang của vùng nội dung -->
    <div class="bleed-x flex overflow-x-auto sm:overflow-visible gap-4 pb-4 px-4 sm:px-6 pt-2 snap-x snap-mandatory hide-scrollbar">

        <!-- Thẻ 1: Thông báo mới nhất từ BĐH -->
        <button @click="openAnnouncements()" type="button"
                class="min-w-[240px] sm:min-w-0 sm:flex-1 snap-center bg-white dark:bg-slate-700 p-4 rounded-3xl shadow-md border border-slate-100 dark:border-slate-600 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="flex justify-between items-start mb-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-900/30 text-amber-500 dark:text-amber-400 flex items-center justify-center">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                </div>
                <span x-show="unreadAnnouncementCount > 0" style="display: none;"
                      class="text-micro font-bold uppercase tracking-wider text-amber-500 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-1 rounded-lg"
                      x-text="unreadAnnouncementCount + ' mới'"></span>
            </div>
            <div>
                <h3 class="text-slate-500 dark:text-slate-400 text-xs font-medium mb-1">Thông báo từ BĐH</h3>
                <p class="text-slate-800 dark:text-white font-bold text-sm leading-tight"
                   x-text="latestAnnouncement ? latestAnnouncement.title : 'Chưa có thông báo nào.'"></p>
            </div>
        </button>

        <!-- Thẻ 2: Sĩ số lớp đang phụ trách -->
        <button @click="changeModule('students')" type="button"
                class="min-w-[200px] sm:min-w-0 sm:flex-1 snap-center bg-white dark:bg-slate-700 p-4 rounded-3xl shadow-md border border-slate-100 dark:border-slate-600 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-900/30 text-blue-500 dark:text-blue-400 flex items-center justify-center mb-3">
                <i data-lucide="users" class="w-5 h-5"></i>
            </div>
            <div>
                <!-- Quản Trị và Ban Điều Hành không phụ trách lớp nào, nên
                     hiện tổng số em trong phạm vi họ thấy thay vì "lớp ()" rỗng. -->
                <h3 class="text-slate-500 dark:text-slate-400 text-xs font-medium mb-1"
                    x-text="user.assignedClass
                          ? (myClasses.length > 1 ? 'Sĩ số các lớp phụ trách' : 'Sĩ số lớp (' + user.assignedClass + ')')
                          : (user.managedBlock
                             ? (myBlocks.length > 1 ? 'Sĩ số các khối phụ trách' : 'Sĩ số khối ' + user.managedBlock)
                             : 'Tổng sĩ số toàn đoàn')"></h3>
                <p class="text-slate-800 dark:text-white font-black text-2xl"><span x-text="user.assignedClass ? myClassSize : accessibleStudents.length"></span> <span class="text-sm font-medium text-slate-400 dark:text-slate-500">em</span></p>
            </div>
        </button>

        <!-- Thẻ 3: Sinh nhật tháng này -->
        <button @click="openBirthdays()" type="button"
                class="min-w-[200px] sm:min-w-0 sm:flex-1 snap-center bg-white dark:bg-slate-700 p-4 rounded-3xl shadow-md border border-slate-100 dark:border-slate-600 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="flex justify-between items-start mb-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-900/30 text-rose-500 dark:text-rose-400 flex items-center justify-center">
                    <i data-lucide="cake" class="w-5 h-5"></i>
                </div>
                <span x-show="birthdaysToday.length > 0" style="display: none;"
                      class="text-micro font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30 px-2 py-1 rounded-lg">Hôm nay</span>
            </div>
            <div>
                <h3 class="text-slate-500 dark:text-slate-400 text-xs font-medium mb-1">Sinh nhật tháng này</h3>
                <p class="text-slate-800 dark:text-white font-black text-2xl"><span x-text="birthdaysInMonth.length"></span> <span class="text-sm font-medium text-slate-400 dark:text-slate-500">em</span></p>
            </div>
        </button>

    </div>
</div>
