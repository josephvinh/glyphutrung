<!-- HEADER: Đẩy lên z-[100] để chống đè tuyệt đối -->
<header class="app-header glass-card pb-8 sm:pb-10 px-5 sm:px-8 shadow-xl rounded-b-sheet sm:rounded-b-shell relative z-[100] select-none">
    <div class="flex justify-between items-start">
        
        <!-- Khối thông tin cá nhân -->
        <div class="flex-1">
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1 tracking-wide uppercase">Chào ngày mới,</p>
            <h1 class="text-xl font-bold tracking-tight mb-1 text-slate-900 dark:text-slate-100">
                <span x-text="user.holyName" class="mr-1"></span> 
                <span x-text="user.fullName"></span>
            </h1>
            <div class="inline-flex items-center bg-slate-200/50 dark:bg-slate-800/50 px-3 py-1 rounded-full backdrop-blur-sm">
                <i data-lucide="shield-check" class="w-3 h-3 text-emerald-600 dark:text-emerald-400 mr-1.5"></i>
                <p class="text-xs text-slate-700 dark:text-slate-300 font-medium">
                    <!-- Chỉ hiện dấu • khi thật sự có lớp/khối đi kèm, nếu không
                         Quản Trị sẽ thấy "Quản trị viên •" với dấu chấm treo lơ lửng. -->
                    <span x-text="user.roleTitle"></span><span
                        x-show="!isUnrestrictedScope"
                        x-text="' • ' + myScopeLabel"></span>
                </p>
            </div>
        </div>

        <!-- Làm mới + Đăng xuất -->
        <div class="flex items-center gap-2">
            <!-- Làm mới / đồng bộ — thay cho kéo-xuống khi cài app ra màn hình chính -->
            <button @click="refreshApp()" :disabled="syncing" type="button" aria-label="Làm mới dữ liệu"
                    class="w-12 h-12 shrink-0 bg-slate-200/50 dark:bg-slate-800/50 hover:bg-slate-300/50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-200 rounded-full flex items-center justify-center border-2 border-slate-300/50 dark:border-slate-600/50 shadow-sm active:scale-90 transition-all disabled:opacity-60">
                <span class="flex" :class="syncing ? 'animate-spin' : ''">
                    <i data-lucide="refresh-cw" class="w-5 h-5 pointer-events-none"></i>
                </span>
            </button>

            <!-- Đăng xuất -->
            <button @click="logout()" type="button" aria-label="Đăng xuất khỏi hệ thống"
                    class="w-12 h-12 shrink-0 bg-slate-200/50 dark:bg-slate-800/50 hover:bg-slate-300/50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-200 rounded-full flex items-center justify-center border-2 border-slate-300/50 dark:border-slate-600/50 shadow-sm active:scale-90 transition-all">
                <i data-lucide="log-out" class="w-5 h-5 pointer-events-none"></i>
            </button>
        </div>

    </div>
</header>
