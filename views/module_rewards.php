<!-- TRẠM ĐỔI QUÀ — Thủ thư đứng quầy: (1) Đổi tại quầy (POS), (2) Xác nhận đơn đặt trước -->
<div x-init="loadRewards()" data-module="rewards" class="module-panel pt-6 pb-28 relative max-w-3xl mx-auto">

    <!-- THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-4 px-1">
        <div class="flex items-center min-w-0">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-3">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <div class="min-w-0">
                <h2 class="text-xl font-black text-slate-800 tracking-tight truncate">Trạm Đổi Quà</h2>
                <p class="text-micro text-slate-400" x-text="rwMode==='pos' ? 'Quét thẻ · trừ Mộc · giao quà tại quầy' : 'Xác nhận đơn đặt trước online'"></p>
            </div>
        </div>
        <button x-show="(rwMode==='pos' && rwStep==='shop') || (rwMode==='confirm' && cfStep==='order')" style="display:none"
                @click="rwMode==='pos' ? rwBackToScan() : cfBackToScan()" type="button"
                class="shrink-0 bg-slate-100 text-slate-600 px-3 py-2 rounded-xl font-bold text-sm flex items-center gap-1 active:scale-95 transition-transform">
            <i data-lucide="scan-line" class="w-4 h-4"></i> Em khác
        </button>
    </div>

    <!-- CHUYỂN CHẾ ĐỘ: Đổi tại quầy <-> Xác nhận đơn đặt trước -->
    <div class="grid grid-cols-2 gap-1.5 bg-slate-100 p-1 rounded-2xl mb-5">
        <button @click="rwSwitchMode('pos')" type="button"
                class="py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-1.5"
                :class="rwMode==='pos' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500'">
            <i data-lucide="store" class="w-4 h-4"></i> Đổi tại quầy
        </button>
        <button @click="rwSwitchMode('confirm')" type="button"
                class="py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-1.5"
                :class="rwMode==='confirm' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500'">
            <i data-lucide="package-check" class="w-4 h-4"></i> Xác nhận đơn
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- CHẾ ĐỘ 1: ĐỔI TẠI QUẦY (POS)                                      -->
    <!-- ================================================================ -->
    <div x-show="rwMode==='pos'" style="display:none">

    <!-- ============ MÀN 1: QUÉT / NHẬP MÃ THẺ ============ -->
    <div x-show="rwStep==='scan'" style="display:none">
        <div class="bg-white rounded-card p-6 shadow-sm border border-slate-100">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center mb-4">
                <i data-lucide="scan-line" class="w-8 h-8 text-blue-500"></i>
            </div>
            <p class="text-center text-sm font-bold text-slate-700 mb-1">Quét thẻ hoặc nhập mã thiếu nhi</p>
            <p class="text-center text-micro text-slate-400 mb-5">Hệ thống sẽ hiện tên em và số Mộc khả dụng.</p>

            <form @submit.prevent="rwSubmitCode()" class="flex gap-2 mb-3">
                <input x-model="rwCode" type="text" inputmode="text" autocomplete="off" placeholder="VD: GDGLPT260001"
                       class="flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded-field py-3 px-4 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase">
                <button type="submit" :disabled="rwLooking"
                        class="shrink-0 bg-blue-600 text-white font-bold text-sm px-5 rounded-2xl active:scale-95 transition-transform shadow-md shadow-blue-200 disabled:opacity-50 flex items-center gap-1">
                    <span x-text="rwLooking ? 'Đang tra…' : 'Tra cứu'"></span>
                </button>
            </form>

            <button @click="rwOpenScan('pos')" type="button"
                    class="w-full bg-slate-800 text-white font-bold text-sm py-3 rounded-2xl active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
                <i data-lucide="camera" class="w-4 h-4"></i> Quét thẻ bằng camera
            </button>
        </div>
    </div>

    <!-- ============ MÀN 2: CHỌN QUÀ + GIỎ ============ -->
    <div x-show="rwStep==='shop'" style="display:none">

        <!-- Thẻ thông tin em + số Mộc -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4 flex items-center gap-3">
            <div class="w-12 h-12 shrink-0 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                <i data-lucide="user" class="w-6 h-6"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-black text-slate-800 truncate" x-text="(rwStudent||{}).fullName || ''"></p>
                <p class="text-micro text-slate-400" x-text="'Mã: ' + ((rwStudent||{}).code || '')"></p>
            </div>
            <div class="text-right shrink-0">
                <p class="text-lg font-black text-amber-600 flex items-center gap-1 justify-end">
                    <i data-lucide="stamp" class="w-4 h-4"></i><span x-text="rwAvailable"></span>
                </p>
                <p class="text-micro text-slate-400">Mộc khả dụng</p>
            </div>
        </div>

        <!-- Lưới quà -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <template x-if="rwGiftsLoading">
                <template x-for="i in 4" :key="'rwskel-'+i">
                    <div class="bg-white rounded-card p-3 shadow-sm border border-slate-100 space-y-3">
                        <div class="skeleton h-16 w-full rounded-2xl"></div>
                        <div class="skeleton h-4 w-full rounded"></div>
                    </div>
                </template>
            </template>

            <template x-for="g in rwGifts" :key="g.id">
                <div class="bg-white rounded-card p-3 shadow-sm border border-slate-100 flex flex-col gap-2"
                     :class="rwGiftUnaffordable(g) ? 'opacity-60' : ''">
                    <div class="w-full aspect-square rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden">
                        <img x-show="g.imageUrl" :src="g.imageUrl" class="w-full h-full object-cover" alt="">
                        <i x-show="!g.imageUrl" data-lucide="gift" class="w-7 h-7 text-slate-300"></i>
                    </div>
                    <h3 class="text-sm font-black text-slate-800 leading-tight truncate" x-text="g.name"></h3>
                    <p class="text-sm font-bold text-amber-600 flex items-center gap-1">
                        <i data-lucide="stamp" class="w-3.5 h-3.5"></i><span x-text="g.stampCost"></span> Mộc
                    </p>
                    <p class="text-micro text-slate-400">Còn <span class="font-bold text-slate-600" x-text="rwStockLeft(g)"></span></p>

                    <!-- Bộ đếm số lượng -->
                    <div x-show="rwCart[g.id]" style="display:none" class="flex items-center justify-between bg-blue-50 rounded-xl px-1 py-1">
                        <button @click="rwRemoveOne(g)" aria-label="Bớt" type="button" class="w-8 h-8 rounded-lg bg-white text-blue-600 font-black active:scale-90 flex items-center justify-center border border-blue-100">−</button>
                        <span class="text-sm font-black text-blue-700" x-text="rwCart[g.id] || 0"></span>
                        <button @click="rwAddToCart(g)" :disabled="rwCannotAddMore(g)" aria-label="Thêm" type="button" class="w-8 h-8 rounded-lg bg-white text-blue-600 font-black active:scale-90 flex items-center justify-center border border-blue-100 disabled:opacity-40">+</button>
                    </div>
                    <button x-show="!rwCart[g.id]" @click="rwAddToCart(g)" :disabled="rwCannotAddMore(g)" type="button"
                            class="w-full bg-blue-600 text-white font-bold text-xs py-2 rounded-xl active:scale-95 transition-transform disabled:opacity-40 disabled:cursor-not-allowed"
                            x-text="rwCannotAddMore(g) ? (rwStockLeft(g) <= 0 ? 'Hết hàng' : 'Không đủ Mộc') : 'Thêm'"></button>
                </div>
            </template>

            <div x-show="!rwGiftsLoading && rwGifts.length===0" style="display:none" class="col-span-full text-center py-10 text-slate-500 text-sm">Chưa có quà nào đang bán.</div>
        </div>
    </div>

    <!-- ============ THANH GIỎ (cố định dưới, chỉ ở màn shop khi có món) ============ -->
    <div x-show="rwStep==='shop' && rwCartCount>0" style="display:none"
         class="fixed inset-x-0 bottom-0 z-[190] px-4 pb-4 pt-2 pointer-events-none">
        <div class="max-w-3xl mx-auto pointer-events-auto bg-white rounded-2xl shadow-2xl border border-slate-200 p-3 flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-black text-slate-800"><span x-text="rwCartCount"></span> món · <span class="text-amber-600" x-text="rwCartTotal"></span> Mộc</p>
                <p class="text-micro text-slate-400">Còn lại sau khi đổi: <span x-text="rwAvailable - rwCartTotal"></span> Mộc</p>
            </div>
            <button @click="rwClearCart()" type="button" class="shrink-0 text-xs font-bold text-slate-500 px-2 py-2">Xoá giỏ</button>
            <button @click="rwConfirm()" :disabled="rwBusy || rwCartTotal > rwAvailable" type="button"
                    class="shrink-0 bg-emerald-500 text-white font-bold text-sm px-5 py-3 rounded-2xl active:scale-95 transition-transform shadow-md shadow-emerald-200 disabled:opacity-50 flex items-center gap-1.5">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span x-text="rwBusy ? 'Đang đổi…' : 'Xác nhận đổi'"></span>
            </button>
        </div>
    </div>

    </div> <!-- /rwMode==='pos' -->

    <!-- ================================================================ -->
    <!-- CHẾ ĐỘ 2: XÁC NHẬN ĐƠN ĐẶT TRƯỚC (đơn em tự đặt ở somoc.php)     -->
    <!-- ================================================================ -->
    <div x-show="rwMode==='confirm'" style="display:none">

    <!-- ============ MÀN 1: QUÉT / NHẬP MÃ THẺ ============ -->
    <div x-show="cfStep==='scan'" style="display:none">
        <div class="bg-white rounded-card p-6 shadow-sm border border-slate-100">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center mb-4">
                <i data-lucide="package-check" class="w-8 h-8 text-blue-500"></i>
            </div>
            <p class="text-center text-sm font-bold text-slate-700 mb-1">Quét thẻ hoặc nhập mã thiếu nhi</p>
            <p class="text-center text-micro text-slate-400 mb-5">Hệ thống sẽ hiện đơn đặt trước (nếu có) của em.</p>

            <form @submit.prevent="cfSubmitCode()" class="flex gap-2 mb-3">
                <input x-model="cfCode" type="text" inputmode="text" autocomplete="off" placeholder="VD: GDGLPT260001"
                       class="flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded-field py-3 px-4 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase">
                <button type="submit" :disabled="cfLooking"
                        class="shrink-0 bg-blue-600 text-white font-bold text-sm px-5 rounded-2xl active:scale-95 transition-transform shadow-md shadow-blue-200 disabled:opacity-50 flex items-center gap-1">
                    <span x-text="cfLooking ? 'Đang tra…' : 'Tra đơn'"></span>
                </button>
            </form>

            <button @click="rwOpenScan('confirm')" type="button"
                    class="w-full bg-slate-800 text-white font-bold text-sm py-3 rounded-2xl active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
                <i data-lucide="camera" class="w-4 h-4"></i> Quét thẻ bằng camera
            </button>
        </div>
    </div>

    <!-- ============ MÀN 2: ĐƠN CHỜ LẤY + GIAO ============ -->
    <div x-show="cfStep==='order' && cfPending" style="display:none">

        <!-- Thẻ thông tin em -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4 flex items-center gap-3">
            <div class="w-12 h-12 shrink-0 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                <i data-lucide="user" class="w-6 h-6"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-black text-slate-800 truncate" x-text="(cfStudent||{}).fullName || ''"></p>
                <p class="text-micro text-slate-400" x-text="'Mã: ' + ((cfStudent||{}).code || '')"></p>
            </div>
            <div class="text-right shrink-0">
                <p class="text-micro text-slate-400">Đơn <span class="font-bold text-slate-600" x-text="'#' + (cfPending||{}).orderId"></span></p>
                <p class="text-micro text-rose-500 font-bold" x-text="'Hạn lấy: ' + cfFormatExpiry((cfPending||{}).expiresAt)"></p>
            </div>
        </div>

        <!-- Danh sách quà đã đặt -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Quà đã đặt</p>
            <div class="divide-y divide-slate-100">
                <template x-for="it in (cfPending||{}).items || []" :key="it.giftId">
                    <div class="py-2 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-700 min-w-0 truncate">
                            <span x-text="it.name"></span> <span class="text-slate-400">× </span><span x-text="it.qty"></span>
                        </p>
                        <p class="text-sm font-bold text-amber-600 shrink-0" x-text="it.lineCost + ' Mộc'"></p>
                    </div>
                </template>
            </div>
            <div class="pt-3 mt-1 border-t border-slate-100 flex items-center justify-between">
                <p class="text-sm font-black text-slate-800">Tổng cộng</p>
                <p class="text-lg font-black text-amber-600 flex items-center gap-1">
                    <i data-lucide="stamp" class="w-4 h-4"></i><span x-text="(cfPending||{}).total"></span> Mộc
                </p>
            </div>
        </div>

        <!-- Nhập mật mã đổi quà + xác nhận giao -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
            <label class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2 block">Mật mã đổi quà của em</label>
            <form @submit.prevent="cfConfirm()" class="flex gap-2">
                <input x-model="cfPassword" type="password" autocomplete="off" placeholder="Nhập mật mã em đã đặt"
                       class="flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded-field py-3 px-4 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" :disabled="cfBusy"
                        class="shrink-0 bg-emerald-500 text-white font-bold text-sm px-5 rounded-2xl active:scale-95 transition-transform shadow-md shadow-emerald-200 disabled:opacity-50 flex items-center gap-1.5">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span x-text="cfBusy ? 'Đang giao…' : 'Xác nhận giao'"></span>
                </button>
            </form>
        </div>

        <!-- Quên mật mã / hủy hộ -->
        <div class="flex flex-col gap-2">
            <button @click="cfOverride()" :disabled="cfBusy" type="button"
                    class="w-full bg-amber-50 text-amber-700 border border-amber-200 font-bold text-sm py-3 rounded-2xl active:scale-[0.98] transition-transform disabled:opacity-50 flex items-center justify-center gap-2">
                <i data-lucide="shield-alert" class="w-4 h-4"></i> Em quên mật mã — giao bằng quyền Thủ thư
            </button>
            <button @click="cfCancelOrder()" :disabled="cfBusy" type="button"
                    class="w-full bg-white text-slate-500 border border-slate-200 font-bold text-sm py-3 rounded-2xl active:scale-[0.98] transition-transform disabled:opacity-50 flex items-center justify-center gap-2">
                <i data-lucide="x-circle" class="w-4 h-4"></i> Hủy hộ đơn này
            </button>
        </div>
    </div>

    </div> <!-- /rwMode==='confirm' -->

    <!-- ============ CAMERA QUÉT THẺ (overlay, dùng chung 2 chế độ) ============ -->
    <div x-show="rwScan.open" style="display:none" class="fixed inset-0 z-[220] bg-slate-900 flex flex-col">
        <div class="flex items-center justify-between p-4 text-white">
            <p class="font-bold text-sm" x-text="rwScan.status || 'Đưa thẻ vào khung'"></p>
            <button @click="rwCloseScan()" aria-label="Đóng" class="w-9 h-9 bg-white/15 rounded-full flex items-center justify-center active:scale-90"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="flex-1 relative overflow-hidden">
            <!-- Ép <video> lên lớp GPU riêng để iOS không vẽ camera thành ô đen
                 (xem chú thích ở module_attendance.php). -->
            <video x-ref="rwVideo" class="absolute inset-0 w-full h-full object-cover"
                   style="transform: translateZ(0); -webkit-transform: translateZ(0);"
                   muted playsinline autoplay></video>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-56 h-56 border-4 border-white/80 rounded-3xl"></div>
            </div>
        </div>
        <div class="p-4 text-center text-white/70 text-xs">Camera sẽ tự nhận khi thấy mã QR trên thẻ.</div>
    </div>
</div>
