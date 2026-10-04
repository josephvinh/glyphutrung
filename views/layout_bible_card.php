<!-- ==========================================================
     DAILY BIBLE VERSE: Kinh Thánh hom nay
     - Hien thi verse neu da boc hom nay
     - Hien thi nut boc neu chua boc
     ========================================================== -->
<div class="mt-2 mb-4" x-data="bibleCard()" x-init="init()">
    <div id="bibleCard" class="relative overflow-hidden bg-gradient-to-br from-slate-800 via-blue-900 to-slate-900 rounded-2xl shadow-lg border border-amber-200/30 p-5">

        <!-- Decorative cross -->
        <div class="absolute top-3 right-3 text-amber-400/20 text-4xl font-bold select-none pointer-events-none">✝</div>

        <!-- Loading state -->
        <div x-show="loading" class="flex items-center justify-center py-4">
            <div class="animate-spin rounded-full h-6 w-6 border-2 border-amber-400/30 border-t-amber-400"></div>
        </div>

        <!-- Has verse today -->
        <div x-show="!loading && verse" class="relative z-10">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-full bg-amber-400/20 flex items-center justify-center">
                    <i data-lucide="book-open" class="w-4 h-4 text-amber-300"></i>
                </div>
                <span class="text-xs font-bold text-amber-300 uppercase tracking-wider">Lời Chúa Hôm Nay</span>
            </div>
            <p class="text-white/90 text-sm leading-relaxed italic" x-text="verse"></p>
            <p class="text-amber-300 text-xs font-semibold mt-3" x-text="'— ' + ref"></p>
        </div>

        <!-- No verse today - show draw button -->
        <div x-show="!loading && !verse" class="relative z-10 flex items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-full bg-amber-400/20 flex items-center justify-center">
                        <i data-lucide="book-open" class="w-4 h-4 text-amber-300"></i>
                    </div>
                    <span class="text-xs font-bold text-amber-300 uppercase tracking-wider">Lời Chúa Hôm Nay</span>
                </div>
                <p class="text-white/70 text-xs">Bốc thăm lời Chúa cho ngày hôm nay của bạn.</p>
            </div>
            <button @click="drawVerse()" type="button"
                    class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-900 text-xs font-bold rounded-full shadow-md active:scale-95 transition-transform">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
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
