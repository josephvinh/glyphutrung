<!-- ==========================================================
     HEADER: Thong tin ca nhan + actions
     - Hien tren moi man hinh, co mau nen dep
     ========================================================== -->
<header class="app-header bg-gradient-to-r from-slate-50 to-white border-b border-slate-200/80 px-4 sm:px-6 relative z-[100] select-none shadow-md">
    <div class="flex justify-between items-center gap-4 py-3 sm:py-4">

        <!-- Khoi thong tin ca nhan -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3">
                <!-- Text info -->
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-slate-500 font-medium mb-0.5">Chào trưởng,</p>
                    <h1 class="text-sm sm:text-base font-bold text-slate-900 leading-tight truncate">
                        <span x-text="user.holyName" class="mr-1 text-blue-600"></span>
                        <span x-text="user.fullName"></span>
                    </h1>
                    <div class="flex items-center gap-1 mt-0.5">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] sm:text-xs font-semibold">
                            <i data-lucide="shield-check" class="w-2.5 h-2.5 sm:w-3 sm:h-3 mr-0.5"></i>
                            <span x-text="user.roleTitle"></span>
                        </span>
                        <span x-show="!isUnrestrictedScope" class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] sm:text-xs font-medium">
                            <span x-text="myScopeLabel"></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <!-- Lam moi / dong bo -->
            <button @click="refreshApp()" :disabled="syncing" type="button" aria-label="Lam moi du lieu"
                    class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-800 flex items-center justify-center transition-all active:scale-95 disabled:opacity-50">
                <span :class="syncing ? 'animate-spin' : ''">
                    <i data-lucide="refresh-cw" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                </span>
            </button>

            <!-- Dang xuat -->
            <button @click="logout()" type="button" aria-label="Dang xuat"
                    class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-slate-100 hover:bg-rose-100 text-slate-600 hover:text-rose-600 flex items-center justify-center transition-all active:scale-95">
                <i data-lucide="log-out" class="w-4 h-4 sm:w-5 sm:h-5"></i>
            </button>
        </div>

    </div>
</header>
