<?php
/**
 * NỘI DUNG HƯỚNG DẪN SỬ DỤNG — nguồn DUY NHẤT.
 * Dùng cho cả module "Hướng dẫn" (views/module_guide.php) lẫn bản xuất .doc
 * (scripts/xuat_huong_dan_doc.php). Sửa nội dung ở ĐÂY là cả hai đổi theo.
 */
return [
    'tieu_de' => 'Hướng dẫn sử dụng TNTT Super App',
    'gioi_thieu' => 'Tài liệu hướng dẫn thao tác cơ bản trên ứng dụng quản lý '
        . 'Đoàn Thiếu Nhi Thánh Thể. Mỗi vai chỉ thấy các chức năng thuộc quyền của '
        . 'mình; phần "Dùng chung" áp dụng cho tất cả.',

    // Phần chung cho mọi vai
    'chung' => [
        [
            'title' => 'Đăng nhập & mật khẩu',
            'steps' => [
                'Mở app, nhập Số điện thoại và Mật khẩu đã được cấp rồi bấm Đăng nhập.',
                'Lần đầu nên đổi mật khẩu: vào Cài đặt → Đổi mật khẩu.',
                'Quên mật khẩu thì liên hệ Ban Điều Hành để cấp lại.',
            ],
        ],
        [
            'title' => 'Màn hình Trang chủ',
            'steps' => [
                'Ba thẻ trên cùng: Thông báo mới, Sĩ số phạm vi của bạn, Sinh nhật trong tháng.',
                'Thẻ "Sắp tới": các việc và buổi họp gần nhất của bạn.',
                'Thẻ "Thông báo gần đây": các thông báo mới từ Ban Điều Hành.',
                'Chấm đỏ nhỏ trên icon là việc cần làm (VD: số buổi chưa điểm danh, thông báo chưa đọc).',
            ],
        ],
        [
            'title' => 'Lịch của tôi (ghi chú & nhắc việc)',
            'steps' => [
                'Bấm "Lịch của tôi" → "Thêm việc" để ghi việc cần nhớ kèm ngày giờ nhắc.',
                'Đến gần giờ, app nhắc bằng chấm đỏ và thông báo đẩy (nếu đã bật chuông).',
                'Buổi họp bạn được mời sẽ TỰ hiện trong lịch; bấm "Tham gia" hoặc "Không".',
                'Bấm "Xong" để đánh dấu việc đã hoàn thành.',
            ],
        ],
        [
            'title' => 'Thông báo',
            'steps' => [
                'Vào "Thông báo" để đọc tin từ Ban Điều Hành / Trưởng khối.',
                'Với buổi họp: xem giờ, địa điểm và chọn Tham gia / Không tham gia.',
            ],
        ],
        [
            'title' => 'Tiện ích khác',
            'steps' => [
                'Nút mặt trời trên đầu: đổi giao diện Sáng / Tối.',
                'Nút mũi tên: Đăng xuất. Nút vòng tròn: làm mới dữ liệu.',
                'Nên "Thêm vào màn hình chính" để dùng như một ứng dụng (PWA).',
            ],
        ],
    ],

    // Phần riêng theo vai — thứ tự hiển thị
    'vai' => [
        'admin' => [
            'label' => 'Quản Trị Hệ Thống',
            'mo_ta' => 'Toàn quyền trên toàn đoàn, kể cả cấu hình hệ thống.',
            'items' => [
                ['title' => 'Phân quyền', 'steps' => [
                    'Cài đặt → Phân quyền: chọn vai, đặt mỗi chức năng là Không thấy / Chỉ xem / Toàn quyền.',
                    'Thay đổi áp dụng ngay cho cả app.',
                ]],
                ['title' => 'Bảo trì & hệ thống', 'steps' => [
                    'Cài đặt → Bảo trì: tạm khoá một chức năng khi cần (Quản trị vẫn vào được để kiểm tra).',
                    'Xem Nhật ký thao tác để tra cứu ai làm gì.',
                ]],
                ['title' => 'Làm được mọi việc của Ban Điều Hành', 'steps' => [
                    'Phát thông báo, tạo buổi họp, nhân sự, niên khoá, lên lớp, giám sát điểm số/phiếu.',
                ]],
            ],
        ],
        'bdh' => [
            'label' => 'Ban Điều Hành',
            'mo_ta' => 'Quản lý toàn đoàn: điều hành, thông báo, nhân sự, giám sát.',
            'items' => [
                ['title' => 'Phát thông báo & tạo buổi họp', 'steps' => [
                    'Thông báo → "Phát mới": nhập tiêu đề, nội dung, chọn phạm vi (toàn đoàn / khối / lớp).',
                    'Tick "Đây là buổi họp" + chọn thời gian, địa điểm → buổi họp tự vào lịch người nhận.',
                    'Mở "Kết quả họp" để xem ai Tham gia / Không / Chưa trả lời.',
                ]],
                ['title' => 'Nhân sự & Niên khoá', 'steps' => [
                    'Nhân sự: duyệt GLV đăng ký mới, phân công khối/lớp, đổi vai.',
                    'Niên khoá: mở/khoá niên khoá, đặt học kỳ.',
                ]],
                ['title' => 'Giám sát (chỉ xem)', 'steps' => [
                    'Điểm số và Phiếu liên lạc: Ban Điều Hành CHỈ XEM để giám sát, việc nhập là của GLV lớp.',
                    'Báo cáo: xem thống kê chuyên cần, học lực toàn đoàn.',
                ]],
                ['title' => 'Lên lớp cuối năm', 'steps' => [
                    'Lên lớp: chọn khối → xét kết quả → khai sơ đồ lớp kế tiếp → chuyển sang niên khoá mới.',
                ]],
            ],
        ],
        'truong_khoi' => [
            'label' => 'Trưởng Khối',
            'mo_ta' => 'Quản lý trong phạm vi khối mình phụ trách.',
            'items' => [
                ['title' => 'Xem & điều hành khối', 'steps' => [
                    'Danh sách/Điểm danh/Báo cáo giới hạn trong khối của bạn.',
                    'Phát thông báo cho khối; tạo buổi họp cho khối (tick "Đây là buổi họp").',
                ]],
                ['title' => 'Theo dõi lên lớp', 'steps' => [
                    'Xem kết quả xét lên lớp của khối (việc chuyển do Ban Điều Hành thực hiện).',
                ]],
            ],
        ],
        'glv_chu_nhiem' => [
            'label' => 'GLV Chủ Nhiệm',
            'mo_ta' => 'Phụ trách chính một lớp: điểm danh, chấm điểm, lập phiếu liên lạc.',
            'items' => [
                ['title' => 'Điểm danh', 'steps' => [
                    'Điểm danh → chọn ngày → chọn buổi → Bắt đầu điểm danh.',
                    'Chọn lớp (nếu phụ trách nhiều lớp), chạm tên em để ghi Có mặt / Đi trễ.',
                    'Hoặc "Quét QR" để điểm danh nhanh bằng thẻ.',
                ]],
                ['title' => 'Nhập điểm số', 'steps' => [
                    'Thiếu Nhi → Điểm số → chọn lớp, học kỳ, đầu điểm → gõ điểm từng em.',
                    'Điểm trung bình tự tính; để trống ô là xoá điểm.',
                ]],
                ['title' => 'Lập phiếu liên lạc', 'steps' => [
                    'Thiếu Nhi → Phiếu liên lạc → chọn lớp → chạm từng em để lập/gửi phiếu.',
                    'Hệ thống nhắc lập phiếu trong 1 tháng cuối trước khi kết thúc học kỳ.',
                ]],
            ],
        ],
        'glv' => [
            'label' => 'Giáo Lý Viên',
            'mo_ta' => 'Phụ tá lớp: điểm danh, tra cứu; điểm số/phiếu tuỳ quyền được cấp.',
            'items' => [
                ['title' => 'Điểm danh', 'steps' => [
                    'Điểm danh → chọn buổi → chọn lớp → chạm tên hoặc quét QR.',
                ]],
                ['title' => 'Tra cứu thiếu nhi', 'steps' => [
                    'Danh sách: chọn khối/lớp hoặc gõ tên để tìm; bấm "Xem hồ sơ" để xem chi tiết.',
                    'Tuỳ quyền, có thể chỉ xem (không sửa) danh sách và phiếu liên lạc.',
                ]],
            ],
        ],
        'du_bi' => [
            'label' => 'Dự Bị',
            'mo_ta' => 'Đang tập sự: xem là chính, hỗ trợ điểm danh.',
            'items' => [
                ['title' => 'Việc thường làm', 'steps' => [
                    'Xem danh sách, thông báo, lịch; hỗ trợ điểm danh khi được phân công.',
                    'Chưa có quyền chấm điểm/lập phiếu/lên lớp; các mục đó chỉ để tham khảo.',
                ]],
            ],
        ],
    ],
];
