<!-- HỒ SƠ CÁ NHÂN
     Không còn là một màn riêng: nay là thẻ đầu tiên trong màn Cài Đặt,
     nên bỏ thanh điều hướng riêng (màn Cài Đặt đã có nút quay lại).
     Không đặt data-module ở đây: changeModule() sẽ ẩn mọi [data-module]
     không khớp, mà thẻ này do settingsTab điều khiển. -->
<div class="relative pb-24">

    <!-- 2. THẺ HỒ SƠ -->
    <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-card p-5 shadow-lg shadow-blue-200 mb-5 text-white">
        <div class="flex items-start gap-4">
            <div class="w-16 h-16 shrink-0 bg-white/20 rounded-2xl flex items-center justify-center border-2 border-white/40 backdrop-blur-sm">
                <i data-lucide="user" class="w-8 h-8"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-micro font-bold uppercase tracking-wider text-blue-200" x-text="myDanhXung"></p>
                <h3 class="text-lg font-black leading-tight mt-0.5">
                    <span class="font-normal text-blue-100" x-text="user.holyName"></span>
                    <span x-text="user.fullName"></span>
                </h3>
                <div class="inline-flex items-center bg-blue-800/40 px-2.5 py-1 rounded-lg mt-2 backdrop-blur-sm">
                    <i data-lucide="shield-check" class="w-3 h-3 text-blue-300 mr-1.5"></i>
                    <span class="text-micro font-bold text-blue-50" x-text="roleLabel(user.role)"></span>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-white/20 grid grid-cols-2 gap-3">
            <div>
                <p class="text-micro font-bold uppercase tracking-wide text-blue-200">Phụ trách</p>
                <p class="text-sm font-bold mt-0.5" x-text="myScopeLabel"></p>
            </div>
            <div>
                <p class="text-micro font-bold uppercase tracking-wide text-blue-200">Điện thoại</p>
                <p class="text-sm font-bold mt-0.5" x-text="user.phone || 'Chưa có'"></p>
            </div>
            <div>
                <p class="text-micro font-bold uppercase tracking-wide text-blue-200">Ngày sinh</p>
                <p class="text-sm font-bold mt-0.5" x-text="user.birthDate ? formatDate(user.birthDate) : 'Chưa có'"></p>
            </div>
        </div>

        <button @click="openProfileForm()" type="button" class="w-full mt-4 py-2.5 bg-white/20 backdrop-blur-sm rounded-xl font-bold text-xs active:scale-[0.98] transition-transform border border-white/30 flex items-center justify-center gap-2">
            <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Sửa thông tin
        </button>
    </div>

    <!-- 3. VIỆC CẦN LÀM -->
    <?php include __DIR__ . '/partial_my_tasks.php'; ?>

    <!-- Empty state riêng cho Cá nhân — chỉ hiện khi không có việc -->
    <div x-show="myTasks.length === 0" style="display: none;" class="text-center py-8 bg-white rounded-card border border-slate-100 border-dashed mb-5">
        <i data-lucide="party-popper" class="tap-safe w-9 h-9 mx-auto text-emerald-300 mb-2"></i>
        <p class="text-slate-500 font-medium text-sm">Xong hết việc rồi. Nghỉ ngơi thôi!</p>
    </div>

    <!-- 4. PHẠM VI PHỤ TRÁCH -->
    <div class="bg-white dark:bg-slate-700 rounded-card p-5 shadow-sm border border-slate-100 dark:border-slate-600 mb-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider" x-text="myScopeLabel"></h3>
            <button x-show="canAccess('students')" @click="openModule('students')" style="display: none;"
                    class="tap-safe text-micro font-bold text-blue-600 flex items-center gap-1 active:scale-95 transition-transform">
                Xem danh sách <i data-lucide="chevron-right" class="w-3 h-3"></i>
            </button>
        </div>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div>
                <p class="text-2xl font-black text-slate-800" x-text="statRoster.active"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Em đang học</p>
            </div>
            <div class="border-x border-slate-100">
                <p class="text-2xl font-black" :class="statSummary.rate >= 75 ? 'text-emerald-600' : 'text-amber-500'"
                   x-text="statSummary.rate + '%'"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Chuyên cần</p>
            </div>
            <div>
                <p class="text-2xl font-black text-rose-500" x-text="birthdaysInMonth.length"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Sinh nhật</p>
            </div>
        </div>
        <p class="text-micro text-slate-500 text-center mt-3" x-text="'Số liệu ' + statMonthLabel"></p>
    </div>

    <!-- 5. HOẠT ĐỘNG CỦA TÔI -->
    <div class="bg-white dark:bg-slate-700 rounded-card p-5 shadow-sm border border-slate-100 dark:border-slate-600 mb-5">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Hoạt động của tôi</h3>
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 shrink-0 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                <i data-lucide="activity" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-black text-slate-800 dark:text-white">
                    <span x-text="myActivity.week"></span> thao tác trong 7 ngày qua
                </p>
                <p class="text-micro font-medium text-slate-500 mt-0.5"
                   x-text="myActivity.total > 0 ? 'Tổng ' + myActivity.total + ' thao tác · gần nhất ' + myActivity.lastAt : 'Chưa có thao tác nào được ghi'"></p>
            </div>
        </div>
    </div>

    <!-- 7. THÔNG BÁO ĐẨY
         Bật theo TỪNG MÁY, không theo tài khoản: một người dùng cả điện
         thoại lẫn máy tính thì bật ở máy nào máy đó nhận. Vì vậy nút này
         luôn nói về "máy này", không nói về "tài khoản của bạn". -->
    <div class="bg-white dark:bg-slate-700 rounded-card p-5 shadow-sm border border-slate-100 dark:border-slate-600 mb-4">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Thông báo</h3>

        <!-- Trường hợp bật được -->
        <div x-show="tbHoTro" style="display: none;">
            <button @click="pushGat()" :disabled="tbDangChay" type="button"
                    class="w-full flex items-center gap-3 text-left active:scale-[0.98] transition-transform disabled:opacity-50">
                <div class="w-10 h-10 shrink-0 rounded-2xl flex items-center justify-center border transition-colors"
                     :class="tbDaBat ? 'bg-emerald-50 border-emerald-100 text-emerald-600' : 'bg-slate-50 border-slate-200 text-slate-500'">
                    <i :data-lucide="tbDaBat ? 'bell-ring' : 'bell-off'" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-700 dark:text-white">Báo ra màn hình máy này</p>
                    <p class="text-micro text-slate-500"
                       x-text="tbDangChay ? 'Đang xử lý…'
                               : (tbDaBat ? 'Đang bật. Có việc mới sẽ hiện ra ngay cả khi app đóng.'
                                          : 'Đang tắt. Bật để nhận thông báo, đơn chờ duyệt, nhắc điểm danh.')"></p>
                </div>
                <!-- Nút gạt -->
                <div class="w-11 h-6 shrink-0 rounded-full p-0.5 transition-colors"
                     :class="tbDaBat ? 'bg-emerald-500' : 'bg-slate-200'">
                    <div class="w-5 h-5 rounded-full bg-white shadow transition-transform"
                         :class="tbDaBat ? 'translate-x-5' : ''"></div>
                </div>
            </button>

            <div x-show="tbDaBat" style="display: none;" class="border-t border-slate-100 mt-3 pt-3 flex items-center justify-between gap-3">
                <p class="text-micro text-slate-500">
                    Đang bật trên <span class="font-bold text-slate-700" x-text="tbSoMay"></span> máy
                </p>
                <button @click="pushThu()" type="button"
                        class="shrink-0 px-3 py-2 bg-slate-50 text-slate-600 rounded-xl font-bold text-xs border border-slate-200 active:scale-95 transition-transform">
                    Gửi thử
                </button>
            </div>

        </div>

        <!-- Bị chặn thì phải nói rõ, kể cả khi đăng ký service worker cũng
             hỏng theo — nếu không, người dùng chỉ thấy "không hỗ trợ" và
             không biết là do chính mình đã bấm Chặn lúc trước. -->
        <p x-show="tbBiChan" style="display: none;"
           class="text-micro text-amber-700 bg-amber-50 border border-amber-100 rounded-2xl px-3 py-2 mt-3 leading-relaxed">
            Trình duyệt đang chặn thông báo của trang này. Mở lại ở:
            Cài đặt trình duyệt → Thông báo → tìm địa chỉ trang này → Cho phép.
        </p>

        <!-- iPhone chưa thêm vào màn hình chính: chỉ dẫn, đừng để nút chết -->
        <div x-show="!tbHoTro && tbCanCaiApp" style="display: none;"
             class="flex items-start gap-3">
            <div class="w-10 h-10 shrink-0 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                <i data-lucide="square-arrow-out-up-right" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-slate-700 dark:text-white">Thêm app vào Màn hình chính trước</p>
                <p class="text-micro text-slate-500 leading-relaxed">
                    Trên iPhone, Apple chỉ cho nhận thông báo khi app đã nằm ở Màn hình chính.
                    Bấm nút Chia sẻ ở thanh dưới Safari → <span class="font-bold">Thêm vào MH chính</span>,
                    rồi mở app từ biểu tượng vừa tạo và quay lại đây.
                </p>
            </div>
        </div>

        <!-- Máy/trình duyệt không làm được -->
        <div x-show="!tbHoTro && !tbCanCaiApp && !tbBiChan" style="display: none;" class="flex items-start gap-3">
            <div class="w-10 h-10 shrink-0 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500">
                <i data-lucide="bell-off" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-slate-700 dark:text-white">Máy này chưa nhận được thông báo</p>
                <p class="text-micro text-slate-500 leading-relaxed">
                    Trình duyệt không hỗ trợ, hoặc máy chủ chưa cấu hình. Thử mở app bằng
                    Chrome hoặc Safari bản mới.
                </p>
            </div>
        </div>
    </div>

    <!-- 8. PHÂN CÔNG CỦA BẠN -->
    <div class="bg-white dark:bg-slate-700 rounded-card p-5 shadow-sm border border-slate-100 dark:border-slate-600 mb-5">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Phân công của bạn</h3>
        <div class="space-y-2">
            <template x-for="a in assignments" :key="a.id">
                <div class="flex items-center gap-2 bg-slate-50 rounded-xl px-3 py-2">
                    <span class="text-micro font-bold uppercase px-2 py-0.5 rounded border"
                          :class="roleChipClass(a.role)" x-text="roleLabel(a.role)"></span>
                    <span class="text-micro text-slate-600 flex-1"
                          x-text="(a.blockName || a.className || 'Toàn đoàn')"></span>
                    <span x-show="a.isPrimary" class="text-micro font-black text-amber-600">★</span>
                </div>
            </template>
            <p x-show="assignments.length === 0" class="text-sm text-slate-500 italic">Chưa có phân công nào.</p>
        </div>
    </div>

    <!-- 9. TÀI KHOẢN -->
    <div class="bg-white dark:bg-slate-700 rounded-card p-5 shadow-sm border border-slate-100 dark:border-slate-600">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Tài khoản</h3>

        <div class="space-y-3">
            <button @click="openChangePassword()" type="button"
                    class="w-full flex items-center gap-3 text-left active:scale-[0.98] transition-transform">
                <div class="w-10 h-10 shrink-0 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                    <i data-lucide="key-round" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-700 dark:text-white">Đổi mật khẩu</p>
                    <p class="text-micro text-slate-500">Nên đổi ngay nếu Ban Điều Hành vừa cấp lại cho bạn</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 shrink-0"></i>
            </button>

            <!-- Đăng nhập sinh trắc: nút gạt kiểu iOS, tự bật/tắt, không popup.
                 Gạt BẬT -> hỏi FaceID/vân tay rồi đăng ký; gạt TẮT -> gỡ khoá.
                 Trạng thái nút gạt chính là phản hồi (thành công thì xanh, huỷ
                 thì bật lại). x-data cục bộ, không đụng component chính. -->
            <div class="w-full flex items-center gap-3 border-t border-slate-100 pt-3"
                 x-data="{
                    on: false, busy: false, ho_tro: (typeof window.PublicKeyCredential !== 'undefined'),
                    async init() { if (this.ho_tro) this.on = await window.Passkey.status(); },
                    async gat() {
                        if (this.busy || !this.ho_tro) return;
                        this.busy = true;
                        const muonBat = !this.on;
                        if (muonBat) this.on = await window.Passkey.register({ silent: true });
                        else         this.on = !(await window.Passkey.remove({ silent: true }));
                        this.busy = false;
                    }
                 }">
                <div class="w-10 h-10 shrink-0 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-700 dark:text-white">Đăng nhập bằng Vân tay / Face ID</p>
                    <p class="text-micro font-medium"
                       :class="on ? 'text-emerald-600' : 'text-slate-500'"
                       x-text="!ho_tro ? 'Thiết bị không hỗ trợ' : (busy ? 'Đang xử lý…' : (on ? 'Đang bật' : 'Đang tắt'))">Đang tắt</p>
                </div>
                <button @click="gat()" type="button" role="switch" aria-label="Bật hoặc tắt đăng nhập sinh trắc"
                        :aria-checked="on ? 'true' : 'false'" :disabled="busy || !ho_tro"
                        class="w-11 h-6 shrink-0 rounded-full relative transition-colors duration-200 ease-in-out disabled:opacity-50"
                        :class="on ? 'bg-emerald-500' : 'bg-slate-300'">
                    <div class="w-4 h-4 bg-white rounded-full absolute top-1 shadow-sm transition-transform duration-200 ease-in-out"
                         :class="on ? 'translate-x-6' : 'translate-x-1'"></div>
                </button>
            </div>

            <button @click="logout()" type="button" class="w-full flex items-center gap-3 border-t border-slate-100 pt-3 text-left active:scale-[0.98] transition-transform">
                <div class="w-10 h-10 shrink-0 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-rose-600">Đăng xuất</p>
                    <p class="text-micro text-slate-500">Kết thúc phiên làm việc</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 shrink-0"></i>
            </button>
        </div>
    </div>

    <!-- ==========================================================
         POPUP SỬA THÔNG TIN CÁ NHÂN
         ========================================================== -->
    <div x-show="showProfileForm" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showProfileForm" x-transition.opacity.duration.300ms @click="showProfileForm = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showProfileForm" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
            <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
                <h3 class="text-lg font-black text-slate-800 dark:text-white">Sửa thông tin</h3>
                <button aria-label="Đóng" @click="showProfileForm = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <div class="p-5 space-y-4">
                <input type="hidden" name="_csrf" :value="window.TNTT.csrfToken">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-1">
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Thánh</label>
                        <input x-model="profileForm.holyName" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Họ và Tên <span class="text-rose-500">*</span></label>
                        <input x-model="profileForm.fullName" type="text" required aria-required="true"
                               aria-describedby="fullName-hint"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <p id="fullName-hint" class="text-micro text-slate-500 mt-1">Bắt buộc nhập</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Số điện thoại</label>
                        <input x-model="profileForm.phone" type="tel" pattern="0[0-9]{9}" placeholder="0xxxxxxxxx"
                               x-ref="phoneInput"
                               :aria-describedby="profileForm.phone && !/^0\d{9}$/.test(profileForm.phone) ? 'phone-error' : undefined"
                               @input="if (profileForm.phone) { profileForm.phone = profileForm.phone.replace(/^\+84/, '0'); }"
                               class="w-full bg-slate-50 border rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500"
                               :class="profileForm.phone && !/^0\d{9}$/.test(profileForm.phone) ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                        <p x-show="profileForm.phone && !/^0\d{9}$/.test(profileForm.phone)" id="phone-error" class="text-micro mt-1 text-rose-600" style="display: none;">
                            SĐT không hợp lệ (cần 10 số, bắt đầu bằng 0)
                        </p>
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ngày sinh</label>
                        <input x-model="profileForm.birthDate" type="date" min="1900-01-01" max="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Vai trò và phân công không tự sửa được -->
                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Danh xưng</span>
                        <span class="text-xs font-bold text-slate-700" x-text="myDanhXung"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Chức vụ</span>
                        <span class="text-xs font-bold text-slate-700" x-text="user.roleTitle"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Phụ trách</span>
                        <span class="text-xs font-bold text-slate-700" x-text="myScopeLabel"></span>
                    </div>
                    <p class="text-micro text-slate-500 leading-snug pt-1 border-t border-slate-200">
                        Ba mục trên do Ban Điều Hành phân công bên màn Khối &amp; Lớp, bạn không tự đổi được.
                    </p>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100">
                <button @click="saveProfile()" type="button" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu thông tin
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
     CỬA SỔ ĐỔI MẬT KHẨU
     Đặt ngoài khối nội dung để nổi trên toàn màn hình.
     ========================================================== -->
<div x-show="showChangePw" style="display: none;" class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center">

    <div x-show="showChangePw" x-transition.opacity.duration.300ms
         @click="showChangePw = false"
         class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

    <div x-show="showChangePw"
         x-transition:enter="transform transition ease-out duration-300"
         x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0"
         x-transition:enter-end="translate-y-0 sm:opacity-100"
         class="modal-sheet relative w-full max-w-md bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">

        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-800 dark:text-white">Đổi mật khẩu</h3>
            <button aria-label="Đóng" @click="showChangePw = false"
                    class="tap-safe w-9 h-9 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center active:scale-90 transition-transform">
                <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
            </button>
        </div>

        <div class="p-5 space-y-4">

            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Mật khẩu hiện tại</label>
                <input x-model="pwForm.current" :type="pwShow ? 'text' : 'password'"
                       autocomplete="current-password"
                       class="w-full bg-slate-50 border border-slate-200 rounded-field px-3 py-3 text-sm font-semibold text-slate-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition-all">
            </div>

            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5" for="pw-new">Mật khẩu mới</label>
                <input id="pw-new" x-model="pwForm.next" :type="pwShow ? 'text' : 'password'"
                       autocomplete="new-password" aria-describedby="pw-strength"
                       class="w-full bg-slate-50 border border-slate-200 rounded-field px-3 py-3 text-sm font-semibold text-slate-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition-all">
                <p id="pw-strength" class="text-micro mt-1.5" aria-live="polite"
                   :class="pwForm.next.length === 0 ? 'text-slate-500'
                          : (pwForm.next.length < 6 ? 'text-rose-600' : 'text-emerald-600')"
                   x-text="pwForm.next.length === 0 ? 'Từ 6 ký tự trở lên'
                          : (pwForm.next.length < 6 ? 'Còn thiếu ' + (6 - pwForm.next.length) + ' ký tự'
                          : 'Đủ dài')"></p>
            </div>

            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5" for="pw-confirm">Nhập lại mật khẩu mới</label>
                <input id="pw-confirm" x-model="pwForm.confirm" :type="pwShow ? 'text' : 'password'"
                       autocomplete="new-password" aria-describedby="pw-match"
                       class="w-full bg-slate-50 border rounded-field px-3 py-3 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500/30 transition-all"
                       :class="pwForm.confirm !== '' && pwForm.confirm !== pwForm.next
                              ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-slate-50'">
                <p id="pw-match" x-show="pwForm.confirm !== '' && pwForm.confirm !== pwForm.next" style="display: none;"
                   class="text-micro text-rose-600 mt-1.5" aria-live="assertive">Hai ô chưa khớp nhau.</p>
            </div>

            <button @click="pwShow = !pwShow" type="button"
                    class="flex items-center gap-2 text-micro font-bold text-slate-500 active:scale-95 transition-transform">
                <svg x-show="!pwShow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="pwShow" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                <span x-text="pwShow ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"></span>
            </button>

        </div>

        <div class="p-4 border-t border-slate-100">
            <button @click="submitChangePassword()" :disabled="pwBusy"
                    class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center disabled:opacity-60">
                <i data-lucide="check" class="w-5 h-5 mr-2"></i>
                <span x-text="pwBusy ? 'Đang lưu…' : 'Đổi mật khẩu'"></span>
            </button>
        </div>
    </div>
</div>
