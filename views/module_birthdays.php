<!-- MÀN HÌNH SINH NHẬT — gồm cả thiếu nhi và Giáo Lý Viên -->
<div data-module="birthdays" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white dark:bg-slate-700 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600 dark:text-slate-300"></i>
        </button>
        <h2 class="text-xl font-black text-slate-800 dark:text-white tracking-tight">Sinh Nhật</h2>
    </div>

    <!-- ==========================================================
         2. HÔM NAY — tách hẳn hai nhóm, vì cách mừng khác nhau:
         thiếu nhi thì GLV chúc trong lớp, còn GLV thì cả đoàn chúc nhau.
         Khối này KHÔNG chịu bộ lọc bên dưới.
         ========================================================== -->
    <div x-show="birthdaysToday.length > 0" style="display: none;" class="space-y-3 mb-5">

        <!-- Thiếu nhi -->
        <div x-show="birthdaysTodayStudents.length > 0" style="display: none;"
             class="bg-gradient-to-br from-rose-500 to-rose-600 rounded-card p-5 shadow-lg shadow-rose-200 text-white">
            <div class="flex items-center mb-3">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center mr-3 backdrop-blur-sm">
                    <i data-lucide="party-popper" class="w-5 h-5"></i>
                </div>
                <div>
                    <p class="text-micro font-bold uppercase tracking-wider text-rose-100">Hôm nay · Thiếu nhi</p>
                    <p class="text-sm font-black">Chúc mừng sinh nhật các em!</p>
                </div>
            </div>
            <div class="space-y-2">
                <template x-for="p in birthdaysTodayStudents" :key="p.key">
                    <div class="bg-white/15 backdrop-blur-sm rounded-2xl px-4 py-3 flex items-center justify-between">
                        <div class="min-w-0 pr-3">
                            <p class="text-sm font-black leading-snug">
                                <span class="font-normal text-rose-100" x-text="p.holyName"></span>
                                <span x-text="p.name"></span>
                            </p>
                            <p class="text-micro text-rose-100 mt-0.5" x-text="p.sub"></p>
                        </div>
                        <span class="shrink-0 text-xs font-black bg-white text-rose-600 px-2.5 py-1 rounded-lg">
                            <span x-text="turningAge(p)"></span> tuổi
                        </span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Giáo Lý Viên -->
        <div x-show="birthdaysTodayMembers.length > 0" style="display: none;"
             class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-card p-5 shadow-lg shadow-blue-200 text-white">
            <div class="flex items-center mb-3">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center mr-3 backdrop-blur-sm">
                    <i data-lucide="cake" class="w-5 h-5"></i>
                </div>
                <div>
                    <p class="text-micro font-bold uppercase tracking-wider text-blue-200">Hôm nay · Giáo Lý Viên</p>
                    <p class="text-sm font-black">Mừng sinh nhật anh chị GLV!</p>
                </div>
            </div>
            <div class="space-y-2">
                <template x-for="p in birthdaysTodayMembers" :key="p.key">
                    <div class="bg-white/15 backdrop-blur-sm rounded-2xl px-4 py-3 flex items-center justify-between">
                        <div class="min-w-0 pr-3">
                            <p class="text-sm font-black leading-snug">
                                <span class="font-normal text-blue-200" x-text="p.holyName"></span>
                                <span x-text="p.name"></span>
                            </p>
                            <p class="text-micro text-blue-200 mt-0.5" x-text="p.sub"></p>
                        </div>
                        <span class="shrink-0 text-xs font-black bg-white text-blue-700 px-2.5 py-1 rounded-lg">
                            <span x-text="turningAge(p)"></span> tuổi
                        </span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- 3. SẮP TỚI TRONG 7 NGÀY -->
    <div x-show="upcomingBirthdays.length > 0" style="display: none;" class="bg-white dark:bg-slate-700 rounded-card p-5 shadow-sm border border-slate-100 dark:border-slate-600 mb-5">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Sắp tới trong 7 ngày</h3>
        <div class="space-y-2.5 xl:space-y-0 xl:grid xl:grid-cols-2 xl:gap-2.5 xl:items-start">
            <template x-for="item in upcomingBirthdays" :key="item.student.key">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 shrink-0 rounded-2xl flex items-center justify-center"
                         :class="item.student.kind === 'member' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-500'">
                        <i data-lucide="cake" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-black text-slate-800 dark:text-white leading-snug">
                            <span class="font-normal text-slate-500" x-text="item.student.holyName"></span>
                            <span x-text="item.student.name"></span>
                        </p>
                        <p class="text-micro text-slate-500 font-medium" x-text="item.student.sub"></p>
                    </div>
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        <span class="text-micro font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-100"
                              x-text="item.days === 1 ? 'Ngày mai' : 'Còn ' + item.days + ' ngày'"></span>
                        <span class="text-micro font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border"
                              :class="birthdayChipClass(item.student.kind)"
                              x-text="birthdayKindLabel(item.student.kind)"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- 4. CHỌN THÁNG -->
    <div class="bg-white dark:bg-slate-700 rounded-card p-4 shadow-sm border border-slate-100 dark:border-slate-600 mb-4">
        <div class="flex items-center gap-2">
            <button aria-label="Tháng trước" @click="shiftBirthdayMonth(-1)" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
            <div class="flex-1 text-center">
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Tháng</p>
                <p class="text-lg font-black text-slate-800 dark:text-white leading-tight" x-text="'Tháng ' + birthdayMonth"></p>
            </div>
            <button aria-label="Tháng sau" @click="shiftBirthdayMonth(1)" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        </div>
    </div>

    <!-- 5. LỌC THEO NHÓM — số đếm hiện ngay trên chip để khỏi phải bấm thử -->
    <div class="grid grid-cols-3 gap-2 mb-4">
        <button @click="birthdayKind = 'all'" type="button"
                class="py-2.5 rounded-xl font-bold text-micro border transition-colors flex flex-col items-center gap-0.5"
                :class="birthdayKind === 'all' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'">
            <span>Tất cả</span>
            <span class="text-micro font-black opacity-80" x-text="birthdayCounts.all"></span>
        </button>
        <button @click="birthdayKind = 'student'" type="button"
                class="py-2.5 rounded-xl font-bold text-micro border transition-colors flex flex-col items-center gap-0.5"
                :class="birthdayKind === 'student' ? 'bg-rose-500 text-white border-rose-500' : 'bg-white text-slate-500 border-slate-200'">
            <span>Thiếu nhi</span>
            <span class="text-micro font-black opacity-80" x-text="birthdayCounts.student"></span>
        </button>
        <button @click="birthdayKind = 'member'" type="button"
                class="py-2.5 rounded-xl font-bold text-micro border transition-colors flex flex-col items-center gap-0.5"
                :class="birthdayKind === 'member' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'">
            <span>Giáo Lý Viên</span>
            <span class="text-micro font-black opacity-80" x-text="birthdayCounts.member"></span>
        </button>
    </div>

    <!-- 6. DANH SÁCH SINH NHẬT TRONG THÁNG -->
    <div class="space-y-3">
        <template x-for="p in birthdaysInMonth" :key="p.key">
            <div style="content-visibility: auto; contain-intrinsic-size: auto 96px;"
                 class="bg-white rounded-field p-4 shadow-sm border flex items-center gap-3.5"
                 :class="isBirthdayToday(p)
                        ? (p.kind === 'member' ? 'border-blue-200 bg-blue-50/40' : 'border-rose-200 bg-rose-50/40')
                        : 'border-slate-100'">

                <!-- Ô ngày: màu theo nhóm, tô đậm nếu đúng hôm nay -->
                <div class="w-12 h-12 shrink-0 rounded-2xl flex flex-col items-center justify-center border"
                     :class="isBirthdayToday(p)
                            ? (p.kind === 'member' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-rose-500 border-rose-500 text-white')
                            : (p.kind === 'member' ? 'bg-blue-50 border-blue-100 text-blue-600' : 'bg-slate-50 border-slate-200 text-slate-600')">
                    <span class="text-base font-black leading-none" x-text="birthDay(p)"></span>
                    <span class="text-micro font-bold uppercase tracking-wide opacity-70">Ngày</span>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span class="text-micro font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border"
                              :class="birthdayChipClass(p.kind)" x-text="birthdayKindLabel(p.kind)"></span>
                    </div>
                    <p class="text-sm font-black text-slate-800 dark:text-white leading-snug">
                        <span class="font-normal text-slate-500" x-text="p.holyName"></span>
                        <span x-text="p.name"></span>
                    </p>
                    <p class="text-micro font-medium text-slate-500 mt-0.5">
                        <span x-text="p.sub"></span>
                        <span class="text-slate-300 mx-1">•</span>
                        <span class="text-slate-500 font-bold">tròn <span x-text="turningAge(p)"></span> tuổi</span>
                    </p>
                </div>

                <!-- Gọi nhanh: thiếu nhi thì gọi mẹ, GLV thì gọi thẳng -->
                <a :href="'tel:' + p.phone" :aria-label="p.phoneLabel + ' ' + p.name" :title="p.phoneLabel"
                   class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center active:scale-90 transition-transform border"
                   :class="p.kind === 'member' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-rose-50 text-rose-500 border-rose-100'">
                    <i data-lucide="phone" class="w-4 h-4"></i>
                </a>
            </div>
        </template>

        <div x-show="birthdaysInMonth.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="cake" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
            <p class="text-slate-500 font-medium text-sm"
               x-text="birthdayKind === 'member' ? 'Tháng này không có GLV nào sinh nhật.'
                      : (birthdayKind === 'student' ? 'Tháng này không có em nào sinh nhật.'
                      : 'Tháng này không có ai sinh nhật.')"></p>
        </div>

        <!-- Nhắc khi GLV chưa khai ngày sinh -->
        <div x-show="birthdayKind !== 'student' && membersWithoutBirthday > 0" style="display: none;"
             class="bg-amber-50 border border-amber-100 rounded-2xl p-3 flex items-start gap-2.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"></i>
            <p class="text-micro text-amber-700 leading-snug">
                Có <span class="font-black" x-text="membersWithoutBirthday"></span> Giáo Lý Viên chưa khai ngày sinh
                nên không hiện ở đây. Bổ sung ở màn <span class="font-bold">Khối &amp; Lớp → Nhân sự</span>.
            </p>
        </div>
    </div>
</div>
