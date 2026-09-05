# Phạm Vi Kiêm Nhiệm — UI Phase 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use `- [ ]`.

**Goal:** Cho giao diện phản ánh đúng kiêm nhiệm — người phụ trách nhiều lớp/khối thấy và thao tác được trên tất cả, thay vì bị khóa vào 1 phân công chính.

**Architecture:** Frontend đối xứng với backend Phase 1. `access.js` là tầng lọc phạm vi ở client (giống `_bootstrap.php` ở server). Thêm getter dẫn xuất `isUnrestrictedScope` / `myBlocks` / `myClasses` đọc từ `this.assignments` (boot data đã có), rồi viết lại các getter `accessibleStudents` / `availableBlocks` / `availableClasses` / `writableClasses` theo chúng, có fallback về `user.*` khi `assignments` rỗng (tương thích ngược). Các module khác (label phạm vi, chọn lớp điểm danh, sĩ số sinh nhật, profile) chỉ đổi để đọc nguồn dẫn xuất này.

**Tech Stack:** Alpine.js, vanilla JS (ES modules gộp qua app.js), PHP views, Tailwind. Không có test JS tự động → xác minh bằng trình duyệt (controller làm, XAMPP phục vụ branch).

**Spec:** Mục "Tác động UI downstream" trong [2026-09-05-scope-per-assignment.md](2026-09-05-scope-per-assignment.md). Phase 1 backend đã xong (branch feature/scope-per-assignment).

## Global Constraints

- `this.assignments` (boot) mỗi phần tử có: `role`, `roleLabel`, `scope` ('toàn đoàn'|'khối'|'lớp'), `blockId`, `blockName`, `classId`, `className`, `isPrimary`, `fromDate`, `toDate`. Dùng `scope`/`className`/`blockName`/`isPrimary`.
- `this.classes` mỗi phần tử có `.name` và `.block` (tên khối, chuỗi). `this.blocks` là mảng tên khối (chuỗi). `this.students` mỗi em có `.className` và `.block`.
- Khi `this.assignments` rỗng → fallback về hành vi cũ theo `user.role`/`user.managedBlock`/`user.assignedClass` (không được vỡ cho dữ liệu cũ / lúc chưa nạp).
- Không đổi backend, không đổi chữ ký boot. Tên hàm JS camelCase. Chuỗi tiếng Việt giữ nguyên dấu.
- Không gộp getter làm hỏng người dùng hiện tại (admin/bdh vẫn thấy toàn bộ; truong_khoi vẫn thấy cả khối).

---

## File Structure

| File | Trách nhiệm |
|------|-------------|
| `public/assets/js/modules/access.js` | Getter dẫn xuất + 4 getter phạm vi viết lại |
| `public/assets/js/modules/core.js` | `myScopeLabel` đa phân công |
| `public/assets/js/modules/attendance.js` | `startSession` mặc định lớp chính, cho chọn trong myClasses |
| `public/assets/js/modules/birthdays.js` | `myClassSize` cộng qua myClasses |
| `views/module_profile.php` | Đánh dấu ★ phân công chính |

---

### Task 1: Getter dẫn xuất + viết lại lọc phạm vi trong access.js

**Files:**
- Modify: `public/assets/js/modules/access.js`

**Interfaces:**
- Produces: getter `isUnrestrictedScope: boolean`, `myBlocks: string[]`, `myClasses: string[]`; và `accessibleStudents`/`availableBlocks`/`availableClasses`/`writableClasses` đọc từ chúng.

- [ ] **Step 1: Thêm 3 getter dẫn xuất ngay sau dòng mở object (sau `window.TNTT.access = {`, trước `get accessibleStudents`)**

```javascript
    // --- Nguồn dẫn xuất phạm vi (kiêm nhiệm): đọc từ this.assignments ---
    // Không giới hạn nếu có BẤT KỲ phân công 'toàn đoàn' nào (Quản Trị / BĐH).
    get isUnrestrictedScope() {
        const a = this.assignments || [];
        if (a.length) return a.some(x => x.scope === 'toàn đoàn');
        return ['admin', 'bdh'].includes(this.user.role);   // fallback dữ liệu cũ
    },

    // Tên các KHỐI mình phụ trách, hợp mọi phân công đang hiệu lực.
    get myBlocks() {
        const a = this.assignments || [];
        if (!a.length) {   // fallback theo phân công chính
            if (this.user.role === 'truong_khoi') return [this.user.managedBlock];
            const cls = this.classes.find(c => c.name === this.user.assignedClass);
            return cls ? [cls.block] : [];
        }
        const set = new Set();
        a.forEach(x => {
            if (x.blockName) set.add(x.blockName);
            else if (x.className) {
                const c = this.classes.find(cc => cc.name === x.className);
                if (c) set.add(c.block);
            }
        });
        return [...set];
    },

    // Tên các LỚP mình phụ trách; phân công khối mở rộng ra mọi lớp trong khối.
    get myClasses() {
        const a = this.assignments || [];
        if (!a.length) {   // fallback theo phân công chính
            return this.user.assignedClass ? [this.user.assignedClass] : [];
        }
        const set = new Set();
        a.forEach(x => {
            if (x.className) set.add(x.className);
            else if (x.blockName) {
                this.classes.filter(c => c.block === x.blockName).forEach(c => set.add(c.name));
            }
        });
        return [...set];
    },
```

- [ ] **Step 2: Viết lại `accessibleStudents` (thay khối dòng 10-14)**

Thay:
```javascript
    get accessibleStudents() {
        if (['admin', 'bdh'].includes(this.user.role)) return this.students;
        if (this.user.role === 'truong_khoi') return this.students.filter(s => s.block === this.user.managedBlock);
        return this.students.filter(s => s.className === this.user.assignedClass);
    },
```
bằng:
```javascript
    get accessibleStudents() {
        if (this.isUnrestrictedScope) return this.students;
        const classes = this.myClasses;
        return this.students.filter(s => classes.includes(s.className));
    },
```

- [ ] **Step 3: Viết lại `availableBlocks` (thay khối dòng 17-22)**

Thay:
```javascript
    get availableBlocks() {
        if (['admin', 'bdh'].includes(this.user.role)) return this.blocks;
        if (this.user.role === 'truong_khoi') return this.blocks.filter(b => b === this.user.managedBlock);
        const cls = this.classes.find(c => c.name === this.user.assignedClass);
        return cls ? [cls.block] : [];
    },
```
bằng:
```javascript
    get availableBlocks() {
        if (this.isUnrestrictedScope) return this.blocks;
        const mine = this.myBlocks;
        return this.blocks.filter(b => mine.includes(b));
    },
```

- [ ] **Step 4: Viết lại nhánh giới hạn trong `availableClasses` (dòng 27-29)**

Thay:
```javascript
        if (!['admin', 'bdh', 'truong_khoi'].includes(this.user.role)) {
            list = list.filter(c => c.name === this.user.assignedClass);
        }
```
bằng:
```javascript
        if (!this.isUnrestrictedScope) {
            const mine = this.myClasses;
            list = list.filter(c => mine.includes(c.name));
        }
```

- [ ] **Step 5: Viết lại `writableClasses` (thay khối dòng 40-47)**

Thay:
```javascript
    get writableClasses() {
        const vt = this.user.role;
        if (vt === 'admin' || vt === 'bdh') return null;
        if (vt === 'truong_khoi') {
            return this.classes.filter(c => c.block === this.user.managedBlock).map(c => c.name);
        }
        return this.user.assignedClass ? [this.user.assignedClass] : [];
    },
```
bằng:
```javascript
    get writableClasses() {
        if (this.isUnrestrictedScope) return null;   // Quản Trị / BĐH: không giới hạn
        return this.myClasses;
    },
```

- [ ] **Step 6: Kiểm cú pháp JS**

Run: `node --check public/assets/js/modules/access.js`
Expected: không lỗi (thoát 0).

- [ ] **Step 7: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/assets/js/modules/access.js && git commit -m "feat(ui): make access.js scope filters kiêm-nhiệm aware"
```

---

### Task 2: Wiring các module phụ (label, điểm danh, sinh nhật, profile)

**Files:**
- Modify: `public/assets/js/modules/core.js` (`myScopeLabel`, ~198-203)
- Modify: `public/assets/js/modules/attendance.js` (`startSession`, ~63-66)
- Modify: `public/assets/js/modules/birthdays.js` (`myClassSize`, ~44-46)
- Modify: `views/module_profile.php` (danh sách phân công, ~209-218)

**Interfaces:**
- Consumes: `myClasses`, `isUnrestrictedScope` từ Task 1.

- [ ] **Step 1: `core.js` — `myScopeLabel` đa phân công**

Thay:
```javascript
    get myScopeLabel() {
        const scope = this.roleScope(this.user.role);
        if (scope === 'toàn đoàn') return 'Toàn đoàn';
        if (scope === 'khối') return 'Khối ' + this.user.managedBlock;
        return 'Lớp ' + this.user.assignedClass;
    },
```
bằng:
```javascript
    get myScopeLabel() {
        const a = this.assignments || [];
        if (this.isUnrestrictedScope) return 'Toàn đoàn';
        if (!a.length) {   // fallback theo phân công chính
            const scope = this.roleScope(this.user.role);
            if (scope === 'khối') return 'Khối ' + this.user.managedBlock;
            return 'Lớp ' + this.user.assignedClass;
        }
        const parts = [...new Set(a.map(x =>
            x.className ? x.className : (x.blockName ? 'Khối ' + x.blockName : 'Toàn đoàn')))];
        return parts.join(' · ');
    },
```

- [ ] **Step 2: `attendance.js` — cho GLV kiêm nhiệm chọn giữa các lớp**

Thay khối `startSession` gán `attendanceClass` (dòng 63-66):
```javascript
        // GLV thì khóa cứng vào lớp mình, cấp trên thì mặc định mở lớp đang phụ trách
        this.attendanceClass = ['admin', 'bdh', 'truong_khoi'].includes(this.user.role)
            ? (this.availableClasses.includes(this.user.assignedClass) ? this.user.assignedClass : '')
            : this.user.assignedClass;
```
bằng:
```javascript
        // Mặc định lớp chính; người kiêm nhiệm vẫn chuyển được sang lớp khác
        // qua bộ chọn (availableClasses đã gồm mọi lớp mình phụ trách).
        this.attendanceClass = this.availableClasses.includes(this.user.assignedClass)
            ? this.user.assignedClass
            : (this.availableClasses[0] || '');
```

- [ ] **Step 3: `birthdays.js` — `myClassSize` cộng qua myClasses**

Thay:
```javascript
    get myClassSize() {
        return this.students.filter(s => s.className === this.user.assignedClass && s.status === 'đang sinh hoạt').length;
    },
```
bằng:
```javascript
    get myClassSize() {
        const classes = this.myClasses;
        return this.students.filter(s => classes.includes(s.className) && s.status === 'đang sinh hoạt').length;
    },
```

- [ ] **Step 4: `views/module_profile.php` — đánh dấu ★ phân công chính**

Trong khối `<template x-for="a in assignments" :key="a.id">` (khoảng dòng 209), đảm bảo có chỉ báo primary. Nếu chưa có, thêm vào trong template, cạnh nhãn vai trò:
```html
                <span x-show="a.isPrimary" class="text-micro font-black text-amber-600" title="Phân công chính">★</span>
```
(Đọc đoạn hiện tại trước; nếu đã có chỉ báo primary tương đương thì bỏ qua step này và ghi rõ trong report.)

- [ ] **Step 5: Kiểm cú pháp**

Run:
```bash
node --check public/assets/js/modules/core.js && node --check public/assets/js/modules/attendance.js && node --check public/assets/js/modules/birthdays.js && php -l views/module_profile.php
```
Expected: tất cả OK.

- [ ] **Step 6: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/assets/js/modules/core.js public/assets/js/modules/attendance.js public/assets/js/modules/birthdays.js views/module_profile.php && git commit -m "feat(ui): wire scope label, attendance class picker, birthday count, profile primary marker to assignments"
```

---

### Task 3: Xác minh trình duyệt + ghi chú tài liệu (controller)

**Files:**
- Modify: `docs/features/kiem-nhiem.md` (ghi chú UI)

- [ ] **Step 1: Xác minh trình duyệt (controller làm — subagent bỏ qua)**

Mở app qua XAMPP (http://localhost/tntt/public/), đăng nhập một GLV kiêm 2 lớp và kiểm:
1. Trang chủ: nhãn phạm vi hiện cả 2 lớp ("Lớp A · Lớp B").
2. Khối/Lớp: bộ lọc lớp liệt kê cả 2 lớp; danh sách thiếu nhi gồm cả 2 lớp.
3. Điểm danh: bắt đầu buổi → mặc định lớp chính, đổi được sang lớp kia; roster đúng theo lớp chọn.
4. Cá nhân: mọi phân công hiển thị, phân công chính có ★.
5. Admin/BĐH vẫn thấy toàn bộ; Trưởng Khối vẫn thấy cả khối (không hồi quy).

- [ ] **Step 2: Ghi chú UI vào tài liệu**

Thêm vào cuối `docs/features/kiem-nhiem.md`:
```markdown

### Giao diện

Giao diện đọc phạm vi từ danh sách phân công của bạn:
- Nhãn phạm vi (Trang chủ) liệt kê mọi lớp/khối bạn phụ trách.
- Bộ lọc lớp, danh sách thiếu nhi, sĩ số gồm tất cả lớp kiêm nhiệm.
- Điểm danh mặc định lớp chính, chuyển được sang lớp khác qua bộ chọn.
- Trang cá nhân đánh dấu ★ cho phân công chính.

Cơ sở: getter `myClasses` / `isUnrestrictedScope` trong `access.js`, có fallback về phân công chính khi chưa có dữ liệu phân công.
```

- [ ] **Step 3: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add docs/features/kiem-nhiem.md && git commit -m "docs: note UI behavior for kiêm nhiệm scope"
```

---

## Self-Review

- **Spec coverage:** access.js core (T1) phủ accessibleStudents/availableBlocks/availableClasses/writableStudents; label (T2.1), điểm danh (T2.2), sinh nhật (T2.3), profile ★ (T2.4); xác minh + docs (T3). Đủ các điểm trong bảng "Tác động UI downstream".
- **Placeholder scan:** mọi step có code thật hoặc lệnh thật.
- **Type consistency:** `myClasses`/`myBlocks` trả `string[]` (tên lớp/khối), khớp cách `this.students` dùng `.className`/`.block` và `this.classes` dùng `.name`/`.block`; `isUnrestrictedScope` boolean. `writableClasses` giữ hợp đồng cũ (null = không giới hạn).

## Rủi ro

- Không có test JS tự động; xác minh dựa vào trình duyệt (T3) — controller chịu trách nhiệm, không giao subagent.
- Fallback khi `assignments` rỗng giữ nguyên hành vi cũ → không hồi quy cho tài khoản chưa có bản ghi phân công.
