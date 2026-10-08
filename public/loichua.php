<?php
/**
 * LỜI CHÚA HÔM NAY - Trang công khai
 *
 * Hiển thị Lời Chúa theo lịch phụng vụ Việt Nam.
 * Không yêu cầu đăng nhập.
 *
 * @see config/loichua.php
 */

// Múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Lấy ngày từ query param hoặc mặc định hôm nay
$defaultDate = date('Y-m-d');
$requestDate = $_GET['date'] ?? $defaultDate;

// Validate format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate)) {
    $requestDate = $defaultDate;
}

// CSS version từ icon
$cssV = @filemtime(__DIR__ . '/assets/img/icon-192.png') ?: 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e3a5f">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="assets/img/icon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/img/icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="assets/img/icon-180.png">
    <title>Lời Chúa Hôm Nay</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;background:linear-gradient(180deg,#1e3a5f 0%,#0f2744 100%);color:#fff;
             font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
             -webkit-font-smoothing:antialiased}
        .wrap{max-width:640px;margin:0 auto;padding:20px 16px calc(40px + env(safe-area-inset-bottom))}
        @media (min-width:640px){.wrap{max-width:720px;padding:40px 32px}}

        /* Header */
        .header{text-align:center;margin-bottom:24px}
        .header .icon{width:72px;height:72px;margin:0 auto 16px;background:linear-gradient(135deg,#ffd700,#ffb347);
                      border-radius:50%;display:flex;align-items:center;justify-content:center;
                      box-shadow:0 4px 20px rgba(255,215,0,.3)}
        .header .icon svg{width:36px;height:36px;stroke:#1e3a5f;fill:none;stroke-width:2.5}
        .header h1{font-size:1.5rem;font-weight:900;margin:0 0 4px;color:#ffd700;letter-spacing:-.01em}
        .header p{font-size:.9rem;color:rgba(255,255,255,.7);margin:0}

        /* Date Navigator */
        .date-nav{display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:24px}
        .date-nav button{width:44px;height:44px;border-radius:50%;border:1px solid rgba(255,255,255,.2);
                        background:rgba(255,255,255,.1);color:#fff;font-size:1.25rem;cursor:pointer;
                        display:flex;align-items:center;justify-content:center;transition:all .2s}
        .date-nav button:hover:not(:disabled){background:rgba(255,255,255,.2)}
        .date-nav button:disabled{opacity:.3;cursor:not-allowed}
        .date-nav .date-display{font-size:.95rem;font-weight:600;color:#ffd700;min-width:180px;text-align:center}

        /* Liturgy Info */
        .liturgy-info{background:rgba(255,255,255,.08);border-radius:16px;padding:16px 20px;
                      margin-bottom:24px;border:1px solid rgba(255,255,255,.1)}
        .liturgy-info .season{font-size:1.1rem;font-weight:700;margin-bottom:8px}
        .liturgy-info .week{font-size:.9rem;color:rgba(255,255,255,.7);margin-bottom:12px}
        .liturgy-info .color{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;
                           background:rgba(255,255,255,.1);border-radius:20px;font-size:.85rem}
        .liturgy-info .color .chip{width:14px;height:14px;border-radius:50%;border:1px solid rgba(255,255,255,.3)}

        /* Reading Cards */
        .readings{display:flex;flex-direction:column;gap:16px;margin-bottom:24px}
        .reading-card{background:rgba(255,255,255,.06);border-radius:16px;padding:20px;
                      border:1px solid rgba(255,255,255,.1)}
        .reading-card h3{font-size:.75rem;font-weight:700;text-transform:uppercase;
                         letter-spacing:.1em;color:rgba(255,255,255,.6);margin:0 0 8px}
        .reading-card .ref{font-size:1rem;font-weight:700;color:#ffd700;margin:0 0 12px}
        .reading-card .text{font-size:1.05rem;line-height:1.8;color:#fff;margin:0;
                           font-style:italic}
        .reading-card.psalm .text{font-size:1.1rem;line-height:2}

        /* Loading */
        .loading{display:flex;flex-direction:column;align-items:center;justify-content:center;
                 min-height:200px;gap:16px}
        .loading .spinner{width:40px;height:40px;border:3px solid rgba(255,215,0,.2);
                          border-top-color:#ffd700;border-radius:50%;animation:spin 1s linear infinite}
        .loading p{color:rgba(255,255,255,.7);font-size:.9rem;margin:0}
        @keyframes spin{to{transform:rotate(360deg)}}

        /* Error */
        .error-box{background:rgba(220,38,38,.15);border:1px solid rgba(220,38,38,.3);
                   border-radius:16px;padding:24px;text-align:center}
        .error-box p{color:#fca5a5;margin:0 0 16px}
        .error-box button{padding:10px 24px;background:#c8203a;color:#fff;border:none;border-radius:8px;
                          font-weight:600;cursor:pointer}

        /* Out of Range */
        .out-of-range{text-align:center;padding:40px 20px}
        .out-of-range p{color:rgba(255,255,255,.7);margin:0 0 16px}
        .out-of-range button{padding:10px 24px;background:#c8203a;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer}
        .out-of-range code{background:rgba(255,255,255,.1);padding:4px 8px;border-radius:4px;font-size:.85rem}

        /* Font Size Controls */
        .font-controls{position:fixed;bottom:calc(20px + env(safe-area-inset-bottom));
                       right:20px;display:flex;gap:8px;z-index:100}
        .font-controls button{width:40px;height:40px;border-radius:50%;border:1px solid rgba(255,255,255,.2);
                             background:rgba(0,0,0,.3);color:#fff;font-size:1.1rem;cursor:pointer;
                             backdrop-filter:blur(8px);transition:all .2s}
        .font-controls button:hover{background:rgba(0,0,0,.5)}

        /* Footer */
        .footer{text-align:center;padding:24px 0;font-size:.75rem;color:rgba(255,255,255,.5);
                 border-top:1px solid rgba(255,255,255,.1)}
        .footer a{color:#ffd700}

        /* Back link */
        .back-link{display:inline-flex;align-items:center;gap:8px;color:rgba(255,255,255,.7);
                   text-decoration:none;font-size:.9rem;margin-bottom:20px}
        .back-link:hover{color:#ffd700}
        .back-link svg{width:18px;height:18px}

        /* Reduced Motion */
        @media(prefers-reduced-motion:reduce){
            .loading .spinner{animation:none}
        }
    </style>
</head>
<body>
<div class="wrap" x-data="loiChuaApp">
    <!-- Header -->
    <header class="header">
        <div class="icon">
            <svg viewBox="0 0 24 24"><path d="M12 2v20M7 7h10" stroke-linecap="round"/></svg>
        </div>
        <h1>Lời Chúa Hôm Nay</h1>
        <p>Lời Chúa theo lịch phụng vụ Việt Nam</p>
    </header>

    <!-- Back Link -->
    <a class="back-link" href="index.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Về trang chủ
    </a>

    <!-- Date Navigator -->
    <nav class="date-nav">
        <button type="button" @click="prevDay()" :disabled="!canGoPrev" aria-label="Ngày trước">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </button>
        <span class="date-display" x-text="formattedDate"></span>
        <button type="button" @click="nextDay()" :disabled="!canGoNext" aria-label="Ngày sau">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </button>
    </nav>

    <!-- Content -->
    <template x-if="!loading && !error && !outOfRange">
        <div class="content">
            <!-- Liturgy Info -->
            <div class="liturgy-info">
                <p class="season" x-text="data.season"></p>
                <p class="week" x-text="data.week"></p>
                <div class="color">
                    <span class="chip" :style="'background-color:' + colorHex"></span>
                    <span x-text="data.colorLabel"></span>
                </div>
            </div>

            <!-- Readings -->
            <div class="readings">
                <template x-for="reading in data.readings" :key="reading.ref">
                    <article class="reading-card" :class="reading.type === 'Đáp Ca' ? 'psalm' : ''">
                        <h3 x-text="reading.type"></h3>
                        <p class="ref" x-text="reading.ref"></p>
                        <p class="text" :style="'font-size:' + fontSize + 'px'" x-text="reading.text || reading.ref + ' — văn bản đang được cập nhật'"></p>
                    </article>
                </template>
            </div>

            <!-- Source -->
            <p style="font-size:.8rem;color:rgba(255,255,255,.5);text-align:center;margin-bottom:24px">
                Nguồn: <a href="https://github.com/ndagnhat/gospel-data" target="_blank" rel="noopener noreferrer">gospel-data</a> · Kinh Thánh CGKPV 2011
            </p>
        </div>
    </template>

    <!-- Loading State -->
    <template x-if="loading">
        <div class="loading">
            <div class="spinner"></div>
            <p>Đang tải lời Chúa...</p>
        </div>
    </template>

    <!-- Error State -->
    <template x-if="error">
        <div class="error-box">
            <p x-text="error"></p>
            <button type="button" @click="fetchData()">Thử lại</button>
        </div>
    </template>

    <!-- Out of Range -->
    <template x-if="outOfRange">
        <div class="out-of-range">
            <p>Chỉ xem được Lời Chúa từ <code x-text="minDate"></code> đến <code x-text="maxDate"></code>.</p>
            <button type="button" @click="goToday()">Về hôm nay</button>
        </div>
    </template>

    <!-- Font Size Controls -->
    <div class="font-controls">
        <button type="button" @click="smaller()" :disabled="fontSize <= 14" aria-label="Giảm cỡ chữ">A-</button>
        <button type="button" @click="larger()" :disabled="fontSize >= 26" aria-label="Tăng cỡ chữ">A+</button>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('loiChuaApp', () => ({
        currentDate: '<?php echo htmlspecialchars($requestDate, ENT_QUOTES, 'UTF-8'); ?>',
        data: null,
        loading: true,
        error: '',
        outOfRange: false,
        fontSize: 17,

        // Limits
        todayStr: '',
        minDate: null,
        maxDate: null,

        init() {
            this.calculateDateLimits();
            try {
                const f = parseInt(localStorage.getItem('loichuaFont'), 10);
                if (f >= 14 && f <= 26) this.fontSize = f;
            } catch (e) {}
            this.fetchData();
        },

        smaller() { this.fontSize--; this.saveFont(); },
        larger() { this.fontSize++; this.saveFont(); },
        saveFont() { try { localStorage.setItem('loichuaFont', this.fontSize); } catch (e) {} },

        goToday() {
            this.currentDate = this.todayStr;
            this.fetchData();
        },

        calculateDateLimits() {
            // Get today in Vietnam timezone (UTC+7)
            const tzOffset = 7 * 60 * 60 * 1000; // 7 hours in ms
            const localNow = new Date(Date.now() + tzOffset);
            const todayStr = localNow.toISOString().split('T')[0];
            this.todayStr = todayStr;

            const today = new Date(todayStr + 'T12:00:00');

            const minDate = new Date(today);
            minDate.setDate(minDate.getDate() - 30);
            this.minDate = this.formatDate(minDate);

            const maxDate = new Date(today);
            maxDate.setDate(maxDate.getDate() + 7);
            this.maxDate = this.formatDate(maxDate);
        },

        async fetchData() {
            this.loading = true;
            this.error = '';
            this.outOfRange = false;

            if (this.currentDate < this.minDate || this.currentDate > this.maxDate) {
                this.outOfRange = true;
                this.loading = false;
                return;
            }

            try {
                const resp = await fetch('api/loichua.php?date=' + this.currentDate);
                const json = await resp.json();

                if (json.success) {
                    this.data = json;
                } else if (resp.status === 400) {
                    this.outOfRange = true;
                } else {
                    this.error = (json.error || 'Không thể tải dữ liệu.') + ' [code: ' + resp.status + ']';
                }
            } catch (e) {
                this.error = 'Lỗi kết nối: ' + e.message;
            } finally {
                this.loading = false;
            }
        },

        prevDay() {
            if (!this.canGoPrev) return;
            const d = new Date(this.currentDate + 'T12:00:00');
            d.setDate(d.getDate() - 1);
            this.currentDate = this.formatDate(d);
            this.fetchData();
        },

        nextDay() {
            if (!this.canGoNext) return;
            const d = new Date(this.currentDate + 'T12:00:00');
            d.setDate(d.getDate() + 1);
            this.currentDate = this.formatDate(d);
            this.fetchData();
        },

        get canGoPrev() {
            if (!this.minDate) return true;
            return this.currentDate > this.minDate;
        },

        get canGoNext() {
            if (!this.maxDate) return true;
            return this.currentDate < this.maxDate;
        },

        get formattedDate() {
            if (!this.currentDate) return 'Đang tải...';
            const d = new Date(this.currentDate + 'T12:00:00');
            if (isNaN(d.getTime())) return this.currentDate;
            const days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
            const months = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
            return days[d.getDay()] + ', ' + d.getDate() + ' Tháng ' + months[d.getMonth()] + ', ' + d.getFullYear();
        },

        get colorHex() {
            const colors = {
                'trang': '#ffffff',
                'xanh': '#4CAF50',
                'đỏ': '#f44336',
                'tím': '#9C27B0',
                'hồng': '#E91E63',
            };
            return colors[this.data?.color] || '#ffffff';
        },

        formatDate(d) {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }
    }));
});
</script>
<script defer src="assets/js/vendor/alpine.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/vendor/alpine.js') ?: 0; ?>"></script>

</body>
</html>
