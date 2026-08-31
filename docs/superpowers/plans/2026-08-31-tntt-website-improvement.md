# TNTT Super App - Kế Hoạch Cải Thiện Toàn Diện

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nâng cấp website TNTT Super App từ bản hoạt động tốt lên bản chuyên nghiệp với bảo mật cao hơn, hiệu suất tốt hơn, UX mượt mà hơn, và tính năng phong phú hơn.

**Architecture:** Ứng dụng PHP thuần (Vanilla PHP) với Alpine.js + Tailwind CSS phía frontend, MySQL database. Cải thiện theo hướng: bảo mật → hiệu suất → UX → tính năng mới → code quality.

**Tech Stack:**
- Backend: PHP 8.x, PDO/MySQLi
- Frontend: Alpine.js, Tailwind CSS, Lucide Icons
- Database: MySQL/MariaDB
- Build: Node.js (cho CSS/asset optimization)
- Testing: PHPUnit, Playwright

**Spec:** Phân tích codebase hiện tại tại `G:\xampp\htdocs\tntt`

---

## Tổng Quan Cải Thiện

### 1. BẢO MẬT (Security)
- [ ] Thêm CSRF Token protection cho tất cả POST requests
- [ ] Nâng cấp hashing password (bcrypt → argon2id)
- [ ] Thêm Content Security Policy (CSP) headers
- [ ] Cải thiện rate limiting cho API endpoints
- [ ] Thêm audit logging chi tiết hơn
- [ ] Bảo vệ against SQL injection ở mức application
- [ ] Thêm XSS protection headers
- [ ] Cấu hình HTTPS headers (HSTS, etc.)

### 2. HIỆU SUẤT (Performance)
- [ ] Implement Redis/Memcached caching cho queries thường dùng
- [ ] Lazy loading cho danh sách thiếu nhi lớn (>100 em)
- [ ] Tối ưu hóa CSS/JS (minification, tree-shaking)
- [ ] Image optimization (WebP conversion, lazy loading)
- [ ] Database query optimization (indexes, query analysis)
- [ ] Implement service worker caching strategy tốt hơn
- [ ] API response compression (gzip/brotli)
- [ ] Pagination cho các endpoint trả nhiều dữ liệu

### 3. TRẢI NGHIỆM NGƯỜI DÙNG (UX)
- [ ] Dark Mode toggle
- [ ] Improved loading states và skeleton screens
- [ ] Toast notifications thay vì alerts
- [ ] Keyboard navigation improvements
- [ ] Improved form validation UX
- [ ] Responsive improvements cho tablet
- [ ] Better error messages
- [ ] Animated transitions giữa các modules
- [ ] Pull-to-refresh cho mobile

### 4. TÍNH NĂNG MỚI (New Features)
- [ ] Dashboard cá nhân hóa cho Ban Điều Hành
- [ ] Calendar view cho lịch sinh hoạt
- [ ] Bulk actions (import/export) cải thiện
- [ ] Push notification preferences
- [ ] Activity feed real-time
- [ ] Student profile cards với QR
- [ ] Parent portal (xem điểm danh con)
- [ ] Attendance analytics dashboards
- [ ] Export reports ra PDF/Excel
- [ ] Multi-language support (future)

### 5. CODE QUALITY
- [ ] Add PHPUnit tests cho core functions
- [ ] API documentation (OpenAPI/Swagger)
- [ ] Refactor monolithic files thành modules nhỏ hơn
- [ ] Add code linting (PHPStan, ESLint)
- [ ] GitHub Actions CI/CD pipeline
- [ ] Database migrations system
- [ ] Environment configuration validation

---

## Chi Tiết Các Task

---

### Task 1: Security - CSRF Protection

**Files:**
- Create: `public/api/csrf.php`
- Modify: `public/api/_bootstrap.php`, `public/api/_common.php`, `public/api/_bootstrap_page.php`
- Test: `tests/unit/CSRFTest.php`

**Interfaces:**
- Consumes: Session
- Produces: `csrf_token()` - returns current CSRF token string, `verify_csrf($token)` - boolean

- [ ] **Step 1: Tạo CSRF token generator**

```php
// public/api/csrf.php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
```

- [ ] **Step 2: Cập nhật _bootstrap.php thêm CSRF check**

```php
// Thêm vào cuối file, trước khi define json_out()
function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($token)) {
        json_fail('Invalid CSRF token.', 403);
    }
}
```

- [ ] **Step 3: Cập nhật index.php thêm CSRF token vào window.TNTT_BOOT**

```php
// Trong page_bootstrap() function
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// ...
'csrfToken' => $_SESSION['csrf_token'],
```

- [ ] **Step 4: Thêm hidden input vào tất cả forms**

```html
<input type="hidden" name="_csrf" :value="csrfToken">
```

- [ ] **Step 5: Cập nhật Alpine.js modules để gửi CSRF token**

```javascript
// Trong core.js - thêm vào init()
window.TNTT.csrfToken = window.TNTT_BOOT?.csrfToken || '';

// Helper function cho tất cả fetch calls
window.TNTT.csrfFetch = async (url, options = {}) => {
    return fetch(url, {
        ...options,
        headers: {
            ...options.headers,
            'X-CSRF-TOKEN': window.TNTT.csrfToken,
            'Content-Type': 'application/json',
        },
    });
};
```

- [ ] **Step 6: Viết unit test cho CSRF functions**

```php
// tests/unit/CSRFTest.php
public function test_csrf_token_generates_32_bytes(): void {
    $token = csrf_token();
    $this->assertEquals(64, strlen($token)); // hex = 32 bytes
}

public function test_verify_csrf_returns_true_for_valid_token(): void {
    $_SESSION['csrf_token'] = 'test_token_123';
    $this->assertTrue(verify_csrf('test_token_123'));
}

public function test_verify_csrf_returns_false_for_invalid_token(): void {
    $_SESSION['csrf_token'] = 'test_token_123';
    $this->assertFalse(verify_csrf('wrong_token'));
}
```

- [ ] **Step 7: Commit**

```bash
git add public/api/csrf.php public/api/_bootstrap.php public/api/_common.php
git add public/index.php public/assets/js/modules/core.js
git add tests/unit/CSRFTest.php
git commit -m "feat(security): add CSRF protection for all POST requests"
```

---

### Task 2: Security - Password Hashing Upgrade (bcrypt → argon2id)

**Files:**
- Modify: `config/install.php`, `public/api/auth.php`
- Create: `config/password.php`

**Interfaces:**
- Consumes: Plain password string
- Produces: `password_hash()` với ARGON2ID, `password_verify()` compatible

- [ ] **Step 1: Tạo password helper với auto-upgrade**

```php
// config/password.php
function password_hash_upgrade(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3,
    ]);
}

function password_verify_upgrade(string $password, string $hash): bool {
    // Verify với method mới
    if (password_verify($password, $hash)) {
        // Nếu hash cũ (bcrypt), upgrade lên argon2id
        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $newHash = password_hash_upgrade($password);
            // TODO: Cập nhật vào database cho user hiện tại
            db_run('UPDATE members SET password_hash = ? WHERE id = ?',
                   [$newHash, $_SESSION['member_id']]);
        }
        return true;
    }
    return false;
}
```

- [ ] **Step 2: Cập nhật auth.php sử dụng password helper**

```php
// Trong login function, thay password_verify thành password_verify_upgrade
// Trong register/create member function, sử dụng password_hash_upgrade
```

- [ ] **Step 3: Tạo migration script cho existing passwords**

```php
// scripts/upgrade_passwords.php - Chạy một lần
// Lặp qua tất cả members và rehash passwords
// User phải đổi password ở lần đăng nhập tiếp theo
```

- [ ] **Step 4: Commit**

```bash
git add config/password.php public/api/auth.php
git commit -m "feat(security): upgrade password hashing to Argon2id with auto-upgrade"
```

---

### Task 3: Security - Security Headers

**Files:**
- Modify: `public/api/_bootstrap.php`, `public/.htaccess`

**Interfaces:**
- Consumes: HTTP response
- Produces: Enhanced HTTP headers

- [ ] **Step 1: Thêm security headers vào _bootstrap.php**

```php
// Sau các header() calls hiện tại
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
```

- [ ] **Step 2: Cập nhật .htaccess**

```apache
# Thêm vào cuối file
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>
```

- [ ] **Step 3: Commit**

```bash
git add public/api/_bootstrap.php public/.htaccess
git commit -m "feat(security): add comprehensive security headers"
```

---

### Task 4: Performance - Database Index Optimization

**Files:**
- Modify: `config/schema.sql`

**Interfaces:**
- Consumes: Existing database
- Produces: Additional indexes cho performance

- [ ] **Step 1: Phân tích slow queries**

```sql
-- Thêm vào cuối schema.sql
-- Index cho các truy vấn thường dùng

-- Điểm danh: lookup theo student + date
ALTER TABLE attendances ADD INDEX idx_att_student_date (student_id, session_date);

-- Leave requests: lookup theo status + date
ALTER TABLE leave_requests ADD INDEX idx_lv_status_date (status, session_date);

-- Scores: lookup theo student + term
ALTER TABLE scores ADD INDEX idx_sc_student_term (student_id, term_id);

-- Reports: lookup theo student
ALTER TABLE reports ADD INDEX idx_rp_student (student_id);

-- Members: lookup theo phone (login)
ALTER TABLE members ADD INDEX idx_member_phone (phone);
```

- [ ] **Step 2: Tạo migration script**

```php
// scripts/add_indexes.php
// Chạy để thêm indexes mà không cần recreate tables
```

- [ ] **Step 3: Commit**

```bash
git add config/schema.sql scripts/add_indexes.php
git commit -m "perf(database): add missing indexes for query optimization"
```

---

### Task 5: Performance - Caching Layer

**Files:**
- Create: `public/api/cache.php`, `config/cache.php`
- Modify: `public/api/data.php`, `public/api/_bootstrap.php`

**Interfaces:**
- Consumes: Cache key, TTL
- Produces: `cache_get($key)`, `cache_set($key, $data, $ttl)`, `cache_del($key)`

- [ ] **Step 1: Tạo simple file-based cache**

```php
// public/api/cache.php
class Cache {
    private static string $dir = __DIR__ . '/../cache';
    
    public static function init(): void {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0755, true);
        }
    }
    
    public static function get(string $key): ?array {
        $file = self::$dir . '/' . md5($key) . '.json';
        if (!file_exists($file)) return null;
        
        $data = json_decode(file_get_contents($file), true);
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        return $data['value'];
    }
    
    public static function set(string $key, $value, int $ttl = 300): void {
        self::init();
        $file = self::$dir . '/' . md5($key) . '.json';
        file_put_contents($file, json_encode([
            'value' => $value,
            'expires' => time() + $ttl,
        ]));
    }
    
    public static function del(string $key): void {
        $file = self::$dir . '/' . md5($key) . '.json';
        if (file_exists($file)) unlink($file);
    }
    
    public static function flush(): void {
        foreach (glob(self::$dir . '/*.json') as $file) {
            unlink($file);
        }
    }
}
```

- [ ] **Step 2: Update data.php với caching**

```php
// Trong data.php
$cacheKey = "data_{$yid}_{$me['id']}";
if ($cached = Cache::get($cacheKey)) {
    json_out($cached);
}

// ... existing code ...

// Cache kết quả, invalidate khi có thay đổi
Cache::set($cacheKey, $result, 300); // 5 minutes
json_out($result);
```

- [ ] **Step 3: Invalidate cache khi data thay đổi**

```php
// Trong các endpoint ghi (attendance, students, etc.)
Cache::del("data_{$yid}_*"); // Xóa tất cả cache cho niên khoá này
```

- [ ] **Step 4: Commit**

```bash
git add public/api/cache.php config/cache.php public/api/data.php
git add public/api/attendance.php public/api/students.php
git commit -m "perf(cache): add file-based caching layer for API responses"
```

---

### Task 6: UX - Dark Mode

**Files:**
- Modify: `public/assets/css/app.css`, `public/assets/css/tailwind.css`
- Create: `public/assets/css/dark.css`
- Modify: `public/assets/js/modules/core.js`

**Interfaces:**
- Consumes: User preference (localStorage)
- Produces: Dark/light theme class on `<html>`

- [ ] **Step 1: Thêm dark mode CSS variables**

```css
/* public/assets/css/dark.css */
@media (prefers-color-scheme: dark) {
    :root {
        --bg-primary: #0f172a;
        --bg-secondary: #1e293b;
        --bg-card: #1e293b;
        --text-primary: #f8fafc;
        --text-secondary: #94a3b8;
        --border-color: #334155;
    }
}

.dark {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-card: #1e293b;
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --border-color: #334155;
}
```

- [ ] **Step 2: Cập nhật Tailwind config để support dark mode**

```js
// tailwind.config.js - thêm vào
module.exports = {
    darkMode: 'class',
    // ...
}
```

- [ ] **Step 3: Thêm dark mode toggle vào settings**

```javascript
// Trong module_settings.js
'Alpine.data'('darkModeToggle', () => ({
    dark: localStorage.getItem('darkMode') === 'true' 
          || window.matchMedia('(prefers-color-scheme: dark)').matches,
    
    init() {
        this.$watch('dark', val => {
            document.documentElement.classList.toggle('dark', val);
            localStorage.setItem('darkMode', val);
        });
    }
}));
```

- [ ] **Step 4: Commit**

```bash
git add public/assets/css/dark.css public/assets/js/modules/core.js
git commit -m "feat(ux): add dark mode support"
```

---

### Task 7: UX - Skeleton Loading States

**Files:**
- Modify: `views/module_students.php`, `views/module_attendance.php`, `views/module_stats.php`
- Create: `public/assets/css/skeleton.css`

**Interfaces:**
- Consumes: Loading state boolean
- Produces: Skeleton UI elements

- [ ] **Step 1: Tạo skeleton CSS**

```css
/* public/assets/css/skeleton.css */
.skeleton {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
    border-radius: 0.5rem;
}

@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-text { height: 1rem; margin-bottom: 0.5rem; }
.skeleton-title { height: 1.5rem; width: 60%; margin-bottom: 1rem; }
.skeleton-avatar { width: 3rem; height: 3rem; border-radius: 50%; }
.skeleton-card { height: 6rem; margin-bottom: 1rem; }
```

- [ ] **Step 2: Thêm skeleton vào module_students.php**

```html
<!-- Trong x-show, thay đổi loading state -->
<template x-if="loading">
    <div>
        <template x-for="i in 5">
            <div class="bg-white rounded-card p-4 mb-3">
                <div class="flex items-center gap-3">
                    <div class="skeleton skeleton-avatar"></div>
                    <div class="flex-1">
                        <div class="skeleton skeleton-title"></div>
                        <div class="skeleton skeleton-text w-40"></div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
```

- [ ] **Step 3: Cập nhật Alpine state**

```javascript
// Trong students.js
data: () => ({
    loading: true,
    students: [],
    
    async init() {
        this.loading = true;
        this.students = await this.loadStudents();
        this.loading = false;
    }
})
```

- [ ] **Step 4: Commit**

```bash
git add public/assets/css/skeleton.css views/module_students.php
git commit -m "feat(ux): add skeleton loading states"
```

---

### Task 8: Feature - Enhanced Dashboard cho Ban Điều Hành

**Files:**
- Modify: `views/layout_hero.php`
- Create: `public/assets/js/modules/dashboard.js`

**Interfaces:**
- Consumes: `window.TNTT_BOOT` data
- Produces: Personalized dashboard với quick stats và recent activities

- [ ] **Step 1: Tạo dashboard module**

```javascript
window.TNTT = window.TNTT || {};
window.TNTT.dashboard = {
    data: () => ({
        // Quick stats
        todayAttendance: 0,
        pendingLeaves: 0,
        unreadAnnouncements: 0,
        upcomingEvents: [],
        
        // Recent activities
        recentLogs: [],
        
        // Computed
        get quickStats() { /* tính toán */ },
        get alerts() { /* cảnh báo */ }
    }),
    
    computed: {
        attendanceRate() {
            const total = this.todayAttendance.total || 1;
            return Math.round((this.todayAttendance.present / total) * 100);
        }
    }
};
```

- [ ] **Step 2: Cập nhật layout_hero.php**

```html
<!-- Thêm dashboard cards cho BĐH -->
<div x-show="isAdmin" class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white rounded-card p-4 shadow-sm">
        <p class="text-micro font-bold text-slate-500">Hôm nay</p>
        <p class="text-2xl font-black text-emerald-600" x-text="todayAttendance.present + '/' + todayAttendance.total"></p>
        <p class="text-micro text-slate-500">Điểm danh</p>
    </div>
    <!-- More cards... -->
</div>
```

- [ ] **Step 3: Commit**

```bash
git add views/layout_hero.php public/assets/js/modules/dashboard.js
git commit -m "feat(dashboard): add enhanced admin dashboard"
```

---

### Task 9: Feature - Calendar View cho Lịch Sinh Hoạt

**Files:**
- Create: `views/module_calendar.php`
- Create: `public/assets/js/modules/calendar.js`
- Modify: `public/index.php`

**Interfaces:**
- Consumes: `programs` data
- Produces: Monthly calendar view với events

- [ ] **Step 1: Tạo calendar PHP view**

```php
<!-- views/module_calendar.php -->
<div x-show="currentModule === 'calendar'" class="module-panel">
    <!-- Calendar header -->
    <div class="flex items-center justify-between mb-4">
        <button @click="prevMonth">&lt;</button>
        <h2 x-text="calendarMonth"></h2>
        <button @click="nextMonth">&gt;</button>
    </div>
    
    <!-- Calendar grid -->
    <div class="grid grid-cols-7 gap-1">
        <template x-for="day in weekDays"><span x-text="day"></span></template>
        <template x-for="date in calendarDays" :key="date.key">
            <div class="min-h-[80px] p-2 bg-white rounded"
                 :class="date.events.length ? 'cursor-pointer hover:bg-blue-50' : ''"
                 @click="date.events.length && showDayEvents(date)">
                <span x-text="date.day"></span>
                <template x-for="event in date.events.slice(0,2)">
                    <div class="text-micro truncate bg-blue-100 text-blue-700 px-1 rounded"
                         x-text="event.name"></div>
                </template>
            </div>
        </template>
    </div>
</div>
```

- [ ] **Step 2: Tạo calendar Alpine module**

```javascript
window.TNTT.calendar = {
    data: () => ({
        currentDate: new Date(),
        programs: [],
        
        get calendarDays() {
            // Generate calendar days với events
        },
        
        get weekDays() {
            return ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        }
    })
};
```

- [ ] **Step 3: Commit**

```bash
git add views/module_calendar.php public/assets/js/modules/calendar.js public/index.php
git commit -m "feat(calendar): add calendar view for schedule"
```

---

### Task 10: Code Quality - CI/CD Pipeline

**Files:**
- Create: `.github/workflows/php.yml`
- Create: `.github/workflows/js.yml`
- Create: `phpunit.xml`
- Create: `.phpcs.xml`

**Interfaces:**
- Consumes: Code changes (push/PR)
- Produces: Automated testing và linting feedback

- [ ] **Step 1: Tạo PHPUnit config**

```xml
<!-- phpunit.xml -->
<?xml version="1.0"?>
<phpunit bootstrap="tests/bootstrap.php">
    <testsuites>
        <testsuite name="TNTT Unit Tests">
            <directory>tests/unit</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">config</directory>
            <directory suffix=".php">public/api</directory>
        </include>
    </coverage>
</phpunit>
```

- [ ] **Step 2: Tạo GitHub Actions workflow**

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  php:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql
      - run: composer install --no-interaction
      - run: ./vendor/bin/phpunit
      - run: ./vendor/bin/phpstan analyze

  js:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: npm ci
      - run: npm run lint
      - run: npm run test
```

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci.yml phpunit.xml .phpcs.xml
git commit -m "ci: add GitHub Actions CI/CD pipeline"
```

---

## Thứ Tự Ưu Tiên Thực Hiện

### Phase 1: Bảo Mật (Tuần 1)
1. CSRF Protection
2. Password Hashing Upgrade
3. Security Headers
4. Rate Limiting Enhancement

### Phase 2: Hiệu Suất (Tuần 2)
1. Database Indexes
2. Caching Layer
3. Asset Optimization

### Phase 3: UX (Tuần 3)
1. Dark Mode
2. Skeleton Loading
3. Toast Notifications
4. Pull-to-Refresh

### Phase 4: Tính Năng Mới (Tuần 4)
1. Enhanced Dashboard
2. Calendar View
3. Export Improvements

### Phase 5: Code Quality (Tuần 5)
1. Unit Tests
2. CI/CD Pipeline
3. API Documentation

---

## Ước Tính Thời Gian

| Task | Độ phức tạp | Ước tính |
|------|-------------|----------|
| CSRF Protection | Trung bình | 2-3 giờ |
| Password Hashing | Trung bình | 2 giờ |
| Security Headers | Thấp | 1 giờ |
| Database Indexes | Thấp | 1 giờ |
| Caching Layer | Trung bình | 3-4 giờ |
| Dark Mode | Trung bình | 3 giờ |
| Skeleton Loading | Trung bình | 2-3 giờ |
| Enhanced Dashboard | Cao | 4-5 giờ |
| Calendar View | Cao | 5-6 giờ |
| CI/CD Pipeline | Trung bình | 3 giờ |

**Tổng cộng: ~26-31 giờ làm việc**

---

## Kết Quả Mong Đợi

Sau khi hoàn thành kế hoạch này:

✅ **Bảo mật:** Ứng dụng được bảo vệ khỏi các cuộc tấn công phổ biến (CSRF, XSS, SQL Injection)
✅ **Hiệu suất:** Load time giảm 40-60%, smooth scrolling với 500+ thiếu nhi
✅ **UX:** Giao diện đẹp hơn, responsive hơn, dark mode cho người dùng thích
✅ **Tính năng:** Dashboard thông minh, lịch trực quan, export đa format
✅ **Code quality:** Tự động test, linting, và deployment
