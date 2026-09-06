<!-- ==========================================================
     APP CENTER: LƯỚI MENU CHỨC NĂNG
     Sinh ra từ moduleDefs trong app.js, nên phân quyền và công tắc
     bảo trì tự áp dụng — không phải sửa tay từng nút ở đây.
     ========================================================== -->
<div class="mb-10 space-y-5">

    <!-- VIỆC CẦN LÀM -->
    <?php include __DIR__ . '/partial_my_tasks.php'; ?>

    <!-- KHU VỰC CHỨC NĂNG -->
    <div x-show="visibleModules('glv').length > 0" class="bg-white rounded-card p-5 sm:p-6 shadow-sm border border-slate-100">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Chức năng</h3>

        <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
            <template x-for="m in visibleModules('glv')" :key="m.key">
                <button @click="openModule(m.key)"
                        class="flex flex-col items-center group active:scale-90 transition-transform"
                        :class="isUnderMaintenance(m.key) ? 'opacity-40' : ''">
                    <div class="w-14 h-14 bg-slate-50 rounded-field shadow-sm border border-slate-100 flex items-center justify-center mb-2 relative"
                         :class="isUnderMaintenance(m.key) ? 'text-slate-400' : m.color">
                        <i :data-lucide="m.icon" class="w-6 h-6"></i>

                        <!-- Chấm đỏ nhắc việc -->
                        <span x-show="!isUnderMaintenance(m.key) && moduleBadge(m.key) > 0" style="display: none;"
                              class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 rounded-full bg-rose-500 text-white text-micro font-black flex items-center justify-center border-2 border-white shadow-sm"
                              x-text="moduleBadge(m.key)"></span>

                        <!-- Đang bảo trì -->
                        <span x-show="!moduleEnabled[m.key]" style="display: none;"
                              class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-slate-700 text-white flex items-center justify-center border-2 border-white shadow-sm">
                            <i data-lucide="wrench" class="w-2.5 h-2.5"></i>
                        </span>
                    </div>
                    <span class="text-micro font-semibold text-center leading-tight"
                          :class="isUnderMaintenance(m.key) ? 'text-slate-400' : 'text-slate-600'"
                          x-text="m.label"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- KHU VỰC BAN ĐIỀU HÀNH -->
    <div x-show="visibleModules('bdh').length > 0" style="display: none;" class="bg-white rounded-card p-5 sm:p-6 shadow-sm border border-slate-100">
        <!-- Tiêu đề khu vực theo đúng việc người đó làm được ở đây,
             không phải ai vào cũng là "Ban Điều Hành" -->
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4"
            x-text="['admin', 'bdh'].includes(user.role) ? 'Ban Điều Hành'
                   : (user.role === 'truong_khoi' ? 'Quản lý khối' : 'Thông tin chung')"></h3>

        <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
            <template x-for="m in visibleModules('bdh')" :key="m.key">
                <button @click="openModule(m.key)"
                        class="flex flex-col items-center group active:scale-90 transition-transform"
                        :class="isUnderMaintenance(m.key) ? 'opacity-40' : ''">
                    <div class="w-14 h-14 rounded-field shadow-md flex items-center justify-center mb-2 relative"
                         :class="isUnderMaintenance(m.key) ? 'bg-slate-300 text-white' : 'bg-slate-800 text-white'">
                        <i :data-lucide="m.icon" class="w-6 h-6"></i>
                        <span x-show="!moduleEnabled[m.key]" style="display: none;"
                              class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-slate-700 text-white flex items-center justify-center border-2 border-white shadow-sm">
                            <i data-lucide="wrench" class="w-2.5 h-2.5"></i>
                        </span>
                    </div>
                    <span class="text-micro font-semibold text-center leading-tight"
                          :class="isUnderMaintenance(m.key) ? 'text-slate-400' : 'text-slate-600'"
                          x-text="m.label"></span>
                </button>
            </template>
        </div>
    </div>

</div>
