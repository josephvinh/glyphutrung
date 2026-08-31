<!-- ==========================================================
     THANH BÊN — CHỈ HIỆN TỪ MÀN HÌNH ≥1024px

     Trên điện thoại, thanh điều hướng dưới là đúng quy ước. Trên máy
     tính thì không: chuột ở trên/bên, màn hình rộng ngang, và người
     dùng chờ thấy hết chức năng chứ không phải bấm về Trang chủ rồi
     mới chọn tiếp.

     Danh sách sinh ra từ visibleModules() — cùng nguồn với lưới App
     Center — nên phân quyền và công tắc bảo trì tự áp dụng.
     ========================================================== -->
<aside class="app-sidebar hidden lg:flex flex-col gap-4">

    <!-- Nhận diện KIÊM nút về Trang chủ.
         Không cần một mục "Trang chủ" riêng nữa: bấm vào tên app là về,
         đúng thói quen trên web. Viền xanh cho biết đang ở Trang chủ. -->
    <button @click="changeModule('dashboard')" type="button"
            aria-label="Về trang chủ"
            class="bg-white rounded-card border shadow-sm px-4 py-4 text-left transition-colors hover:bg-slate-50"
            :class="currentModule === 'dashboard' ? 'border-blue-300 ring-2 ring-blue-100' : 'border-slate-100'">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-field bg-gradient-to-br from-blue-600 to-blue-700 text-white flex items-center justify-center shrink-0 shadow-md shadow-blue-200">
                <i data-lucide="church" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-black text-slate-800 leading-tight truncate">GLY PHÚ TRUNG</p>
                <p class="text-micro font-semibold text-slate-400 truncate" x-text="year ? year.name : ''"></p>
            </div>
        </div>
    </button>

    <!-- Điều hướng -->
    <nav class="bg-white rounded-card border border-slate-100 shadow-sm p-3 space-y-4">

        <!-- Chức năng -->
        <div x-show="visibleModules('glv').length > 0">
            <p class="px-3 pb-2 text-micro font-bold text-slate-400 uppercase tracking-wider">Chức năng</p>
            <div class="space-y-0.5">
                <template x-for="m in visibleModules('glv')" :key="'sb-' + m.key">
                    <button @click="openModule(m.key)" type="button"
                            class="nav-item w-full"
                            :class="[ currentModule === m.key ? 'nav-item-on' : '',
                                      isUnderMaintenance(m.key) ? 'opacity-50' : '' ]">
                        <span class="nav-ico" :class="currentModule === m.key ? '' : m.color">
                            <i :data-lucide="m.icon" class="w-[18px] h-[18px]"></i>
                        </span>
                        <span class="flex-1 text-left truncate" x-text="m.label"></span>

                        <!-- Chấm nhắc việc -->
                        <span x-show="!isUnderMaintenance(m.key) && moduleBadge(m.key) > 0" style="display: none;"
                              class="shrink-0 min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-micro font-black flex items-center justify-center"
                              x-text="moduleBadge(m.key)"></span>

                        <!-- Đang bảo trì -->
                        <i x-show="!moduleEnabled[m.key]" style="display: none;"
                           data-lucide="wrench" class="w-3.5 h-3.5 shrink-0 text-slate-400"></i>
                    </button>
                </template>
            </div>
        </div>

        <!-- Khu vực điều hành / quản lý khối -->
        <div x-show="visibleModules('bdh').length > 0" style="display: none;">
            <p class="px-3 pb-2 text-micro font-bold text-slate-400 uppercase tracking-wider"
               x-text="['admin', 'bdh'].includes(user.role) ? 'Ban Điều Hành'
                      : (user.role === 'truong_khoi' ? 'Quản lý khối' : 'Thông tin chung')"></p>
            <div class="space-y-0.5">
                <template x-for="m in visibleModules('bdh')" :key="'sb2-' + m.key">
                    <button @click="openModule(m.key)" type="button"
                            class="nav-item w-full"
                            :class="[ currentModule === m.key ? 'nav-item-on' : '',
                                      isUnderMaintenance(m.key) ? 'opacity-50' : '' ]">
                        <span class="nav-ico nav-ico-dark" :class="currentModule === m.key ? 'nav-ico-dark-on' : ''">
                            <i :data-lucide="m.icon" class="w-[18px] h-[18px]"></i>
                        </span>
                        <span class="flex-1 text-left truncate" x-text="m.label"></span>
                        <i x-show="!moduleEnabled[m.key]" style="display: none;"
                           data-lucide="wrench" class="w-3.5 h-3.5 shrink-0 text-slate-400"></i>
                    </button>
                </template>
            </div>
        </div>
    </nav>

    <!-- Tài khoản.
         Cá nhân đã gộp vào Cài Đặt (thẻ đầu tiên), nên ở đây chỉ còn một
         lối vào duy nhất, và ai cũng thấy. -->
    <div class="bg-white rounded-card border border-slate-100 shadow-sm p-3">
        <button @click="openSettings()" type="button"
                class="nav-item w-full" :class="currentModule === 'settings' ? 'nav-item-on' : ''">
            <span class="nav-ico"><i data-lucide="settings" class="w-[18px] h-[18px]"></i></span>
            <span class="flex-1 text-left truncate">
                <span class="block leading-tight">Cài đặt</span>
                <span class="block text-micro font-medium text-slate-400 truncate" x-text="user.fullName"></span>
            </span>
            <span x-show="myTasks.length > 0 || maintenanceCount > 0" style="display: none;"
                  class="shrink-0 w-2 h-2 rounded-full bg-rose-500"></span>
        </button>
    </div>

</aside>
