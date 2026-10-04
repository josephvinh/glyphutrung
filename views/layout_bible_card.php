<!-- ==========================================================
     DAILY BIBLE VERSE: Kinh Thánh hom nay
     ========================================================== -->
<div class="mt-2 mb-4" x-data="bibleCard()" x-init="init()">
    <!-- Has verse today -->
    <div x-show="!loading && verse" id="bibleCard" class="relative overflow-hidden bg-gradient-to-br from-amber-500 via-orange-500 to-amber-600 rounded-2xl shadow-xl border border-amber-300/50 p-5 cursor-pointer hover:shadow-2xl transition-shadow" @click="drawVerse()">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <span class="inline-block text-xs font-bold text-amber-100 uppercase tracking-wider mb-2">✨ Lời Chúa Hôm Nay</span>
                <p class="text-white font-medium text-base leading-relaxed" x-text="verse"></p>
                <p class="text-amber-100 font-semibold text-sm mt-3" x-text="'— ' + ref"></p>
            </div>
            <div class="shrink-0 w-10 h-10 rounded-full bg-amber-400/30 flex items-center justify-center">
                <i data-lucide="sparkles" class="w-5 h-5 text-amber-200"></i>
            </div>
        </div>
    </div>

    <!-- No verse today - show draw button -->
    <div x-show="!loading && !verse" id="bibleCard" class="relative overflow-hidden bg-gradient-to-br from-blue-600 via-indigo-600 to-blue-700 rounded-2xl shadow-xl border border-blue-400/30 p-5">
        <div class="flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <span class="inline-block text-xs font-bold text-blue-200 uppercase tracking-wider mb-1">✨ Lời Chúa Hôm Nay</span>
                <p class="text-white/90 text-sm">Bốc thăm lời Chúa mỗi ngày để nhận lời Chúa dành riêng cho bạn.</p>
            </div>
            <button @click="drawVerse()" type="button"
                    class="shrink-0 inline-flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-900 text-sm font-bold rounded-full shadow-lg active:scale-95 transition-all">
                <i data-lucide="sparkles" class="w-4 h-4"></i>
                Bốc
            </button>
        </div>
    </div>

    <!-- Loading state -->
    <div x-show="loading" id="bibleCard" class="bg-gradient-to-br from-blue-600 via-indigo-600 to-blue-700 rounded-2xl shadow-xl border border-blue-400/30 p-5">
        <div class="flex items-center justify-center py-4">
            <div class="animate-spin rounded-full h-6 w-6 border-2 border-blue-300/30 border-t-blue-300"></div>
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
            this.fetchFromApi();
        },
        async fetchFromApi() {
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
