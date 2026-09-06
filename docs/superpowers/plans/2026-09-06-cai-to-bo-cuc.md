# Kế hoạch cải tổ bố cục (Information Architecture) — TNTT Super App

> **Cho người thực thi:** Làm lần lượt từng Task theo thứ tự. Mỗi Task tự chạy được, tự kiểm chứng, tự commit. Đánh dấu `- [x]` khi xong từng bước.

**Mục tiêu:** Giảm số icon phẳng trên Trang chủ, gộp các module trùng vai, và làm Trang chủ chủ động ("biết việc cần làm") — mà không đụng tới backend/quyền.

**Kiến trúc hiện tại (giữ nguyên nền):** SPA Alpine.js. Mỗi màn là một `<div data-module="…" class="module-panel">` trong `public/index.php`, bật/tắt bằng `currentModule`. Lưới nút Trang chủ sinh từ `moduleDefs` trong `core.js` qua `visibleModules(area)`. Điều hướng qua `openModule(key)` / `changeModule(key)`.

**Nguồn dữ liệu chính:**
- `public/assets/js/modules/core.js` — `moduleDefs` (dòng 466–483), `visibleModules` (538), `openModule` (551), `myTasks` (294).
- `views/module_menu.php` — lưới nút Trang chủ (2 khu: `glv`, `bdh`).
- `views/layout_hero.php` — dải thẻ trượt trên đầu Trang chủ.
- `views/layout_bottomnav.php` — thanh dưới (hiện 3 tab).
- `public/index.php` — include các module (dòng 123–147).

## Ràng buộc chung (mọi Task đều phải giữ)
- **Không đụng backend/PHP-API/bảng quyền.** Chỉ sửa view + JS frontend.
- **Không phá phân quyền:** mọi nút vẫn qua `canAccess(key)` / `isUnderMaintenance(key)` như cũ.
- **DRY:** không lặp markup — tách partial khi dùng lại.
- **Kiểm chứng bắt buộc mỗi Task:** `php -l` cho view sửa; `node --check` cho JS sửa; `php phunit10.phar --no-coverage` vẫn 25/25; mở preview kiểm mắt.
- **Tiếng Việt** cho mọi nhãn hiển thị.
- Commit cuối mỗi Task, message có `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

---

## Task 1 — Đưa "Việc cần làm" ra Trang chủ

**Vì sao:** `myTasks` (core.js:294) đã tính sẵn (điểm danh dang dở, đơn chờ duyệt, phiếu liên lạc thiếu, thông báo chưa đọc) nhưng chỉ hiện trong *Cá nhân*. Đưa lên đầu Trang chủ để người dùng hành động ngay.

**Files:**
- Tạo: `views/partial_my_tasks.php`
- Sửa: `views/module_profile.php` (dòng ~49–68 — thay khối cứng bằng include partial)
- Sửa: `views/module_menu.php` (chèn partial lên đầu, chỉ hiện ở Trang chủ)

- [ ] **B1. Tách khối "Việc cần làm" thành partial.** Copy nguyên khối `<h3>Việc cần làm</h3> … myTasks … empty-state` hiện ở `module_profile.php` vào `views/partial_my_tasks.php`. Bọc ngoài cùng bằng `x-show="myTasks.length > 0"` (ở Trang chủ ẩn hẳn khi rảnh; trong Cá nhân thì giữ cả empty-state — xem B2).

  Nội dung `partial_my_tasks.php` (rút gọn, dùng cho Trang chủ — chỉ hiện khi có việc):
  ```html
  <!-- VIỆC CẦN LÀM — dùng chung Trang chủ + Cá nhân -->
  <div x-show="myTasks.length > 0" style="display:none" class="mb-5">
      <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 px-1">Việc cần làm</h3>
      <div class="space-y-2.5">
          <template x-for="t in myTasks" :key="t.key">
              <button @click="openModule(t.go)" type="button"
                      class="w-full flex items-center gap-3 bg-white rounded-card p-3.5 shadow-sm border text-left active:scale-[0.99] transition-transform"
                      :class="t.cls">
                  <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" :class="t.cls">
                      <i :data-lucide="t.icon" class="w-4.5 h-4.5"></i>
                  </span>
                  <span class="min-w-0">
                      <span class="block text-sm font-black text-slate-800 leading-snug" x-text="t.text"></span>
                      <span class="block text-micro text-slate-500" x-text="t.detail"></span>
                  </span>
                  <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 ml-auto shrink-0"></i>
              </button>
          </template>
      </div>
  </div>
  ```

- [ ] **B2. Sửa `module_profile.php`:** thay khối cũ bằng `<?php include __DIR__ . '/partial_my_tasks.php'; ?>` + giữ riêng một empty-state "Hôm nay không có việc cần làm 🎉" bằng `x-show="myTasks.length === 0"` (để Cá nhân vẫn có trạng thái rảnh).

- [ ] **B3. Sửa `module_menu.php`:** chèn `<?php include __DIR__ . '/partial_my_tasks.php'; ?>` ngay đầu (trên khu "Chức năng"). Vì partial đã tự `x-show="myTasks.length>0"` nên Trang chủ rảnh sẽ không thấy gì thừa.

- [ ] **B4. Kiểm chứng:** `php -l views/module_menu.php views/module_profile.php`; mở preview → Trang chủ hiện danh sách việc khi có; bấm một việc phải nhảy đúng module (`openModule(t.go)`); Cá nhân vẫn hoạt động.

- [ ] **B5. Commit:** `feat(home): đưa "Việc cần làm" lên Trang chủ`

---

## Task 2 — Bỏ "Sinh nhật" khỏi lưới nút (đã có thẻ ở Hero)

**Vì sao:** Sinh nhật là nhắc nhở, đã có Thẻ 3 ở Hero (`layout_hero.php`). Để nó ngang hàng Điểm danh làm phình lưới.

**Files:** Sửa `public/assets/js/modules/core.js` (moduleDefs, dòng 470).

- [ ] **B1.** Thêm `hidden: true` vào def `birthdays`:
  ```js
  { key: 'birthdays', label: 'Sinh nhật', icon: 'cake', color: 'text-rose-500', area: 'glv', badge: 'birthday', hidden: true },
  ```
  (`visibleModules` đã lọc `!m.hidden`; `openBirthdays()` từ Hero vẫn chạy.)

- [ ] **B2. Kiểm chứng:** `node --check public/assets/js/modules/core.js`; preview → lưới không còn nút Sinh nhật; Thẻ Hero "Sinh nhật tháng này" bấm vẫn mở đúng.

- [ ] **B3. Commit:** `refactor(home): bỏ Sinh nhật khỏi lưới, giữ ở thẻ Hero`

---

## Task 3 — Chia lưới nút theo cụm có tiêu đề

**Vì sao:** Khu "Chức năng" đang ~8 nút phẳng trộn "hằng ngày" với "quản trị hiếm dùng". Chia cụm giảm nhiễu.

**Nhóm dự kiến (thêm field `group`):**
- Khu `glv`: **Hằng ngày** (students, attendance, leave) · **Theo dõi** (stats, analytics) · **Quản lý** (org)
- Khu `bdh`: **Chương trình** (promotion, programs, calendar) · **Điều hành** (announcements, staff, years)

> Ghi chú: Nhân sự (`staff`) và Niên khoá (`years`) ĐÃ được chuyển sang `area: 'bdh'` (icon `text-white` cho nền tối). Task 3 chỉ cần gán `group` cho chúng.

**Files:**
- Sửa: `public/assets/js/modules/core.js` (moduleDefs + getter mới `moduleGroups`)
- Sửa: `views/module_menu.php` (lặp theo nhóm)

- [ ] **B1.** Thêm `group: '<tên>'` vào từng def trong `moduleDefs`. Ví dụ:
  ```js
  { key: 'students', label: 'Thiếu Nhi', icon: 'users', color: 'text-blue-600', area: 'glv', group: 'Hằng ngày' },
  { key: 'attendance', label: 'Điểm danh', icon: 'clipboard-check', color: 'text-blue-600', area: 'glv', group: 'Hằng ngày' },
  { key: 'leave', label: 'Xin phép', icon: 'file-text', color: 'text-blue-600', area: 'glv', group: 'Hằng ngày', badge: 'leave' },
  { key: 'stats', label: 'Thống kê', icon: 'bar-chart-3', color: 'text-emerald-600', area: 'glv', group: 'Theo dõi' },
  { key: 'analytics', label: 'Phân tích', icon: 'bar-chart-2', color: 'text-purple-600', area: 'glv', group: 'Theo dõi' },
  { key: 'org', label: 'Khối lớp', icon: 'layers', color: 'text-indigo-600', area: 'glv', group: 'Quản lý' },
  // bdh (staff/years đã chuyển sang đây):
  { key: 'promotion', ..., area: 'bdh', group: 'Chương trình' },
  { key: 'programs', ..., area: 'bdh', group: 'Chương trình' },
  { key: 'calendar', ..., area: 'bdh', group: 'Chương trình' },
  { key: 'announcements', ..., area: 'bdh', group: 'Điều hành' },
  { key: 'staff', ..., area: 'bdh', group: 'Điều hành', badge: 'staff' },
  { key: 'years', ..., area: 'bdh', group: 'Điều hành' },
  ```
  > Lưu ý: nếu làm Task 4 trước thì `stats`/`analytics` đã ẩn và thay bằng `reporthub` (group 'Theo dõi'). Thứ tự nhóm lấy theo thứ tự xuất hiện đầu tiên trong mảng — sắp def đúng thứ tự mong muốn.

- [ ] **B2.** Thêm getter vào core.js (cạnh `visibleModules`):
  ```js
  // Gom nút theo nhóm, giữ thứ tự nhóm xuất hiện lần đầu trong moduleDefs
  moduleGroups(area) {
      const out = [];
      this.visibleModules(area).forEach(m => {
          const name = m.group || '';
          let g = out.find(x => x.name === name);
          if (!g) { g = { name, items: [] }; out.push(g); }
          g.items.push(m);
      });
      return out;
  },
  ```

- [ ] **B3.** Sửa `module_menu.php` khu "Chức năng" (glv): thay `<template x-for="m in visibleModules('glv')">` bằng vòng lặp nhóm:
  ```html
  <template x-for="g in moduleGroups('glv')" :key="g.name">
      <div class="mb-5 last:mb-0">
          <p x-show="g.name" class="text-micro font-bold text-slate-400 uppercase tracking-wider mb-2.5 px-0.5" x-text="g.name"></p>
          <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
              <template x-for="m in g.items" :key="m.key">
                  <!-- GIỮ NGUYÊN nội dung nút cũ (icon, badge, wrench, label) -->
              </template>
          </div>
      </div>
  </template>
  ```
  Làm tương tự cho khu `bdh` (thay `visibleModules('bdh')` → `moduleGroups('bdh')`). **Giữ nguyên** phần thân nút (không đổi style icon/badge).

- [ ] **B4. Kiểm chứng:** `node --check core.js`; `php -l views/module_menu.php`; preview đủ vai (GLV thường chỉ thấy nhóm có quyền, nhóm rỗng không hiện tiêu đề mồ côi vì `visibleModules` đã lọc quyền).

- [ ] **B5. Commit:** `feat(home): chia lưới nút theo cụm (Hằng ngày/Theo dõi/Quản lý)`

---

## Task 4 — Gộp Thống kê + Phân tích thành module "Báo cáo" (tab)

**Vì sao:** Hai module báo cáo gần trùng gây phân vân. Gộp một cửa nhiều tab — đúng công thức đã thắng ở "Thiếu Nhi".

**Cách làm:** Tạo một *hub* `reporthub` (nhãn "Báo cáo"), ẩn `stats`+`analytics` khỏi lưới, và nhét hai màn cũ vào hub qua tab. Không đổi backend: hub hiện khi `canAccess('stats') || canAccess('analytics')`.

**Files:**
- Tạo: `views/module_reporthub.php`, `views/partial_reports_tabs.php`
- Sửa: `public/index.php` (include hub; đổi `data-module` của stats/analytics để nằm trong hub)
- Sửa: `core.js` (moduleDefs +reporthub, ẩn stats/analytics; `openModule`; state `reportsTab`; helper hiển thị)

- [ ] **B1.** Thêm state mặc định trong `core.js` (nơi khai báo state, cùng chỗ các `*Tab` khác): `reportsTab: 'stats',`

- [ ] **B2.** `moduleDefs`: thêm def hub, đặt `hidden:true` cho stats+analytics:
  ```js
  { key: 'reporthub', label: 'Báo cáo', icon: 'bar-chart-3', color: 'text-emerald-600', area: 'glv', group: 'Theo dõi' },
  { key: 'stats',     label: 'Thống kê', icon: 'bar-chart-3', color: 'text-emerald-600', area: 'glv', hidden: true },
  { key: 'analytics', label: 'Phân tích', icon: 'bar-chart-2', color: 'text-purple-600', area: 'glv', hidden: true },
  ```

- [ ] **B3.** Cho hub qua được cửa quyền. Trong `core.js`:
  - `canAccess`: thêm ngoại lệ đầu hàm:
    ```js
    canAccess(key) {
        if (key === 'reporthub') return this.permOf('stats') !== 'none' || this.permOf('analytics') !== 'none';
        return this.permOf(key) !== 'none';
    },
    ```
  - `openModule`: thêm nhánh `if (key === 'reporthub') return this.openReportHub();` (đặt TRƯỚC các nhánh stats/analytics).
  - Thêm hàm:
    ```js
    openReportHub() {
        // mở tab đầu tiên mà người dùng có quyền
        this.reportsTab = this.permOf('stats') !== 'none' ? 'stats' : 'analytics';
        this.changeModule('reporthub');
        if (this.reportsTab === 'stats' && this.openStats) this.openStats(true);
        if (this.reportsTab === 'analytics' && this.openAnalytics) this.openAnalytics(true);
    },
    ```
    > Nếu `openStats()/openAnalytics()` gọi `changeModule('stats')` bên trong (chúng có — analytics.js:15), thêm tham số `khongDoiMan=false` để khi gọi từ hub **không** tự đổi `currentModule`. Sửa 2 hàm đó: bọc `if (!khongDoiMan) this.changeModule('stats')`.

- [ ] **B4.** Tạo `views/partial_reports_tabs.php` (thanh tab, mẫu theo `partial_children_tabs.php`):
  ```html
  <div class="flex gap-1 bg-slate-100 rounded-2xl p-1 mb-4">
      <button @click="reportsTab='stats'" x-show="permOfKey('stats')"
              :class="reportsTab==='stats' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500'"
              class="flex-1 py-2 rounded-xl text-sm font-bold transition-colors">Thống kê</button>
      <button @click="reportsTab='analytics'" x-show="permOfKey('analytics')"
              :class="reportsTab==='analytics' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500'"
              class="flex-1 py-2 rounded-xl text-sm font-bold transition-colors">Phân tích</button>
  </div>
  ```
  (Thêm helper nhỏ `permOfKey(k){ return this.permOf(k)!=='none'; }` nếu chưa có cái tương đương, để `x-show` gọn.)

- [ ] **B5.** Tạo `views/module_reporthub.php`:
  ```php
  <div data-module="reporthub" class="module-panel">
      <?php include __DIR__ . '/partial_reports_tabs.php'; ?>
      <div x-show="reportsTab==='stats'"><?php /* thân của module_stats hiện tại */ ?></div>
      <div x-show="reportsTab==='analytics'"><?php /* thân của module_analytics hiện tại */ ?></div>
  </div>
  ```
  Cách gọn nhất: **giữ** `module_stats.php`/`module_analytics.php` nhưng đổi `data-module="stats"` → bọc trong `x-show="currentModule==='reporthub' && reportsTab==='stats'"` và **đưa include của chúng vào trong** `module_reporthub.php` thay vì ở ngoài. Chọn một trong hai cách, miễn hai màn cũ chỉ hiện khi ở hub + đúng tab.

- [ ] **B6.** `public/index.php`: bỏ 2 dòng include `module_stats.php`(135)/`module_analytics.php`(147) ở ngoài, thêm `include module_reporthub.php` (hub tự include 2 màn con). Đảm bảo không còn `data-module="stats"`/`"analytics"` đứng độc lập.

- [ ] **B7. Kiểm chứng:** `node --check core.js`; `php -l` các view mới/sửa; preview → lưới chỉ còn "Báo cáo"; vào hub đổi tab mượt; vai chỉ có quyền `stats` không thấy tab Phân tích và ngược lại; PHPUnit 25/25.

- [ ] **B8. Commit:** `feat(reports): gộp Thống kê + Phân tích thành module Báo cáo (tab)`

---

## Task 5 — Thanh dưới 5 tab (Trang chủ · Thiếu Nhi · Điểm danh · Thông báo · Cá nhân)

**Vì sao:** Thanh dưới là "đất vàng" mobile nhưng đang chỉ 3 tab. Thêm 2 lối tắt phủ ~90% việc hằng ngày.

**Files:** Sửa `views/layout_bottomnav.php`. Kiểm tra `views/layout_sidebar.php` để desktop đồng bộ (tùy chọn).

- [ ] **B1.** Viết lại `layout_bottomnav.php` thành 5 nút cùng mẫu nút hiện có:
  1. **Trang chủ** — `changeModule('dashboard')` — icon `home` (luôn hiện)
  2. **Thiếu Nhi** — `changeModule('students')` — icon `users` — bọc `x-show="canAccess('students')"`
  3. **Điểm danh** — `openAttendance()` — icon `clipboard-check` — `x-show="canAccess('attendance')"`
  4. **Thông báo** — `openAnnouncements()` — icon `megaphone` — chấm đỏ `x-show="unreadAnnouncementCount>0"` (ai cũng đọc được)
  5. **Cá nhân** — `openSettings('profile')` — icon `user` (luôn hiện) — giữ chấm đỏ `myTasks.length>0 || maintenanceCount>0`
  - Active-state theo `currentModule` như cũ. Giữ class `flex-1 …` để 5 nút chia đều; `active` tô `text-blue-600 + bg-blue-50`.

- [ ] **B2.** (Tùy chọn) `layout_sidebar.php` — nếu đang liệt kê lối tắt tương tự, thêm Thiếu Nhi + Thông báo cho khớp trải nghiệm máy tính.

- [ ] **B3. Kiểm chứng:** `php -l views/layout_bottomnav.php`; preview `mobile` (resize) → 5 tab đều, không tràn; đăng nhập vai GLV kiêm nhiệm bấm từng tab đúng đích; vai không có quyền students thì tab đó ẩn (còn 4, vẫn cân).

- [ ] **B4. Commit:** `feat(nav): thanh dưới 5 tab (Thiếu Nhi + Thông báo)`

---

## Kiểm tra tổng cuối (sau cả 5 Task)
- [ ] `node --check` mọi JS đã sửa; `php -l` mọi view đã sửa/tạo.
- [ ] `php phpunit10.phar --no-coverage` → 25/25.
- [ ] E2E preview với **3 vai**: admin/BĐH, GVCN kiêm 2 lớp, Dự Bị — kiểm: Trang chủ (việc cần làm + lưới cụm), Báo cáo (tab), thanh dưới 5 tab, không nút nào lọt quyền.
- [ ] Rà `git grep -n "visibleModules('") ` để chắc không còn chỗ gọi kiểu cũ chưa nhóm.
- [ ] Cập nhật `docs/HANDOFF.md` mục bố cục.

## Thứ tự đề xuất & rủi ro
1. **Task 1** (việc cần làm) — nhỏ, giá trị cao, gần như không rủi ro.
2. **Task 2** (bỏ Sinh nhật) — 1 dòng.
3. **Task 5** (thanh dưới) — độc lập, dễ kiểm mắt.
4. **Task 3** (nhóm lưới) — trung bình.
5. **Task 4** (hub Báo cáo) — **rủi ro cao nhất** (đụng canAccess/openModule + 2 màn con), làm cuối khi các thứ khác đã ổn.

## Việc KHÔNG làm trong plan này (ghi nhận)
- Gộp Lên lớp/Chương trình/Lịch trình thành *một hub nhiều tab* (như Báo cáo): plan này chỉ **nhóm** chúng (Task 3) cho nhẹ rủi ro. Nếu muốn hub thật, mở plan riêng theo đúng khuôn Task 4.
