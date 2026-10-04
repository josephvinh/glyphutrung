<!-- ==========================================================
     DAILY BIBLE VERSE: Kinh Thánh hom nay
     - Hien thi verse neu da boc hom nay
     - Hien thi nut boc neu chua boc
     ========================================================== -->
<div class="mt-2 mb-4" x-data="bibleCard()" x-init="init()">
    <div id="bibleCard" class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-blue-800 to-indigo-900 rounded-2xl shadow-xl border border-blue-400/20 p-5">

        <!-- Loading state -->
        <div x-show="loading" class="flex items-center justify-center py-4">
            <div class="animate-spin rounded-full h-6 w-6 border-2 border-blue-300/30 border-t-blue-300"></div>
        </div>

        <!-- Has verse today -->
        <div x-show="!loading && verse" class="relative z-10">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-9 h-9 rounded-xl bg-blue-500/40 flex items-center justify-center shadow-inner">
                    <i data-lucide="book-open" class="w-5 h-5 text-blue-200"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-blue-200 uppercase tracking-wider">Lời Chúa Hôm Nay</span>
                    <p class="text-[10px] text-blue-300/60">Dành cho bạn</p>
                </div>
            </div>
            <p class="text-white text-sm leading-relaxed" x-text="verse"></p>
            <p class="text-amber-300 text-xs font-semibold mt-3" x-text="'— ' + ref"></p>
        </div>

        <!-- No verse today - show draw button -->
        <div x-show="!loading && !verse" class="relative z-10 flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-9 h-9 rounded-xl bg-blue-500/40 flex items-center justify-center shadow-inner">
                        <i data-lucide="book-open" class="w-5 h-5 text-blue-200"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-blue-200 uppercase tracking-wider">Lời Chúa Hôm Nay</span>
                        <p class="text-[10px] text-blue-300/60">Nhận lời Chúa dành riêng cho bạn</p>
                    </div>
                </div>
                <p class="text-blue-100/80 text-xs">Bốc thăm lời Chúa mỗi ngày để bắt đầu ngày mới.</p>
            </div>
            <button @click="drawVerse()" type="button"
                    class="shrink-0 inline-flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-900 text-sm font-bold rounded-full shadow-lg active:scale-95 transition-all">
                <i data-lucide="sparkles" class="w-4 h-4"></i>
                Bốc
            </button>
        </div>
    </div>
</div>

<script>
function bibleCard() {
    return {
        verse: '',
        ref: '',
        loading: true,
        init() {
            this.loadTodayVerse();
        },
        loadTodayVerse() {
            // Kiem tra localStorage truoc
            try {
                const stored = localStorage.getItem('dailyBibleVerse');
                if (stored) {
                    const data = JSON.parse(stored);
                    const today = new Date().toDateString();
                    if (data.date === today && data.verse) {
                        this.verse = data.verse;
                        this.ref = data.ref || '';
                        this.loading = false;
                        return;
                    }
                }
            } catch(e) {}

            // Goi API de lay verse (API se tra ve verse da luu neu cung IP trong 1 gio)
            this.fetchFromApi();
        },
        async fetchFromApi() {
            try {
                const resp = await fetch('api/bible.php?action=random');
                const data = await resp.json();
                if (data.success && data.verse) {
                    this.verse = data.verse;
                    this.ref = data.ref || '';
                    // Luu vao localStorage de hien thi offline
                    try {
                        localStorage.setItem('dailyBibleVerse', JSON.stringify({
                            date: new Date().toDateString(),
                            verse: data.verse,
                            ref: data.ref || '',
                            ts: Date.now()
                        }));
                    } catch(e) {}
                }
            } catch(e) {}
            this.loading = false;
        },
        async drawVerse() {
            this.loading = true;
            try {
                const resp = await fetch('api/bible.php?action=random');
                const data = await resp.json();
                if (data.success && data.verse) {
                    this.verse = data.verse;
                    this.ref = data.ref || '';
                    try {
                        localStorage.setItem('dailyBibleVerse', JSON.stringify({
                            date: new Date().toDateString(),
                            verse: data.verse,
                            ref: data.ref || '',
                            ts: Date.now()
                        }));
                    } catch(e) {}
                }
            } catch(e) {
                alert('Không thể tải lời Chúa. Vui lòng thử lại.');
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
