<!-- MÀN LỊCH CỦA TÔI — ghi chú cá nhân + buổi họp được mời -->
<div data-module="notes" class="module-panel pt-6 pb-10 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')"
                class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <div class="min-w-0 flex-1">
            <h2 class="text-xl font-black text-slate-800 tracking-tight leading-tight">Lịch của tôi</h2>
            <p class="text-micro text-slate-500">Việc riêng bạn tự ghi + các buổi họp được mời</p>
        </div>
        <button @click="openCreateNote()" type="button"
                class="shrink-0 flex items-center gap-1.5 px-3.5 py-2.5 bg-blue-600 text-white rounded-2xl font-bold text-xs active:scale-95 transition-transform shadow-md shadow-blue-200">
            <i data-lucide="calendar-plus" class="w-4 h-4"></i> Thêm việc
        </button>
    </div>

    <!-- 2. AGENDA THEO NGÀY -->
    <div class="space-y-6">
        <template x-for="g in notesByDay" :key="g.day">
            <div>
                <h3 class="text-xs font-black text-slate-500 uppercase tracking-wider mb-2.5 px-1 flex items-center gap-2">
                    <span x-text="g.label"></span>
                    <span class="text-micro font-medium text-slate-400" x-text="formatFullDate(g.day)"></span>
                </h3>
                <div class="space-y-2.5">
                    <template x-for="it in g.items" :key="it.kind + '-' + it.id">
                        <div class="bg-white rounded-card p-4 shadow-sm border"
                             :class="it.kind === 'meeting' ? 'border-teal-200 bg-teal-50/30'
                                     : (isItemOverdue(it) ? 'border-rose-200' : 'border-slate-100')">

                            <div class="flex items-start gap-3">
                                <!-- Cột giờ -->
                                <div class="w-14 shrink-0 text-center">
                                    <p class="text-sm font-black leading-none"
                                       :class="it.kind === 'meeting' ? 'text-teal-600' : (isItemOverdue(it) ? 'text-rose-500' : 'text-blue-600')"
                                       x-text="itemTime(it)"></p>
                                    <p class="text-micro font-bold uppercase tracking-wide mt-1"
                                       :class="it.kind === 'meeting' ? 'text-teal-500' : 'text-slate-400'"
                                       x-text="it.kind === 'meeting' ? 'Họp' : 'Việc'"></p>
                                </div>

                                <div class="w-px self-stretch bg-slate-100"></div>

                                <!-- Nội dung -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-black text-slate-800 leading-snug"
                                       :class="it.kind === 'note' && it.done ? 'line-through text-slate-400' : ''"
                                       x-text="it.title"></p>
                                    <p x-show="it.desc" style="display:none" class="text-micro text-slate-500 mt-0.5 leading-snug" x-text="it.desc"></p>

                                    <!-- Buổi họp: người phát + địa điểm + nút RSVP -->
                                    <template x-if="it.kind === 'meeting'">
                                        <div class="mt-2">
                                            <p class="text-micro text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                <span x-show="it.place" style="display:none" class="flex items-center gap-1">
                                                    <i data-lucide="map-pin" class="w-3 h-3"></i><span x-text="it.place"></span>
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <i data-lucide="user" class="w-3 h-3"></i><span x-text="it.by"></span>
                                                </span>
                                            </p>
                                            <div class="flex gap-2 mt-2">
                                                <button @click="rsvpMeeting(it.ref, 'tham gia')" type="button"
                                                        class="flex items-center gap-1 px-3 py-1.5 rounded-xl font-bold text-micro border active:scale-95 transition-transform"
                                                        :class="it.rsvp === 'tham gia' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-emerald-600 border-emerald-200'">
                                                    <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Tham gia
                                                </button>
                                                <button @click="rsvpMeeting(it.ref, 'không tham gia')" type="button"
                                                        class="flex items-center gap-1 px-3 py-1.5 rounded-xl font-bold text-micro border active:scale-95 transition-transform"
                                                        :class="it.rsvp === 'không tham gia' ? 'bg-rose-500 text-white border-rose-500' : 'bg-white text-rose-500 border-rose-200'">
                                                    <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Không
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Ghi chú: nút xong / sửa / xoá -->
                                    <template x-if="it.kind === 'note'">
                                        <div class="flex gap-2 mt-2">
                                            <button @click="toggleNote(it.ref)" type="button"
                                                    class="flex items-center gap-1 px-3 py-1.5 rounded-xl font-bold text-micro border active:scale-95 transition-transform"
                                                    :class="it.done ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-emerald-600 border-emerald-200'">
                                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                                <span x-text="it.done ? 'Đã xong' : 'Xong'"></span>
                                            </button>
                                            <button @click="openEditNote(it.ref)" type="button" aria-label="Sửa"
                                                    class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-500 border border-slate-200 active:scale-90 transition-transform">
                                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                            </button>
                                            <button @click="deleteNote(it.ref)" type="button" aria-label="Xoá"
                                                    class="w-8 h-8 flex items-center justify-center rounded-xl bg-rose-50 text-rose-500 border border-rose-100 active:scale-90 transition-transform">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Rỗng -->
        <div x-show="notesByDay.length === 0" style="display: none;" class="text-center py-16 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="calendar-check" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
            <p class="text-slate-600 font-semibold text-base mb-1">Chưa có việc nào</p>
            <p class="text-slate-400 text-sm mb-4">Ghi lại việc cần nhớ, hệ thống sẽ nhắc khi gần tới.</p>
            <button @click="openCreateNote()" type="button"
                    class="px-5 py-2.5 bg-blue-50 text-blue-600 rounded-full font-bold text-xs active:scale-95 transition-transform border border-blue-100 hover:bg-blue-100">
                Thêm việc đầu tiên
            </button>
        </div>
    </div>

    <!-- ==========================================================
         POPUP THÊM / SỬA VIỆC
         ========================================================== -->
    <div x-show="showNoteModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showNoteModal" x-transition.opacity.duration.300ms @click="showNoteModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showNoteModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl max-h-[90dvh] flex flex-col overflow-hidden">

            <div class="flex justify-center pt-3 pb-2 bg-white"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white">
                <h3 class="text-lg font-black text-slate-800" x-text="isEditingNote ? 'Sửa việc' : 'Thêm việc'"></h3>
                <button aria-label="Đóng" @click="showNoteModal = false" class="tap-safe w-8 h-8 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên việc</label>
                    <input x-model="noteForm.title" type="text" placeholder="VD: Chuẩn bị giáo án, Họp phụ huynh..."
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ghi chú (tuỳ chọn)</label>
                    <textarea x-model="noteForm.note" rows="3" placeholder="Chi tiết thêm nếu cần..."
                              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ngày nhắc</label>
                        <input x-model="noteForm.date" type="date"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giờ nhắc</label>
                        <input x-model="noteForm.time" type="time" :disabled="noteForm.allDay"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-50">
                    </div>
                </div>
                <label class="flex items-center gap-2.5 cursor-pointer select-none">
                    <input x-model="noteForm.allDay" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-semibold text-slate-600">Cả ngày (nhắc lúc 07:00 sáng)</span>
                </label>
            </div>

            <div class="p-4 border-t border-slate-100 bg-white flex gap-3">
                <button x-show="isEditingNote" style="display:none" @click="deleteNote(notes.find(n => n.id === noteForm.id))"
                        class="w-14 shrink-0 bg-red-50 text-red-500 rounded-2xl border border-red-100 active:scale-95 transition-transform flex justify-center items-center">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                </button>
                <button @click="saveNote()" class="flex-1 bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu việc
                </button>
            </div>
        </div>
    </div>
</div>
