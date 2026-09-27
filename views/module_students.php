<!-- MÀN HÌNH DANH SÁCH LỚP -->
<div data-module="students" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG GỘP (Thiếu nhi) -->
    <?php include __DIR__ . '/partial_children_tabs.php'; ?>

    <!-- 2. THANH TÌM KIẾM & BỘ LỌC -->
    <div class="mb-4 sticky top-0 z-10 bg-slate-50/95 backdrop-blur-sm -mx-4 px-4 pt-2 pb-3 sm:bg-transparent sm:backdrop-blur-none sm:-mx-0 sm:px-0 sm:pt-0 sm:pb-0 sm:static sm:z-auto">
        <div class="relative flex gap-2">
            <div class="relative flex-1 min-w-0">
                <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input x-model="searchQuery" type="text" placeholder="Tìm tên, mã số..." class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 pl-10 pr-10 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder:font-normal">
                <button aria-label="Xóa ô tìm kiếm" x-show="searchQuery !== ''" @click="searchQuery = ''" style="display: none;" class="tap-safe absolute right-1 top-1/2 -translate-y-1/2 p-2 flex items-center justify-center text-slate-400 active:scale-90 transition-transform">
                    <div class="w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </div>
                </button>
            </div>

            <button @click="showFilter = !showFilter" type="button" aria-label="Mở bộ lọc danh sách" :aria-expanded="showFilter ? 'true' : 'false'" :class="showFilter || hasActiveFilter ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'" class="w-12 shrink-0 rounded-field border shadow-sm flex items-center justify-center active:scale-90 transition-all relative">
                <i data-lucide="filter" class="w-5 h-5"></i>
                <!-- Chấm đỏ báo đang có bộ lọc bật, kể cả khi bảng lọc đã đóng lại -->
                <span x-show="hasActiveFilter && !showFilter" style="display: none;" class="absolute -top-1 -right-1 w-3 h-3 bg-rose-500 rounded-full border-2 border-slate-50"></span>
            </button>
        </div>

        <!-- BẢNG LỌC -->
        <div x-show="showFilter" style="display: none;" x-collapse class="mt-3 bg-white p-4 rounded-card shadow-sm border border-slate-100 border-t-4 border-t-blue-500">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div x-show="availableBlocks.length > 1" style="display: none;">
                    <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Khối</label>
                    <select x-model="filterBlock" @change="filterClass = ''" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                        <option value="">Tất cả các khối</option>
                        <template x-for="b in availableBlocks" :key="b">
                            <option :value="b" x-text="b"></option>
                        </template>
                    </select>
                </div>
                <div x-show="availableClasses.length > 1" style="display: none;">
                    <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Lớp</label>
                    <select x-model="filterClass" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                        <option value="">Tất cả các lớp</option>
                        <template x-for="cls in availableClasses" :key="cls">
                            <option :value="cls" x-text="cls"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Tình trạng</label>
                    <select x-model="filterStatus" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                        <option value="">Tất cả tình trạng</option>
                        <option value="đang sinh hoạt">Đang sinh hoạt</option>
                        <option value="dừng sinh hoạt">Dừng sinh hoạt</option>
                        <option value="chuyển xứ">Chuyển xứ</option>
                    </select>
                </div>
                <!-- Enhanced: Giới tính -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Giới tính</label>
                    <select x-model="filterGender" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                        <option value="">Tất cả</option>
                        <option value="1">Nam</option>
                        <option value="0">Nữ</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-end mt-4">
                <!-- Enhanced: Tuổi từ -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Tuổi từ</label>
                    <select x-model="filterAgeFrom" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                        <option value="">Tất cả</option>
                        <template x-for="age in ageOptions" :key="age">
                            <option :value="age" x-text="age + ' tuổi'"></option>
                        </template>
                    </select>
                </div>
                <!-- Enhanced: Tuổi đến -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Tuổi đến</label>
                    <select x-model="filterAgeTo" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700">
                        <option value="">Tất cả</option>
                        <template x-for="age in ageOptions" :key="age">
                            <option :value="age" x-text="age + ' tuổi'"></option>
                        </template>
                    </select>
                </div>
                <!-- Enhanced: Địa chỉ -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Địa chỉ</label>
                    <input x-model="filterAddress" type="text" placeholder="Tìm theo địa chỉ..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>

            <div class="mt-4 flex justify-end" x-show="hasActiveFilter" style="display: none;">
                <button @click="clearFilters()" type="button" class="flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-slate-200">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Xóa bộ lọc
                </button>
            </div>
        </div>
    </div>

    <!-- PHẠM VI GHI — cho biết TRƯỚC khi chọn file là sẽ vào lớp nào.
         Chỉ hiện với người bị giới hạn lớp; Quản Trị / Ban Điều Hành
         ghi được mọi lớp nên không cần nhắc. -->
    <div x-show="canEditModule('students') && writableClasses !== null" style="display: none;"
         class="mb-4 bg-sky-50 border border-sky-200 rounded-2xl p-3 flex items-start gap-2.5">
        <i data-lucide="info" class="w-4 h-4 text-sky-600 shrink-0 mt-0.5"></i>
        <p class="text-micro text-sky-900 leading-snug">
            <template x-if="writableClasses && writableClasses.length === 1">
                <span>Bạn nhập danh sách cho lớp
                    <span class="font-black" x-text="writableClasses[0]"></span>.
                    Bấm <span class="font-bold">Tải mẫu</span> để lấy file đã điền sẵn đúng lớp này.</span>
            </template>
            <template x-if="writableClasses && writableClasses.length > 1">
                <span>Bạn nhập được cho các lớp:
                    <span class="font-black" x-text="writableClasses.join(', ')"></span>.
                    Dòng nào ghi lớp khác sẽ bị bỏ qua.</span>
            </template>
            <template x-if="writableClasses && writableClasses.length === 0">
                <span>Bạn <span class="font-bold">chưa được phân công lớp</span> nên chưa nhập danh sách được.
                    Vui lòng liên hệ Ban Điều Hành.</span>
            </template>
        </p>
    </div>

    <!-- HÀNG NÚT CÔNG CỤ: THỐNG KÊ, VIEW MODE, NHẬP/XUẤT -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
            <div class="text-sm font-bold text-slate-500">Tổng: <span x-text="filteredStudents.length" class="text-blue-600 text-base"></span> em</div>
            <!-- Select All checkbox (only show when there are students) -->
            <div x-show="filteredStudents.length > 0" style="display: none;" class="flex items-center gap-1.5 ml-2">
                <input type="checkbox"
                       :checked="allDisplayedSelected"
                       @click="toggleSelectAll()"
                       class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                       title="Chọn tất cả">
                <span class="text-xs font-medium text-slate-500 select-none">Chọn tất cả</span>
            </div>
            <!-- Selected count badge -->
            <div x-show="selectedStudents.length > 0" style="display: none;">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">
                    <span x-text="selectedStudents.length"></span> đã chọn
                </span>
            </div>
        </div>
        <div class="flex gap-2">
            <!-- Grid/List Toggle -->
            <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-0.5">
                <button @click="setViewMode('grid')" type="button" :title="'Xem dạng lưới'"
                        :class="viewMode === 'grid' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="px-3 py-1.5 rounded-lg font-bold text-xs transition-all flex items-center gap-1.5">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Lưới</span>
                </button>
                <button @click="setViewMode('list')" type="button" :title="'Xem dạng danh sách'"
                        :class="viewMode === 'list' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="px-3 py-1.5 rounded-lg font-bold text-xs transition-all flex items-center gap-1.5">
                    <i data-lucide="list" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">DS</span>
                </button>
            </div>

            <!-- PDF Export Dropdown (Phase 4) -->
            <div class="relative" x-data="{ showPdfMenu: false }" @keydown.escape.window="showPdfMenu = false">
                <button @click="showPdfMenu = !showPdfMenu" type="button" title="Xuất PDF"
                        class="flex items-center gap-1 px-3 py-2 bg-rose-50 text-rose-600 rounded-xl font-bold text-xs border border-rose-100 hover:bg-rose-100 active:scale-95 transition-all">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">PDF</span>
                    <i data-lucide="chevron-down" class="w-3 h-3"></i>
                </button>
                <div x-show="showPdfMenu" @click.away="showPdfMenu = false" style="display: none;"
                     x-transition class="absolute right-0 top-full mt-1 bg-white rounded-xl shadow-lg border p-2 z-50 min-w-[160px]">
                    <button @click="exportPdf('list')" type="button"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 rounded-lg flex items-center gap-2">
                        <i data-lucide="list" class="w-4 h-4 text-slate-400"></i>
                        Danh sách lớp
                    </button>
                    <button @click="exportPdf('cards')" type="button"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 rounded-lg flex items-center gap-2">
                        <i data-lucide="id-card" class="w-4 h-4 text-slate-400"></i>
                        Thẻ từng em
                    </button>
                </div>
            </div>

            <!-- In thẻ QR nay là thẻ riêng trong module Thiếu Nhi -->
            <button x-show="canEditModule('students')" style="display: none;"
                    @click="openAddStudent()" type="button"
                    class="flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white rounded-xl font-bold text-xs active:scale-95 transition-transform border border-blue-600 shadow-md shadow-blue-200">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Thêm
            </button>

            <input type="file" x-ref="fileInput" accept=".csv,text/csv" class="hidden" @change="handleImport($event)">
            <!-- Hỏi hệ thống phân quyền, KHÔNG khoá cứng theo vai trò.
                 Trước đây chỗ này ghi ['admin','bdh'] nên GLV Chủ Nhiệm
                 dù được cấp quyền sửa vẫn không thấy nút — mà gọi thẳng
                 API thì lại nhập được. Giao diện và quyền phải nói cùng
                 một điều. -->
            <button x-show="canEditModule('students')" style="display: none;"
                    @click="$refs.fileInput.click()"
                    class="flex items-center gap-1.5 px-3 py-2 bg-emerald-50 text-emerald-600 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-emerald-100 shadow-sm">
                <i data-lucide="file-down" class="w-4 h-4"></i> Nhập
            </button>
            <!-- Danh sách rỗng thì nút này tải FILE MẪU, nên đổi nhãn luôn
                 để người dùng biết trước khi bấm, khỏi tưởng nút hỏng. -->
            <button @click="exportToCSV()"
                    :title="filteredStudents.length === 0
                            ? 'Tải file mẫu đúng định dạng để điền rồi nhập lại'
                            : 'Xuất danh sách đang xem ra file CSV'"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl font-bold text-xs active:scale-95 transition-transform shadow-sm border"
                    :class="filteredStudents.length === 0
                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                            : 'bg-blue-50 text-blue-600 border-blue-100'">
                <span x-show="filteredStudents.length > 0" style="display: none;" class="inline-flex items-center justify-center"><i data-lucide="file-up" class="w-4 h-4"></i></span>
                <span x-show="filteredStudents.length === 0" style="display: none;" class="inline-flex items-center justify-center"><i data-lucide="file-down" class="w-4 h-4"></i></span>
                <span x-text="filteredStudents.length === 0 ? 'Tải mẫu' : 'Xuất'"></span>
            </button>
        </div>
    </div>

    <!-- 3. DANH SÁCH THIẾU NHI -->
    <!-- Skeleton loading state - chỉ hiện khi đang sync và chưa có dữ liệu -->
    <?php include __DIR__ . '/partial_students_skeleton.php'; ?>

    <!-- Actual student list - hiện khi KHÔNG sync HOẶC đã có dữ liệu -->
    <div x-show="!syncing || students.length > 0" style="display: none;">

        <!-- ===== GRID VIEW (Card Layout) ===== -->
        <div x-show="viewMode === 'grid'" style="display: none;" class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-2 xl:grid-cols-3 xl:gap-4 xl:items-start">
        <template x-for="student in displayedStudents" :key="student.id">

            <!-- content-visibility: bỏ qua việc dựng hình các thẻ ngoài màn hình.
                 contain-intrinsic-size: báo trước chiều cao ước lượng để thanh cuộn khỏi giật. -->
            <div style="content-visibility: auto; contain-intrinsic-size: auto 420px;"
                 class="bg-white rounded-card p-5 shadow-sm border relative overflow-hidden group transition-all"
                 :class="isStudentSelected(student.id) ? 'border-blue-400 ring-2 ring-blue-200' : 'border-slate-100'">
                <!-- Bulk selection checkbox -->
                <div class="absolute top-3 left-3 z-10">
                    <input type="checkbox"
                           :checked="isStudentSelected(student.id)"
                           @click.stop="toggleStudentSelection(student.id)"
                           class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                </div>
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="min-w-0 pl-6">
                        <h3 class="text-base font-black text-slate-800 leading-tight">
                            <span x-text="student.holyName" class="font-normal text-slate-500 block mb-0.5"></span>
                            <span x-text="student.name"></span>
                        </h3>
                        <p class="text-xs font-bold text-blue-600 mt-1.5">
                            <span x-text="student.code"></span> <span class="text-slate-400 mx-1">•</span> <span class="text-slate-500" x-text="student.className"></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <span class="text-micro font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg" :class="{'bg-emerald-50 text-emerald-600': student.status === 'đang sinh hoạt', 'bg-rose-50 text-rose-600': student.status === 'dừng sinh hoạt', 'bg-slate-100 text-slate-500': student.status === 'chuyển xứ'}" x-text="student.status"></span>
                        <!-- Favorite star button -->
                        <button @click.stop="toggleFavorite(student.id)"
                                :title="isFavorite(student.id) ? 'Bỏ yêu thích' : 'Yêu thích'"
                                class="tap-safe w-8 h-8 rounded-full flex items-center justify-center transition-colors"
                                :class="isFavorite(student.id) ? 'text-amber-500 hover:text-amber-600' : 'text-slate-300 hover:text-amber-500'">
                            <i data-lucide="star" class="w-5 h-5" :fill="isFavorite(student.id) ? 'currentColor' : 'none'"></i>
                        </button>
                        <!-- Quick Actions: Copy Phone & Edit (luôn hiện trên mobile, hover trên desktop) -->
                        <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                            <!-- Copy phone dropdown -->
                            <div class="relative" x-data="{ showCopyMenu: false }" @click.away="showCopyMenu = false" @keydown.escape.window="showCopyMenu = false">
                                <button @click="showCopyMenu = !showCopyMenu" type="button" title="Sao chép SĐT" class="tap-safe w-8 h-8 bg-slate-50 hover:bg-amber-50 rounded-full flex items-center justify-center text-slate-400 hover:text-amber-600 border border-slate-200 transition-colors">
                                    <i data-lucide="clipboard" class="w-4 h-4"></i>
                                </button>
                                <div x-show="showCopyMenu" style="display: none;" x-transition class="absolute right-0 top-full mt-1 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-50 min-w-[140px]">
                                    <button @click="copyPhone(student.fatherPhone); showCopyMenu = false" type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2">
                                        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                                        <span class="font-medium">Cha:</span>
                                        <span class="text-slate-600" x-text="student.fatherPhone || '—'"></span>
                                    </button>
                                    <button @click="copyPhone(student.motherPhone); showCopyMenu = false" type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2">
                                        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                                        <span class="font-medium">Mẹ:</span>
                                        <span class="text-slate-600" x-text="student.motherPhone || '—'"></span>
                                    </button>
                                </div>
                            </div>
                            <button aria-label="Sửa hồ sơ thiếu nhi" x-show="canEditModule('students')" style="display: none;" @click="openEdit(student)" class="tap-safe w-8 h-8 bg-slate-50 hover:bg-blue-50 rounded-full flex items-center justify-center text-slate-400 hover:text-blue-600 border border-slate-200 transition-colors">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="space-y-2 mb-4 bg-slate-50 p-3.5 rounded-2xl">
                    <div class="flex items-center text-sm">
                        <i data-lucide="calendar" class="w-4 h-4 text-slate-400 mr-2.5"></i>
                        <span class="text-slate-600 font-medium" x-text="formatDate(student.birthDate)"></span>
                        <!-- Giới tính: dùng chấm ngăn cách cho gọn, khỏi tốn thêm một dòng -->
                        <span class="text-slate-300 mx-2">•</span>
                        <span class="text-slate-600 font-medium" :class="student.gender === 1 ? 'text-blue-600' : 'text-rose-500'" x-text="genderLabel(student.gender)"></span>
                    </div>
                    <div class="flex items-start text-sm">
                        <i data-lucide="map-pin" class="w-4 h-4 text-slate-400 mr-2.5 mt-0.5 shrink-0"></i>
                        <span class="text-slate-600 font-medium leading-tight" x-text="student.address"></span>
                    </div>
                </div>
                <div class="space-y-3 border-t border-slate-100 pt-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Tên Cha</p>
                            <p class="text-sm font-semibold text-slate-700" x-text="student.fatherName"></p>
                        </div>
                        <a :href="'tel:' + student.fatherPhone" :aria-label="'Gọi cha của ' + student.name" class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 active:scale-90 transition-transform shadow-sm border border-blue-100"><i data-lucide="phone" class="w-4 h-4 fill-blue-100"></i></a>
                    </div>
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Tên Mẹ</p>
                            <p class="text-sm font-semibold text-slate-700" x-text="student.motherName"></p>
                        </div>
                        <a :href="'tel:' + student.motherPhone" :aria-label="'Gọi mẹ của ' + student.name" class="w-10 h-10 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 active:scale-90 transition-transform shadow-sm border border-rose-100"><i data-lucide="phone" class="w-4 h-4 fill-rose-100"></i></a>
                    </div>
                </div>

                <!-- Nút xem hồ sơ tổng hợp -->
                <div class="border-t border-slate-100 pt-3 mt-3">
                    <button @click="openStudentProfile(student)" type="button"
                            class="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 rounded-xl font-bold text-xs transition-colors border border-slate-200 hover:border-blue-200">
                        <i data-lucide="folder-open" class="w-4 h-4"></i> Xem hồ sơ
                    </button>
                </div>
            </div>
        </template>
        </div>

        <!-- ===== LIST VIEW (Table Layout) ===== -->
        <div x-show="viewMode === 'list'" style="display: none;" class="bg-white rounded-card shadow-sm border border-slate-100 overflow-hidden">

            <!-- ĐIỆN THOẠI: bảng nhiều cột cuộn ngang rất khó đọc, nên dưới 640px
                 hiển thị dạng THẺ gọn; từ 640px trở lên mới dùng bảng đầy đủ.
                 (Danh sách nguồn giống hệt bảng: displayedStudents.) -->
            <div class="sm:hidden">
                <template x-for="(student, index) in displayedStudents" :key="'m-' + student.id">
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100"
                         :class="isStudentSelected(student.id) ? 'bg-blue-50' : ''">
                        <input type="checkbox"
                               :checked="isStudentSelected(student.id)"
                               @click="toggleStudentSelection(student.id)"
                               class="w-4 h-4 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">

                        <!-- Khối thông tin: bấm để mở hồ sơ em -->
                        <button type="button" @click="openStudentProfile(student)" class="flex-1 min-w-0 text-left">
                            <p class="text-micro font-bold text-blue-600 leading-tight truncate">
                                <span x-text="student.code"></span>
                                <span class="text-slate-300 mx-1">•</span>
                                <span class="text-slate-500 font-medium" x-text="student.className"></span>
                            </p>
                            <p class="text-sm font-bold text-slate-800 leading-snug truncate">
                                <span class="font-normal text-slate-500" x-text="student.holyName"></span>
                                <span x-text="student.name"></span>
                            </p>
                            <p class="text-micro text-slate-400 mt-1 truncate">
                                <span class="font-medium" :class="student.gender === 1 ? 'text-blue-600' : 'text-rose-500'" x-text="genderLabel(student.gender)"></span>
                                <span class="text-slate-300 mx-1">•</span>
                                <span x-text="calculateAge(student.birthDate) + ' tuổi'"></span>
                                <span class="text-slate-300 mx-1">•</span>
                                <span class="font-bold uppercase tracking-wider" :class="{'text-emerald-600': student.status === 'đang sinh hoạt', 'text-rose-600': student.status === 'dừng sinh hoạt', 'text-slate-500': student.status === 'chuyển xứ'}" x-text="student.status"></span>
                            </p>
                        </button>

                        <!-- Hành động -->
                        <div class="flex items-center gap-1 shrink-0">
                            <button @click="toggleFavorite(student.id)" type="button"
                                    :title="isFavorite(student.id) ? 'Bỏ yêu thích' : 'Yêu thích'"
                                    class="tap-safe w-9 h-9 rounded-full flex items-center justify-center transition-colors"
                                    :class="isFavorite(student.id) ? 'text-amber-500' : 'text-slate-300'">
                                <i data-lucide="star" class="w-5 h-5" :fill="isFavorite(student.id) ? 'currentColor' : 'none'"></i>
                            </button>
                            <!-- Sao chép SĐT (giống thẻ ở chế độ lưới) -->
                            <div class="relative" x-data="{ showCopyMenu: false }" @click.away="showCopyMenu = false" @keydown.escape.window="showCopyMenu = false">
                                <button @click="showCopyMenu = !showCopyMenu" type="button" title="Sao chép SĐT" class="tap-safe w-9 h-9 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 border border-slate-200 transition-colors">
                                    <i data-lucide="clipboard" class="w-4 h-4"></i>
                                </button>
                                <div x-show="showCopyMenu" style="display: none;" x-transition class="absolute right-0 top-full mt-1 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-50 min-w-[140px]">
                                    <button @click="copyPhone(student.fatherPhone); showCopyMenu = false" type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2">
                                        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                                        <span class="font-medium">Cha:</span>
                                        <span class="text-slate-600" x-text="student.fatherPhone || '—'"></span>
                                    </button>
                                    <button @click="copyPhone(student.motherPhone); showCopyMenu = false" type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2">
                                        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                                        <span class="font-medium">Mẹ:</span>
                                        <span class="text-slate-600" x-text="student.motherPhone || '—'"></span>
                                    </button>
                                </div>
                            </div>
                            <button x-show="canEditModule('students')" style="display: none;" @click="openEdit(student)" type="button" aria-label="Sửa hồ sơ" class="tap-safe w-9 h-9 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 border border-slate-200 transition-colors">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- MÁY TÍNH BẢNG / DESKTOP: bảng đầy đủ (ẩn trên điện thoại) -->
            <div class="overflow-x-auto hidden sm:block">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro w-8">
                                <input type="checkbox"
                                       :checked="allDisplayedSelected"
                                       @click="toggleSelectAll()"
                                       class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                            </th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">STT</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">Tên Thánh</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">Họ Tên</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">Lớp</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">GT</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">Tuổi</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">Tình trạng</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">SĐT Cha</th>
                            <th class="px-4 py-3 text-left font-bold text-slate-600 uppercase tracking-wide text-micro">SĐT Mẹ</th>
                            <th class="px-4 py-3 text-center font-bold text-slate-600 uppercase tracking-wide text-micro">Hành động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(student, index) in displayedStudents" :key="student.id">
                            <tr class="hover:bg-slate-50 transition-colors"
                                :class="isStudentSelected(student.id) ? 'bg-blue-50' : ''">
                                <td class="px-4 py-3">
                                    <input type="checkbox"
                                           :checked="isStudentSelected(student.id)"
                                           @click="toggleStudentSelection(student.id)"
                                           class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                </td>
                                <td class="px-4 py-3 text-slate-500 font-medium" x-text="index + 1"></td>
                                <td class="px-4 py-3 text-slate-600" x-text="student.holyName"></td>
                                <td class="px-4 py-3 font-semibold text-slate-800 hover:text-blue-600 cursor-pointer" @click="openStudentProfile(student)" title="Xem hồ sơ em" x-text="student.name"></td>
                                <td class="px-4 py-3 text-slate-600" x-text="student.className"></td>
                                <td class="px-4 py-3">
                                    <span :class="student.gender === 1 ? 'text-blue-600' : 'text-rose-500'" x-text="student.gender === 1 ? 'Nam' : 'Nữ'"></span>
                                </td>
                                <td class="px-4 py-3 text-slate-600" x-text="calculateAge(student.birthDate)"></td>
                                <td class="px-4 py-3">
                                    <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-lg" :class="{'bg-emerald-50 text-emerald-600': student.status === 'đang sinh hoạt', 'bg-rose-50 text-rose-600': student.status === 'dừng sinh hoạt', 'bg-slate-100 text-slate-500': student.status === 'chuyển xứ'}" x-text="student.status"></span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <span class="text-slate-600" x-text="student.fatherPhone || '—'"></span>
                                        <button @click="copyPhone(student.fatherPhone)" type="button" title="Sao chép SĐT Cha" class="p-1 text-slate-400 hover:text-amber-600 transition-colors">
                                            <i data-lucide="clipboard" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <span class="text-slate-600" x-text="student.motherPhone || '—'"></span>
                                        <button @click="copyPhone(student.motherPhone)" type="button" title="Sao chép SĐT Mẹ" class="p-1 text-slate-400 hover:text-amber-600 transition-colors">
                                            <i data-lucide="clipboard" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="openStudentProfile(student)" type="button" title="Xem hồ sơ" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <i data-lucide="folder-open" class="w-4 h-4"></i>
                                        </button>
                                        <button @click="toggleFavorite(student.id)" type="button" :title="isFavorite(student.id) ? 'Bỏ yêu thích' : 'Yêu thích'" class="p-1.5 transition-colors rounded-lg" :class="isFavorite(student.id) ? 'text-amber-500 hover:text-amber-600 hover:bg-amber-50' : 'text-slate-300 hover:text-amber-500 hover:bg-amber-50'">
                                            <i data-lucide="star" class="w-4 h-4" :fill="isFavorite(student.id) ? 'currentColor' : 'none'"></i>
                                        </button>
                                        <button x-show="canEditModule('students')" style="display: none;" @click="openEdit(student)" type="button" title="Sửa hồ sơ" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- NÚT TẢI THÊM: Chỉ hiện ra khi số lượng đang hiển thị nhỏ hơn tổng số kết quả lọc -->
        <div x-show="displayLimit < filteredStudents.length" style="display: none;" class="text-center pt-2 pb-6">
            <button @click="loadMore()" type="button" class="px-6 py-2.5 bg-slate-200 text-slate-600 rounded-full font-bold text-sm active:scale-95 transition-transform border border-slate-300 shadow-sm">
                Tải thêm danh sách...
            </button>
        </div>

        <!-- Empty state với icon rõ ràng -->
        <!-- Vai toàn đoàn (Quản Trị/BĐH) chưa chọn lọc: mời chọn khối/lớp,
             KHÔNG đổ cả đoàn ra cho nhẹ -->
        <div x-show="isUnrestrictedScope && searchQuery === '' && filterBlock === '' && filterClass === ''"
             style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="filter" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
            <p class="text-slate-600 font-semibold text-base mb-1">Chọn khối hoặc lớp để xem</p>
            <p class="text-slate-400 text-sm">Đoàn đông nên danh sách chỉ hiện khi bạn lọc theo khối/lớp, hoặc gõ tìm tên/mã.</p>
        </div>

        <!-- Đã lọc/tìm nhưng không ra kết quả -->
        <div x-show="filteredStudents.length === 0 && !(isUnrestrictedScope && searchQuery === '' && filterBlock === '' && filterClass === '')"
             style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="search-x" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
            <p class="text-slate-600 font-semibold text-base mb-1">Không tìm thấy dữ liệu phù hợp</p>
            <p class="text-slate-400 text-sm">Thử thay đổi từ khóa tìm kiếm hoặc xóa bộ lọc</p>
        </div>
    </div>

    <!-- ======================================================
         BULK ACTION BAR — Fixed bottom bar when items selected
         ====================================================== -->
    <div x-show="selectedStudents.length > 0"
         x-transition:enter="transform transition ease-out duration-300"
         x-transition:enter-start="translate-y-full opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transform transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="translate-y-full opacity-0"
         style="display: none;"
         class="fixed bottom-20 left-1/2 -translate-x-1/2 bg-blue-600 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 z-[150]">
        <span class="text-sm font-bold" x-text="selectedStudents.length + ' em đã chọn'"></span>
        <div class="w-px h-5 bg-blue-400"></div>
        <button @click="openBulkMoveModal()" type="button"
                class="flex items-center gap-1.5 bg-white text-blue-600 px-3 py-1.5 rounded-xl font-bold text-xs shadow-sm hover:bg-blue-50 transition-colors">
            <i data-lucide="arrow-right-left" class="w-3.5 h-3.5"></i>
            Chuyển lớp
        </button>
        <button x-show="canEditModule('students')" @click="confirmBulkDelete()" type="button"
                class="flex items-center gap-1.5 bg-rose-500 text-white px-3 py-1.5 rounded-xl font-bold text-xs shadow-sm hover:bg-rose-600 transition-colors">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            Xóa
        </button>
        <button @click="clearSelection()" type="button" aria-label="Bỏ chọn"
                class="p-1.5 hover:bg-blue-500 rounded-lg transition-colors">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

    <!-- ======================================================
         BULK MOVE MODAL
         ====================================================== -->
    <div x-show="showBulkMoveModal" style="display: none;"
         class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showBulkMoveModal" x-transition.opacity.duration.300
             @click="showBulkMoveModal = false"
             class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showBulkMoveModal"
             x-transition:enter="transform transition ease-out duration-300"
             x-transition:enter-start="translate-y-full opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transform transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-full opacity-0"
             class="modal-sheet relative w-full max-w-sm bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl h-[60dvh] sm:h-auto sm:max-h-[80vh] flex flex-col overflow-hidden">
            <div class="flex justify-center pt-3 pb-2 bg-white"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white">
                <h3 class="text-lg font-black text-slate-800">
                    Chuyển lớp cho <span x-text="selectedStudents.length"></span> em
                </h3>
                <button aria-label="Đóng" @click="showBulkMoveModal = false"
                        class="tap-safe w-8 h-8 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <p class="text-sm text-slate-600">
                    Các em sau sẽ được chuyển sang lớp mới:
                </p>
                <div class="max-h-40 overflow-y-auto bg-slate-50 rounded-xl p-3 space-y-1">
                    <template x-for="sid in selectedStudents" :key="sid">
                        <div class="flex items-center gap-2 text-sm text-slate-700">
                            <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                            <span x-text="studentIndex && studentIndex.get(sid) ? studentIndex.get(sid).name : '#' + sid"></span>
                        </div>
                    </template>
                </div>
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Chọn lớp đích</label>
                    <select x-model="targetClassForMove"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">— Chọn lớp —</option>
                        <template x-for="cls in classes" :key="cls.id">
                            <option :value="cls.name" x-text="cls.block + ' · ' + cls.name"></option>
                        </template>
                    </select>
                </div>
                <p x-show="targetClassForMove" style="display: none;"
                   class="text-xs text-slate-500">
                    Các em sẽ được ghi danh vào lớp <strong x-text="targetClassForMove"></strong> với tình trạng <strong>đang sinh hoạt</strong>.
                </p>
            </div>
            <div class="shrink-0 p-4 border-t border-slate-100 flex gap-3 bg-white"
                 style="padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px))">
                <button @click="showBulkMoveModal = false" type="button"
                        class="flex-1 py-3.5 bg-slate-100 text-slate-700 font-bold rounded-2xl active:scale-[0.98] transition-transform">
                    Hủy
                </button>
                <button @click="executeBulkMove()" type="button" :disabled="!targetClassForMove || busy"
                        class="flex-1 py-3.5 bg-blue-600 text-white font-bold rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 disabled:opacity-50 flex items-center justify-center gap-2">
                    <span x-show="!busy">Xác nhận chuyển lớp</span>
                    <span x-show="busy" style="display: none;">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Đang xử lý...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- POPUP CHỈNH SỬA -->
    <div x-show="showEditModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6"
         @keydown.window.ctrl.s.prevent="if(showEditModal) saveEdit()"
         @input.window="if(showEditModal) scheduleDraftSave()">
        <div x-show="showEditModal" x-transition.opacity.duration.300ms @click="tryCloseEdit()" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showEditModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl h-[88dvh] sm:h-[80dvh] flex flex-col overflow-hidden">
            <div class="flex justify-center pt-3 pb-2 bg-white"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-black text-slate-800" x-text="editModalTitle"></h3>
                    <!-- Draft indicator -->
                    <div x-show="hasDraft" style="display: none;" class="text-xs text-amber-600 flex items-center gap-1">
                        <i data-lucide="clock" class="w-3 h-3"></i>
                        <span>Đã lưu nháp</span>
                        <span x-text="'(' + getDraftAge() + ')'"></span>
                    </div>
                </div>
                <button aria-label="Đóng" @click="tryCloseEdit()" class="tap-safe w-8 h-8 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <input type="hidden" name="_csrf" :value="window.TNTT.csrfToken">
                <!-- Mã số: máy chủ tự cấp, không sửa được -->
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
                    <i data-lucide="hash" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    <span class="text-micro font-bold text-slate-500 uppercase">Mã số</span>
                    <span class="ml-auto text-sm font-black text-blue-600 tracking-wide" x-text="editData.code"></span>
                    <span x-show="editData.isNew" style="display: none;" class="text-micro text-slate-400">(tự cấp)</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Thánh</label><input x-model="editData.holyName" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></div>
                    <div class="col-span-2"><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Họ và Tên</label><input x-model="editData.name" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ngày Sinh</label><input x-model="editData.birthDate" type="date" min="1900-01-01" max="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giới tính</label>
                        <select x-model.number="editData.gender" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option :value="1">Nam</option>
                            <option :value="0">Nữ</option>
                        </select>
                    </div>
                </div>
                <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Địa chỉ</label><input x-model="editData.address" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></div>
                <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Cha</label><input x-model="editData.fatherName" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800"></div>
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">SĐT Cha</label><input x-model="editData.fatherPhone" type="tel" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Mẹ</label><input x-model="editData.motherName" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800"></div>
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">SĐT Mẹ</label><input x-model="editData.motherPhone" type="tel" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800"></div>
                </div>
                <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 pb-6">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp</label>
                        <select x-model="editData.className" :disabled="!canEditModule('students')" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-60">
                            <!-- Chỉ lớp mình được ghi vào. Chọn lớp ngoài phạm vi
                                 thì máy chủ cũng từ chối, liệt kê ra chỉ tổ gây hụt hẫng. -->
                            <template x-for="ten in (writableClasses === null ? classes.map(c => c.name) : writableClasses)" :key="ten">
                                <option :value="ten" x-text="ten"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tình trạng</label>
                        <select x-model="editData.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="đang sinh hoạt">Đang sinh hoạt</option>
                            <option value="dừng sinh hoạt">Dừng sinh hoạt</option>
                            <option value="chuyển xứ">Chuyển xứ</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="shrink-0 p-4 border-t border-slate-100 flex gap-3 bg-white"
                 style="padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px))">
                <button @click="tryCloseEdit()" type="button" class="flex-1 py-3.5 bg-slate-100 text-slate-700 font-bold rounded-2xl active:scale-[0.98] transition-transform">
                    Hủy
                </button>
                <button @click="saveEdit()" type="button" :disabled="busy" class="flex-1 py-3.5 bg-blue-600 text-white font-bold rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 disabled:opacity-50">
                    <span x-text="busy ? 'Đang lưu...' : 'Lưu thay đổi'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
