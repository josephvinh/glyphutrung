<!-- MÀN CÀI ĐẶT — chỉ Quản Trị Hệ Thống -->
<div data-module="settings" class="module-panel pt-16 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <h2 class="text-xl font-black text-slate-800 tracking-tight">Cài Đặt</h2>
    </div>

    <div>

        <!-- 2. CÁC THẺ
             Thẻ "Cá nhân" ai cũng thấy — đây là hồ sơ của chính mình.
             Bốn thẻ còn lại vẫn chỉ Quản Trị Hệ Thống mới có, y như cũ. -->
        <div class="bg-white rounded-field p-1.5 shadow-sm border border-slate-100 flex gap-1.5 mb-5 overflow-x-auto hide-scrollbar">
            <button @click="settingsTab = 'profile'" type="button"
                    class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5 relative"
                    :class="settingsTab === 'profile' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
                <i data-lucide="user" class="w-4 h-4"></i> Cá nhân
                <span x-show="myTasks.length > 0" style="display: none;"
                      class="w-2 h-2 rounded-full bg-rose-500"></span>
            </button>
            <button x-show="canViewLogs" style="display: none;" @click="settingsTab = 'logs'" type="button"
                    class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
                    :class="settingsTab === 'logs' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
                <i data-lucide="scroll-text" class="w-4 h-4"></i> Nhật ký
            </button>
            <button x-show="isAdmin" style="display: none;" @click="settingsTab = 'perms'" type="button"
                    class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
                    :class="settingsTab === 'perms' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
                <i data-lucide="shield-check" class="w-4 h-4"></i> Phân quyền
            </button>
            <button x-show="isAdmin" style="display: none;" @click="settingsTab = 'maintenance'" type="button"
                    class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5 relative"
                    :class="settingsTab === 'maintenance' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
                <i data-lucide="wrench" class="w-4 h-4"></i> Bảo trì
                <span x-show="maintenanceCount > 0" style="display: none;"
                      class="min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-micro font-black flex items-center justify-center"
                      x-text="maintenanceCount"></span>
            </button>
            <!-- Quy tắc Điểm — chỉ Admin/BĐH -->
            <button x-show="isAdmin || isBdh" style="display: none;" @click="settingsTab = 'scoreRules'" type="button"
                    class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
                    :class="settingsTab === 'scoreRules' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
                <i data-lucide="graduation-cap" class="w-4 h-4"></i> Điểm
            </button>
            <!-- Quy tắc Chuyên cần — chỉ Admin/BĐH -->
            <button x-show="isAdmin || isBdh" style="display: none;" @click="settingsTab = 'attendanceRules'" type="button"
                    class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
                    :class="settingsTab === 'attendanceRules' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
                <i data-lucide="calendar-check" class="w-4 h-4"></i> Chuyên cần
            </button>
        </div>

        <!-- THẺ: CÁ NHÂN (ai cũng thấy) -->
        <div x-show="settingsTab === 'profile'">
            <?php include __DIR__ . '/module_profile.php'; ?>
        </div>

        <!-- ==========================================================
             TAB 1: NHẬT KÝ THAO TÁC
             Một dòng một việc: ai — làm gì — lúc nào. Không bảng, không cột.
             ========================================================== -->
        <div x-show="settingsTab === 'logs'">

            <div class="relative mb-3">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500"></i>
                <input x-model="logSearch" type="text" placeholder="Tìm theo người hoặc nội dung..." class="w-full bg-white border border-slate-200 rounded-field py-3.5 pl-12 pr-4 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>

            <div class="flex gap-2 mb-4 overflow-x-auto hide-scrollbar bleed-x px-4 sm:px-6">
                <button @click="logFilter = ''" type="button"
                        class="shrink-0 px-4 py-2 rounded-full font-bold text-xs border transition-colors"
                        :class="logFilter === '' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'">Tất cả</button>
                <template x-for="k in Object.keys(logDefs)" :key="k">
                    <button @click="logFilter = k" type="button"
                            class="shrink-0 px-4 py-2 rounded-full font-bold text-xs border transition-colors"
                            :class="logFilter === k ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'"
                            x-text="logDefs[k].label"></button>
                </template>
            </div>

            <!-- Bộ lọc nâng cao: 4 ô trên 1 hàng (desktop), dọc (mobile) -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 mb-3 px-1">
                <!-- Người thực hiện -->
                <select x-model="logFilterActor"
                        class="tap-safe w-full bg-white border border-slate-200 rounded-field px-3 py-2.5 text-sm font-medium text-slate-700">
                    <option value="">Tất cả người</option>
                    <template x-for="a in logActorOptions" :key="a">
                        <option :value="a" x-text="a"></option>
                    </template>
                </select>

                <!-- Module -->
                <select x-model="logFilterModule"
                        class="tap-safe w-full bg-white border border-slate-200 rounded-field px-3 py-2.5 text-sm font-medium text-slate-700">
                    <option value="">Tất cả module</option>
                    <template x-for="m in logModuleOptions" :key="m">
                        <option :value="m" x-text="m"></option>
                    </template>
                </select>

                <!-- Từ ngày -->
                <input x-model="logFilterFrom"
                       type="date" aria-label="Từ ngày"
                       class="tap-safe w-full bg-white border border-slate-200 rounded-field px-3 py-2.5 text-sm font-medium text-slate-700">

                <!-- Đến ngày -->
                <input x-model="logFilterTo"
                       type="date" aria-label="Đến ngày"
                       class="tap-safe w-full bg-white border border-slate-200 rounded-field px-3 py-2.5 text-sm font-medium text-slate-700">
            </div>

            <!-- Nút Xóa bộ lọc — chỉ hiện khi có filter đang áp -->
            <div x-show="logFilterActor || logFilterModule || logFilterFrom || logFilterTo"
                 class="mb-3 px-1">
                <button @click="logFilterActor = ''; logFilterModule = ''; logFilterFrom = ''; logFilterTo = ''"
                        type="button"
                        class="text-xs font-bold text-blue-600 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-100 active:scale-95 transition-transform">
                    <i data-lucide="x" class="w-3 h-3 inline"></i> Xóa bộ lọc
                </button>
            </div>

            <div class="flex justify-between items-center mb-3 px-1 gap-2">
                <div class="text-sm font-bold text-slate-500">
                    <span x-text="filteredLogs.length" class="text-blue-600 text-base"></span> thao tác
                </div>
                <div class="flex items-center gap-1.5">
                    <!-- Dropdown Xuất: CSV / XLSX (giữ nguyên filter đang áp) -->
                    <div x-data="{ open: false }" @click.outside="open = false" class="relative" x-show="filteredLogs.length > 0">
                        <button @click="open = !open" type="button"
                                class="flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-700 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-blue-100 shadow-sm">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i> Xuất
                            <i data-lucide="chevron-down" class="w-3 h-3"></i>
                        </button>
                        <div x-show="open" x-cloak style="display: none;"
                             class="absolute right-0 mt-1 w-32 bg-white border border-slate-200 rounded-xl shadow-lg z-10 overflow-hidden">
                            <button @click="open = false; exportLogs('csv')" type="button"
                                    class="w-full text-left px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                                <i data-lucide="file-text" class="w-3.5 h-3.5"></i> CSV
                            </button>
                            <button @click="open = false; exportLogs('xlsx')" type="button"
                                    class="w-full text-left px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2 border-t border-slate-100">
                                <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> XLSX (Excel)
                            </button>
                        </div>
                    </div>
                    <button x-show="logs.length > 0" @click="clearLogs()" style="display: none;"
                            class="flex items-center gap-1.5 px-3 py-2 bg-white text-slate-500 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-slate-200 shadow-sm">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Xóa nhật ký
                    </button>
                </div>
            </div>

            <!-- Gom theo ngày -->
            <div class="space-y-5">
                <template x-for="g in groupedLogs" :key="g.day">
                    <div>
                        <p class="text-micro font-bold text-slate-500 uppercase tracking-wider mb-2 px-1" x-text="g.label"></p>
                        <div class="bg-white rounded-card border border-slate-100 shadow-sm overflow-hidden">
                            <template x-for="(l, i) in g.items" :key="l.id">
                                <div class="flex items-start gap-3 px-4 py-3"
                                     :class="[
                                         i > 0 ? 'border-t border-slate-50' : '',
                                         l.diff ? 'cursor-pointer active:bg-slate-50 transition-colors' : ''
                                     ]"
                                     :role="l.diff ? 'button' : ''"
                                     :tabindex="l.diff ? '0' : ''"
                                     :aria-label="l.diff ? 'Bấm để xem chi tiết thay đổi' : ''"
                                     @click="l.diff && openLogDiff(l)"
                                     @keydown.enter.prevent="l.diff && openLogDiff(l)"
                                     @keydown.space.prevent="l.diff && openLogDiff(l)">

                                    <div class="tap-safe w-8 h-8 shrink-0 rounded-xl flex items-center justify-center border"
                                         :class="logDef(l.action).cls">
                                        <i :data-lucide="logDef(l.action).icon" class="w-3.5 h-3.5"></i>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 leading-snug" x-text="l.what"></p>
                                        <p class="text-micro text-slate-500 leading-snug mt-0.5">
                                            <span class="font-semibold text-slate-500" x-text="l.actor"></span>
                                            <template x-if="l.detail">
                                                <span><span class="mx-1 text-slate-300">·</span><span x-text="l.detail"></span></span>
                                            </template>
                                            <template x-if="l.target_label">
                                                <span><span class="mx-1 text-slate-300">·</span><span class="text-blue-600" x-text="l.target_label"></span></span>
                                            </template>
                                        </p>
                                    </div>

                                    <span class="shrink-0 text-micro text-slate-500 font-medium pt-0.5" x-text="l.at.slice(11)"></span>
                                    <i x-show="l.diff" style="display: none;" data-lucide="chevron-right" class="w-4 h-4 text-slate-300 shrink-0 self-center"></i>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div x-show="filteredLogs.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                    <i data-lucide="scroll-text" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                    <p class="text-slate-500 font-medium text-sm mb-1" x-text="logs.length === 0 ? 'Chưa có thao tác nào được ghi.' : 'Không tìm thấy thao tác phù hợp.'"></p>
                    <p x-show="logs.length === 0" style="display: none;" class="text-slate-500 text-xs px-8 leading-snug">
                        Nhật ký ghi việc tạo, sửa, xóa, duyệt đơn, phát thông báo, đổi phân quyền — và việc sửa điểm danh sau giờ chốt.
                    </p>
                </div>
            </div>

            <!-- MODAL: xem diff (Trường/Cũ/Mới) của 1 dòng nhật ký.
                 Mở bằng openLogDiff(log) từ dòng có `diff` (parse từ diff_json). -->
            <div x-show="viewingLog" style="display: none;"
                 x-transition.opacity
                 class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4"
                 @click.self="closeLogDiff()"
                 @keydown.escape.window="closeLogDiff()">
                <div class="bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl shadow-xl max-h-[90vh] flex flex-col overflow-hidden"
                     role="dialog" aria-modal="true" aria-labelledby="logDiffTitle">

                    <div class="flex items-start gap-3 px-5 py-4 border-b border-slate-100 shrink-0">
                        <div class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center border"
                             :class="logDef(viewingLog && viewingLog.action).cls">
                            <i :data-lucide="logDef(viewingLog && viewingLog.action).icon" class="w-4 h-4"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p id="logDiffTitle" class="text-sm font-black text-slate-800 leading-snug" x-text="viewingLog && viewingLog.what"></p>
                            <p class="text-micro text-slate-500 mt-0.5">
                                <span class="font-semibold" x-text="viewingLog && viewingLog.actor"></span>
                                <span class="mx-1 text-slate-300">·</span>
                                <span x-text="viewingLog && viewingLog.at"></span>
                                <template x-if="viewingLog && viewingLog.ip">
                                    <span><span class="mx-1 text-slate-300">·</span><span class="font-mono" x-text="viewingLog.ip"></span></span>
                                </template>
                            </p>
                        </div>
                        <button @click="closeLogDiff()" type="button" aria-label="Đóng"
                                class="tap-safe w-9 h-9 shrink-0 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 active:scale-90 transition">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        <template x-if="viewingLog && viewingLog.target_label">
                            <div class="mb-3 px-3 py-2 bg-blue-50 border border-blue-100 rounded-xl text-micro text-blue-700">
                                <span class="font-bold">Đối tượng:</span>
                                <span x-text="viewingLog.target_label"></span>
                            </div>
                        </template>

                        <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <div class="grid grid-cols-[120px_1fr_1fr] bg-slate-50 text-micro font-bold text-slate-500 uppercase tracking-wider">
                                <div class="px-3 py-2 border-r border-slate-200">Trường</div>
                                <div class="px-3 py-2 border-r border-slate-200">Cũ</div>
                                <div class="px-3 py-2">Mới</div>
                            </div>
                            <template x-for="row in logDiffRows" :key="row.field">
                                <div class="grid grid-cols-[120px_1fr_1fr] text-xs"
                                     :class="row.changed ? 'bg-amber-50/30' : ''">
                                    <div class="px-3 py-2 border-t border-r border-slate-100 font-mono text-slate-500 break-all" x-text="row.field"></div>
                                    <div class="px-3 py-2 border-t border-r border-slate-100 break-all"
                                         :class="row.changed ? 'text-rose-600 line-through' : 'text-slate-500'"
                                         x-text="formatDiffValue(row.before)"></div>
                                    <div class="px-3 py-2 border-t border-slate-100 break-all"
                                         :class="row.changed ? 'text-emerald-700 font-semibold' : 'text-slate-500'"
                                         x-text="formatDiffValue(row.after)"></div>
                                </div>
                            </template>
                            <div x-show="logDiffRows.length === 0" class="px-3 py-4 text-center text-slate-500 text-xs">
                                Không có dữ liệu thay đổi.
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-3 border-t border-slate-100 shrink-0">
                        <button @click="closeLogDiff()" type="button"
                                class="w-full py-2.5 rounded-xl font-bold text-sm bg-slate-100 text-slate-600 active:scale-[0.98] transition-transform">
                            Đóng
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================================
             TAB 2: PHÂN QUYỀN
             Chọn vai trò trước, rồi chỉnh từng module — gọn hơn nhiều
             so với nhét bảng 9x5 vào màn hình điện thoại.
             ========================================================== -->
        <div x-show="settingsTab === 'perms'" style="display: none;">

            <div class="flex gap-2 mb-4 overflow-x-auto hide-scrollbar bleed-x px-4 sm:px-6">
                <template x-for="r in roleDefs" :key="r.value">
                    <button @click="permRoleTab = r.value" type="button"
                            class="shrink-0 px-4 py-2 rounded-full font-bold text-xs border transition-colors"
                            :class="permRoleTab === r.value ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'"
                            x-text="r.label"></button>
                </template>
            </div>

            <div class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
                <i data-lucide="info" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
                <p class="text-micro text-slate-600 leading-snug">
                    Đang chỉnh quyền cho <span class="font-bold" x-text="roleLabel(permRoleTab)"></span>.
                    Phạm vi dữ liệu vẫn theo cấp — <span class="font-bold">Toàn quyền</span> của GLV nghĩa là toàn quyền
                    <span class="italic">trong lớp mình</span>, không phải toàn đoàn.
                </p>
            </div>

            <div class="space-y-2.5">
                <template x-for="m in moduleDefs" :key="m.key">
                    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="tap-safe w-9 h-9 shrink-0 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500">
                                <i :data-lucide="m.icon" class="w-4 h-4"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-black text-slate-800 leading-snug" x-text="m.label"></p>
                                <p class="text-micro font-medium text-slate-500" x-text="m.area === 'bdh' ? 'Khu điều hành' : 'Khu nghiệp vụ'"></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="lv in ['none', 'view', 'edit']" :key="lv">
                                <button @click="setPermission(m.key, permRoleTab, lv)" type="button"
                                        class="py-2.5 rounded-xl font-bold text-micro border transition-colors"
                                        :class="permissions[m.key][permRoleTab] === lv ? permChipClass(lv) + ' ring-2 ring-offset-1 ring-slate-300' : 'bg-slate-50 text-slate-500 border-slate-200'"
                                        x-text="permLabel(lv)"></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <button @click="resetPermissions()" type="button" class="w-full mt-4 py-3 bg-white border border-slate-200 rounded-field font-bold text-sm text-slate-500 active:scale-[0.98] transition-transform flex items-center justify-center gap-2 shadow-sm">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Đặt lại mặc định
            </button>
        </div>

        <!-- ==========================================================
             TAB 3: BẢO TRÌ
             ========================================================== -->
        <div x-show="settingsTab === 'maintenance'" style="display: none;">

            <div class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
                <i data-lucide="info" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
                <p class="text-micro text-slate-600 leading-snug">
                    Tắt một chức năng thì mọi người thấy nút mờ kèm dấu bảo trì và không vào được.
                    Riêng <span class="font-bold">Quản Trị</span> vẫn vào bình thường để kiểm tra trước khi mở lại.
                </p>
            </div>

            <div class="space-y-2.5">
                <template x-for="m in moduleDefs" :key="m.key">
                    <div class="bg-white rounded-field p-4 shadow-sm border flex items-center gap-3"
                         :class="moduleEnabled[m.key] ? 'border-slate-100' : 'border-slate-300 border-dashed'">

                        <div class="w-10 h-10 shrink-0 rounded-2xl flex items-center justify-center border"
                             :class="moduleEnabled[m.key] ? 'bg-slate-50 border-slate-100 text-slate-500' : 'bg-slate-100 border-slate-200 text-slate-500'">
                            <i :data-lucide="m.icon" class="w-5 h-5"></i>
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-black leading-snug"
                               :class="moduleEnabled[m.key] ? 'text-slate-800' : 'text-slate-500'" x-text="m.label"></p>
                            <p class="text-micro font-medium"
                               :class="moduleEnabled[m.key] ? 'text-emerald-600' : 'text-slate-500'"
                               x-text="moduleEnabled[m.key] ? 'Đang hoạt động' : 'Đang bảo trì'"></p>
                        </div>

                        <button @click="toggleModuleEnabled(m.key)" type="button" role="switch" :aria-label="'Bật hoặc tắt chức năng ' + m.label" :aria-checked="moduleEnabled[m.key] ? 'true' : 'false'"
                                class="w-11 h-6 shrink-0 rounded-full relative transition-colors duration-200 ease-in-out"
                                :class="moduleEnabled[m.key] ? 'bg-emerald-500' : 'bg-slate-300'">
                            <div class="w-4 h-4 bg-white rounded-full absolute top-1 shadow-sm transition-transform duration-200 ease-in-out"
                                 :class="moduleEnabled[m.key] ? 'translate-x-6' : 'translate-x-1'"></div>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- QUY TẮC ĐIỂM HỌC TẬP -->
        <div x-show="settingsTab === 'scoreRules'" style="display: none;">
            <?php include __DIR__ . '/settings_score_rules.php'; ?>
        </div>

        <!-- QUY TẮC CHUYÊN CẦN -->
        <div x-show="settingsTab === 'attendanceRules'" style="display: none;">
            <?php include __DIR__ . '/settings_attendance_rules.php'; ?>
        </div>

    </div>

</div>
