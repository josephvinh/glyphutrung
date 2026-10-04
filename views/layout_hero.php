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
                class="min-w-[280px] sm:min-w-0 sm:flex-1 snap-center bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="flex justify-between items-start mb-2">
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                </div>
                <span x-show="unreadAnnouncementCount > 0" style="display: none;"
                      class="text-micro font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full"
                      x-text="unreadAnnouncementCount + ' mới'"></span>
            </div>
            <div>
                <p class="text-slate-800 font-semibold text-sm leading-snug line-clamp-2"
                   x-text="latestAnnouncement ? latestAnnouncement.title : 'Chưa có thông báo nào.'"></p>
                <p class="text-slate-400 text-xs mt-1" x-text="latestAnnouncement ? latestAnnouncement.publishedAt : ''"></p>
            </div>
        </button>

        <!-- Thẻ 2: Sĩ số lớp đang phụ trách -->
        <button @click="changeModule('students')" type="button"
                class="min-w-[160px] sm:min-w-0 sm:flex-1 snap-center bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center mb-2">
                <i data-lucide="users" class="w-4 h-4"></i>
            </div>
            <div>
                <p class="text-slate-800 font-black text-2xl"><span x-text="user.assignedClass ? myClassSize : accessibleStudents.length"></span> <span class="text-sm font-medium text-slate-400">em</span></p>
                <p class="text-slate-400 text-xs mt-0.5 truncate"
                   x-text="user.assignedClass
                          ? (myClasses.length > 1 ? 'các lớp' : user.assignedClass)
                          : (user.managedBlock
                             ? (myBlocks.length > 1 ? 'các khối' : user.managedBlock)
                             : 'toàn đoàn')"></p>
            </div>
        </button>

        <!-- Thẻ 3: Sinh nhật tháng này -->
        <button @click="openBirthdays()" type="button"
                class="min-w-[140px] sm:min-w-0 sm:flex-1 snap-center bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between text-left active:scale-[0.98] transition-transform">
            <div class="flex justify-between items-start mb-2">
                <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
                    <i data-lucide="cake" class="w-4 h-4"></i>
                </div>
                <span x-show="birthdaysToday.length > 0" style="display: none;"
                      class="text-micro font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full">Hôm nay</span>
            </div>
            <div>
                <p class="text-slate-800 font-black text-2xl"><span x-text="birthdaysInMonth.length"></span> <span class="text-sm font-medium text-slate-400">em</span></p>
                <p class="text-slate-400 text-xs mt-0.5">sinh nhật</p>
            </div>
        </button>

    </div>
</div>
