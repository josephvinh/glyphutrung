# Độ phủ test theo kịch bản TC-01…TC-119

> Đối chiếu mỗi kịch bản trong `docs/TEST_SCENARIOS.md` (theo issue #208–#217) với test thật trong `tests/`.
> Ngày lập: 2026-10-13, trên master `af7ee54`. Lập bằng cách đọc tên test và nhãn ca (`rec(...)`), **chưa chạy lại** từng test.
> Ký hiệu: ✅ có test tự động · 🟡 phủ một phần (nêu rõ phần thiếu) · ⬜ chưa có test.
> Tên file PHPUnit ở `tests/unit/`; file `.py`/`.js` ở `tests/e2e/` (chạy trên DB test).

## Cách dùng

- Khi thêm test cho một TC, đổi trạng thái ở đây trong **cùng PR**.
- TC ⬜ ở khu vực dữ liệu/phân quyền ưu tiên trước; TC thuần giao diện (phím tắt, huy hiệu…) để sau.
- Một số mô tả TC trong issue đã lệch thực tế (ghi chú ở cột bằng chứng): `toggle` → `mark`/`unmark`, mặc định +30 phút đã bỏ, định dạng mã thiếu nhi.

## Tổng hợp

| Issue | Nhóm | Phạm vi | ✅ | 🟡 | ⬜ |
|---|---|---|---|---|---|
| #208 | Xác thực & phân quyền | TC-01…10 | 9 | 1 | 0 |
| #209 | Quản lý thiếu nhi | TC-11…36 | 7 | 5 | 14 |
| #210 | Điểm danh | TC-37…50 | 6 | 2 | 6 |
| #211 | Điểm số | TC-51…57 | 3 | 2 | 2 |
| #212 | Chương trình | TC-58…65 | 1 | 3 | 4 |
| #213 | Khối & lớp | TC-66…76 | 3 | 3 | 5 |
| #214 | Nhân sự | TC-77…88 | 7 | 1 | 4 |
| #215 | Thông báo | TC-89…101 | 4 | 1 | 8 |
| #216 | Sổ Mộc & đổi quà | TC-102…109 | 6 | 1 | 1 |
| #217 | Báo cáo & trang công khai | TC-110…119 | 5 | 3 | 2 |
| | **Tổng** | 119 | **51** | **22** | **46** |

## #208 — Xác thực & phân quyền

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-01 | Đăng nhập đúng | ✅ | e2e.py AUTH-03 |
| TC-02 | Sai mật khẩu | ✅ | e2e.py AUTH-01 |
| TC-03 | Khóa sau nhiều lần sai | ✅ | e2e.py AUTH-02 (429) |
| TC-04 | Tài khoản bị vô hiệu không đăng nhập được | 🟡 | extra.py STAFF-08 chỉ phủ 'chờ duyệt'; chưa phủ 'đã nghỉ' |
| TC-05 | Buộc đổi mật khẩu lần đầu | ✅ | MustChangePwTest; e2e.py AUTH-04/05 |
| TC-06 | GLV chỉ thấy lớp mình | ✅ | DataScopeTest; e2e2.py IDOR-01 |
| TC-07 | Trưởng khối thấy cả khối | ✅ | DataScopeTest::truong_khoi_sees_only_classes_of_own_block |
| TC-08 | BĐH thấy toàn đoàn | ✅ | DataScopeTest::bdh_is_unrestricted |
| TC-09 | API không đủ quyền bị từ chối | ✅ | e2e.py SEC-01, PERM-*; PermissionHardeningTest |
| TC-10 | POST thiếu CSRF bị từ chối | ✅ | CSRFTest; e2e.py CSRF-01/02/03 |

## #209 — Quản lý thiếu nhi

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-11 | Danh sách, phân trang 20 | ⬜ | Chưa có |
| TC-12 | Tìm theo tên (không dấu) | ⬜ | Chưa có test tìm kiếm (từng vỡ ở #194/#203 — nên ưu tiên) |
| TC-13 | Lọc theo lớp | ⬜ | Chưa có |
| TC-14 | Lọc theo giới tính | ⬜ | Chưa có |
| TC-15 | Lọc theo tuổi | ⬜ | Chưa có |
| TC-16 | Lọc theo trạng thái | ⬜ | Chưa có |
| TC-17 | Lọc theo địa chỉ | ⬜ | Chưa có |
| TC-18 | Thêm thiếu nhi đủ trường | ✅ | flows.py STU-01 |
| TC-19 | Thêm thiếu cột bắt buộc | ✅ | flows.py STU-03/04/05; p3_regress.py |
| TC-20 | Tự sinh mã thiếu nhi | 🟡 | flows.py STU-01 kiểm mã `GDGLPT26xxxx`; issue ghi `TN+năm+STT` — mô tả TC lệch thực tế |
| TC-21 | Sửa thông tin | ✅ | flows.py STU-02 |
| TC-22 | Đổi lớp (tự cập nhật khối) | 🟡 | STU-06 phủ chuyển lớp hàng loạt; đổi lớp ở form sửa chưa có |
| TC-23 | Cảnh báo đóng form chưa lưu | ⬜ | Chưa có (client) |
| TC-24 | Tự lưu nháp localStorage | ⬜ | Chưa có (client) |
| TC-25 | Xóa một em | 🟡 | e2e2.py IDOR-05 phủ xóa ngoài phạm vi; xóa hợp lệ chưa có |
| TC-26 | Xóa hàng loạt | 🟡 | Như TC-25 |
| TC-27 | Chuyển lớp hàng loạt | ✅ | flows.py STU-06; e2e2.py IDOR-06 |
| TC-28 | Xuất Excel danh sách | ✅ | excel.js |
| TC-29 | Tải mẫu Excel khi chưa có em | ✅ | excel.js |
| TC-30 | Nhập từ Excel | ✅ | extra.py IMP-01…06; excel.js; p3_regress.py |
| TC-31 | Xuất PDF danh sách | ⬜ | Chưa có |
| TC-32 | Xuất PDF thẻ | 🟡 | e2e.py IDOR-03 chỉ kiểm phạm vi in; nội dung PDF chưa có |
| TC-33 | Phím tắt j/k | ⬜ | Chưa có |
| TC-34 | Phím tắt e | ⬜ | Chưa có |
| TC-35 | Phím tắt n | ⬜ | Chưa có |
| TC-36 | Sao chép SĐT | ⬜ | Chưa có |

## #210 — Điểm danh

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-37 | Mở buổi hôm nay | 🟡 | API: flows.py ATT-01; giao diện chưa có |
| TC-38 | Chỉ hiện chương trình đã bắt đầu | ⬜ | Chưa có |
| TC-39 | Sửa buổi đã qua | 🟡 | flows.py ATT-01 (CN đã qua); OverrideLockTest |
| TC-40 | Đánh dấu có mặt | ✅ | flows.py ATT-01; QrScanApiTest; attendance_regress.js |
| TC-41 | Gỡ điểm danh | ✅ | flows.py ATT-02; attendance_regress.js OFF-01. Mô tả TC còn ghi `toggle`, nay là `mark`/`unmark` |
| TC-42 | Tự ghi 'đi trễ' sau giờ chốt | ✅ | CutoffTest; AttendanceStatusFixTest; QrScanApiTest::status_is_server_computed. Mô tả TC ghi mặc định +30 phút — đã bỏ ở #207 |
| TC-43 | Chống chạm đôi 450 ms | ⬜ | Chưa có (client) |
| TC-44 | Hiện 'vắng có phép' | ⬜ | Chưa có (stats.js) |
| TC-45 | Hiện 'vắng không phép' | ⬜ | Chưa có (stats.js; xem #245 mục 3) |
| TC-46 | Hiện 'chưa điểm danh' | ⬜ | Chưa có |
| TC-47 | Thống kê buổi | ⬜ | Chưa có |
| TC-48 | Quét thẻ QR | ✅ | QrScanApiTest; QrScanTest; attendance_regress.js QR-01…04, SCOPE-* |
| TC-49 | Hàng đợi offline | ✅ | attendance_regress.js OFF-01…04, SYNC-01/02, SW-01 |
| TC-50 | Xuất Excel điểm danh | ✅ | ExportApiTest; excel.js |

## #211 — Điểm số

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-51 | Nhập điểm miệng | ✅ | flows.py SCORE-01 |
| TC-52 | Điểm ngoài 0–10 | ✅ | flows.py SCORE-01 (giá trị xấu) |
| TC-53 | Xóa điểm bằng ô trống | ⬜ | Chưa xác nhận SCORE-01 có ca ô trống |
| TC-54 | Điểm trung bình theo trọng số | 🟡 | TracuuTest, ThiDuaTest (tính ở máy chủ); phía bảng điểm client chưa có |
| TC-55 | Trung bình khi thiếu cột | 🟡 | TracuuTest::weighted_avg_skips_missing_columns |
| TC-56 | Xếp loại học lực | ⬜ | Chưa có |
| TC-57 | Xuất Excel bảng điểm | ✅ | ExportApiTest; excel.js |

## #212 — Chương trình

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-58 | Danh sách chương trình | ⬜ | Chưa có |
| TC-59 | Chương trình nhiều ngày | ⬜ | Chưa có |
| TC-60 | Chương trình chiến dịch | ⬜ | Chưa có |
| TC-61 | Khoảng hiệu lực | 🟡 | StampEngineTest::attendance_before_effective_from_not_counted |
| TC-62 | Tạo chương trình lặp | ✅ | extra.py PRG-01, PRG-04 |
| TC-63 | Tạo chiến dịch | 🟡 | extra.py PRG-02 phủ ca bị từ chối; ca hợp lệ chưa có |
| TC-64 | Bật/tắt chương trình | ⬜ | Chưa có |
| TC-65 | Gán lớp cho chương trình | 🟡 | QrScanApiTest::respects_program_classes, skips_class_outside_program (qua điểm danh) |

## #213 — Khối & lớp

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-66 | Tạo khối | ⬜ | Chưa có |
| TC-67 | Đổi tên khối (cascade) | ⬜ | Chưa có |
| TC-68 | Xóa khối còn lớp bị chặn | ✅ | flows.py ORG-01; orgflow.py |
| TC-69 | Gán Trưởng khối | 🟡 | extra.py ASG-01, ASG-04 |
| TC-70 | Tạo lớp | ⬜ | Chưa có |
| TC-71 | Đổi tên lớp (cascade) | ⬜ | Chưa có |
| TC-72 | Xóa lớp còn em bị chặn | ✅ | flows.py ORG-02; p2_regress.py (#80) |
| TC-73 | Gán GLV chủ nhiệm | 🟡 | extra.py ASG-01; AssignmentTest |
| TC-74 | Thêm GLV phụ tá | 🟡 | AssignmentTest |
| TC-75 | Gỡ phân công | ✅ | extra.py ASG-07…10 |
| TC-76 | Hiện sĩ số lớp | ⬜ | Chưa có |

## #214 — Nhân sự

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-77 | Danh sách theo cấp vai | ⬜ | Chưa có |
| TC-78 | Lọc theo vai | ⬜ | Chưa có |
| TC-79 | Tìm nhân sự | ⬜ | Chưa có |
| TC-80 | Duyệt đăng ký | ✅ | extra.py STAFF-09/10 |
| TC-81 | Từ chối đăng ký | ✅ | extra.py STAFF-13 |
| TC-82 | Sửa thông tin nhân sự | ✅ | extra.py STAFF-01…04 |
| TC-83 | Đổi vai | 🟡 | extra.py STAFF-05/06; PermissionHardeningTest |
| TC-84 | Không đổi vai khi còn phân công | ✅ | PermissionHardeningTest::role_change_on_kiem_nhiem_member_is_blocked |
| TC-85 | Không sửa admin/BĐH | ✅ | StaffGuardTest |
| TC-86 | Đặt lại mật khẩu | ✅ | flows.py ORG-05; StaffGuardTest::06e |
| TC-87 | Bật/tắt vai thủ thư | ⬜ | Chưa có |
| TC-88 | Xóa nhân sự | ✅ | extra.py STAFF-15/16; StaffGuardTest::admin_delete_rules |

## #215 — Thông báo

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-89 | Danh sách thông báo đã phát | ⬜ | Chưa có |
| TC-90 | Huy hiệu chưa đọc | ⬜ | Chưa có (notification center #260 mới thêm) |
| TC-91 | Đánh dấu đã đọc | ✅ | flows.py ANN-03 |
| TC-92 | Đọc tất cả | ⬜ | Chưa có |
| TC-93 | Tạo thông báo toàn đoàn | ✅ | flows.py ANN-01 |
| TC-94 | Tạo thông báo theo khối | ⬜ | Chưa có |
| TC-95 | Tạo thông báo theo lớp | ⬜ | Chưa có |
| TC-96 | Lịch họp + RSVP | ✅ | extra.py (mục ANNOUNCEMENTS meeting + RSVP) |
| TC-97 | Mức khẩn | ⬜ | Chưa có |
| TC-98 | Sửa thông báo | ⬜ | Chưa có |
| TC-99 | Thu hồi về nháp | ⬜ | Chưa có |
| TC-100 | Xóa thông báo | ✅ | flows.py ANN-04/05 |
| TC-101 | Trưởng khối chỉ sửa thông báo khối mình | 🟡 | flows.py ANN-02 phủ GLV; Trưởng khối chưa có |

## #216 — Sổ Mộc & đổi quà

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-102 | Xem số dư Mộc | ✅ | StampProfileTest; SomocTest |
| TC-103 | Quét thẻ xem số dư | 🟡 | RewardsScopeTest (phạm vi); luồng quét ở quầy chưa có |
| TC-104 | Thêm quà vào giỏ | ⬜ | Chưa có (client) |
| TC-105 | Xác nhận đổi quà | ✅ | RewardsRedeemTest; extra.py (REWARDS) |
| TC-106 | Không đủ Mộc | ✅ | RewardsRedeemTest::rejects_when_insufficient_balance |
| TC-107 | Hết hàng | ✅ | RewardsRedeemTest::rejects_when_out_of_stock |
| TC-108 | Xác nhận đặt trước bằng mật khẩu | ✅ | RewardsOrderTest::confirm_* |
| TC-109 | Thủ thư vượt mật khẩu | ✅ | RewardsOrderTest::confirm_override_skips_password |

## #217 — Báo cáo & trang công khai

| TC | Nội dung | Trạng thái | Bằng chứng / phần thiếu |
|---|---|---|---|
| TC-110 | Báo cáo điểm danh tháng | 🟡 | ExportApiTest (xuất); màn báo cáo chưa có |
| TC-111 | Bảng xếp hạng bxh.php | ✅ | e2e public.py PUB-21/22; ThiDuaTest |
| TC-112 | Thống kê dashboard | ⬜ | Chưa có |
| TC-113 | Tra cứu tracuu.php | ✅ | TracuuTest; public.py PUB-10…17 |
| TC-114 | Sổ Mộc somoc.php | ✅ | SomocTest; public.py PUB-18…20 |
| TC-115 | Đặt quà online | ✅ | RewardsOrderTest::place_*; RewardsOrderApiTest |
| TC-116 | Xem/sửa hồ sơ cá nhân | ⬜ | Chưa có |
| TC-117 | Đổi mật khẩu | 🟡 | PasswordTest (băm); MustChangePwTest::password_change_is_the_only_open_door |
| TC-118 | Buộc đổi mật khẩu | ✅ | MustChangePwTest |
| TC-119 | Đổi niên khóa | 🟡 | flows.py YEAR-01…04 (tạo/kích hoạt); chuyển niên khóa trên UI chưa có |

