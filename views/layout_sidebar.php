<!-- ==========================================================
     THANH BÊN — CHỈ HIỆN TỪ MÀN HÌNH ≥1024px

     Active state: indicator bar bên trái + nền nhạt
     ========================================================== -->
<aside class="app-sidebar hidden lg:flex flex-col gap-3 w-64 shrink-0">

    <!-- Nhận diện KIÊM nút về Trang chủ.
         Không cần một mục "Trang chủ" riêng nữa: bấm vào tên app là về,
         đúng thói quen trên web. Viền xanh cho biết đang ở Trang chủ. -->
    <button @click="router.navigate('/dashboard')" type="button"
            aria-label="Về trang chủ"
            class="sidebar-brand flex items-center gap-3 p-3 rounded-xl transition-all"
            :class="currentModule === 'dashboard' ? 'bg-blue-50 border border-blue-200' : 'bg-white border border-slate-200 hover:bg-slate-50'">
        <img src="assets/img/icon-192.png" alt="Logo Gia Đình Giáo Lý Phú Trung"
             class="w-12 h-12 rounded-xl object-contain shadow-sm bg-white p-1">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-black text-slate-900 leading-tight">GLY PHÚ TRUNG</p>
            <p class="text-xs font-medium text-slate-500 truncate" x-text="year ? year.name : ''"></p>
        </div>
    </button>

    <!-- Main Navigation -->
    <nav class="bg-white rounded-xl border border-slate-200 p-2 space-y-1">
        <p class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Chức năng</p>

        <!-- Chức năng -->
        <template x-for="m in visibleModules('glv')" :key="'sb-' + m.key">
            <button @click="router.navigate('/' + m.key)" type="button"
                    class="nav-item w-full"
                    :class="[ (currentModule === m.key || (m.key === 'students' && currentModule === 'student_profile')) ? 'nav-item-on' : '',
                              isUnderMaintenance(m.key) ? 'opacity-50' : '' ]">
                <span class="nav-ico" :class="(currentModule === m.key || (m.key === 'students' && currentModule === 'student_profile')) ? '' : m.color">
                    <i :data-lucide="m.icon" class="w-[18px] h-[18px]"></i>
                </span>
                <span class="flex-1 text-left truncate" x-text="m.label"></span>
            </button>
        </template>

        <!-- Khu vực điều hành / quản lý khối -->
        <div x-show="visibleModules('bdh').length > 0" style="display: none;">
            <p class="px-3 pb-2 text-micro font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider"
               x-text="['admin', 'bdh'].includes(user.role) ? 'Ban Điều Hành'
                      : (user.role === 'truong_khoi' ? 'Quản lý khối' : 'Thông tin chung')"></p>
            <div class="space-y-0.5">
                <template x-for="m in visibleModules('bdh')" :key="'sb2-' + m.key">
                    <button @click="router.navigate('/' + m.key)" type="button"
                            class="nav-item w-full"
                            :class="[ currentModule === m.key ? 'nav-item-on' : '',
                                      isUnderMaintenance(m.key) ? 'opacity-50' : '' ]">
                        <span class="nav-ico nav-ico-dark" :class="currentModule === m.key ? 'nav-ico-dark-on' : ''">
                            <i :data-lucide="m.icon" class="w-[18px] h-[18px]"></i>
                        </span>
                        <span class="flex-1 text-left truncate" x-text="m.label"></span>
                        <span x-show="!moduleEnabled[m.key]" style="display: none;" class="inline-flex items-center justify-center"><i data-lucide="wrench" class="w-3.5 h-3.5 shrink-0 text-slate-400 dark:text-slate-500"></i></span>
                    </button>
                </template>
            </div>
        </div>
    </nav>

    <!-- Tài khoản.
         Cá nhân đã gộp vào Cài Đặt (thẻ đầu tiên), nên ở đây chỉ còn một
         lối vào duy nhất, và ai cũng thấy. -->
    <div class="bg-white rounded-card border border-slate-100 shadow-sm p-3">
        <button @click="router.navigate('/settings')" type="button"
                class="nav-item w-full" :class="currentModule === 'settings' ? 'nav-item-on' : ''">
            <span class="nav-ico"><i data-lucide="settings" class="w-[18px] h-[18px]"></i></span>
            <span class="flex-1 text-left truncate">
                <span class="block leading-tight">Cài đặt</span>
                <span class="block text-micro font-medium text-slate-400 truncate" x-text="user.fullName"></span>
            </span>
            <span x-show="myTasks.length > 0 || maintenanceCount > 0" style="display: none;"
                  class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
        </button>
    </div>

</aside>
