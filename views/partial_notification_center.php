<!-- ==========================================================
     NOTIFICATION CENTER — slide-up sheet
     Include ở cuối body trong index.php
     ========================================================== -->
<div x-show="showNotificationCenter"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="display: none;"
     class="fixed inset-0 z-[250]">

    <!-- Backdrop -->
    <div x-show="showNotificationCenter"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;"
         class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
         @click="notifications.close()">
    </div>

    <!-- Sheet -->
    <div x-show="showNotificationCenter"
         x-transition:enter="transform transition ease-out duration-300"
         x-transition:enter-start="translate-y-full opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transform transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="translate-y-full opacity-0"
         style="display: none;"
         class="sheet-panel absolute bottom-0 left-0 right-0 bg-white rounded-t-sheet shadow-2xl flex flex-col max-h-[85dvh]">

        <!-- Drag handle -->
        <div class="flex justify-center pt-3 pb-2 shrink-0">
            <div class="w-12 h-1.5 bg-slate-200 rounded-full"></div>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between px-5 pb-3 border-b border-slate-100 shrink-0">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-black text-slate-800">Thông báo</h2>
                <span x-show="notifications.unreadCount > 0"
                      style="display: none;"
                      class="px-1.5 py-0.5 rounded-full bg-rose-500 text-white text-micro font-black"
                      x-text="notifications.unreadCount"></span>
            </div>
            <div class="flex items-center gap-1">
                <button x-show="notifications.unreadCount > 0"
                        style="display: none;"
                        @click="notifications.markAllRead()"
                        class="tap-safe px-3 py-1.5 rounded-xl text-micro font-bold text-blue-600 hover:bg-blue-50 active:scale-95 transition-all">
                    Đánh dấu đã đọc
                </button>
                <button aria-label="Đóng"
                        @click="notifications.close()"
                        class="tap-safe w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center active:scale-90 transition-transform shrink-0">
                    <i data-lucide="x" class="w-4 h-4 text-slate-500"></i>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-1 px-5 py-3 border-b border-slate-100 shrink-0">
            <button @click="notifications.setFilter('all')"
                    class="px-4 py-1.5 rounded-full text-micro font-bold transition-all"
                    :class="notifications.filter === 'all'
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                Tất cả
            </button>
            <button @click="notifications.setFilter('unread')"
                    class="px-4 py-1.5 rounded-full text-micro font-bold transition-all flex items-center gap-1"
                    :class="notifications.filter === 'unread'
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                Chưa đọc
                <span x-show="notifications.unreadCount > 0"
                      style="display: none;"
                      class="w-4 h-4 rounded-full bg-rose-500 text-white text-[10px] font-black flex items-center justify-center"
                      x-text="notifications.unreadCount > 99 ? '99+' : notifications.unreadCount"></span>
            </button>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto">
            <!-- Loading skeleton -->
            <div x-show="notifications.loading && notifications.items.length === 0"
                 style="display: none;"
                 class="p-5 space-y-3">
                <template x-for="n in 4" :key="n">
                    <div class="flex items-start gap-3 animate-pulse">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 shrink-0"></div>
                        <div class="flex-1 space-y-2 pt-1">
                            <div class="h-3 bg-slate-100 rounded w-3/4"></div>
                            <div class="h-2.5 bg-slate-100 rounded w-full"></div>
                            <div class="h-2 bg-slate-100 rounded w-1/3"></div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Notification items -->
            <template x-for="item in notifications.items" :key="item.id">
                <div @click="notifications.openNotification(item)"
                     class="flex items-start gap-3 px-5 py-4 border-b border-slate-50 cursor-pointer hover:bg-slate-50 active:bg-slate-100 transition-colors"
                     :class="{ 'bg-blue-50/50': !item.is_read }">
                    <!-- Icon -->
                    <div class="w-10 h-10 rounded-xl border shrink-0 flex items-center justify-center"
                         :class="notifications.colorForTag(item.tag)">
                        <i :data-lucide="notifications.iconForTag(item.tag)" class="w-5 h-5"></i>
                    </div>
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-slate-800 leading-snug" x-text="item.title"></p>
                            <!-- Unread dot -->
                            <span x-show="!item.is_read"
                                  style="display: none;"
                                  class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mt-1.5"></span>
                        </div>
                        <p class="text-micro text-slate-500 mt-0.5 leading-relaxed line-clamp-2"
                           x-text="item.body || ''"></p>
                        <p class="text-micro text-slate-400 mt-1"
                           x-text="formatRelativeTime(item.created_at)"></p>
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <div x-show="!notifications.loading && notifications.items.length === 0"
                 style="display: none;"
                 class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mb-4">
                    <i data-lucide="bell-off" class="w-7 h-7 text-slate-300"></i>
                </div>
                <p class="text-sm font-bold text-slate-700">Không có thông báo nào</p>
                <p class="text-micro text-slate-400 mt-1">Thông báo mới sẽ xuất hiện ở đây</p>
            </div>

            <!-- Load more -->
            <div x-show="notifications.hasMore && !notifications.loading"
                 style="display: none;"
                 class="p-4 text-center">
                <button @click="notifications.loadMore()"
                        class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-micro rounded-xl active:scale-95 transition-all">
                    Xem thêm
                </button>
            </div>
        </div>

    </div>
</div>
