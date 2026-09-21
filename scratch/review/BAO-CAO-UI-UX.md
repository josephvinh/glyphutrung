# BÁO CÁO KIỂM THỬ GIAO DIỆN (UI/UX)
## Web app quản lý đoàn Thiếu Nhi Thánh Thể — "GIA ĐÌNH GIÁO LÝ PHÚ TRUNG"

**Phạm vi:** 28 ảnh chụp màn hình trong `scratch\review\shots\` (14 trang × desktop + mobile)
**Ngày thực hiện:** theo dấu thời gian tệp ảnh

---

## PHẦN 0 — PHƯƠNG PHÁP VÀ GIỚI HẠN (cần đọc trước)

**Tôi không thể trực tiếp "nhìn" ảnh.** Model đang chạy (`deepseek-v4-flash`) không khai báo
đầu vào hình ảnh, nên công cụ `read_image` trả về lỗi:

> `model "deepseek-v4-flash" does not declare image input`

**Nguyên nhân gốc (đây cũng là một phát hiện cấu hình):**
Trong `~/.dsh/settings.yaml`, mục `llm-deepseek.models` khai báo 2 model có khả năng đọc ảnh là
`deepseek-flash` và `deepseek-v4-flash-vision-exp`. Tôi đã truy vấn trực tiếp danh sách model của
gateway (`GET https://api.key4u.vn/v1/models`, 419 model) và **cả hai id này đều KHÔNG tồn tại**
trên gateway. Vì vậy mọi nỗ lực đọc ảnh qua harness đều thất bại. Ngoài ra adapter `llm-deepseek`
coi mọi model id không có trong catalog là **text-only**, nên dù trỏ sang model vision thật của
gateway (ví dụ `gemini-2.5-flash`) thì `read_image` vẫn bị từ chối.

**Cách tôi lấy dữ liệu thay thế:** gọi trực tiếp gateway Key4U (bằng chính API key trong
`~/.dsh/.credentials.yaml`) tới 2 model **có khả năng đọc ảnh thật** đang hoạt động:
`gemini-2.5-flash` và `gemini-3.1-pro-preview`. Mỗi ảnh được **2 model độc lập phân tích**,
sau đó tôi **đối chiếu chéo**; các phát hiện quan trọng được **kiểm chứng lại bằng một pass hỏi
thẳng câu hỏi cụ thể** (cả 2 model). Script: `scratch/review/vision_analyze.mjs`,
`vision_verify.mjs`; dữ liệu thô: `vision-flash.json`, `vision-pro.json`, `verify-flash.json`,
`verify-pro.json`.

**Giới hạn — xin nói rõ để tránh hiểu sai báo cáo:**
1. Toàn bộ mô tả dưới đây là **quan sát gián tiếp qua model vision**, không phải mắt tôi.
   Tôi đã giảm thiểu rủi ro bịa bằng cách chỉ báo cáo khi **cả 2 model cùng xác nhận**, và ghi
   rõ khi chỉ **một model** báo.
2. Ảnh desktop là **1440×900** và ảnh mobile là **780×1688** (= viewport 390×844 ở DPR 2).
   Cả hai **đều là ảnh chụp VIEWPORT, không phải full-page**. Nghĩa là phần nội dung nằm dưới
   màn hình **không xuất hiện trong ảnh**. Do đó một số hiện tượng "bị cắt ở đáy" có thể chỉ là
   **hệ quả bình thường của việc chụp viewport**, không chắc là lỗi.
3. Ảnh tĩnh **không cho biết trang có cuộn được hay không**, nên không thể kết luận chắc chắn
   một số vấn đề (ví dụ nội dung bị thanh điều hướng che) có khắc phục được bằng thao tác cuộn.
4. Không ảnh nào bị trống, trắng hay hỏng — **cả 28 ảnh đều đọc được nội dung**.

---

## 1. Dashboard (Tổng quan) — `dashboard-desktop.png`, `dashboard-mobile.png`

**Desktop**
- **UI:** Mục menu cuối trong sidebar ("Niên khoá", và ở một số ảnh là khối "Cài đặt") nằm sát
  rạt/cắt lẹm mép dưới của khung nền trắng, thiếu khoảng đệm đáy, mất cân đối so với khoảng trống
  phía trên. *Cả 2 model đều báo.* ⚠️ Có thể một phần là hệ thống cuộn của sidebar (xem giới hạn #2).
- **UX (chỉ `gemini-3.1-pro-preview` báo):** Thẻ thống kê có nhãn "2 MỚI" nhưng nội dung chỉ hiển
  thị duy nhất 1 tiêu đề "Họp GLV đầu tháng" — người dùng không biết thông báo thứ hai ở đâu.
- **UX (chỉ pro báo):** Thẻ có nhãn đỏ "HÔM NAY" nhưng tiêu đề lại ghi "Sinh nhật tháng này 18 em"
  → nhập nhằng: 18 em sinh nhật *hôm nay* hay *trong tháng*?

**Mobile**
- **UI — mức CAO (cả 2 model, đã kiểm chứng):** Hàng thẻ thống kê nằm ngang bị **tràn/cắt cụt ở
  mép phải màn hình**. Chữ còn đọc được trên thẻ bị cắt: **"Tổng sĩ số toàn đ…"** kèm "180 em"
  (ký tự sau "đ" bị cắt mất một nửa). Đây là dạng cuộn ngang nhưng không có dấu hiệu thị giác nào
  báo cho người dùng biết còn thẻ bị ẩn.
- **UX (chỉ pro báo):** Các dòng trong "Thông báo gần đây" có dấu chấm đỏ ở **cả hai đầu** dòng
  (trái và phải) → lặp tín hiệu thị giác. (Có thể là chủ ý: trái = phân loại, phải = chưa đọc.)
- **UX (chỉ pro báo):** Thẻ "Sắp tới" ở trạng thái trống ("Không có việc nào sắp tới") nhưng vẫn
  chiếm khung thẻ khá lớn giữa màn hình.

---

## 2. Thiếu Nhi (Danh sách) — `students-desktop.png`, `students-mobile.png`

**Desktop**
- **UI — mức CAO (cả 2 model, đã kiểm chứng):** Trong ô tìm kiếm, **biểu tượng kính lúp bị đè lên
  ký tự đầu tiên của chữ placeholder**. Bằng chứng: một model đọc placeholder thành
  "**Tm** tên, mã số..." (chữ "T" bị kính lúp che), model kia xác nhận kính lúp "nằm chồng ngay
  lên chữ T". Nguyên nhân điển hình: thiếu `padding-left` cho input có icon đặt absolute.
- **UX — mức TRUNG BÌNH (cả 2 model, đã kiểm chứng):** Trong thẻ thiếu nhi (Giuse Ngô Thanh An):
  - Có **hàng chỉ có biểu tượng ghim vị trí nhưng KHÔNG có địa chỉ** đi kèm → hàng trống vô nghĩa.
  - Nhãn **"TÊN CHA" và "TÊN MẸ" để trống hoàn toàn**, không có tên phụ huynh, dù có kèm nút gọi điện.
- **UI (chỉ `gemini-2.5-flash` báo):** Khối "Cài đặt Nguyễn Văn A" ở đáy sidebar đè lên viền dưới sidebar.

**Mobile**
- **UI — mức CAO (cả 2 model):** **Thanh điều hướng đáy che/cắt cụt nội dung thẻ thiếu nhi** —
  phần "TÊN CHA" và nút gọi điện bị cắt ở đáy màn hình.
- **UI (cả 2 model, đã kiểm chứng):** Lặp lại lỗi kính lúp đè lên placeholder "Tìm tên, mã số...".
- **UX (cả 2 model, đã kiểm chứng):** Lặp lại lỗi ghim vị trí không có địa chỉ và TÊN CHA/TÊN MẸ
  không có tên (mobile còn thấy nhãn "TÊN MẸ" bị che khuất).
- **UI (chỉ pro báo):** Tên phân bổ lớp "Ấu Nhi 4" bị ngắt dòng không hợp lý ("Ấu" ở trên, "Nhi 4" rớt xuống).

---

## 3. Điểm Danh — `attendance-desktop.png`, `attendance-mobile.png`

**Desktop**
- **UX — mức TRUNG BÌNH (cả 2 model, đã kiểm chứng):** **Định dạng ngày không nhất quán.**
  Ô nhập ngày hiển thị `09/19/2026` (Tháng/Ngày/Năm), nhưng dòng chữ mô tả ngay bên dưới hiển thị
  `Thứ Bảy, 19/09/2026` (Ngày/Tháng/Năm). Với người dùng Việt Nam, `09/19` dễ bị đọc nhầm thành
  ngày 9 tháng 19. Đây là lỗi điển hình của `<input type="date">` render theo locale trình duyệt (en-US)
  trong khi phần còn lại của app dùng định dạng Việt Nam.
- **UI (chỉ flash báo):** Mục "Niên khoá" ở cuối sidebar bị cắt cụt phần dưới.

**Mobile**
- **UX — mức TRUNG BÌNH (cả 2 model, đã kiểm chứng):** Cùng lỗi định dạng ngày
  (`09/19/2026` trong ô nhập vs `Thứ Bảy, 19/09/2026` ở dòng mô tả).
- Ngoài ra: trạng thái trống ("không có chương trình") hiển thị rõ ràng, có nút gợi ý
  "Xem Chúa Nhật gần nhất" — **hợp lý, không phải lỗi**.

---

## 4. Xin Phép — `leave-desktop.png`, `leave-mobile.png`

**Desktop**
- **UX — mức TRUNG BÌNH (pro báo):** Cùng lỗi định dạng ngày như trang Điểm danh
  (`09/19/2026` vs `19/09/2026`).
- **UI (chỉ flash báo):** Mục "Niên khoá" ở sidebar bị cắt cụt phần dưới.

**Mobile**
- **UX — mức TRUNG BÌNH (cả 2 model, đã kiểm chứng):** Ô "NGÀY XIN PHÉP" hiển thị `09/19/2026`
  (MM/DD) trong khi dòng mô tả dưới hiển thị `Thứ Bảy, 19/09/2026` (DD/MM) — **ngược thứ tự nhau**.
- *(`gemini-2.5-flash` cho trang này: không phát hiện vấn đề nào khác.)*

---

## 5. Thống Kê (Report Hub) — `reporthub-desktop.png`, `reporthub-mobile.png`

**Desktop**
- **UI — mức TRUNG BÌNH (cả 2 model báo ở dạng khác nhau):** Mục "Niên khoá" ở cuối sidebar bị cắt
  lẹm một phần ở mép dưới khung viền trắng, ngay trên nút "Cài đặt" (pro), hoặc bị cắt cụt (flash).
- **UI (pro báo):** Dòng "Tỷ lệ không bị trừ điểm (Có mặt + Đi trễ) — **88%**" bị **cắt cụt một nửa
  ở cạnh dưới màn hình**.
- **UX (pro báo — số liệu được cả 2 model đọc giống nhau):** Tổng các phần trăm trong "Cơ cấu chuyên cần"
  là 77% + 11% + 0% + 13% = **101%** → lỗi làm tròn khi hiển thị, gây thắc mắc về độ chính xác.
  (Dữ liệu gốc vẫn khớp: 829 + 115 + 1 + 135 = 1080 lượt.)
- **UX (pro báo — cả 2 model đọc cùng các con số):** Hai chỉ số dễ gây nhầm lẫn:
  "TỶ LỆ CÓ MẶT **87%**" và "CÓ MẶT 829 (**77%**)" — cùng nói về "có mặt" nhưng hai tỷ lệ khác nhau,
  không có nhãn giải thích khác biệt.
- **UI (chỉ flash báo):** Thanh cuộn dọc hiển thị ở mép phải của sidebar.

**Mobile**
- **UI — mức CAO (cả 2 model):** **Thanh điều hướng đáy đè lên phần nội dung cuộn** — các thẻ số liệu
  nằm dưới mục "CƠ CẤU CHUYÊN CẦN" bị che khuất, chỉ còn thấy lấp ló các chữ số màu đỏ/xám chìm
  dưới nền thanh menu trắng. Pro chẩn đoán nguyên nhân: **thiếu `padding-bottom`** ở vùng chứa nội dung.
- **UI (chỉ flash báo):** Nhãn "ĐIỂM DANH", "Thông báo", "Cá nhân" trên thanh điều hướng bị **cắt
  một phần ở phía dưới**, không hiển thị trọn vẹn.
- **UI (chỉ flash báo):** Số "115" và "11%" trong thẻ "ĐI TRỄ" hơi lệch lên trên so với "829"/"77%"
  trong thẻ "CÓ MẶT" → căn chỉnh không đồng đều.
- **UI (pro báo):** Dòng "Chỉ tính các buổi đã qua giờ chốt" nằm quá sát viền dưới khung chọn
  "THÁNG 9/2026", thiếu khoảng đệm.
- **UX (chỉ flash báo):** Hai biểu tượng ở góc trên bên phải header (làm mới, mũi tên) **không có
  nhãn chữ**, khó hiểu với người dùng mới.

---

## 6. Thư viện & Sổ tay — `thu_vien-desktop.png`, `thu_vien-mobile.png`

**Desktop**
- **UI — mức THẤP (chỉ pro báo):** Tab **"Chờ duyệt" dùng chữ màu vàng trên nền trắng** →
  độ tương phản thấp, khó đọc (vấn đề accessibility).
- **UI (chỉ flash báo):** Mục "Niên khoá" ở cuối sidebar bị cắt cụt phần dưới.

**Mobile**
- **Không phát hiện vấn đề.** *(Cả 2 model độc lập đều trả về `khong_thay_van_de: true`.)*

---

## 7. Khối & Lớp (Org) — `org-desktop.png`, `org-mobile.png`

**Desktop**
- **UI — mức TRUNG BÌNH (chỉ flash báo, 2 mục):** Nút **"QUẢN TRỊ HỆ THỐNG"** và nút
  **"BAN ĐIỀU HÀNH"** (màu đỏ) bị **cắt cụt viền bo góc bên phải** và nằm quá sát mép phải của card chứa nó.
- **UI — mức THẤP (chỉ flash báo, 5 mục):** Nhiều phần tử nằm quá sát mép card:
  dòng "Không sửa được vai trò" + icon ổ khóa (sát mép trên và phải); icon sửa/xóa trong card
  "Khai Tâm" và "Ấu Nhi"; mũi tên dropdown trong ô "TRƯỜNG KHỐI" của cả 2 khối.
- **UI (cả 2 model báo):** Mục "Niên khoá" ở cuối khối menu trắng nằm sát rạt mép cắt/thiếu padding đáy.
- **UX (pro báo — số liệu cả 2 model đọc giống nhau):** Hai khối "Khai Tâm" và "Ấu Nhi" hiển thị
  **số liệu giống hệt nhau "4 lớp • 120 em"** → nghi ngờ là **dữ liệu mẫu (placeholder) chưa cập nhật**.
- **UX (chỉ flash báo):** Hai nút hành động trong cùng một danh sách dùng màu khác nhau (đen cho
  "QUẢN TRỊ HỆ THỐNG", đỏ cho "BAN ĐIỀU HÀNH") mà không rõ quy tắc → khó hiểu về mức độ ưu tiên.

**Mobile**
- **UI — mức TRUNG BÌNH (cả 2 model, đã kiểm chứng):** Ô chọn **"TRƯỞNG KHỐI"** trong thẻ "Khai Tâm"
  bị **cắt cụt chữ ở mép phải ô**: đọc được `Anna Phạm Thị Chủ Nhiệm — K` (phần sau chữ "K" bị mất).
- **UI (cả 2 model báo):** Nội dung thẻ "Ấu Nhi" bị cắt ngang ở cạnh dưới — do thanh điều hướng đáy
  đè lên, hoặc thiếu khoảng đệm cuối trang. *(Pro nêu rõ: không phân biệt được là do chưa cuộn hết
  hay do lỗi thiếu padding.)*
- **UI — mức THẤP (chỉ pro báo):** Tên "Maria Trần Thị Điều Hành" dài nên chữ "Hành" rớt xuống dòng lẻ loi.

---

## 8. Lịch của tôi (Notes) — `notes-desktop.png`, `notes-mobile.png`

**Desktop**
- **UX — mức THẤP (chỉ pro báo):** **Trùng lặp hành động** — hai nút cùng chức năng hiển thị đồng
  thời trên một màn hình trống: "Thêm việc" (góc phải tiêu đề) và "Thêm việc đầu tiên" (giữa khung).
- **UI — mức TRUNG BÌNH (chỉ pro báo):** Chữ trong nút "Thêm việc" nằm quá sát lề phải của nút,
  thiếu padding so với lề trái → mất cân đối.
- **UI (cả 2 model báo):** Mục "Niên khoá" và khối "Cài đặt Nguyễn Văn A" ở đáy sidebar bị cắt
  cụt/thiếu padding đáy.

**Mobile**
- **UI — mức CAO (cả 2 model, đã kiểm chứng):** **Nút "Thêm việc" màu đỏ đè lên dòng chữ phụ đề**
  "Việc riêng bạn tự ghi + các buổi họp **được** mời" (pro xác định chính xác từ bị đè là "được").
  Đồng thời **cạnh phải của nút bị cắt sát lề màn hình**, mất mép bo tròn bên phải.

---

## 9. Hướng dẫn sử dụng (Guide) — `guide-desktop.png`, `guide-mobile.png`

**Desktop**
- **UI (chỉ flash báo):** Mục "Niên khoá" và phần tử "Cài đặt" ở cuối sidebar bị cắt cụt phần dưới.
- *(`gemini-3.1-pro-preview` cho trang này: không phát hiện vấn đề.)*

**Mobile**
- **UI — mức CAO (cả 2 model, đã kiểm chứng):** **Chữ của nội dung trang vẫn nhìn thấy được tại vùng
  thanh điều hướng đáy** — đọc được dòng `"Phần Thông báo hiển thị các tin tức mới"` nằm mờ ngay
  dưới các nhãn của thanh điều hướng. Pro kết luận thanh điều hướng **bán trong suốt**; flash mô tả
  nền "trắng đục" nhưng cũng xác nhận có chữ lộ ra. **Hai model bất đồng về việc thanh này trong suốt
  hay không**, nhưng **cùng xác nhận hiện tượng chữ nội dung nằm trong vùng thanh điều hướng**.
  Pro chẩn đoán: thiếu `padding-bottom` ở vùng chứa nội dung chính.

---

## 10. Lên Lớp (Promotion) — `promotion-desktop.png`, `promotion-mobile.png`

**Desktop**
- **UI — mức TRUNG BÌNH (cả 2 model báo):** Có một **biểu tượng hình phễu lọc nhỏ nằm trôi nổi bất
  thường ở mép trái bên trong vùng nội dung** (ngay dưới dòng "Chưa chọn khối", sát cạnh trái của
  khung viền đứt nét), **không đi kèm văn bản hay nút có viền** → không rõ mục đích.
- **UI — mức THẤP (cả 2 model báo):** Mục "Niên khoá" ở sidebar bị cắt sát viền dưới, thiếu padding.

**Mobile**
- **UI — mức TRUNG BÌNH (cả 2 model, đã kiểm chứng):** **Biểu tượng hình phễu bị rớt xuống dòng riêng,
  lệch hẳn sang sát lề trái**, phá vỡ bố cục căn giữa của đoạn văn hướng dẫn. Pro mô tả: icon nằm kẹp
  giữa dòng "...bấm nút lọc" và dòng "phía trên...", lệch khỏi lề căn giữa của cả đoạn. Đây là lỗi
  **icon inline trong văn bản bị wrap sai dòng**.

---

## 11. Chương Trình (Programs) — `programs-desktop.png`, `programs-mobile.png`

**Desktop**
- **UI — mức TRUNG BÌNH (cả 2 model báo):** Mục "Niên khoá" ở cuối khối "BAN ĐIỀU HÀNH" **bị cắt ngang
  nửa dưới** (cắt cả icon và chữ), không hiển thị trọn vẹn do tràn/bị che bởi viền dưới khung trắng.

**Mobile**
- **UI — mức CAO (cả 2 model báo):** **Thanh điều hướng đáy che mất phần chữ mô tả của thẻ chương
  trình thứ hai ("Học Giáo Lý Sáng")** — pro xác định đoạn bị cắt là
  "...Buổi này có được cộng vào điểm chuyên cần cuối năm không", phần "cuối năm không" bị cắt mất một nửa.

---

## 12. Thông Báo (Announcements) — `announcements-desktop.png`, `announcements-mobile.png`

**Desktop**
- **UI (chỉ flash báo):** Mục "Niên khoá" ở cuối sidebar bị cắt cụt phần dưới.
- *(`gemini-3.1-pro-preview` cho trang này: không phát hiện vấn đề.)*

**Mobile**
- **UI — mức TRUNG BÌNH (cả 2 model báo):** **Thanh điều hướng đáy che/cắt cụt phần đáy của thẻ
  thông báo thứ hai** (thẻ bị cắt cụt bởi thanh menu).
- **UI — mức THẤP (chỉ pro báo):** Nhãn **"BUỔI HỌP"** chỉ hiển thị màu chữ mà **không có khối nền
  bo góc** như các nhãn lân cận ("THƯỜNG", "TOÀN ĐOÀN", "ĐÃ PHÁT") → thiếu nhất quán thị giác.
  (Pro tự ghi chú: có thể là chủ ý thiết kế.)

---

## 13. Nhân sự (Staff) — `staff-desktop.png`, `staff-mobile.png`

**Desktop**
- **UI — mức TRUNG BÌNH (cả 2 model báo):** **Dải nút lọc (chip) bị tràn/cắt cụt ở cạnh phải** —
  nút "Giáo Lý Viên" mất viền phải, và còn một phần tử khác chỉ lộ ra một góc nhỏ ngay bên cạnh.
- **UX (chỉ pro báo):** Dải lọc tràn ngang nhưng **không có dấu hiệu thị giác nào** (mũi tên điều
  hướng, bóng mờ ở mép) cho biết có thể cuộn ngang để xem các bộ lọc bị ẩn.
- **UI (cả 2 model báo):** Mục "Niên khoá" bị cắt lẹm phần dưới, nằm sát mép dưới vùng menu cuộn.

**Mobile**
- **UI — mức CAO (cả 2 model, đã kiểm chứng):** **Thanh điều hướng đáy KHÔNG đục hoàn toàn — chữ của
  nội dung trang lộ xuyên qua.** Cả 2 model độc lập đọc được chính xác chữ **"GLV CHỦ NHIỆM"** nằm mờ
  ngay dưới cụm nhãn "Thiếu Nhi" / "Điểm danh" của thanh điều hướng. Pro mô tả đây là hiệu ứng
  **trong suốt mờ** (nghi vấn `z-index` / nền bán trong suốt của thanh điều hướng). Đồng thời thanh
  này cũng **che mất một phần nội dung** thẻ nhân sự ở cuối danh sách.
- **UI (cả 2 model báo):** Chip lọc ngoài cùng bên phải (sau "Ban Điều Hành") bị **cắt cụt nửa chừng
  ở mép phải màn hình**.

---

## 14. Niên Khoá (Years) — `years-desktop.png`, `years-mobile.png`

**Desktop**
- **UI — mức TRUNG BÌNH (cả 2 model báo ở dạng khác nhau):** Mục "Niên khoá" ở sidebar:
  flash báo bị **che khuất/cắt cụt nửa dưới**; pro báo **khối nền hồng đánh dấu trạng thái đang chọn
  bị tràn và cắt phẳng ở mép phải**, không bo góc đồng bộ với sidebar.
- **UI — mức TRUNG BÌNH (cả 2 model báo):** Nút **"Đang dùng" có chữ màu trắng trên nền hồng quá nhạt**
  → **độ tương phản kém, rất khó đọc**. Pro ghi chú: có thể đây là trạng thái disabled, nhưng xét về
  khả năng tiếp cận (accessibility) thì vẫn là vi phạm.

**Mobile**
- **UI — mức TRUNG BÌNH (cả 2 model báo):** Cùng lỗi tương phản — **chữ trắng và icon dấu tick trên
  nền hồng/cam rất nhạt** ở nút "Đang dùng" (góc dưới bên phải thẻ niên khoá 2026–2027), khó đọc và
  khó nhận diện.

---

# TỔNG KẾT

## Mức CAO — nên sửa trước

| # | Vấn đề | Trang / ảnh | Bằng chứng |
|---|--------|-------------|------------|
| 1 | **Thanh điều hướng đáy trên mobile không đục hoàn toàn — chữ nội dung lộ xuyên qua** ("GLV CHỦ NHIỆM" ở Nhân sự, "Phần Thông báo hiển thị các tin tức mới" ở Hướng dẫn) | `staff-mobile`, `guide-mobile` | Cả 2 model đọc được **cùng một chuỗi chữ** lộ qua thanh nav → rất đáng tin |
| 2 | **Nội dung bị thanh điều hướng đáy che/cắt cụt** — nguyên nhân nhiều khả năng là thiếu `padding-bottom` cho vùng nội dung | `students-mobile`, `programs-mobile`, `reporthub-mobile`, `announcements-mobile`, `org-mobile` | Cả 2 model báo trên 5 trang |
| 3 | **Nút "Thêm việc" (đỏ) đè lên dòng phụ đề và bị cắt ở mép phải màn hình** | `notes-mobile` | Cả 2 model xác nhận; pro xác định từ bị đè là "được" |
| 4 | **Thẻ thống kê bị cắt cụt ở mép phải màn hình** ("Tổng sĩ số toàn đ…") | `dashboard-mobile` | Cả 2 model xác nhận, đọc được cùng chuỗi bị cắt |
| 5 | **Icon kính lúp đè lên chữ placeholder trong ô tìm kiếm** (thiếu `padding-left`) | `students-desktop`, `students-mobile` | Cả 2 model xác nhận; một model đọc placeholder thành "Tm tên, mã số..." |

## Mức TRUNG BÌNH

| # | Vấn đề | Trang / ảnh |
|---|--------|-------------|
| 6 | **Định dạng ngày không nhất quán** — ô nhập `09/19/2026` (MM/DD) vs dòng mô tả `19/09/2026` (DD/MM) | `attendance-desktop`, `attendance-mobile`, `leave-desktop`, `leave-mobile` |
| 7 | **Dải chip lọc tràn/cắt cụt ở mép phải**, không có dấu hiệu cho biết cuộn được | `staff-desktop`, `staff-mobile` |
| 8 | **Mục cuối sidebar desktop ("Niên khoá") bị cắt lẹm/sát rạt mép dưới khung trắng**, thiếu padding đáy — xuất hiện lặp lại trên **hầu hết các trang desktop** | dashboard, students, attendance, leave, reporthub, thu_vien, org, notes, guide, promotion, programs, announcements, staff, years |
| 9 | **Icon phễu lọc bị rớt dòng, lệch lề trái, phá bố cục đoạn văn hướng dẫn** | `promotion-mobile` (và icon trôi nổi vô nghĩa ở `promotion-desktop`) |
| 10 | **Ô chọn "TRƯỞNG KHỐI" cắt cụt chữ** (`Anna Phạm Thị Chủ Nhiệm — K`) | `org-mobile` |
| 11 | **Dữ liệu để trống vô nghĩa:** hàng chỉ có icon ghim vị trí mà không có địa chỉ; nhãn "TÊN CHA"/"TÊN MẸ" không có tên | `students-desktop`, `students-mobile` |
| 12 | **Nút "Đang dùng" chữ trắng trên nền hồng nhạt — tương phản kém** | `years-desktop`, `years-mobile` |

## Mức THẤP

- Tab **"Chờ duyệt" chữ vàng trên nền trắng**, tương phản thấp — `thu_vien-desktop`.
- Nhãn **"BUỔI HỌP" thiếu khối nền bo góc** so với các nhãn cùng hàng — `announcements-mobile`.
- **Tổng phần trăm "Cơ cấu chuyên cần" = 101%** (77+11+0+13) do làm tròn — `reporthub-desktop`.
- Hai chỉ số dễ nhầm: **"TỶ LỆ CÓ MẶT 87%"** vs **"CÓ MẶT 829 (77%)"** không có nhãn phân biệt — `reporthub-desktop`.
- **Trùng lặp hành động:** nút "Thêm việc" và "Thêm việc đầu tiên" cùng chức năng trên một màn hình — `notes-desktop`.
- **Số liệu lặp y hệt "4 lớp • 120 em"** cho cả 2 khối (nghi dữ liệu mẫu) — `org-desktop`.
- Nút **"QUẢN TRỊ HỆ THỐNG" / "BAN ĐIỀU HÀNH" bị cắt viền bo góc bên phải**; nhiều icon sửa/xóa và mũi tên dropdown nằm quá sát mép card — `org-desktop`.
- **Nhãn thanh điều hướng bị cắt phần dưới** ("ĐIỂM DANH", "Thông báo", "Cá nhân") — `reporthub-mobile`.
- **Căn chỉnh số liệu không đồng đều** giữa thẻ "CÓ MẶT" và "ĐI TRỄ" — `reporthub-mobile`.
- Thẻ **"Sắp tới" trống nhưng chiếm diện tích lớn** — `dashboard-mobile`.
- **Dấu chấm đỏ lặp ở cả hai đầu** mỗi dòng thông báo — `dashboard-mobile`.
- **Nhập nhằng ngữ nghĩa** nhãn "HÔM NAY" vs tiêu đề "Sinh nhật tháng này" — `dashboard-desktop`.
- Thẻ **"2 MỚI" nhưng chỉ hiển thị 1 tiêu đề thông báo** — `dashboard-desktop`.
- **Icon không có nhãn chữ** ở header (làm mới, đăng xuất) và cạnh tiêu đề (quay lại) — xuất hiện trên **hầu hết các trang**.
- **Ngắt dòng không hợp lý** tên lớp "Ấu Nhi 4" — `students-mobile`; tên người "Maria Trần Thị Điều Hành" — `org-mobile`.
- **Thanh cuộn hiện ở mép phải sidebar** — `reporthub-desktop`.

---

## NHẬN XÉT CHUNG

**Điểm mạnh quan sát được:** Giao diện desktop nhìn chung **gọn gàng, bố cục nhất quán, chữ sắc nét,
không phát hiện phần tử vỡ/hỏng hay màu sắc bất thường nghiêm trọng**. Hệ thống trạng thái trống
(empty state) được xử lý tốt, có thông báo rõ ràng và nút gợi ý hành động. Tông màu đỏ–trắng nhất
quán trên toàn app.

**Hai nhóm lỗi mang tính HỆ THỐNG, lặp lại trên nhiều trang (nên ưu tiên sửa gốc thay vì sửa lẻ):**

1. **Vấn đề thanh điều hướng đáy trên mobile** — vừa **che mất nội dung** (thiếu `padding-bottom`
   ở vùng nội dung chính), vừa có hiện tượng **nền không đục hoàn toàn** khiến chữ nội dung lộ xuyên qua.
   Ảnh hưởng ít nhất 6 trang mobile. **Đây là vấn đề nghiêm trọng nhất của bộ ảnh.**
2. **Mục menu cuối trong sidebar desktop** luôn bị cắt lẹm/sát rạt mép dưới khung trắng do thiếu
   khoảng đệm đáy — lặp lại trên **gần như toàn bộ 14 trang desktop**.

**Vấn đề dữ liệu cần xác minh với backend (không phải lỗi CSS):**
- `students`: ghim vị trí không có địa chỉ; "TÊN CHA"/"TÊN MẸ" để trống.
- `org`: hai khối khác nhau hiển thị số liệu giống hệt "4 lớp • 120 em".
- `reporthub`: tổng phần trăm = 101%; hai chỉ số "có mặt" với hai tỷ lệ khác nhau (87% vs 77%).

**Lưu ý cuối cùng về độ tin cậy:** các phát hiện ở **mức CAO** và các mục **#6, #7, #9, #10** đã được
**kiểm chứng lại bằng câu hỏi tập trung trên cả 2 model** và đều cho kết quả nhất quán — độ tin cậy cao.
Các mục **chỉ một model báo** (ghi rõ trong từng trang ở trên) cần được kiểm chứng lại bằng mắt người
hoặc bằng công cụ khác trước khi đưa vào backlog chính thức.
