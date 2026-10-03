<!-- ==========================================================
     HEADER: Thông tin cá nhân + actions
     - Desktop: nằm ngang, gọn gàng
     - Mobile: full width với padding thoáng
     ========================================================== -->
<header class="app-header bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-4 sm:px-6 lg:px-8 relative z-[100] select-none">
    <div class="flex justify-between items-center gap-4 py-4">

        <!-- Khối thông tin cá nhân -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3">
                <!-- Avatar -->
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-black text-lg sm:text-xl shadow-md shrink-0">
                    <span x-text="user.fullName ? user.fullName.charAt(0).toUpperCase() : '?'"></span>
                </div>

                <!-- Text info -->
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-slate-500 font-medium mb-0.5">Chào ngày mới,</p>
                    <h1 class="text-base sm:text-lg font-bold text-slate-900 leading-tight truncate">
                        <span x-text="user.holyName" class="mr-1 text-blue-600"></span>
                        <span x-text="user.fullName"></span>
                    </h1>
                    <div class="inline-flex items-center gap-1.5 mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">
                            <i data-lucide="shield-check" class="w-3 h-3 mr-1"></i>
                            <span x-text="user.roleTitle"></span>
                        </span>
                        <span x-show="!isUnrestrictedScope" class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-medium">
                            <span x-text="myScopeLabel"></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 shrink-0">
            <!-- Làm mới / đồng bộ -->
            <button @click="refreshApp()" :disabled="syncing" type="button" aria-label="Làm mới dữ liệu"
                    class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-800 flex items-center justify-center transition-all active:scale-95 disabled:opacity-50">
                <span :class="syncing ? 'animate-spin' : ''">
                    <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                </span>
            </button>

            <!-- Đăng xuất -->
            <button @click="logout()" type="button" aria-label="Đăng xuất"
                    class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-slate-100 hover:bg-rose-100 text-slate-600 hover:text-rose-600 flex items-center justify-center transition-all active:scale-95">
                <i data-lucide="log-out" class="w-5 h-5"></i>
            </button>
        </div>

    </div>
</header>
