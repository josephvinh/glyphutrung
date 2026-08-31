<!-- HEADER: Đẩy lên z-[100] để chống đè tuyệt đối -->
<header class="app-header bg-gradient-to-br from-blue-600 to-blue-700 text-white pb-8 sm:pb-10 px-5 sm:px-8 shadow-xl rounded-b-sheet sm:rounded-b-shell relative z-[100]">
    <div class="flex justify-between items-start">
        
        <!-- Khối thông tin cá nhân -->
        <div class="flex-1">
            <p class="text-xs text-blue-200 font-medium mb-1 tracking-wide uppercase">Chào ngày mới,</p>
            <h1 class="text-xl font-bold tracking-tight mb-1">
                <span x-text="user.holyName" class="mr-1"></span> 
                <span x-text="user.fullName"></span>
            </h1>
            <div class="inline-flex items-center bg-blue-800/40 px-3 py-1 rounded-full backdrop-blur-sm">
                <i data-lucide="shield-check" class="w-3 h-3 text-blue-300 mr-1.5"></i>
                <p class="text-xs text-blue-100 font-medium">
                    <!-- Chỉ hiện dấu • khi thật sự có lớp/khối đi kèm, nếu không
                         Quản Trị sẽ thấy "Quản trị viên •" với dấu chấm treo lơ lửng. -->
                    <span x-text="user.roleTitle"></span><span
                        x-show="user.assignedClass || user.managedBlock"
                        x-text="' • ' + (user.assignedClass || user.managedBlock)"></span>
                </p>
            </div>
        </div>

        <!-- ĐĂNG XUẤT
             Trước đây chỗ này là menu thả xuống, nhưng mục "Hồ sơ cá nhân"
             không dẫn đi đâu và mục "Đăng xuất" chỉ đóng menu chứ không gọi
             máy chủ — bấm vào vẫn còn nguyên phiên. Nay là nút đăng xuất
             thật, một chạm, có hỏi xác nhận trong logout(). -->
        <button @click="logout()" type="button" aria-label="Đăng xuất khỏi hệ thống"
                class="w-12 h-12 shrink-0 bg-white/20 hover:bg-white/30 rounded-full flex items-center justify-center border-2 border-white/40 shadow-sm active:scale-90 transition-all">
            <i data-lucide="log-out" class="text-white w-5 h-5 pointer-events-none"></i>
        </button>

    </div>
</header>
