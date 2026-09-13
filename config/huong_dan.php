<?php
/**
 * NỘI DUNG HƯỚNG DẪN SỬ DỤNG — nguồn DUY NHẤT.
 * Dùng cho cả module "Hướng dẫn" (views/module_guide.php) lẫn bản xuất .doc
 * (scripts/xuat_huong_dan_doc.php). Sửa nội dung ở ĐÂY là cả hai đổi theo.
 */
return [
    'tieu_de' => 'Cẩm nang sử dụng Ứng dụng TNTT',
    'gioi_thieu' => 'Chào mừng bạn đến với ứng dụng quản lý Đoàn Thiếu Nhi Thánh Thể! Dưới đây là hướng dẫn các thao tác cơ bản nhất. Tùy vào vai trò của bạn (Ban Điều Hành, Trưởng khối, GLV...), ứng dụng sẽ chỉ hiển thị những chức năng phù hợp.',

    // Phần chung cho mọi vai
    'chung' => [
        [
            'title' => 'Tài khoản & Mật khẩu',
            'steps' => [
                'Đăng nhập bằng Số điện thoại của bạn.',
                'Nên đổi mật khẩu ngay trong lần đăng nhập đầu tiên (Vào Cài đặt → Đổi mật khẩu).',
                'Nếu lỡ quên mật khẩu, đừng lo! Hãy nhờ Ban Điều Hành đặt lại mật khẩu mới giùm bạn.',
            ],
        ],
        [
            'title' => 'Màn hình Trang chủ có gì?',
            'steps' => [
                'Hãy để ý các "Chấm đỏ" 🔴 — đó là lời nhắc việc (VD: thông báo chưa đọc, buổi học chưa điểm danh, đơn xin phép chờ duyệt).',
                'Phần "Sắp tới" sẽ nhắc bạn các công việc hoặc lịch họp sắp diễn ra.',
                'Phần "Thông báo" hiển thị các tin tức mới nhất từ Ban Điều Hành hoặc Trưởng khối.',
            ],
        ],
        [
            'title' => 'Lịch cá nhân & Nhắc việc',
            'steps' => [
                'Vào "Lịch của tôi" để tự tạo ghi chú việc cần làm. Ứng dụng sẽ tự động nhắc khi đến hạn.',
                'Khi BĐH mời họp, lịch họp sẽ tự động bay vào đây! Bạn chỉ việc bấm "Tham gia" hoặc "Không" để báo lại.',
                'Việc nào làm xong rồi thì bấm nút "Xong" để gạch bỏ nhé.',
            ],
        ],
        [
            'title' => 'Cài App ra màn hình điện thoại',
            'steps' => [
                'Bạn KHÔNG cần tải App từ Store. Chỉ cần mở web bằng Safari (iPhone) hoặc Chrome (Android).',
                'Chọn menu của trình duyệt, bấm "Thêm vào màn hình chính" (Add to Home Screen).',
                'Từ giờ bạn có thể mở ứng dụng bằng Icon trên điện thoại cực mượt mà!',
            ],
        ],
    ],

    // Phần riêng theo vai — thứ tự hiển thị
    'vai' => [
        'admin' => [
            'label' => 'Quản Trị Hệ Thống',
            'mo_ta' => 'Nắm toàn bộ quyền lực, cài đặt sâu vào hệ thống.',
            'items' => [
                ['title' => 'Quản lý Phân quyền', 'steps' => [
                    'Vào Cài đặt → Phân quyền: Cấu hình chi tiết ai được xem, ai được sửa tính năng nào.',
                ]],
                ['title' => 'Chế độ Bảo trì', 'steps' => [
                    'Vào Cài đặt → Bảo trì: Tạm khóa một chức năng để sửa chữa. Người khác sẽ thấy chữ "Bảo trì", riêng Quản trị vẫn vào dùng được để test.',
                ]],
                ['title' => 'Nhật ký hệ thống', 'steps' => [
                    'Kiểm soát mọi hành động: Ai làm gì, xóa gì, sửa gì đều được hệ thống ghi lại chi tiết.',
                ]],
            ],
        ],
        'bdh' => [
            'label' => 'Ban Điều Hành',
            'mo_ta' => 'Điều phối toàn đoàn: báo tin, nhân sự, năm học.',
            'items' => [
                ['title' => 'Thông báo & Mời họp', 'steps' => [
                    'Vào Thông báo → "Phát mới": Chọn gửi cho cả đoàn, hoặc gửi riêng từng khối/lớp.',
                    'Tick chọn "Đây là buổi họp" để ứng dụng tự lên lịch cho người nhận. Xem được ngay ai đi họp, ai vắng!',
                ]],
                ['title' => 'Nhân sự & Năm học', 'steps' => [
                    'Nhân sự: Duyệt tài khoản cho GLV mới, phân công GLV vào lớp, cấp quyền Trưởng khối.',
                    'Niên khóa: Khởi tạo năm học mới, chia thời gian Học kỳ 1 và Học kỳ 2.',
                ]],
                ['title' => 'Lên lớp cuối năm', 'steps' => [
                    'Chốt sổ cực nhanh: Chọn khối → Xét kết quả → Chuyển các em sang sơ đồ lớp mới hàng loạt chỉ với vài nút bấm.',
                ]],
            ],
        ],
        'truong_khoi' => [
            'label' => 'Trưởng Khối',
            'mo_ta' => 'Theo dõi và quản lý khối mình phụ trách.',
            'items' => [
                ['title' => 'Bao quát toàn khối', 'steps' => [
                    'Dễ dàng xem danh sách, kết quả điểm danh, và điểm số của tất cả các lớp trong khối.',
                ]],
                ['title' => 'Thông báo nội bộ', 'steps' => [
                    'Gửi thông báo hoặc gọi họp riêng các GLV trong khối của mình một cách nhanh chóng.',
                ]],
            ],
        ],
        'glv_chu_nhiem' => [
            'label' => 'GLV Chủ Nhiệm',
            'mo_ta' => 'Nắm lớp trực tiếp: điểm danh, chấm điểm, đánh giá.',
            'items' => [
                ['title' => 'Điểm danh siêu tốc', 'steps' => [
                    'Vào "Điểm danh" → Chọn ngày → Chạm tên các em để đánh dấu Có mặt/Đi trễ/Vắng.',
                    'Hoặc chọn "Quét QR" dùng Camera quét thẻ để điểm danh nhanh như siêu thị!',
                ]],
                ['title' => 'Sổ điểm thông minh', 'steps' => [
                    'Vào "Thiếu Nhi" → "Điểm số": Nhập điểm theo từng cột, ứng dụng sẽ tự động tính điểm Trung bình.',
                    'Gõ sai? Cứ để trống ô đó là hệ thống tự xóa điểm.',
                ]],
                ['title' => 'Phiếu liên lạc điện tử', 'steps' => [
                    'Tạo và gửi phiếu liên lạc cho phụ huynh dễ dàng. Hệ thống sẽ tự động nhắc nhở bạn khi sắp hết học kỳ!',
                ]],
            ],
        ],
        'glv' => [
            'label' => 'Giáo Lý Viên',
            'mo_ta' => 'Phụ tá lớp: điểm danh, tra cứu thông tin.',
            'items' => [
                ['title' => 'Điểm danh phụ', 'steps' => [
                    'Hỗ trợ GLV Chủ nhiệm điểm danh tay hoặc quét mã QR khi được phân công.',
                ]],
                ['title' => 'Tra cứu thiếu nhi', 'steps' => [
                    'Mở "Danh sách" để xem hồ sơ, số điện thoại phụ huynh của các em trong lớp để tiện liên lạc.',
                ]],
            ],
        ],
        'du_bi' => [
            'label' => 'Dự Bị',
            'mo_ta' => 'Đang học việc, làm quen với hệ thống.',
            'items' => [
                ['title' => 'Hỗ trợ lớp', 'steps' => [
                    'Chủ yếu xem danh sách lớp, đọc thông báo và theo dõi lịch họp.',
                    'Có thể giúp các trưởng quét mã QR điểm danh. Chưa có quyền sửa điểm hay lập phiếu.',
                ]],
            ],
        ],
    ],
];
