<div x-show="syncing && students.length === 0" style="display: none;" class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-2 xl:grid-cols-3 xl:gap-4 xl:items-start">
    <template x-for="i in 5" :key="'sk-' + i">
        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">
            <div class="flex items-center gap-3 mb-4">
                <div class="skeleton skeleton-avatar"></div>
                <div class="flex-1">
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-text w-40"></div>
                </div>
            </div>
            <div class="space-y-2 mb-4 bg-slate-50 p-3.5 rounded-2xl">
                <div class="skeleton skeleton-text-sm w-full"></div>
                <div class="skeleton skeleton-text-sm w-3/4"></div>
            </div>
            <div class="space-y-3 border-t border-slate-100 pt-4">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="skeleton skeleton-text-sm w-16 mb-1"></div>
                        <div class="skeleton skeleton-text w-24"></div>
                    </div>
                    <div class="skeleton w-10 h-10 rounded-full"></div>
                </div>
                <div class="flex justify-between items-center">
                    <div>
                        <div class="skeleton skeleton-text-sm w-16 mb-1"></div>
                        <div class="skeleton skeleton-text w-24"></div>
                    </div>
                    <div class="skeleton w-10 h-10 rounded-full"></div>
                </div>
            </div>
        </div>
    </template>
</div>
