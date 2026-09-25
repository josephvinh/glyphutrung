# SPEC: Hoàn Thiện & Chuẩn Hoá UI/UX (TNTT Super App)

> **Mục tiêu:** Tối ưu trải nghiệm chạm di động, khắc phục triệt để lỗi bố cục responsive trên Tablet, chuẩn hoá tương tác HTML5 và nâng cao tính nhất quán thiết kế.  
> **Trạng thái:** Bản thảo (Draft)  
> **Ngày lập:** 25/09/2026  
> **Dự án:** TNTT Super App — Gia Đình Giáo Lý Phú Trung  

---

## 1. Bối cảnh & Mục tiêu

Ứng dụng TNTT Super App phục vụ chủ yếu cho các Giáo lý viên (GLV) thao tác trên điện thoại di động vào sáng Chúa Nhật (điểm danh, xin phép, xem hồ sơ) và Ban Điều Hành (BĐH) / GLV thao tác quản lý trên máy tính, máy tính bảng (nhập điểm, lập sổ liên lạc, báo cáo, phân công).

Sau quá trình rà soát toàn diện hệ thống UI/UX, tài liệu này đặc tả chi tiết các vấn đề cần khắc phục và lộ trình triển khai theo 4 giai đoạn rõ ràng.

---

## 2. Danh Sách User Stories & Tiêu Chí Nghiệm Thu (Acceptance Criteria)

### US-01: Điều hướng mượt mà trên mọi kích thước màn hình (Đặc biệt là Tablet)
- **User Story:** Là một người dùng sử dụng iPad, máy tính bảng hoặc điện thoại xoay ngang (chiều rộng từ 640px đến 1023px), tôi muốn luôn nhìn thấy thanh điều hướng để có thể chuyển đổi giữa các module một cách thuận tiện.
- **Tiêu chí nghiệm thu:**
  - Màn hình từ 640px đến 1023px hiển thị thanh điều hướng đáy (`app-bottomnav`).
  - Màn hình từ 1024px trở lên chuyển sang hiển thị thanh điều hướng bên trái (`app-sidebar`) và ẩn thanh đáy.
  - Không có khoảng kích thước màn hình nào bị mất trắng thanh điều hướng.

### US-02: Thao tác chạm nhanh trên điện thoại không phụ thuộc chuột / hover
- **User Story:** Là một GLV sử dụng điện thoại cảm ứng tại nhà thờ, tôi muốn nhìn thấy ngay các nút chức năng (sửa nhanh, sao chép số điện thoại phụ huynh) trên thẻ học sinh mà không phải tìm cách "hover" như trên máy tính.
- **Tiêu chí nghiệm thu:**
  - Cụm nút thao tác trên thẻ thiếu nhi (Grid View) luôn hiển thị rõ ràng trên thiết bị cảm ứng / màn hình nhỏ.
  - Trên màn hình máy tính (`lg:` trở lên), giữ hiệu ứng hiện khi di chuột (`group-hover`) để giữ giao diện gọn gàng.

### US-03: Xem hồ sơ thiếu nhi thuận tiện từ chế độ danh sách (List View)
- **User Story:** Khi chuyển sang xem dạng bảng danh sách (List View), tôi muốn bấm trực tiếp vào tên em hoặc nút hành động để xem ngay hồ sơ tổng hợp của em đó.
- **Tiêu chí nghiệm thu:**
  - Tên học sinh trong bảng danh sách có thể click để mở Hồ sơ thiếu nhi (`openStudentProfile(student)`).
  - Cột hành động có icon xem hồ sơ (`folder-open`).
  - Hỗ trợ sao chép cả SĐT Cha lẫn SĐT Mẹ.

### US-04: Tương tác an toàn, không xung đột click ở màn Sổ liên lạc
- **User Story:** Là một GLV lập sổ liên lạc, khi tôi tích chọn vào ô vuông (checkbox) để in nhiều phiếu, hệ thống chỉ chọn em đó thay vì nhảy vào popup sửa phiếu.
- **Tiêu chí nghiệm thu:**
  - Thẻ học sinh trong Sổ liên lạc không bọc bằng `<button>` chứa `<input>`.
  - Bấm vào checkbox chỉ thay đổi trạng thái chọn in (`selectedReports`).
  - Bấm vào vùng thông tin em mới kích hoạt mở form nhập/sửa phiếu.

### US-05: Nhất quán nhận diện thương hiệu & bố cục thị giác
- **User Story:** Là người dùng ứng dụng, tôi muốn các tab, màu sắc chủ đạo và tên gọi giữa các màn hình phải đồng bộ, tạo cảm giác chuyên nghiệp.
- **Tiêu chí nghiệm thu:**
  - Tab "Phiếu liên lạc" được gọi đồng nhất ở cả màn Thiếu Nhi lẫn trong Hồ sơ chi tiết (thay vì viết tắt "Phiếu Đ.Giá").
  - Các tab trong Hồ sơ thiếu nhi sử dụng chung tông màu chủ đạo của hệ thống (Xanh thương hiệu `blue-600`), không đổi mỗi tab một màu lòe loẹt.
  - Loại bỏ việc lồng đúp class `.module-panel` ở Hub Báo Cáo.

### US-06: Tiếp cận (Accessibility) & Tương thích môi trường
- **User Story:** Là người dùng bàn phím hoặc công cụ đọc màn hình, tôi muốn các ô nhập điểm được gán nhãn rõ ràng; và ứng dụng chạy tốt ngay cả khi cài đặt ở thư mục con.
- **Tiêu chí nghiệm thu:**
  - Các ô nhập điểm trong `module_scores.php` có `:aria-label` chứa tên học sinh và loại điểm.
  - Trang đăng nhập sử dụng đường dẫn tài nguyên tương đối, tương thích cả khi chạy ở `localhost/tntt/public/`.
  - Console trình duyệt không bắn lỗi `TypeError` liên quan đến `profileStudent.code`.

---

## 3. Thiết Kế Kỹ Thuật Chi Tiết (Technical Specification)

### Giai đoạn 1: Sửa Lỗi Nghiêm Trọng & Chuẩn Hoá HTML (Phase 1 - Critical)

#### 1.1 Khắc phục "Hố đen điều hướng" Tablet trong CSS
- **Tệp:** `public/assets/css/app.css`
- **Chi tiết thay đổi:**
  - Xoá quy tắc ẩn sai ở dòng 140–144:
    ```css
    /* XOÁ HOẶC NÂNG LÊN 1024px: */
    @media (min-width: 640px) {
        .app-bottomnav { display: none !important; }
    }
    ```
  - Lý do: Khối `@media (min-width: 1024px)` ở dòng 367 đã có sẵn `.has-sidebar .app-bottomnav { display: none; }`. Do đó, giữ `app-bottomnav` hiển thị cho mọi kích thước `< 1024px` đảm bảo tablet (640px – 1023px) luôn có thanh điều hướng dưới đáy.

#### 1.2 Tái cấu trúc thẻ học sinh trong Sổ liên lạc (`module_reports.php`)
- **Tệp:** `views/module_reports.php`
- **Chi tiết thay đổi:**
  - Đổi thẻ bao quanh từ `<button type="button" @click="...">` thành `<div class="w-full text-left bg-white rounded-field p-4 shadow-sm border flex items-center gap-3 ...">`.
  - Khu vực thông tin em (`<div class="flex-1 min-w-0 cursor-pointer" @click="...">`) nhận sự kiện mở form hoặc xem trước phiếu.
  - Ô checkbox nằm trong container riêng biệt, sự kiện `@click.stop` không bị nuốt bởi thẻ button cha.

#### 1.3 Chuẩn hoá đường dẫn asset trang Đăng nhập
- **Tệp:** `views/layout_login.php`
- **Chi tiết thay đổi:**
  - Sửa các thẻ `<link>` từ tuyệt đối `/assets/...` và `/manifest.json` thành tương đối `assets/...` và `manifest.json`, khớp chuẩn với `public/index.php`.

#### 1.4 Khắc phục lỗi Console `profileStudent.code`
- **Tệp:** `views/module_student_profile.php`
- **Chi tiết thay đổi:**
  - Kiểm tra `profileStudent` an toàn tại dòng 354 và 362:
    ```html
    <!-- Trước: -->
    <p x-text="profileStudent.code"></p>
    <!-- Sau: -->
    <p x-text="profileStudent ? profileStudent.code : ''"></p>
    ```

---

### Giai đoạn 2: Tối Ưu Trải Nghiệm Di Động & Chạm (Phase 2 - Mobile Touch)

#### 2.1 Hiển thị cụm nút Thao tác nhanh trên thiết bị di động
- **Tệp:** `views/module_students.php`
- **Chi tiết thay đổi:**
  - Sửa class hiển thị của cụm nút Sao chép SĐT & Sửa:
    ```html
    <!-- Trước: opacity-0 group-hover:opacity-100 transition-opacity -->
    <!-- Sau: opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity -->
    ```
  - Đảm bảo người dùng di động nhìn thấy và bấm được nút ngay lập tức.

#### 2.2 Bổ sung "Xem hồ sơ" & Cải tiến cột Hành động trong List View
- **Tệp:** `views/module_students.php`
- **Chi tiết thay đổi:**
  - Cột Họ Tên: Thêm class click để mở hồ sơ:
    ```html
    <td class="px-4 py-3 font-semibold text-slate-800 hover:text-blue-600 cursor-pointer"
        @click="openStudentProfile(student)" x-text="student.name"></td>
    ```
  - Cột Hành động: Thêm nút xem hồ sơ với icon `folder-open`:
    ```html
    <button @click="openStudentProfile(student)" type="button" title="Xem hồ sơ"
            class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
        <i data-lucide="folder-open" class="w-4 h-4"></i>
    </button>
    ```

---

### Giai đoạn 3: Tính Nhất Quán Thị Giác & Thẩm Mỹ (Phase 3 - Visual Polish)

#### 3.1 Gỡ bỏ lồng đúp `.module-panel` ở Báo Cáo
- **Tệp:** `views/module_stats.php`
- **Chi tiết thay đổi:**
  - Đổi thẻ bao ngoài từ `<div class="module-panel pt-6 pb-24 relative" ...>` thành `<div class="relative" ...>` để không bị nhân đôi padding và hiệu ứng animation với file cha `module_reporthub.php`.

#### 3.2 Đồng bộ phong cách Tab trong Hồ sơ thiếu nhi
- **Tệp:** `views/module_student_profile.php`
- **Chi tiết thay đổi:**
  - Đổi tên tab thứ 4 từ "Phiếu Đ.Giá" thành "Phiếu liên lạc".
  - Chuẩn hoá màu sắc khi active về màu chủ đạo: nền trắng, chữ `text-blue-600`, viền `border-slate-200/60`, đổ bóng mềm; tab không active dùng `text-slate-500`.

#### 3.3 Dọn dẹp comment cũ trong Header
- **Tệp:** `views/layout_header.php`
- **Chi tiết thay đổi:**
  - Sửa comment `<!-- Dark mode toggle + Đăng xuất -->` thành `<!-- Làm mới + Đăng xuất -->` cho đúng thực tế tính năng.

---

### Giai đoạn 4: Tiếp Cận (a11y) & Tương Tác Bàn Phím (Phase 4 - Accessibility)

#### 4.1 Gán nhãn cho ô nhập điểm
- **Tệp:** `views/module_scores.php`
- **Chi tiết thay đổi:**
  - Thêm thuộc tính `:aria-label="'Điểm ' + currentScoreType.label + ' của em ' + s.name"` vào thẻ `<input>` nhập điểm.

#### 4.2 Xử lý phím Escape đóng Dropdown
- **Tệp:** `views/module_students.php`
- **Chi tiết thay đổi:**
  - Thêm `@keydown.escape.window="showPdfMenu = false"` cho menu xuất PDF.
  - Thêm `@keydown.escape.window="showCopyMenu = false"` cho menu sao chép SĐT.

---

## 4. Kế Hoạch Kiểm Thử & Xác Minh (Verification Plan)

| Kiểm thử | Cách thực hiện | Kết quả kỳ vọng |
|---|---|---|
| **Tablet Breakpoint** | Mở trình duyệt DevTools, kéo chiều rộng từ 640px đến 1023px (iPad 768px, 820px). | Thanh bottom nav vẫn hiển thị đầy đủ 5 tab, cho phép chuyển đổi module bình thường. |
| **Desktop Breakpoint** | Mở chiều rộng ≥ 1024px. | Thanh sidebar xuất hiện, bottom nav ẩn, layout chia 2 cột hoàn hảo. |
| **Mobile Touch** | Chuyển chế độ Mobile viewport (375px - 430px). | Nút sao chép SĐT và nút Sửa hồ sơ hiển thị rõ ràng, không bị ẩn mờ. |
| **Sổ liên lạc Checkbox** | Mở màn Sổ liên lạc, bấm vào ô checkbox của 3 em liên tiếp. | 3 ô checkbox được tích chọn (`selectedReports.length === 3`), không bị kích hoạt mở modal nhập phiếu. |
| **List View xem hồ sơ** | Mở Danh sách, chuyển sang "DS" (List view), bấm vào tên em hoặc icon thư mục. | Chuyển màn hình sang xem Hồ sơ tổng hợp của đúng em đó. |
| **PHPUnit & Typecheck** | Chạy `php phpunit10.phar --no-coverage` và `npm run typecheck`. | 43/43 tests passed, không có lỗi TypeScript hay cú pháp PHP. |
