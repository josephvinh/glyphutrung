<!-- ==========================================================
     APP CENTER: LƯỚI MENU CHỨC NĂNG
     Sinh ra từ moduleDefs, nên phân quyền + bảo trì tự áp dụng.
     - Một lưới PHẲNG, gộp mọi khu, không tiêu đề nhóm (visibleFlat()).
     - Ẩn trên máy tính (.home-fn-grid trong app.css) vì đã có thanh bên.
     ========================================================== -->
<div class="mb-10 space-y-5">

    <!-- VIỆC CẦN LÀM — ẩn với Quản trị + Ban Điều Hành ở Trang chính
         (họ không có việc lớp để nhắc; vẫn nhận nhắc việc cá nhân qua push). -->
    <template x-if="!['admin','bdh'].includes(user.role)">
        <?php include __DIR__ . '/partial_my_tasks.php'; ?>
    </template>

    <!-- BẢNG THI ĐUA (trang công khai, chỉ xem) — mở tab mới để chia sẻ cho các em -->
    <a href="bxh.php" target="_blank" rel="noopener"
       class="flex items-center gap-3 bg-gradient-to-br from-blue-600 to-blue-700 text-white rounded-card p-4 shadow-sm active:scale-[0.99] transition-transform">
        <span class="w-11 h-11 rounded-2xl bg-white/15 flex items-center justify-center text-2xl shrink-0">🏆</span>
        <span class="min-w-0">
            <span class="block font-black leading-tight">Bảng thi đua</span>
            <span class="block text-micro text-white/80">Xếp hạng tự động từ chuyên cần &amp; học tập · mở để khích lệ các em</span>
        </span>
        <i data-lucide="chevron-right" class="w-5 h-5 ml-auto shrink-0 opacity-80"></i>
    </a>

    <!-- LƯỚI CHỨC NĂNG — phẳng, đồng nhất kiểu nút -->
    <div x-show="visibleFlat().length > 0"
         class="home-fn-grid bg-white rounded-card p-5 sm:p-6 shadow-sm border border-slate-100">
        <div class="grid grid-cols-4 sm:grid-cols-6 gap-x-3 gap-y-5">
            <template x-for="m in visibleFlat()" :key="m.key">
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

</div>
