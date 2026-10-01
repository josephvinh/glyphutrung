<!-- MÀN HÌNH XIN PHÉP -->
<div data-module="leave" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="w-10 h-10 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <h2 class="text-xl font-black text-slate-800 tracking-tight">Xin Phép</h2>
    </div>

    <!-- 2. HAI TAB -->
    <div class="bg-white rounded-field p-1.5 shadow-sm border border-slate-100 flex gap-1.5 mb-5">
        <button @click="leaveTab = 'create'" type="button"
                class="flex-1 py-2.5 rounded-2xl font-bold text-xs transition-colors flex items-center justify-center gap-1.5"
                :class="leaveTab === 'create' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            <i data-lucide="file-plus" class="w-4 h-4"></i> Tạo đơn
        </button>
        <button x-show="canApproveLeave" @click="leaveTab = 'approve'" type="button"
                class="flex-1 py-2.5 rounded-2xl font-bold text-xs transition-colors flex items-center justify-center gap-1.5 relative"
                :class="leaveTab === 'approve' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
            <i data-lucide="check-check" class="w-4 h-4"></i> Duyệt đơn
            <span x-show="pendingLeaveCount > 0" style="display: none;"
                  class="min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-micro font-black flex items-center justify-center"
                  x-text="pendingLeaveCount"></span>
        </button>
    </div>

    <!-- ==========================================================
         TAB 1: TẠO ĐƠN
         Một màn duy nhất, hành vi tự đổi theo giờ chốt:
         chưa chốt = xin phép sớm, đã chốt = xin phép trễ.
         ========================================================== -->
    <div x-show="leaveTab === 'create'">

        <!-- Chọn buổi -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4 space-y-3">
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Ngày xin phép</label>
                <input x-model="leaveDate" type="date" min="2000-01-01" max="2100-12-31" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <p class="text-xs font-medium text-slate-500 mt-1.5 ml-1" x-text="formatFullDate(leaveDate)"></p>
            </div>
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Chương trình</label>
                <select x-model="leaveProgramId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">-- Chọn chương trình --</option>
                    <template x-for="p in leaveProgramsOnDate" :key="p.id">
                        <option :value="p.id" x-text="p.name + ' (' + p.startTime + ')'"></option>
                    </template>
                </select>
            </div>
        </div>

        <!-- Ngày không có chương trình -->
        <div x-show="leaveProgramsOnDate.length === 0" style="display: none;" class="text-center py-10 px-6 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="calendar-off" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
            <p class="text-slate-500 font-medium text-sm">Ngày này không có chương trình nào.</p>
        </div>

        <template x-if="leaveProgramsOnDate.length > 0 && leaveSession">
            <div>
                <!-- Băng trạng thái: quyết định luồng nào đang chạy -->
                <div class="rounded-card p-4 mb-4 border flex items-start"
                     :class="isLeaveExpired ? 'bg-slate-100 border-slate-200'
                            : (isLeavePastCutoff ? 'bg-amber-50 border-amber-100' : 'bg-emerald-50 border-emerald-100')">
                    <div class="w-10 h-10 shrink-0 rounded-2xl flex items-center justify-center mr-3"
                         :class="isLeaveExpired ? 'bg-slate-200 text-slate-500'
                                : (isLeavePastCutoff ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600')">
                        <i data-lucide="info" class="w-5 h-5"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-black leading-tight"
                           :class="isLeaveExpired ? 'text-slate-600' : (isLeavePastCutoff ? 'text-amber-700' : 'text-emerald-700')"
                           x-text="isLeaveExpired ? 'Đã hết hạn xin phép'
                                  : (isLeavePastCutoff ? 'Xin phép trễ (đã quá ' + leaveCutoff + ')' : 'Xin phép sớm — chốt lúc ' + leaveCutoff)"></p>
                        <p class="text-micro leading-tight mt-1"
                           :class="isLeaveExpired ? 'text-slate-500' : (isLeavePastCutoff ? 'text-amber-600' : 'text-emerald-600')"
                           x-text="isLeaveExpired ? 'Chỉ nộp được trong ngày diễn ra buổi đó.'
                                  : (isLeavePastCutoff ? 'Chỉ còn xin phép được cho các em đang bị đánh vắng không phép.' : 'Chọn bất kỳ em nào trong lớp để nộp đơn.')"></p>
                    </div>
                </div>

                <!-- Tìm kiếm -->
                <div x-show="!isLeaveExpired" style="display: none;" class="relative mb-4">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500"></i>
                    <input x-model="leaveSearch" type="text" placeholder="Tìm tên em cần xin phép..." class="w-full bg-white border border-slate-200 rounded-field py-3.5 pl-12 pr-10 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                    <button aria-label="Xóa ô tìm kiếm" x-show="leaveSearch !== ''" @click="leaveSearch = ''" style="display: none;" class="absolute right-1 top-1/2 -translate-y-1/2 p-2 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                        <div class="w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </div>
                    </button>
                </div>

                <!-- Danh sách em đủ điều kiện -->
                <div class="space-y-2.5">
                    <template x-for="student in leaveEligibleStudents" :key="student.id">
                        <button @click="openLeaveForm(student)" type="button"
                                style="content-visibility: auto; contain-intrinsic-size: auto 84px;"
                                class="w-full text-left bg-white rounded-field p-3.5 shadow-sm border border-slate-100 flex items-center gap-3 active:scale-[0.98] transition-all">
                            <div class="w-11 h-11 shrink-0 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500">
                                <i data-lucide="file-plus" class="w-5 h-5"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-micro font-bold text-blue-600 leading-tight">
                                    <span x-text="student.code"></span>
                                    <span class="text-slate-300 mx-1">•</span>
                                    <span class="text-slate-500 font-medium" x-text="student.className"></span>
                                </p>
                                <p class="text-sm font-black text-slate-800 leading-snug">
                                    <span class="font-normal text-slate-500" x-text="student.holyName"></span>
                                    <span x-text="student.name"></span>
                                </p>
                            </div>
                            <!-- Đã có đơn rồi thì báo luôn, khỏi bấm vào mới biết -->
                            <span x-show="leaveRequestOf(student.id)" style="display: none;"
                                  class="shrink-0 text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg border"
                                  :class="leaveRequestOf(student.id) ? leaveChipClass(leaveRequestOf(student.id).status) : ''"
                                  x-text="leaveRequestOf(student.id) ? leaveRequestOf(student.id).status : ''"></span>
                        </button>
                    </template>

                    <div x-show="!isLeaveExpired && leaveEligibleStudents.length === 0" style="display: none;" class="text-center py-10 bg-white rounded-card border border-slate-100 border-dashed">
                        <i data-lucide="user-check" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                        <p class="text-slate-500 font-medium text-sm px-6" x-text="isLeavePastCutoff ? 'Không còn em nào đang vắng không phép.' : 'Không tìm thấy em nào phù hợp.'"></p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- ==========================================================
         TAB 2: DUYỆT ĐƠN
         ========================================================== -->
    <div x-show="leaveTab === 'approve'" style="display: none;">

        <!-- Bộ lọc trạng thái -->
        <div class="flex gap-2 mb-4 overflow-x-auto hide-scrollbar bleed-x px-4 sm:px-6">
            <template x-for="f in [{v:'chờ duyệt',l:'Chờ duyệt'},{v:'đã duyệt',l:'Đã duyệt'},{v:'từ chối',l:'Từ chối'},{v:'',l:'Tất cả'}]" :key="f.v">
                <button @click="leaveFilter = f.v" type="button"
                        class="shrink-0 px-4 py-2 rounded-full font-bold text-xs border transition-colors"
                        :class="leaveFilter === f.v ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'"
                        x-text="f.l"></button>
            </template>
        </div>

        <div class="space-y-4 xl:space-y-0 xl:grid xl:grid-cols-2 xl:gap-4 xl:items-start">
            <!-- Skeleton khi đang nạp lại số liệu (vd đổi niên khoá) -->
            <template x-if="syncing && visibleLeaveRequests.length === 0">
                <div class="space-y-4 xl:contents">
                    <template x-for="i in 4" :key="'sk-lv-' + i">
                        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 min-w-0 pr-3">
                                    <div class="skeleton skeleton-text-sm w-20 mb-1"></div>
                                    <div class="skeleton skeleton-title w-2/3"></div>
                                </div>
                                <div class="skeleton skeleton-badge shrink-0"></div>
                            </div>
                            <div class="skeleton w-full h-20 rounded-2xl"></div>
                        </div>
                    </template>
                </div>
            </template>
            <template x-for="req in visibleLeaveRequests" :key="req.id">
                <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">

                    <div class="flex justify-between items-start mb-3">
                        <div class="min-w-0 pr-3">
                            <p class="text-micro font-bold text-blue-600 leading-tight">
                                <span x-text="studentById(req.studentId) ? studentById(req.studentId).code : ''"></span>
                                <span class="text-slate-300 mx-1">•</span>
                                <span class="text-slate-500 font-medium" x-text="studentById(req.studentId) ? studentById(req.studentId).className : ''"></span>
                            </p>
                            <h3 class="text-base font-black text-slate-800 leading-snug">
                                <span class="font-normal text-slate-500" x-text="studentById(req.studentId) ? studentById(req.studentId).holyName : ''"></span>
                                <span x-text="studentById(req.studentId) ? studentById(req.studentId).name : ''"></span>
                            </h3>
                        </div>
                        <span class="shrink-0 text-micro font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg border"
                              :class="leaveChipClass(req.status)" x-text="req.status"></span>
                    </div>

                    <!-- Buổi xin nghỉ -->
                    <div class="bg-slate-50 rounded-2xl p-3.5 mb-3 space-y-2">
                        <div class="flex items-center text-sm">
                            <i data-lucide="calendar" class="w-4 h-4 text-slate-500 mr-2.5 shrink-0"></i>
                            <span class="text-slate-600 font-medium" x-text="formatFullDate(req.date)"></span>
                        </div>
                        <div class="flex items-center text-sm">
                            <i data-lucide="clock" class="w-4 h-4 text-slate-500 mr-2.5 shrink-0"></i>
                            <span class="text-slate-600 font-medium"
                                  x-text="programById(req.programId) ? programById(req.programId).name + ' (' + programById(req.programId).startTime + ')' : ''"></span>
                        </div>
                        <div class="flex items-start text-sm">
                            <i data-lucide="message-square" class="w-4 h-4 text-slate-500 mr-2.5 mt-0.5 shrink-0"></i>
                            <span class="text-slate-700 font-semibold leading-snug" x-text="req.reason"></span>
                        </div>
                    </div>

                    <p class="text-micro text-slate-500 mb-3 ml-1">
                        Nộp bởi <span class="font-bold text-slate-500" x-text="req.createdBy"></span> lúc <span x-text="req.createdAt"></span>
                    </p>

                    <!-- Kết quả xử lý -->
                    <div x-show="req.status !== 'chờ duyệt'" style="display: none;"
                         class="rounded-2xl p-3 mb-1 border"
                         :class="req.status === 'đã duyệt' ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100'">
                        <p class="text-micro font-bold" :class="req.status === 'đã duyệt' ? 'text-emerald-700' : 'text-rose-700'">
                            <span x-text="req.status === 'đã duyệt' ? 'Đã duyệt bởi' : 'Từ chối bởi'"></span>
                            <span x-text="req.approvedBy"></span> · <span x-text="req.approvedAt"></span>
                        </p>
                        <p x-show="req.rejectReason" style="display: none;" class="text-micro text-rose-600 mt-1 leading-snug" x-text="'Lý do: ' + req.rejectReason"></p>
                    </div>

                    <!-- Nút xử lý -->
                    <div x-show="req.status === 'chờ duyệt'" class="grid grid-cols-2 gap-3 pt-1">
                        <button @click="openRejectForm(req)" type="button" class="py-3 bg-rose-50 text-rose-600 rounded-2xl font-bold text-sm border border-rose-100 active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                            <i data-lucide="x" class="w-4 h-4"></i> Từ chối
                        </button>
                        <button @click="approveLeave(req)" type="button" class="py-3 bg-emerald-600 text-white rounded-2xl font-bold text-sm shadow-md shadow-emerald-200 active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                            <i data-lucide="check" class="w-4 h-4"></i> Duyệt
                        </button>
                    </div>
                </div>
            </template>

            <div x-show="visibleLeaveRequests.length === 0 && !syncing" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="inbox" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Không có đơn nào.</p>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         POPUP NHẬP LÝ DO XIN PHÉP
         ========================================================== -->
    <div x-show="showLeaveModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showLeaveModal" x-transition.opacity.duration.300ms @click="showLeaveModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showLeaveModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
            <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
                <h3 class="text-lg font-black text-slate-800">Đơn xin phép</h3>
                <button aria-label="Đóng" @click="showLeaveModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-5 space-y-4">
                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100">
                    <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-1">Xin phép cho</p>
                    <p class="text-base font-black text-slate-800 leading-snug">
                        <span class="font-normal text-slate-500" x-text="leaveForm.studentId && studentById(leaveForm.studentId) ? studentById(leaveForm.studentId).holyName : ''"></span>
                        <span x-text="leaveForm.studentId && studentById(leaveForm.studentId) ? studentById(leaveForm.studentId).name : ''"></span>
                    </p>
                    <p class="text-xs font-medium text-slate-500 mt-2" x-text="leaveProgram ? leaveProgram.name + ' • ' + formatFullDate(leaveDate) : ''"></p>
                </div>
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lý do xin phép</label>
                    <textarea x-model="leaveForm.reason" rows="3" placeholder="VD: Em bị sốt, gia đình xin cho nghỉ..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
                </div>
            </div>
            <div class="p-4 border-t border-slate-100">
                <button @click="submitLeave()" type="button" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="send" class="w-5 h-5 mr-2"></i> Nộp đơn
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         POPUP LÝ DO TỪ CHỐI
         ========================================================== -->
    <div x-show="showRejectModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showRejectModal" x-transition.opacity.duration.300ms @click="showRejectModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showRejectModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
            <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
                <h3 class="text-lg font-black text-slate-800">Từ chối đơn</h3>
                <button aria-label="Đóng" @click="showRejectModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-5">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lý do từ chối</label>
                <textarea x-model="rejectForm.reason" rows="3" placeholder="Ghi rõ để GLV giải thích lại với phụ huynh..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 resize-none"></textarea>
            </div>
            <div class="p-4 border-t border-slate-100">
                <button @click="confirmReject()" type="button" class="w-full bg-rose-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-rose-200 flex justify-center items-center">
                    <i data-lucide="x-circle" class="w-5 h-5 mr-2"></i> Xác nhận từ chối
                </button>
            </div>
        </div>
    </div>

</div>
