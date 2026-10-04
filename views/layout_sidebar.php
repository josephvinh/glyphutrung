<!-- ==========================================================
     THANH BÊN — CHỈ HIỆN TỪ MÀN HÌNH ≥1024px

     Active state: indicator bar bên trái + nền nhạt
     ========================================================== -->
<aside class="app-sidebar hidden lg:flex flex-col gap-3 w-64 shrink-0">

    <!-- Logo + Brand -->
    <button @click="changeModule('dashboard')" type="button"
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

        <template x-for="m in visibleModules('glv')" :key="'sb-' + m.key">
            <button @click="openModule(m.key)" type="button"
                    class="sidebar-item w-full"
                    :class="isActiveModule(m.key) ? 'sidebar-item-active' : 'sidebar-item-inactive'">

                <!-- Active indicator bar -->
                <span class="sidebar-indicator"
                      :class="isActiveModule(m.key) ? 'sidebar-indicator-active' : ''"></span>

                <!-- Icon -->
                <span class="sidebar-icon"
                      :class="isActiveModule(m.key) ? 'sidebar-icon-active' : m.color">
                    <i :data-lucide="m.icon" class="w-5 h-5"></i>
                </span>

                <!-- Label -->
                <span class="flex-1 text-left text-sm font-semibold truncate" x-text="m.label"></span>

                <!-- Badge -->
                <span x-show="!isUnderMaintenance(m.key) && moduleBadge(m.key) > 0" style="display: none;"
                      class="sidebar-badge" x-text="moduleBadgeLabel(m.key)"></span>

                <!-- Maintenance icon -->
                <span x-show="!moduleEnabled[m.key]" style="display: none;"
                      class="w-5 h-5 flex items-center justify-center">
                    <i data-lucide="wrench" class="w-4 h-4 text-slate-400"></i>
                </span>
            </button>
        </template>
    </nav>

    <!-- Admin Section -->
    <nav x-show="visibleModules('bdh').length > 0" class="bg-white rounded-xl border border-slate-200 p-2 space-y-1" style="display: none;">
        <p class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider"
           x-text="['admin', 'bdh'].includes(user.role) ? 'Ban Điều Hành'
                  : (user.role === 'truong_khoi' ? 'Quản lý khối' : 'Thông tin chung')">Quản lý</p>

        <template x-for="m in visibleModules('bdh')" :key="'sb2-' + m.key">
            <button @click="openModule(m.key)" type="button"
                    class="sidebar-item w-full"
                    :class="isActiveModule(m.key) ? 'sidebar-item-active' : 'sidebar-item-inactive'">
                <span class="sidebar-indicator"
                      :class="isActiveModule(m.key) ? 'sidebar-indicator-active' : ''"></span>
                <span class="sidebar-icon sidebar-icon-dark"
                      :class="isActiveModule(m.key) ? 'sidebar-icon-active' : ''">
                    <i :data-lucide="m.icon" class="w-5 h-5"></i>
                </span>
                <span class="flex-1 text-left text-sm font-semibold truncate" x-text="m.label"></span>
                <span x-show="!moduleEnabled[m.key]" style="display: none;"
                      class="w-5 h-5 flex items-center justify-center">
                    <i data-lucide="wrench" class="w-4 h-4 text-slate-400"></i>
                </span>
            </button>
        </template>
    </nav>

    <!-- Settings -->
    <div class="mt-auto bg-white rounded-xl border border-slate-200 p-2">
        <button @click="openSettings()" type="button"
                class="sidebar-item w-full"
                :class="isActiveModule('settings') ? 'sidebar-item-active' : 'sidebar-item-inactive'">
            <span class="sidebar-indicator"
                  :class="isActiveModule('settings') ? 'sidebar-indicator-active' : ''"></span>
            <span class="sidebar-icon"
                  :class="isActiveModule('settings') ? 'sidebar-icon-active' : 'text-slate-500'">
                <i data-lucide="settings" class="w-5 h-5"></i>
            </span>
            <div class="flex-1 text-left min-w-0">
                <p class="text-sm font-semibold text-slate-900 truncate">Cài đặt</p>
                <p class="text-xs font-medium text-slate-500 truncate" x-text="user.fullName"></p>
            </div>
            <span x-show="myTasks.length > 0 || maintenanceCount > 0" style="display: none;"
                  class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
        </button>
    </div>

</aside>
