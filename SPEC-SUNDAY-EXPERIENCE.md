# SPEC: Tối Ưu Trải Nghiệm Giáo Lý Viên & Sáng Chúa Nhật (Phần 2)

> **Mục tiêu:** Nâng cấp toàn diện các công cụ hỗ trợ Giáo lý viên (GLV) vào sáng Chúa Nhật: Tìm kiếm thông minh đa năng, Điểm danh ngoại tuyến chống mất mạng, và Tối ưu máy quét QR phản hồi tức thì.  
> **Trạng thái:** Đề xuất / Bản thảo kỹ thuật (Draft)  
> **Ngày lập:** 25/09/2026  
> **Dự án:** TNTT Super App — Gia Đình Giáo Lý Phú Trung  

---

## 1. Bối cảnh & Thách Thức Thực Tế

Vào mỗi sáng Chúa Nhật tại khuôn viên Giáo xứ Phú Trung:
1. **Áp lực thời gian & Không gian:** Hơn 500 thiếu nhi tập trung trong khoảng 15–20 phút trước Thánh Lễ hoặc trước giờ học giáo lý. GLV vừa ổn định hàng ngũ vừa phải hoàn tất điểm danh.
2. **Nghẽn mạng 4G / Wi-Fi chập chờn:** Khu vực sân nhà thờ và các phòng học tập trung đông người thường làm mạng di động bị yếu hoặc ngắt quãng. Hiện tại, nếu mất mạng khi chạm tên điểm danh hoặc quét QR, thao tác có thể bị rollback (xoá điểm danh trên màn hình) hoặc rơi vào trạng thái chưa gửi được lên máy chủ, gây hoang mang cho GLV.
3. **Thói quen tìm kiếm thiếu nhi theo Tên Thánh:** GLV và Thiếu nhi thường gọi nhau kèm Tên Thánh (ví dụ: *Têrêsa Mai*, *Giuse Dũng*, *Phaolô Trí*). Bộ lọc tìm kiếm hiện tại chỉ kiểm tra từng trường rời rạc (`s.name`, `s.holyName`), dẫn đến việc khi gõ kết hợp cả Tên Thánh lẫn Tên họ thì không tìm ra em nào.
4. **Phụ huynh liên hệ khẩn cấp:** Khi phụ huynh gọi điện báo đón con hoặc xin phép, GLV cần tra cứu nhanh em đó bằng số điện thoại của cha hoặc mẹ.

---

## 2. Danh Sách User Stories & Tiêu Chí Nghiệm Thu (Acceptance Criteria)

### US-01: Tìm kiếm thông minh toàn diện (Tên Thánh + Tên Họ + SĐT)
- **User Story:** Là một GLV, tôi muốn gõ "teresa linh", "giuse nam" hoặc 4 số cuối điện thoại phụ huynh để tìm ra ngay thiếu nhi trong lớp mà không cần nhớ chính xác họ tên đầy đủ hay phân biệt dấu tiếng Việt.
- **Tiêu chí nghiệm thu:**
  - Hỗ trợ gõ không dấu (ví dụ: `daminh dung` tìm được `Đaminh Nguyễn Văn Dũng`).
  - Hỗ trợ tìm theo chuỗi ghép Tên Thánh + Tên Họ (`holyName + ' ' + name`).
  - Hỗ trợ tìm kiếm theo số điện thoại của Cha (`fatherPhone`) hoặc Mẹ (`motherPhone`).
  - Áp dụng đồng bộ trên cả 3 màn hình: **Danh sách thiếu nhi**, **Điểm danh** và **Xin phép**.

### US-02: Điểm danh ngoại tuyến & Tự động đồng bộ (Offline Resilience)
- **User Story:** Là một GLV đang đứng điểm danh ở góc sân nhà thờ nơi sóng 4G bị mất, tôi muốn việc chạm điểm danh vào tên thiếu nhi vẫn được ghi nhận tức thì, không bị nhảy ngược lại, và tự động đồng bộ lên máy chủ ngay khi có mạng trở lại.
- **Tiêu chí nghiệm thu:**
  - Khi mất mạng hoặc máy chủ không phản hồi: Điểm danh chạm tay **không bị rollback xoá khỏi màn hình**.
  - Dữ liệu điểm danh chưa gửi được lưu vào hàng đợi cục bộ (`localStorage: tntt_offline_attendance`).
  - Hiển thị huy hiệu/thanh thông báo nhỏ (Offline Badge): *"Ngoại tuyến: Đang lưu tạm X em, sẽ tự đồng bộ khi có mạng"*.
  - Khi thiết bị kết nối lại mạng (hoặc khi bấm nút "Làm mới / Đồng bộ" trên header): Hệ thống tự động đẩy toàn bộ hàng đợi lên server và dọn sạch hàng đợi.

### US-03: Trải nghiệm quét mã QR camera siêu tốc & Đèn pin (Focus Fast Scanner)
- **User Story:** Là một GLV quét thẻ QR cho toàn khối lúc sáng sớm hoặc trong hành lang thiếu sáng, tôi muốn máy quét có nút bật đèn Flash camera và phản hồi rung/âm thanh rõ ràng để không cần phải nhìn chằm chằm vào màn hình.
- **Tiêu chí nghiệm thu:**
  - Bổ sung nút bật/tắt **Đèn pin (Flash/Torch)** trên khung ngắm quét QR (hỗ trợ các máy có đèn flash).
  - Phản hồi rung haptic (`navigator.vibrate(60)`) kết hợp âm bíp mỗi khi nhận diện thẻ thành công.
  - Trường hợp đóng camera khi mạng đang mất: Toàn bộ các mã trong hàng đợi quét chưa kịp gửi sẽ được lưu bền vững vào bộ nhớ tạm để gửi tiếp, không bị mất.

---

## 3. Thiết Kế Kỹ Thuật Chi Tiết (Technical Specification)

### 3.1. Chuẩn hoá Bộ máy Tìm kiếm Dùng Chung (`matchStudentSearch`)
- **Tệp chỉnh sửa:** `public/assets/js/modules/shell.js`, `public/assets/js/modules/access.js`, `public/assets/js/modules/attendance.js`, `public/assets/js/modules/leave.js`.
- **Hàm tiện ích mới trong `shell.js`:**
  ```javascript
  /**
   * So khớp tìm kiếm thiếu nhi toàn diện:
   * 1. Tên, Tên thánh riêng lẻ
   * 2. Chuỗi kết hợp: "Tên Thánh + Tên" (VD: "teresa mai")
   * 3. Mã định danh GDGLPT
   * 4. Số điện thoại Cha hoặc Mẹ
   */
  matchStudentSearch(student, query) {
      if (!query) return true;
      const q = this.normalizeText(query);
      if (!q) return true;

      // 1. Tên thánh, họ tên riêng lẻ & kết hợp
      const holy = this.normalizeText(student.holyName || '');
      const name = this.normalizeText(student.name || '');
      const full = (holy + ' ' + name).trim();
      const code = this.normalizeText(student.code || '');

      if (name.includes(q) || holy.includes(q) || full.includes(q) || code.includes(q)) {
          return true;
      }

      // 2. Tìm theo số điện thoại phụ huynh (bỏ ký tự trắng/chấm/gạch nối)
      const cleanPhone = str => String(str || '').replace(/\D/g, '');
      const qDigits = q.replace(/\D/g, '');
      if (qDigits.length >= 3) {
          const fatherPhone = cleanPhone(student.fatherPhone);
          const motherPhone = cleanPhone(student.motherPhone);
          if (fatherPhone.includes(qDigits) || motherPhone.includes(qDigits)) {
              return true;
          }
      }

      return false;
  }
  ```

---

### 3.2. Cơ Chế Hàng Đợi Điểm Danh Ngoại Tuyến (Offline Attendance Queue)
- **Tệp chỉnh sửa:** `public/assets/js/modules/attendance.js`, `public/assets/js/modules/core.js`, `views/module_attendance.php`.
- **Cấu trúc hàng đợi lưu trữ trong `localStorage`:**
  Key: `tntt_offline_attendance_queue`
  ```json
  [
    {
      "id": "att_1727280000_123",
      "programId": 5,
      "date": "2026-09-27",
      "studentId": 123,
      "status": "có mặt",
      "action": "toggle",
      "createdAt": "2026-09-27T07:15:00.000Z"
    }
  ]
  ```

- **Luồng xử lý khi chạm điểm danh (`toggleAttendance`):**
  1. Cập nhật giao diện tức thì (Optimistic UI) bằng `_attThem(newRec)` hoặc `_attXoa(...)`.
  2. Kiểm tra `navigator.onLine`:
     - Nếu đang offline: Đẩy bản ghi vào `tntt_offline_attendance_queue` ngay lập tức, hiển thị Offline Badge, **không rollback**.
     - Nếu online: Gửi `api('attendance', 'toggle', ...)`. Nếu fetch thất bại vì rớt mạng giữa chừng: Tự động gom bản ghi vào hàng đợi offline thay vì rollback.
  3. **Bộ xử lý tự động đồng bộ (Auto Sync Worker):**
     - Lắng nghe sự kiện `window.addEventListener('online', () => this.syncOfflineAttendance())`.
     - Tích hợp vào hàm `refreshApp()` (khi bấm nút Làm mới trên Header).
     - Định kỳ kiểm tra mỗi 15 giây nếu hàng đợi có phần tử và mạng đang khả dụng.
     - Sau khi đồng bộ thành công, gọi `window.TNTT.toast.success('Đã đồng bộ X bản ghi điểm danh lên máy chủ!')`.

- **UI Indicator trong `views/module_attendance.php`:**
  - Bổ sung thanh trạng thái nhỏ phía trên danh sách điểm danh khi có hàng đợi:
    ```html
    <div x-show="offlineAttendanceCount > 0" class="mb-4 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between text-xs text-amber-800">
        <span class="flex items-center gap-1.5 font-bold">
            <i data-lucide="cloud-off" class="w-4 h-4 text-amber-600"></i>
            Có <span x-text="offlineAttendanceCount"></span> lượt điểm danh đang lưu tạm trên máy
        </span>
        <button @click="syncOfflineAttendance()" class="font-bold underline text-amber-900 active:scale-95">Đồng bộ ngay</button>
    </div>
    ```

---

### 3.3. Tối Ưu Quét Mã QR — Đèn Flash & Bền Vững Dữ Liệu
- **Tệp chỉnh sửa:** `public/assets/js/modules/qrscan.js`, `views/module_attendance.php`.
- **Nút bật/tắt đèn pin (Torch / Flashlight):**
  - Sử dụng MediaStream Track Capabilities:
    ```javascript
    async toggleTorch() {
        if (!this._qrStream) return;
        const track = this._qrStream.getVideoTracks()[0];
        if (!track) return;
        const capabilities = track.getCapabilities ? track.getCapabilities() : {};
        if (!capabilities.torch) {
            window.TNTT.toast.warning('Thiết bị không hỗ trợ bật đèn pin từ trình duyệt.');
            return;
        }
        this.qrTorchOn = !this.qrTorchOn;
        await track.applyConstraints({
            advanced: [{ torch: this.qrTorchOn }]
        });
    }
    ```
- **Bảo lưu mã quét dở dang khi mất mạng:**
  - Trong `ketThucQuet()`: Nếu còn mã trong `_qrHang` chưa kịp gửi do mất mạng, lưu toàn bộ vào `tntt_offline_attendance_queue` theo dạng batch để tiến trình đồng bộ nền gửi tiếp, không yêu cầu GLV phải điểm danh tay lại.

---

## 4. Kế Hoạch Kiểm Thử & Nghiệm Thu (Verification Plan)

| Nội dung kiểm thử | Phương pháp kiểm thử | Kết quả kỳ vọng |
|---|---|---|
| **Tìm kiếm Tên Thánh + Họ Tên** | Gõ `teresa mai`, `phaolo hung`, `daminh` trên ô tìm kiếm Thiếu nhi và Điểm danh. | Lọc đúng các em có Tên Thánh và Họ Tên khớp, không bị rỗng kết quả. |
| **Tìm kiếm bằng Số điện thoại** | Gõ 4 số cuối điện thoại của phụ huynh vào ô tìm kiếm. | Hiển thị đúng em thiếu nhi tương ứng của phụ huynh đó. |
| **Điểm danh ngoại tuyến (Offline)** | Mở DevTools Network, chọn chế độ `Offline`. Bấm điểm danh 3 em. | Thẻ của 3 em chuyển màu xanh "có mặt", xuất hiện thông báo vàng "Có 3 lượt điểm danh đang lưu tạm". |
| **Tự động đồng bộ khi có mạng** | Chuyển Network trở lại `Online` (hoặc bấm nút "Đồng bộ ngay"). | Hàng đợi được gửi lên server thành công, thông báo xanh chúc mừng, danh sách máy chủ cập nhật đủ 3 em. |
| **Đèn pin & Rung khi quét QR** | Bật camera quét QR trên điện thoại thực tế (Android/iOS). | Nút đèn pin bật sáng đèn flash sau máy; khi quét trúng thẻ điện thoại rung nhẹ 60ms và phát tiếng bíp. |
| **Kiểm tra hồi quy (Regression)** | Chạy `php phpunit10.phar --no-coverage` và `npm run typecheck`. | 43/43 tests passed, 0 lỗi TypeScript. |

---

## 5. Lộ Trình Triển Khai

1. **Bước 1:** Cập nhật bộ máy tìm kiếm đa năng (`matchStudentSearch`) trong `shell.js`, tích hợp vào `access.js`, `attendance.js`, `leave.js`.
2. **Bước 2:** Xây dựng cơ chế hàng đợi ngoại tuyến `offlineQueue` và hàm `syncOfflineAttendance()` trong `attendance.js`.
3. **Bước 3:** Thêm chỉ báo UI Offline Banner trong `views/module_attendance.php`.
4. **Bước 4:** Nâng cấp máy quét QR (`qrscan.js`) với nút Đèn pin và cơ chế bảo lưu hàng đợi khi tắt camera.
5. **Bước 5:** Kiểm thử thực địa (test offline network simulation), chạy PHPUnit, typecheck và commit.
