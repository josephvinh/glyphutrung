/* ==========================================================
   TNTT SUPER APP — điểm khởi động

   Trước đây toàn bộ ứng dụng nằm trong MỘT tệp 3.109 dòng: 105
   thuộc tính trạng thái và 272 phương thức dùng chung một không
   gian tên, nên sửa Điểm Danh có thể làm vỡ Thống Kê mà không ai
   hay. Nay mỗi màn hình là một mảnh riêng trong modules/, đặt tên
   khớp với views/module_*.php cho dễ lần.

   Tệp này chỉ làm một việc: gộp các mảnh lại thành component
   tnttApp của Alpine.
   ========================================================== */

document.addEventListener('alpine:init', () => {

    // Thứ tự nạp không quan trọng về mặt chức năng, nhưng giữ đúng
    // thứ tự này để khi cần đọc thì đi từ nền tảng ra ngoài.
    const MANH = [
        'core',           // người dùng, gọi máy chủ, nhật ký, phân quyền, danh mục
        'programs',       // chương trình sinh hoạt
        'students',       // danh sách thiếu nhi + CSV
        'attendance',     // điểm danh
        'qrscan',         // quét QR điểm danh
        'qrcard',         // in thẻ QR cho thiếu nhi
        'leave',          // xin phép
        'birthdays',      // sinh nhật
        'announcements',  // thông báo
        'stats',          // thống kê
        'scores',         // điểm số
        'reports',        // sổ liên lạc
        'promotion',      // lên lớp cuối năm
        'org',            // khối lớp & nhân sự
        'push',           // thông báo đẩy ra màn hình điện thoại
        'access',         // lọc theo phạm vi quyền
        'shell',          // tiện ích chung, icon, init()
        'dashboard',      // trang chủ với stats tổng hợp
    ];

    /**
     * Gộp các mảnh thành một đối tượng.
     *
     * KHÔNG dùng Object.assign được: component có 82 getter (các
     * thuộc tính dẫn xuất như filteredStudents, todaySession...).
     * Object.assign ĐỌC getter rồi chép GIÁ TRỊ tại thời điểm gộp,
     * biến chúng thành hằng số chết — Alpine sẽ không bao giờ tính
     * lại. Phải chép mô tả thuộc tính để getter vẫn là getter.
     */
    function gopManh() {
        const dich = {};
        const thieu = [];

        for (const ten of MANH) {
            const manh = window.TNTT && window.TNTT[ten];
            if (!manh) { thieu.push(ten); continue; }
            Object.defineProperties(dich, Object.getOwnPropertyDescriptors(manh));
        }

        // Thiếu mảnh nghĩa là index.php chưa nạp tệp đó — thường do cập
        // nhật mà quên chép index.php mới. Trước đây chỉ ghi console.error,
        // nên triệu chứng duy nhất là "bấm nút không thấy gì" và rất khó
        // lần ra. Nay báo thẳng, kèm đúng tên tệp cần chép.
        if (thieu.length) {
            const tin = 'Bản cài đặt thiếu ' + thieu.length + ' phần:\n  '
                      + thieu.join(', ') + '\n\n'
                      + 'Nguyên nhân: tệp public/index.php trên máy chủ là bản cũ,\n'
                      + 'chưa nạp các tệp assets/js/modules/*.js mới.\n\n'
                      + 'Hãy chép public/index.php của bản cập nhật lên máy chủ.';
            console.error('[TNTT] ' + tin);
            setTimeout(() => alert(tin), 800);
        }
        return dich;
    }

    Alpine.data('tnttApp', gopManh);
});
