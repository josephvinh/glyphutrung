# Quy Ước Audit Chức Năng (Chi Tiết)

> Bộ ba audit của dự án: **bảo mật** (`SECURITY_AUDIT.md` — ai lấy được gì không
> được phép), **design** (`DESIGN_AUDIT.md` — trông/đụng vào thế nào), và
> **chức năng** (tài liệu này — mỗi tính năng có **làm đúng việc** không, với
> **mọi vai**, trong **mọi ca**). `TESTING.md` nói *cách chạy* test; tài liệu này
> nói *rà cái gì* để khẳng định chức năng đúng.
>
> Chính lớp này lẽ ra bắt được các lỗi như: điều hướng vỡ (#194), mất
> `normalizeText`, quyền Thư viện bị nuốt khi cài mới — tất cả đều "chạy" theo
> nghĩa không crash backend, nhưng **sai chức năng**.

---

## 1. Mục tiêu & khi nào

- **Mục tiêu:** chứng minh mỗi tính năng cho **kết quả đúng** và **dữ liệu toàn
  vẹn**, không chỉ "không báo lỗi". Audit chức năng trả lời: *đúng vai thấy đúng
  thứ? thao tác cho đúng số? ca biên có hỏng? dữ liệu sau thao tác có nhất quán?*
- **Khi nào:**
  - PR đụng một module → audit chức năng **module đó** (happy + biên + lỗi + đúng
    vai) trước khi merge.
  - Định kỳ / trước phát hành → audit **toàn app** theo ma trận ở mục 2, xuất
    `docs/audit/FUNCTIONAL_AUDIT_<YYYY-MM>.md`.
  - Sau mỗi sự cố chức năng → thêm ca vào checklist để không tái phát.

---

## 2. Phạm vi

### 2.1 Ma trận Module × Vai

Kiểm **mỗi module** dưới **mỗi vai** — vì phân quyền theo vai là trung tâm app.

- **Vai:** `admin`, `bdh` (Ban Điều Hành), `truong_khoi`, `glv_chu_nhiem`, `glv`,
  `du_bi`, `thu_thu`; **+ công khai** (không đăng nhập).
- **Module:** `students`, `student_profile`, `attendance`, `scores`, `reports`,
  `leave`, `promotion`, `programs`, `org`, `staff`, `years`, `settings`,
  `announcements`, `calendar`, `notes`, `birthdays`, `reporthub`, `stats`,
  `analytics`, `qrcard`, `thu_vien`, `guide`, `gifts`, `rewards`; **+ trang công
  khai** `tracuu.php`, `somoc.php` (+ đặt/hủy đơn), `bxh.php`.

Với mỗi ô (module, vai) xác nhận: **thấy/không thấy** đúng; **sửa được/chỉ xem**
đúng; và **phạm vi** đúng (GLV chỉ lớp mình, trưởng khối chỉ khối mình, admin/BĐH
toàn đoàn). Người phạm vi hẹp thao tác lên **lớp khác → phải bị chặn** (trùng với
test bảo mật "lớp khác → 403").

### 2.2 Bốn khía cạnh cho mỗi tính năng

1. **Happy path** — luồng thường, dữ liệu hợp lệ, cho kết quả đúng.
2. **Ca biên** — rỗng/null, biên thời gian (giờ chốt), số lượng lớn (import 500+
   em), niên khoá **đã khóa**, trùng, Unicode/dấu tiếng Việt.
3. **Xử lý lỗi** — input sai → thông báo thân thiện, **không** đổi dữ liệu, không
   lộ lỗi gốc.
4. **Toàn vẹn dữ liệu** — sau thao tác, DB ở trạng thái hợp lệ (kiểm bằng truy
   vấn, không chỉ nhìn UI).

### 2.3 Nhất quán liên-tính-năng (dễ bỏ sót nhất)

- Điểm danh buổi có tính Mộc → **Sổ Mộc recalc** đúng (ví/chuỗi).
- Gỡ điểm danh → hoàn Mộc; đặt đơn quà → **giữ** Mộc/tồn; giao → **trừ**; hủy/quá
  hạn → **hoàn**.
- Lên lớp (promotion) → tạo **ghi danh niên khoá mới**, giữ lịch sử năm cũ.
- Xóa/đổi khối-lớp → **phân công** (`member_assignments`) + cột dẫn xuất trên
  `members` đi theo (`recompute_member_primary`).
- Duyệt/sửa vai → vai gốc + khối/lớp hiển thị tính lại đúng.

---

## 3. Bất biến nghiệp vụ (phải LUÔN đúng — kiểm sau mỗi thao tác liên quan)

Audit chức năng tốt là săn chỗ **vi phạm bất biến**:

- **Ví Mộc:** `current_balance ≥ 0`, `held_balance ≥ 0`, và `held ≤ current`
  (khả dụng = `current − held ≥ 0`). `total_earned` chỉ cộng earn/bonus.
- **Ghi danh:** mỗi `(year_id, student_id)` **đúng một** dòng `enrollments`.
- **Điểm danh:** trạng thái (`có mặt`/`đi trễ`/vắng) do **máy chủ** quyết theo giờ
  chốt, **không** nhận từ client; không điểm danh buổi **tương lai**; mỗi
  `(program, date, student)` tối đa một dòng.
- **Phạm vi:** dữ liệu một người nhận được ⊆ phạm vi phân công của họ (không có em
  lớp khác lọt vào payload).
- **Phân công là nguồn thật:** `members.role_code/block_id/class_id` luôn = dẫn
  xuất từ `member_assignments` đang hiệu lực.
- **Niên khoá khóa sổ:** mọi endpoint **ghi** từ chối khi `status='đã khóa'`.
- **Mã thiếu nhi** bền theo em (đổi mã = hỏng thẻ QR) và duy nhất toàn đoàn.
- **Điểm số** ∈ [0,10]; ô trống = **xóa** điểm, không phải chấm 0.

---

## 4. Vòng đời trạng thái (state machine) — kiểm chuyển hợp lệ + CHẶN chuyển sai

Mỗi máy trạng thái: xác nhận chuyển **đúng** chạy được, và chuyển **sai** bị từ
chối (không "nhảy cóc"):

| Đối tượng | Trạng thái | Quy tắc chuyển cần kiểm |
|-----------|-----------|--------------------------|
| Thành viên | `chờ duyệt → đang phục vụ / tạm nghỉ / đã nghỉ` | duyệt mới bật; `đã nghỉ` không đăng nhập được; `chờ duyệt` chưa vào app |
| Đơn phép | `chờ duyệt → đã duyệt / từ chối` | chỉ duyệt đơn lớp mình; đã xử lý không xử lý lại |
| Đơn quà | `chờ lấy → đã giao / đã hủy / quá hạn` | giao cần mật mã (hoặc override có log); mỗi em tối đa 1 đơn `chờ lấy`; hủy/quá hạn hoàn Mộc+tồn |
| Tài liệu TV | `cho_duyet → da_duyet / tu_choi` | GLV đăng = chờ duyệt; BĐH đăng = duyệt luôn; sửa lại về chờ duyệt |
| Kết quả năm | `chưa xét → lên lớp / ở lại / ra trường` | cần khai sơ đồ lớp kế tiếp; không chuyển nửa vời |
| Thông báo | `nháp → đã phát` | phát mới dội chuông; thu hồi về nháp |
| Phiếu LL | `nháp → đã gửi` | gửi phải có nhận xét |
| Chương trình | `kích hoạt → đã đóng` | đóng rồi không điểm danh; xóa chặn nếu đã có điểm danh |
| Niên khoá | `đang mở → đã khóa` | khóa chặn mọi ghi; không khóa năm đang dùng |
| Đổi mật khẩu | `must_change_pw=1 → 0` | khi cờ bật chỉ cho đổi mật khẩu, chặn API khác |

---

## 5. Checklist theo nhóm tính năng (ca cụ thể cần phủ)

**Điểm danh**
- [ ] Chạm tay trước/sau giờ chốt → `có mặt`/`đi trễ` đúng; sau "giờ vắng" → chặn.
- [ ] Quét QR lô: em lớp khác/không sinh hoạt/không thuộc buổi → bị bỏ, báo rõ.
- [ ] Buổi tương lai → chặn ghi; gỡ bản ghi cũ vẫn được; Mộc recalc đúng.
- [ ] Đổi giờ máy điện thoại **không** đổi được trạng thái (máy chủ quyết).

**Thiếu nhi (danh sách / import / chuyển / xóa)**
- [ ] Thêm mới: mã do máy chủ cấp, duy nhất. Sửa: giữ mã.
- [ ] Import: dòng sai ngày sinh/lớp không tồn tại/ngoài phạm vi → bỏ qua, báo
      dòng; không ghi NULL âm thầm; 500+ dòng chạy gọn.
- [ ] Chuyển/xóa em lớp khác (IDOR) → chặn; xóa em còn lịch sử năm khác → chặn.

**Điểm / Phiếu liên lạc**
- [ ] Điểm ngoài [0,10] → từ chối; ô trống → xóa điểm; học kỳ sai năm → chặn.
- [ ] Phiếu gửi thiếu nhận xét → chặn; số liệu điểm danh là bản chụp lúc lập.

**Sổ Mộc / Đổi quà**
- [ ] Đặt đơn vượt Mộc khả dụng → từ chối; giữ held đúng; mỗi em 1 đơn chờ lấy.
- [ ] Giao đúng mật mã / override có log; hủy hoàn Mộc+tồn; quá hạn tự dọn.
- [ ] Hai quầy thao tác đồng thời (đua) không làm âm ví/tồn (khóa `FOR UPDATE`).

**Nhân sự / Khối-Lớp / Phân công**
- [ ] Duyệt chỉ đặt vai cơ sở (glv/du_bi); gán chủ nhiệm/trưởng khối đúng phạm vi.
- [ ] Không gán/hạ vai cho admin/BĐH; đổi vai người đang kiêm nhiệm ở màn Nhân sự
      → báo làm ở Khối&Lớp.
- [ ] Xóa khối còn lớp / lớp còn em hoặc còn người phụ trách → chặn.

**Lên lớp cuối năm**
- [ ] Thiếu sơ đồ lớp kế tiếp → chặn; lên/ở lại/ra trường tạo đúng ghi danh năm
      đích + giữ lịch sử; chạy lại không nhân đôi.

**Niên khoá / Thông báo / Xin phép / Lịch**
- [ ] Khóa niên khoá → mọi ghi bị chặn; đổi niên khoá đang dùng → chặn.
- [ ] Thông báo khối: trưởng khối chỉ gửi khối mình; RSVP họp; đọc/đã đọc.
- [ ] Xin phép sau giờ chốt chỉ cho em đang vắng; duyệt đúng phạm vi.

**Trang công khai**
- [ ] Tra cứu cần mã **+ ngày sinh**; sai → lỗi gộp; vượt ngưỡng → 429.
- [ ] Chỉ lộ tên/lớp/điểm/Mộc; không lộ SĐT/địa chỉ; không liệt kê được toàn bộ em.

**Điều hướng / khung app (bài học #194)**
- [ ] Đổi màn: `currentModule` đồng bộ, thanh dưới/sidebar/tab sáng đúng mục.
- [ ] Nút Back trình duyệt chạy; route lạ không ẩn hết màn.
- [ ] Tìm kiếm (tên/mã/SĐT, bỏ dấu) ra kết quả; **0** `pageerror`/`console.error`.

---

## 6. Phương pháp & công cụ

- **Chạy app thật theo TỪNG vai.** Tạo tài khoản mẫu mỗi vai (glv lớp A, trưởng
  khối, bdh…) rồi đi hết luồng. Khung Playwright ở `TESTING.md` mục 4 — nhân rộng
  theo vai bằng cách đăng nhập từng tài khoản.
- **Kiểm DB sau thao tác**, không chỉ nhìn UI: truy vấn xác nhận bất biến (mục 3)
  và trạng thái (mục 4). Ví dụ sau khi giao đơn quà: `SELECT current_balance,
  held_balance FROM student_stamps …` phải khớp.
- **PHPUnit theo-vai:** mở rộng `tests/unit/` (đã có `PermissionTest`, `ScopeTest`,
  `DataScopeTest`, `P1ApiHarness`, `StampEngineTest`, `RewardsOrder*Test`…). Mỗi
  bất biến/chuyển-trạng-thái nên có một test.
- **Đối chiếu acceptance/SPEC** của issue: mỗi tiêu chí hoàn thành = một ca kiểm.
- Lỗi chức năng tìm được mà chưa sửa → ghi lại; **sửa** đi theo quy trình tính
  năng (nhánh + PR riêng), không trộn báo cáo với bản vá.

---

## 7. Định dạng báo cáo

Mỗi finding: **mức** (🔴 chặn dùng / 🟠 sai rõ / 🟡 lệch nhỏ / 🔵 gợi ý), **module
+ vai + ca**, **kỳ vọng vs thực tế**, **bằng chứng** (bước tái hiện / truy vấn DB
/ ảnh), **cách sửa**. Báo cáo toàn-app: `docs/audit/FUNCTIONAL_AUDIT_<YYYY-MM>.md`
(kèm bảng tổng theo mức, như `SECURITY_AUDIT.md`).

---

## 8. Gắn vào quy trình

- `FEATURE_WORKFLOW.md`: Định nghĩa "Xong" đã gồm test theo-vai + smoke; audit
  chức năng là bước xác nhận cuối cho module bị đụng.
- `TESTING.md`: cung cấp khung chạy; audit chức năng quyết định **ca nào** cần phủ.
- Subagent `tntt-tester`/`tntt-reviewer` có thể thực hiện — **chỉ khi người dùng
  yêu cầu dùng subagent**; báo cáo theo mục 7.

---
_Cập nhật khi thêm module/vai mới hoặc đổi quy tắc nghiệp vụ (bất biến, trạng thái)._
