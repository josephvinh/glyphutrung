<!-- MÀN HÌNH LỊCH TRÌNH - Hiển thị chương trình/sự kiện trên lịch tháng -->
<div data-module="calendar" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="w-10 h-10 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Lịch Trình</h2>
        </div>
        <div class="text-micro font-bold text-slate-500" x-text="programs.length + ' chương trình'"></div>
    </div>

    <!-- 2. ĐIỀU HƯỚNG THÁNG -->
    <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
        <div class="flex items-center gap-2">
            <button aria-label="Tháng trước" @click="prevMonth()" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform hover:bg-slate-100">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
            <div class="flex-1 text-center">
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Lịch</p>
                <p class="text-lg font-black text-slate-800 leading-tight" x-text="calendarMonthLabel"></p>
            </div>
            <button aria-label="Tháng sau" @click="nextMonth()" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform hover:bg-slate-100">
                <i data-lucide="chevron-right" class="w-5 h-5"></i>
            </button>
        </div>
    </div>

    <!-- 3. LƯỚI LỊCH THÁNG -->
    <div class="bg-white rounded-card shadow-sm border border-slate-100 overflow-hidden mb-4">
        <!-- Hàng ngày trong tuần -->
        <div class="grid grid-cols-7 bg-slate-50 border-b border-slate-100">
            <template x-for="day in weekDays" :key="day">
                <div class="py-2.5 text-center">
                    <span class="text-micro font-bold text-slate-500 uppercase tracking-wider" x-text="day"></span>
                </div>
            </template>
        </div>

        <!-- Các ngày trong tháng -->
        <div class="grid grid-cols-7">
            <template x-for="date in calendarDays" :key="date.key">
                <div class="min-h-[80px] sm:min-h-[100px] p-1.5 sm:p-2 border-t border-r border-slate-100 relative transition-colors hover:bg-slate-50"
                     :class="date.isEmpty ? 'bg-slate-50/50' : (date.isToday ? 'bg-blue-50/50' : '')">
                    <!-- Số ngày -->
                    <div class="flex items-center justify-between mb-1">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-micro font-bold"
                              :class="date.isToday
                                ? 'bg-blue-600 text-white'
                                : (date.isEmpty ? 'text-slate-300' : 'text-slate-600')"
                              x-text="date.day || ''"></span>
                        <!-- Chấm báo có sự kiện -->
                        <span x-show="date.events.length > 2" class="w-2 h-2 rounded-full bg-rose-500"></span>
                    </div>

                    <!-- Sự kiện trong ngày -->
                    <div class="space-y-1">
                        <template x-for="(event, idx) in date.events.slice(0, 3)" :key="event.id">
                            <button @click="showEventDetail(event)"
                                    class="w-full text-left px-1.5 py-1 rounded text-micro font-semibold truncate transition-all active:scale-95"
                                    :class="event.type === 'chiến dịch'
                                        ? 'bg-amber-100 text-amber-700 hover:bg-amber-200'
                                        : 'bg-blue-100 text-blue-700 hover:bg-blue-200'"
                                    :title="event.name + ' - ' + event.startTime">
                                <span x-text="event.name"></span>
                            </button>
                        </template>
                        <!-- Sự kiện ẩn -->
                        <div x-show="date.events.length > 3" class="text-micro text-slate-500 font-medium text-center">
                            +<span x-text="date.events.length - 3"></span> khác
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- 4. CHƯƠNG TRÌNH TRONG THÁNG (DANH SÁCH) -->
    <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <i data-lucide="list" class="w-4 h-4"></i>
            Chương trình tháng này
            <span class="ml-auto text-blue-600" x-text="'(' + programsInMonth.length + ')'"></span>
        </h3>
        <div class="space-y-2">
            <template x-for="prog in programsInMonth" :key="prog.id">
                <button @click="showEventDetail(prog)" type="button" class="w-full text-left bg-slate-50 hover:bg-slate-100 rounded-xl px-4 py-3 flex items-center gap-3 transition-colors">
                    <div class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center"
                         :class="prog.type === 'chiến dịch' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600'">
                        <i data-lucide="calendar" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-800 truncate" x-text="prog.name"></p>
                        <p class="text-micro text-slate-500" x-text="programSchedule(prog) + ' · ' + prog.startTime"></p>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 shrink-0"></i>
                </button>
            </template>
            <div x-show="programsInMonth.length === 0" class="text-center py-6 text-slate-500 text-sm">
                <i data-lucide="calendar-x" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                Không có chương trình trong tháng này
            </div>
        </div>
    </div>

    <!-- 5. CHI TIẾT SỰ KIỆN -->
    <div x-show="showEventDetailModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showEventDetailModal" x-transition.opacity.duration.300ms @click="showEventDetailModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showEventDetailModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
            <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
                <h3 class="text-lg font-black text-slate-800">Chi tiết</h3>
                <button aria-label="Đóng" @click="showEventDetailModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-5" x-show="selectedEvent">
                <div class="flex items-start gap-4 mb-4">
                    <div class="w-12 h-12 shrink-0 rounded-2xl flex items-center justify-center"
                         :class="selectedEvent?.type === 'chiến dịch' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600'">
                        <i data-lucide="calendar" class="w-6 h-6"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-black text-slate-800" x-text="selectedEvent?.name"></h4>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                                  :class="selectedEvent?.type === 'chiến dịch' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600'"
                                  x-text="selectedEvent?.type"></span>
                            <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                                  :class="selectedEvent?.status === 'kích hoạt' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                                  x-text="selectedEvent?.status"></span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                        <i data-lucide="repeat" class="w-5 h-5 text-slate-500 shrink-0"></i>
                        <div>
                            <p class="text-micro font-semibold text-slate-500">Lịch trình</p>
                            <p class="text-sm font-bold text-slate-800" x-text="selectedEvent ? programSchedule(selectedEvent) : ''"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                        <i data-lucide="clock" class="w-5 h-5 text-slate-500 shrink-0"></i>
                        <div>
                            <p class="text-micro font-semibold text-slate-500">Giờ bắt đầu</p>
                            <p class="text-sm font-bold text-slate-800" x-text="selectedEvent?.startTime"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                        <i data-lucide="alarm-clock" class="w-5 h-5 text-slate-500 shrink-0"></i>
                        <div>
                            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Chốt điểm danh</p>
                            <p class="text-sm font-bold text-rose-500" x-text="selectedEvent?.cutoffTime || (selectedEvent?.startTime ? addMinutes(selectedEvent.startTime, CUTOFF_MINUTES) : '--:--')"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                        <i data-lucide="check-circle" class="w-5 h-5 text-slate-500 shrink-0"></i>
                        <div>
                            <p class="text-micro font-semibold text-slate-500">Tính điểm Chuyên cần</p>
                            <p class="text-sm font-bold" :class="selectedEvent?.countForAttendance ? 'text-emerald-600' : 'text-slate-500'" x-text="selectedEvent?.countForAttendance ? 'Có' : 'Không'"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
